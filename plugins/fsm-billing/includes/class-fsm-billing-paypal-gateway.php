<?php
/**
 * FSM Billing PayPal Gateway
 *
 * BYO-PayPal: each org connects their own PayPal Invoicing app (Client ID +
 * Secret + Webhook ID from their own Business account), money goes straight
 * to them, no platform cut. Ports the proven request/OAuth/webhook-signature
 * pattern from steinmetz-paypal-invoicing (SPPI_Api_Client / SPPI_Webhook)
 * to a multi-tenant, per-org-credentialed context.
 *
 * @package FSM\Billing
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Billing PayPal Gateway class.
 */
class FSM_Billing_Paypal_Gateway {

    /**
     * Get the API base for an org's configured PayPal mode.
     *
     * @param string $mode 'sandbox' or 'live'.
     * @return string
     */
    private static function api_base($mode) {
        return $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Get a cached (or fresh) OAuth2 access token for an org's PayPal app.
     *
     * @param int   $org_id   Organization ID.
     * @param array $settings Decrypted settings from FSM_Billing_Payment_Settings::get().
     * @return string|WP_Error
     */
    private static function get_access_token($org_id, $settings) {
        $cache_key = 'fsm_billing_paypal_token_' . md5($org_id . '|' . $settings['paypal_client_id']);
        $cached = get_transient($cache_key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if ('' === $settings['paypal_client_id'] || '' === $settings['paypal_client_secret']) {
            return new WP_Error('paypal_not_configured', __('PayPal is not connected for this organization.', 'fsm-billing'));
        }

        $response = wp_remote_post(self::api_base($settings['paypal_mode']) . '/v1/oauth2/token', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($settings['paypal_client_id'] . ':' . $settings['paypal_client_secret']),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => ['grant_type' => 'client_credentials'],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $code || empty($body['access_token'])) {
            $detail = $body['error_description'] ?? __('Unexpected response from PayPal.', 'fsm-billing');
            return new WP_Error('paypal_token_failed', $detail);
        }

        $expires = isset($body['expires_in']) ? max(60, (int) $body['expires_in'] - 60) : 3000;
        set_transient($cache_key, $body['access_token'], $expires);

        return $body['access_token'];
    }

    /**
     * Generic authenticated JSON request to the PayPal API for a given org.
     *
     * @param int    $org_id  Organization ID.
     * @param array  $settings Decrypted settings.
     * @param string $method  HTTP method.
     * @param string $path    Path beginning with '/'.
     * @param mixed  $body    Body to JSON-encode, or null.
     * @return array|WP_Error {code, body, location} on success-ish, WP_Error on transport/auth failure.
     */
    private static function request($org_id, $settings, $method, $path, $body = null) {
        $token = self::get_access_token($org_id, $settings);
        if (is_wp_error($token)) {
            return $token;
        }

        $args = [
            'method' => $method,
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if (null !== $body) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request(self::api_base($settings['paypal_mode']) . $path, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $decoded = '' === $raw ? [] : json_decode($raw, true);

        return [
            'code' => (int) $code,
            'body' => is_array($decoded) ? $decoded : [],
            'location' => wp_remote_retrieve_header($response, 'location'),
        ];
    }

    /**
     * Create + send a PayPal invoice for an invoice's remaining balance and
     * return the hosted recipient view URL. Always a single line item for the
     * exact balance due — same "customer pays exactly the total" guarantee
     * steinmetz-paypal-invoicing uses, which sidesteps per-item tax/rounding
     * mismatches entirely.
     *
     * @param object $invoice Invoice row.
     * @return string|WP_Error
     */
    public static function create_pay_link($invoice) {
        $settings = FSM_Billing_Payment_Settings::get($invoice->organization_id);

        $balance_due = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        if ($balance_due <= 0) {
            return new WP_Error('nothing_due', __('This invoice has no balance due.', 'fsm-billing'));
        }

        $payload = [
            'detail' => [
                'invoice_number' => $invoice->invoice_number,
                'reference' => (string) $invoice->id,
                'currency_code' => 'USD',
                'payment_term' => ['term_type' => 'DUE_ON_RECEIPT'],
            ],
            'invoicer' => [
                'name' => ['given_name' => get_bloginfo('name') ?: 'Invoice'],
            ],
            'primary_recipients' => [[
                'billing_info' => [
                    'email_address' => $invoice->customer_email ?: null,
                    'name' => ['given_name' => $invoice->customer_name ?: 'Customer'],
                ],
            ]],
            'items' => [[
                'name' => sprintf(__('Invoice %s', 'fsm-billing'), $invoice->invoice_number),
                'quantity' => '1',
                'unit_amount' => ['currency_code' => 'USD', 'value' => number_format($balance_due, 2, '.', '')],
            ]],
        ];

        $create = self::request($invoice->organization_id, $settings, 'POST', '/v2/invoicing/invoices', $payload);
        if (is_wp_error($create)) {
            return $create;
        }
        if (!in_array($create['code'], [200, 201], true)) {
            return new WP_Error('paypal_error', $create['body']['message'] ?? __('PayPal did not accept the invoice.', 'fsm-billing'));
        }

        $paypal_invoice_id = $create['body']['id'] ?? self::parse_id_from_href($create['location']);
        if (!$paypal_invoice_id) {
            return new WP_Error('paypal_error', __('PayPal did not return an invoice ID.', 'fsm-billing'));
        }

        FSM_Billing_DB::update_invoice($invoice->id, ['paypal_invoice_id' => $paypal_invoice_id]);

        $send = self::request($invoice->organization_id, $settings, 'POST', '/v2/invoicing/invoices/' . rawurlencode($paypal_invoice_id) . '/send', ['send_to_recipient' => true]);
        if (is_wp_error($send)) {
            return $send;
        }
        if (!in_array($send['code'], [200, 202], true)) {
            return new WP_Error('paypal_error', $send['body']['message'] ?? __('PayPal did not send the invoice.', 'fsm-billing'));
        }

        $details = self::request($invoice->organization_id, $settings, 'GET', '/v2/invoicing/invoices/' . rawurlencode($paypal_invoice_id));
        if (is_wp_error($details) || $details['code'] !== 200) {
            // Invoice was created and sent successfully; just couldn't fetch the hosted link back.
            return new WP_Error('paypal_partial', __('Invoice sent, but the payment link could not be retrieved. Check PayPal directly.', 'fsm-billing'));
        }

        $url = $details['body']['detail']['metadata']['recipient_view_url'] ?? '';
        if (!$url) {
            return new WP_Error('paypal_partial', __('Invoice sent, but PayPal did not return a payment link.', 'fsm-billing'));
        }

        return $url;
    }

    /**
     * Handle an incoming PayPal webhook request for a given org.
     *
     * @param WP_REST_Request $request Request.
     * @param int              $org_id  Organization ID from the route.
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_webhook($request, $org_id) {
        $settings = FSM_Billing_Payment_Settings::get($org_id);

        if ('' === $settings['paypal_webhook_id']) {
            return new WP_Error('not_configured', __('No PayPal webhook ID configured.', 'fsm-billing'), ['status' => 400]);
        }

        $raw_body = $request->get_body();
        $headers = self::normalize_headers($request);

        $verified = self::verify_webhook_signature($org_id, $settings, $headers, $raw_body);
        if (is_wp_error($verified)) {
            return $verified;
        }

        $event = json_decode($raw_body, true);
        if (!is_array($event) || empty($event['id']) || empty($event['event_type'])) {
            return rest_ensure_response(['status' => 'ignored']);
        }

        $resource = $event['resource'] ?? [];
        $paypal_invoice_id = $resource['invoice']['id'] ?? ($resource['id'] ?? '');
        $invoice = $paypal_invoice_id ? FSM_Billing_DB::get_invoice_by_paypal_id($paypal_invoice_id) : null;

        if (!$invoice || (int) $invoice->organization_id !== (int) $org_id) {
            return rest_ensure_response(['status' => 'no_matching_invoice']);
        }

        switch ($event['event_type']) {
            case 'INVOICING.INVOICE.PAID':
                self::handle_paid($invoice, $event);
                break;

            case 'INVOICING.INVOICE.CANCELLED':
                if (in_array($invoice->status, ['draft', 'sent'], true)) {
                    FSM_Billing_DB::update_invoice($invoice->id, ['status' => 'void', 'voided_at' => current_time('mysql')]);
                }
                break;

            // PARTIALLY_PAID / REFUNDED: intentionally not auto-applied in v1 (PayPal's
            // payload doesn't cleanly give the incremental amount for a manual-mark
            // parity with steinmetz-paypal-invoicing's same scope decision) — surfaces
            // in the PayPal dashboard for manual reconciliation.
            default:
                break;
        }

        return rest_ensure_response(['status' => 'ok']);
    }

    /**
     * Record full payment of the outstanding balance for a PAID webhook event. Idempotent via event ID.
     *
     * @param object $invoice Invoice row.
     * @param array  $event   Decoded PayPal event.
     */
    private static function handle_paid($invoice, $event) {
        if (in_array($invoice->status, ['paid', 'void'], true)) {
            return;
        }

        $balance_due = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        if ($balance_due <= 0) {
            return;
        }

        FSM_Billing_DB::record_payment([
            'invoice_id' => $invoice->id,
            'organization_id' => $invoice->organization_id,
            'amount' => $balance_due,
            'method' => 'other',
            'reference' => $invoice->paypal_invoice_id,
            'processor' => 'paypal',
            'processor_payment_id' => $invoice->paypal_invoice_id,
            'idempotency_key' => 'paypal_' . $event['id'],
            'recorded_by' => 0,
            'paid_at' => current_time('mysql'),
        ]);
    }

    /**
     * Verify a webhook event with PayPal's verify-webhook-signature API.
     *
     * CRITICAL: PayPal signs the exact bytes of the event body. Decoding and
     * re-encoding it (json_decode -> wp_json_encode) changes the JSON and
     * makes verification fail — so the request body is built as a string
     * with the raw event spliced in verbatim. (Same bug class documented and
     * fixed in steinmetz-paypal-invoicing 2026-06-04.)
     *
     * @param int    $org_id   Organization ID.
     * @param array  $settings Decrypted settings.
     * @param array  $headers  Lowercased request headers.
     * @param string $raw_body Raw request body.
     * @return true|WP_Error
     */
    private static function verify_webhook_signature($org_id, $settings, $headers, $raw_body) {
        $required = ['paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time'];
        foreach ($required as $h) {
            if (empty($headers[$h])) {
                return new WP_Error('missing_headers', sprintf(__('Missing webhook header: %s', 'fsm-billing'), $h), ['status' => 400]);
            }
        }

        $token = self::get_access_token($org_id, $settings);
        if (is_wp_error($token)) {
            return new WP_Error('verification_failed', __('Could not authenticate with PayPal to verify webhook.', 'fsm-billing'), ['status' => 401]);
        }

        $meta = wp_json_encode([
            'auth_algo' => $headers['paypal-auth-algo'],
            'cert_url' => $headers['paypal-cert-url'],
            'transmission_id' => $headers['paypal-transmission-id'],
            'transmission_sig' => $headers['paypal-transmission-sig'],
            'transmission_time' => $headers['paypal-transmission-time'],
            'webhook_id' => $settings['paypal_webhook_id'],
        ]);
        $request_body = substr($meta, 0, -1) . ',"webhook_event":' . $raw_body . '}';

        $response = wp_remote_post(self::api_base($settings['paypal_mode']) . '/v1/notifications/verify-webhook-signature', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => $request_body,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('verification_failed', __('Webhook verification transport error.', 'fsm-billing'), ['status' => 401]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = $body['verification_status'] ?? '';

        if ('SUCCESS' !== $status) {
            return new WP_Error('verification_failed', __('PayPal webhook signature verification failed.', 'fsm-billing'), ['status' => 401]);
        }

        return true;
    }

    /**
     * Lowercase + collapse WP_REST_Request headers to a simple map.
     *
     * @param WP_REST_Request $request Request.
     * @return array
     */
    private static function normalize_headers($request) {
        $out = [];
        foreach ($request->get_headers() as $key => $value) {
            $out[strtolower(str_replace('_', '-', $key))] = is_array($value) ? reset($value) : $value;
        }
        return $out;
    }

    /**
     * Extract the trailing ID segment from a PayPal resource href.
     *
     * @param string $href Href/location value.
     * @return string
     */
    private static function parse_id_from_href($href) {
        if (!$href) {
            return '';
        }
        $parts = explode('/', untrailingslashit($href));
        return end($parts);
    }
}
