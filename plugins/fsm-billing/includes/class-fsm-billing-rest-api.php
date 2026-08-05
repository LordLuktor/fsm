<?php
/**
 * FSM Billing REST API
 *
 * REST API endpoints for invoices, line items, and payments.
 *
 * @package FSM\Billing
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Billing REST API class.
 */
class FSM_Billing_REST_API {

    /**
     * Namespace.
     */
    const NAMESPACE = 'fsm/v1';

    /**
     * Register routes.
     */
    public static function register_routes() {
        register_rest_route(self::NAMESPACE, '/invoices', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_invoices'],
                'permission_callback' => function() {
                    return current_user_can('fsm_view_billing');
                },
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'create_invoice'],
                'permission_callback' => function() {
                    return current_user_can('fsm_manage_billing');
                },
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/invoices/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_invoice'],
                'permission_callback' => function() {
                    return current_user_can('fsm_view_billing');
                },
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [__CLASS__, 'update_invoice'],
                'permission_callback' => function() {
                    return current_user_can('fsm_manage_billing');
                },
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/invoices/(?P<id>\d+)/line-items', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'add_line_item'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_billing');
            },
        ]);

        register_rest_route(self::NAMESPACE, '/invoices/(?P<id>\d+)/line-items/(?P<line_id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [__CLASS__, 'delete_line_item'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_billing');
            },
        ]);

        register_rest_route(self::NAMESPACE, '/invoices/(?P<id>\d+)/send', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'send_invoice'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_billing');
            },
        ]);

        register_rest_route(self::NAMESPACE, '/invoices/(?P<id>\d+)/void', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'void_invoice'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_billing');
            },
        ]);

        register_rest_route(self::NAMESPACE, '/invoices/(?P<id>\d+)/payments', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'record_payment'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_billing');
            },
        ]);
    }

    /**
     * Verify the current user's organization context can access this invoice row.
     *
     * @param object $invoice Invoice row (must have organization_id).
     * @return true|WP_Error
     */
    private static function verify_access($invoice) {
        return FSM_Billing_DB::verify_access($invoice);
    }

    /**
     * List invoices for the current organization context.
     */
    public static function get_invoices($request) {
        $org_id = (int) FSM_Organizations::get_current_context();
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $page = max(1, (int) ($request->get_param('page') ?: 1));

        $args = [
            'status' => $request->get_param('status') ?: '',
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ];

        $invoices = FSM_Billing_DB::get_invoices($org_id, $args);
        $total = FSM_Billing_DB::count_invoices($org_id, $args);

        return rest_ensure_response([
            'data' => array_map([__CLASS__, 'prepare_invoice'], $invoices),
            'total' => $total,
            'pages' => (int) ceil($total / $per_page),
            'page' => $page,
        ]);
    }

    /**
     * Get a single invoice with line items and payments.
     */
    public static function get_invoice($request) {
        $invoice = FSM_Billing_DB::get_invoice((int) $request->get_param('id'));
        if (!$invoice) {
            return FSM_REST_API::error('not_found', __('Invoice not found.', 'fsm-billing'), 404);
        }

        $access = self::verify_access($invoice);
        if (is_wp_error($access)) {
            return $access;
        }

        return rest_ensure_response(self::prepare_invoice($invoice, true));
    }

    /**
     * Create a standalone draft invoice (not tied to a work order).
     */
    public static function create_invoice($request) {
        $org_id = (int) FSM_Organizations::get_current_context();
        if (!$org_id) {
            return FSM_REST_API::error('missing_org', __('No organization context.', 'fsm-billing'), 400);
        }

        $tax_rate = $request->get_param('tax_rate');
        if ($tax_rate === null) {
            $tax_rate = FSM_Organizations::get_tax_rate($org_id);
        }

        $invoice_id = FSM_Billing_DB::create_invoice([
            'organization_id' => $org_id,
            'work_order_id' => $request->get_param('work_order_id') ?: null,
            'customer_name' => sanitize_text_field((string) $request->get_param('customer_name')),
            'customer_email' => sanitize_email((string) $request->get_param('customer_email')),
            'status' => 'draft',
            'tax_rate' => FSM_Organizations::sanitize_money($tax_rate),
            'notes' => sanitize_textarea_field((string) $request->get_param('notes')),
            'created_by' => get_current_user_id(),
        ]);

        if (!$invoice_id) {
            return FSM_REST_API::error('create_failed', __('Failed to create invoice.', 'fsm-billing'), 500);
        }

        return rest_ensure_response(self::prepare_invoice(FSM_Billing_DB::get_invoice($invoice_id), true));
    }

    /**
     * Update invoice header fields. Cannot edit a paid or void invoice.
     */
    public static function update_invoice($request) {
        $invoice = FSM_Billing_DB::get_invoice((int) $request->get_param('id'));
        if (!$invoice) {
            return FSM_REST_API::error('not_found', __('Invoice not found.', 'fsm-billing'), 404);
        }

        $access = self::verify_access($invoice);
        if (is_wp_error($access)) {
            return $access;
        }

        if (in_array($invoice->status, ['paid', 'void'], true)) {
            return FSM_REST_API::error('locked', __('Cannot edit a paid or void invoice.', 'fsm-billing'), 400);
        }

        $data = [];
        if (null !== $request->get_param('customer_name')) {
            $data['customer_name'] = sanitize_text_field($request->get_param('customer_name'));
        }
        if (null !== $request->get_param('customer_email')) {
            $data['customer_email'] = sanitize_email($request->get_param('customer_email'));
        }
        if (null !== $request->get_param('due_date')) {
            $data['due_date'] = sanitize_text_field($request->get_param('due_date')) ?: null;
        }
        if (null !== $request->get_param('notes')) {
            $data['notes'] = sanitize_textarea_field($request->get_param('notes'));
        }
        if (null !== $request->get_param('tax_rate')) {
            $data['tax_rate'] = FSM_Organizations::sanitize_money($request->get_param('tax_rate'));
        }

        if ($data) {
            FSM_Billing_DB::update_invoice($invoice->id, $data);
            if (isset($data['tax_rate'])) {
                FSM_Billing_DB::recalculate_totals($invoice->id);
            }
        }

        return rest_ensure_response(self::prepare_invoice(FSM_Billing_DB::get_invoice($invoice->id), true));
    }

    /**
     * Add a line item to a draft/sent invoice.
     */
    public static function add_line_item($request) {
        $invoice = FSM_Billing_DB::get_invoice((int) $request->get_param('id'));
        if (!$invoice) {
            return FSM_REST_API::error('not_found', __('Invoice not found.', 'fsm-billing'), 404);
        }

        $access = self::verify_access($invoice);
        if (is_wp_error($access)) {
            return $access;
        }

        if (in_array($invoice->status, ['paid', 'void'], true)) {
            return FSM_REST_API::error('locked', __('Cannot edit a paid or void invoice.', 'fsm-billing'), 400);
        }

        $type = $request->get_param('type');
        if (!in_array($type, ['labor', 'part', 'fee', 'discount'], true)) {
            return FSM_REST_API::error('invalid_type', __('Invalid line item type.', 'fsm-billing'), 400);
        }

        $description = sanitize_text_field((string) $request->get_param('description'));
        if ($description === '') {
            return FSM_REST_API::error('missing_description', __('Description is required.', 'fsm-billing'), 400);
        }

        $quantity = (float) $request->get_param('quantity');
        $unit_price = (float) $request->get_param('unit_price');
        // Discounts are the only line item type allowed a negative price.
        if ($type !== 'discount' && $unit_price < 0) {
            $unit_price = 0;
        }

        $line_id = FSM_Billing_DB::add_line_item([
            'invoice_id' => $invoice->id,
            'organization_id' => $invoice->organization_id,
            'type' => $type,
            'description' => $description,
            'inventory_item_id' => $request->get_param('inventory_item_id') ?: null,
            'quantity' => $quantity ?: 1,
            'unit_price' => $unit_price,
            'taxable' => $request->get_param('taxable') === false ? 0 : 1,
            'sort_order' => count(FSM_Billing_DB::get_line_items($invoice->id)),
        ]);

        if (!$line_id) {
            return FSM_REST_API::error('create_failed', __('Failed to add line item.', 'fsm-billing'), 500);
        }

        $invoice = FSM_Billing_DB::recalculate_totals($invoice->id);

        return rest_ensure_response(self::prepare_invoice($invoice, true));
    }

    /**
     * Remove a line item from a draft/sent invoice.
     */
    public static function delete_line_item($request) {
        $invoice = FSM_Billing_DB::get_invoice((int) $request->get_param('id'));
        if (!$invoice) {
            return FSM_REST_API::error('not_found', __('Invoice not found.', 'fsm-billing'), 404);
        }

        $access = self::verify_access($invoice);
        if (is_wp_error($access)) {
            return $access;
        }

        if (in_array($invoice->status, ['paid', 'void'], true)) {
            return FSM_REST_API::error('locked', __('Cannot edit a paid or void invoice.', 'fsm-billing'), 400);
        }

        FSM_Billing_DB::delete_line_item((int) $request->get_param('line_id'));
        $invoice = FSM_Billing_DB::recalculate_totals($invoice->id);

        return rest_ensure_response(self::prepare_invoice($invoice, true));
    }

    /**
     * Mark a draft invoice as sent.
     */
    public static function send_invoice($request) {
        $invoice = FSM_Billing_DB::get_invoice((int) $request->get_param('id'));
        if (!$invoice) {
            return FSM_REST_API::error('not_found', __('Invoice not found.', 'fsm-billing'), 404);
        }

        $access = self::verify_access($invoice);
        if (is_wp_error($access)) {
            return $access;
        }

        if ($invoice->status !== 'draft') {
            return FSM_REST_API::error('invalid_status', __('Only draft invoices can be sent.', 'fsm-billing'), 400);
        }

        FSM_Billing_DB::update_invoice($invoice->id, [
            'status' => 'sent',
            'sent_at' => current_time('mysql'),
        ]);

        return rest_ensure_response(self::prepare_invoice(FSM_Billing_DB::get_invoice($invoice->id), true));
    }

    /**
     * Void an invoice. Cannot void an invoice that already has payments recorded.
     */
    public static function void_invoice($request) {
        $invoice = FSM_Billing_DB::get_invoice((int) $request->get_param('id'));
        if (!$invoice) {
            return FSM_REST_API::error('not_found', __('Invoice not found.', 'fsm-billing'), 404);
        }

        $access = self::verify_access($invoice);
        if (is_wp_error($access)) {
            return $access;
        }

        if ((float) $invoice->amount_paid > 0) {
            return FSM_REST_API::error('has_payments', __('Cannot void an invoice with recorded payments.', 'fsm-billing'), 400);
        }

        FSM_Billing_DB::update_invoice($invoice->id, [
            'status' => 'void',
            'voided_at' => current_time('mysql'),
        ]);

        return rest_ensure_response(self::prepare_invoice(FSM_Billing_DB::get_invoice($invoice->id), true));
    }

    /**
     * Record a manual payment (cash/check/card/bank transfer/other) against an invoice.
     */
    public static function record_payment($request) {
        $invoice = FSM_Billing_DB::get_invoice((int) $request->get_param('id'));
        if (!$invoice) {
            return FSM_REST_API::error('not_found', __('Invoice not found.', 'fsm-billing'), 404);
        }

        $access = self::verify_access($invoice);
        if (is_wp_error($access)) {
            return $access;
        }

        if (in_array($invoice->status, ['void'], true)) {
            return FSM_REST_API::error('locked', __('Cannot record a payment on a void invoice.', 'fsm-billing'), 400);
        }

        $amount = (float) $request->get_param('amount');
        if ($amount <= 0) {
            return FSM_REST_API::error('invalid_amount', __('Payment amount must be greater than zero.', 'fsm-billing'), 400);
        }

        $method = $request->get_param('method');
        if (!in_array($method, ['cash', 'check', 'card', 'bank_transfer', 'other'], true)) {
            $method = 'other';
        }

        $payment_id = FSM_Billing_DB::record_payment([
            'invoice_id' => $invoice->id,
            'organization_id' => $invoice->organization_id,
            'amount' => round($amount, 2),
            'method' => $method,
            'reference' => sanitize_text_field((string) $request->get_param('reference')),
            'notes' => sanitize_textarea_field((string) $request->get_param('notes')),
            'recorded_by' => get_current_user_id(),
            'paid_at' => current_time('mysql'),
        ]);

        if (!$payment_id) {
            return FSM_REST_API::error('create_failed', __('Failed to record payment.', 'fsm-billing'), 500);
        }

        return rest_ensure_response(self::prepare_invoice(FSM_Billing_DB::get_invoice($invoice->id), true));
    }

    /**
     * Prepare an invoice for API response.
     *
     * @param object $invoice  Invoice row.
     * @param bool   $detailed Include line items and payments.
     * @return array
     */
    private static function prepare_invoice($invoice, $detailed = false) {
        $data = [
            'id' => (int) $invoice->id,
            'organization_id' => (int) $invoice->organization_id,
            'work_order_id' => $invoice->work_order_id ? (int) $invoice->work_order_id : null,
            'invoice_number' => $invoice->invoice_number,
            'customer_name' => $invoice->customer_name,
            'customer_email' => $invoice->customer_email,
            'status' => $invoice->status,
            'subtotal' => (float) $invoice->subtotal,
            'tax_rate' => (float) $invoice->tax_rate,
            'tax_amount' => (float) $invoice->tax_amount,
            'total' => (float) $invoice->total,
            'amount_paid' => (float) $invoice->amount_paid,
            'balance_due' => round((float) $invoice->total - (float) $invoice->amount_paid, 2),
            'due_date' => $invoice->due_date,
            'created_at' => $invoice->created_at,
            'sent_at' => $invoice->sent_at,
            'paid_at' => $invoice->paid_at,
        ];

        if ($detailed) {
            $data['notes'] = $invoice->notes;
            $data['line_items'] = array_map(function($item) {
                return [
                    'id' => (int) $item->id,
                    'type' => $item->type,
                    'description' => $item->description,
                    'inventory_item_id' => $item->inventory_item_id ? (int) $item->inventory_item_id : null,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'taxable' => (bool) $item->taxable,
                    'line_total' => (float) $item->line_total,
                ];
            }, FSM_Billing_DB::get_line_items($invoice->id));

            $data['payments'] = array_map(function($payment) {
                return [
                    'id' => (int) $payment->id,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                    'paid_at' => $payment->paid_at,
                ];
            }, FSM_Billing_DB::get_payments($invoice->id));
        }

        return $data;
    }
}
