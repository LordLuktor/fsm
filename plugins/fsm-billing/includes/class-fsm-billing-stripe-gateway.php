<?php
/**
 * FSM Billing Stripe Gateway
 *
 * BYO-Stripe: each org connects their own Stripe secret key, money goes
 * straight to their account, no platform cut. Creates a single-line-item
 * Checkout Session for the invoice's exact total (never itemizes — this
 * guarantees the amount Stripe collects is bit-for-bit the invoice total,
 * same design decision steinmetz-paypal-invoicing made for the same reason)
 * and verifies webhooks manually (no Stripe SDK / composer dependency in
 * this codebase) using the raw request body.
 *
 * @package FSM\Billing
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Billing Stripe Gateway class.
 */
class FSM_Billing_Stripe_Gateway {

    /**
     * How much clock skew to tolerate on a webhook's timestamp, in seconds.
     * Matches Stripe's own default tolerance.
     */
    const WEBHOOK_TOLERANCE = 300;

    /**
     * Create a Checkout Session for an invoice and return the hosted pay URL.
     *
     * @param object $invoice Invoice row.
     * @return string|WP_Error Hosted checkout URL, or WP_Error on failure.
     */
    public static function create_pay_link($invoice) {
        $settings = FSM_Billing_Payment_Settings::get($invoice->organization_id);
        $secret_key = $settings['stripe_secret_key'];

        if ('' === $secret_key) {
            return new WP_Error('stripe_not_configured', __('Stripe is not connected for this organization.', 'fsm-billing'));
        }

        $total_cents = (int) round(((float) $invoice->total - (float) $invoice->amount_paid) * 100);
        if ($total_cents <= 0) {
            return new WP_Error('nothing_due', __('This invoice has no balance due.', 'fsm-billing'));
        }

        $return_base = self::get_return_url_base($invoice->organization_id);

        $body = [
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $total_cents,
                    'product_data' => [
                        'name' => sprintf(__('Invoice %s', 'fsm-billing'), $invoice->invoice_number),
                    ],
                ],
            ]],
            'metadata' => [
                'fsm_invoice_id' => (string) $invoice->id,
                'fsm_organization_id' => (string) $invoice->organization_id,
            ],
            'customer_email' => $invoice->customer_email ?: null,
            'success_url' => $return_base . '?fsm_invoice=' . $invoice->id . '&fsm_pay=success',
            'cancel_url' => $return_base . '?fsm_invoice=' . $invoice->id . '&fsm_pay=cancelled',
        ];

        $response = self::request($secret_key, 'POST', '/v1/checkout/sessions', self::to_form_params($body));

        if (is_wp_error($response)) {
            return $response;
        }

        if (empty($response['url'])) {
            return new WP_Error('stripe_error', $response['error']['message'] ?? __('Stripe did not return a checkout URL.', 'fsm-billing'));
        }

        return $response['url'];
    }

    /**
     * Handle an incoming Stripe webhook request. Verifies the signature
     * against the invoice's organization's stored webhook secret using the
     * RAW request body (re-encoding the parsed JSON would change the bytes
     * and break signature verification — the same class of bug documented
     * in steinmetz-paypal-invoicing for PayPal's webhook signature check).
     *
     * @param WP_REST_Request $request Request.
     * @param int              $org_id  Organization ID from the route.
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_webhook($request, $org_id) {
        $settings = FSM_Billing_Payment_Settings::get($org_id);
        $webhook_secret = $settings['stripe_webhook_secret'];

        if ('' === $webhook_secret) {
            return new WP_Error('not_configured', __('No Stripe webhook secret configured.', 'fsm-billing'), ['status' => 400]);
        }

        $signature_header = $request->get_header('stripe-signature');
        $raw_body = $request->get_body();

        $verified = self::verify_signature($raw_body, $signature_header, $webhook_secret);
        if (is_wp_error($verified)) {
            return $verified;
        }

        $event = json_decode($raw_body, true);
        if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
            return new WP_Error('invalid_payload', __('Malformed webhook payload.', 'fsm-billing'), ['status' => 400]);
        }

        if ($event['type'] === 'checkout.session.completed') {
            self::handle_checkout_completed($event, $org_id);
        }

        // Always 200 once verified — Stripe retries on non-2xx, and we've
        // either handled the event type or intentionally ignore it.
        return rest_ensure_response(['received' => true]);
    }

    /**
     * Process a checkout.session.completed event: record the payment and
     * roll the invoice status forward. Idempotent via the Stripe event ID.
     *
     * @param array $event  Decoded Stripe event.
     * @param int   $org_id Organization ID (from the route, already verified against this org's secret).
     */
    private static function handle_checkout_completed($event, $org_id) {
        $session = $event['data']['object'] ?? [];
        if (($session['payment_status'] ?? '') !== 'paid') {
            return;
        }

        $invoice_id = (int) ($session['metadata']['fsm_invoice_id'] ?? 0);
        if (!$invoice_id) {
            return;
        }

        $invoice = FSM_Billing_DB::get_invoice($invoice_id);
        // Defense in depth: the invoice must actually belong to the org this
        // webhook route/secret is for, even though the session was created by us.
        if (!$invoice || (int) $invoice->organization_id !== (int) $org_id) {
            return;
        }

        if (in_array($invoice->status, ['paid', 'void'], true)) {
            return;
        }

        FSM_Billing_DB::record_payment([
            'invoice_id' => $invoice_id,
            'organization_id' => $org_id,
            'amount' => round((float) ($session['amount_total'] ?? 0) / 100, 2),
            'method' => 'card',
            'reference' => (string) ($session['id'] ?? ''),
            'processor' => 'stripe',
            'processor_payment_id' => (string) ($session['payment_intent'] ?? $session['id'] ?? ''),
            'idempotency_key' => 'stripe_' . $event['id'],
            'recorded_by' => 0,
            'paid_at' => current_time('mysql'),
        ]);
    }

    /**
     * Verify a Stripe-Signature header against the raw body and a webhook secret.
     *
     * @param string $raw_body  Raw request body, exactly as received.
     * @param string $header    Stripe-Signature header value.
     * @param string $secret    Webhook signing secret.
     * @return true|WP_Error
     */
    private static function verify_signature($raw_body, $header, $secret) {
        if (!$header) {
            return new WP_Error('missing_signature', __('Missing Stripe-Signature header.', 'fsm-billing'), ['status' => 400]);
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            $kv = explode('=', $pair, 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])][] = trim($kv[1]);
            }
        }

        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $signatures = $parts['v1'] ?? [];

        if (!$timestamp || empty($signatures)) {
            return new WP_Error('malformed_signature', __('Malformed Stripe-Signature header.', 'fsm-billing'), ['status' => 400]);
        }

        if (abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE) {
            return new WP_Error('stale_signature', __('Webhook timestamp outside tolerance.', 'fsm-billing'), ['status' => 400]);
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $raw_body, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return new WP_Error('invalid_signature', __('Stripe webhook signature verification failed.', 'fsm-billing'), ['status' => 401]);
    }

    /**
     * Where Stripe Checkout should redirect back to after payment. The FSM
     * frontend's own invoice page handles the ?fsm_invoice=&fsm_pay= params.
     *
     * @param int $org_id Organization ID (unused for now; placeholder for future per-org portal domains).
     * @return string
     */
    private static function get_return_url_base($org_id) {
        return 'https://fsm.steinmetz.ltd/invoices';
    }

    /**
     * Make an authenticated request to the Stripe API.
     *
     * @param string $secret_key Stripe secret key (Basic auth username, empty password).
     * @param string $method     HTTP method.
     * @param string $path       API path, e.g. '/v1/checkout/sessions'.
     * @param array  $form_body  Pre-flattened application/x-www-form-urlencoded body.
     * @return array|WP_Error Decoded JSON response, or WP_Error on transport failure.
     */
    private static function request($secret_key, $method, $path, $form_body = []) {
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($secret_key . ':'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 20,
        ];

        if ($form_body) {
            $args['body'] = $form_body;
        }

        $response = wp_remote_request('https://api.stripe.com' . $path, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            return is_array($data) ? $data : ['error' => ['message' => 'Stripe API error (HTTP ' . $code . ')']];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Flatten a nested array into Stripe's bracket-notation form encoding,
     * e.g. ['line_items' => [['quantity' => 1]]] -> 'line_items[0][quantity]=1'.
     *
     * @param array  $data   Nested data.
     * @param string $prefix Internal recursion prefix.
     * @return array Flat key => value pairs suitable for wp_remote_request's 'body'.
     */
    private static function to_form_params($data, $prefix = '') {
        $params = [];

        foreach ($data as $key => $value) {
            if (null === $value || '' === $value) {
                continue;
            }

            $form_key = $prefix === '' ? $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $params += self::to_form_params($value, $form_key);
            } else {
                $params[$form_key] = $value;
            }
        }

        return $params;
    }
}
