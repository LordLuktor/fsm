<?php
/**
 * FSM Billing Database
 *
 * Custom table creation and management for invoices, line items, and payments.
 *
 * @package FSM\Billing
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Billing DB class.
 */
class FSM_Billing_DB {

    /**
     * Valid invoice statuses.
     *
     * @var array
     */
    const STATUSES = ['draft', 'sent', 'partial', 'paid', 'overdue', 'void'];

    /**
     * Create custom tables.
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $invoices_table = FSM_Billing::get_invoices_table();
        $line_items_table = FSM_Billing::get_line_items_table();
        $payments_table = FSM_Billing::get_payments_table();

        $sql_invoices = "CREATE TABLE $invoices_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) unsigned NOT NULL DEFAULT 0,
            work_order_id bigint(20) unsigned DEFAULT NULL,
            invoice_number varchar(50) DEFAULT NULL,
            paypal_invoice_id varchar(191) DEFAULT NULL,
            customer_name varchar(255) DEFAULT '',
            customer_email varchar(255) DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'draft',
            subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
            tax_rate decimal(5,2) NOT NULL DEFAULT 0.00,
            tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            amount_paid decimal(10,2) NOT NULL DEFAULT 0.00,
            due_date date DEFAULT NULL,
            notes text,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            voided_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY organization_id (organization_id),
            KEY work_order_id (work_order_id),
            KEY status (status),
            KEY paypal_invoice_id (paypal_invoice_id)
        ) $charset_collate;";

        $sql_line_items = "CREATE TABLE $line_items_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            organization_id bigint(20) unsigned NOT NULL DEFAULT 0,
            type enum('labor','part','fee','discount') NOT NULL DEFAULT 'fee',
            description varchar(255) NOT NULL DEFAULT '',
            inventory_item_id bigint(20) unsigned DEFAULT NULL,
            quantity decimal(10,2) NOT NULL DEFAULT 1.00,
            unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
            taxable tinyint(1) NOT NULL DEFAULT 1,
            line_total decimal(10,2) NOT NULL DEFAULT 0.00,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY organization_id (organization_id)
        ) $charset_collate;";

        $sql_payments = "CREATE TABLE $payments_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            organization_id bigint(20) unsigned NOT NULL DEFAULT 0,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            method enum('cash','check','card','bank_transfer','other') NOT NULL DEFAULT 'other',
            reference varchar(255) DEFAULT '',
            notes text,
            processor varchar(20) DEFAULT NULL,
            processor_payment_id varchar(191) DEFAULT NULL,
            idempotency_key varchar(191) DEFAULT NULL,
            recorded_by bigint(20) unsigned DEFAULT NULL,
            paid_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY invoice_id (invoice_id),
            KEY organization_id (organization_id),
            KEY processor_payment_id (processor_payment_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_invoices);
        dbDelta($sql_line_items);
        dbDelta($sql_payments);
    }

    /**
     * Check whether the current user's organization context can access this invoice.
     * Shared by the REST API and the wp-admin UI so org-scoping logic lives in one place.
     *
     * @param object $invoice Invoice row (must have organization_id).
     * @return true|WP_Error
     */
    public static function verify_access($invoice) {
        $context = FSM_Organizations::get_current_context();

        if ($context === null && FSM_Organizations::is_super_admin()) {
            return true;
        }

        if ($context === null) {
            return new WP_Error('forbidden', __('Access denied.', 'fsm-billing'), ['status' => 403]);
        }

        if ((int) $invoice->organization_id !== (int) $context) {
            return new WP_Error('forbidden', __('Access denied.', 'fsm-billing'), ['status' => 403]);
        }

        return true;
    }

    /**
     * Get an invoice by ID.
     *
     * @param int $id Invoice ID.
     * @return object|null
     */
    public static function get_invoice($id) {
        global $wpdb;
        $table = FSM_Billing::get_invoices_table();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    /**
     * Get the invoice for a work order, if one exists.
     *
     * @param int $work_order_id Work order ID.
     * @return object|null
     */
    public static function get_invoice_by_work_order($work_order_id) {
        global $wpdb;
        $table = FSM_Billing::get_invoices_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE work_order_id = %d ORDER BY id DESC LIMIT 1",
            $work_order_id
        ));
    }

    /**
     * Get an invoice by its PayPal invoice ID (set when a PayPal pay link is generated).
     *
     * @param string $paypal_invoice_id PayPal invoice ID.
     * @return object|null
     */
    public static function get_invoice_by_paypal_id($paypal_invoice_id) {
        global $wpdb;
        $table = FSM_Billing::get_invoices_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE paypal_invoice_id = %s",
            $paypal_invoice_id
        ));
    }

    /**
     * List invoices for an organization.
     *
     * @param int   $org_id Organization ID (0 = all, super-admin use only).
     * @param array $args   Additional filters.
     * @return array
     */
    public static function get_invoices($org_id, $args = []) {
        global $wpdb;
        $table = FSM_Billing::get_invoices_table();

        $defaults = [
            'status' => '',
            'customer_email' => '',
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);

        $where = 'WHERE 1=1';
        $params = [];

        if ($org_id) {
            $where .= ' AND organization_id = %d';
            $params[] = $org_id;
        }

        if ($args['status']) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if ($args['customer_email']) {
            $where .= ' AND customer_email = %s';
            $params[] = $args['customer_email'];
        }

        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}") ?: 'id DESC';
        $sql = "SELECT * FROM $table $where ORDER BY $orderby LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Count invoices matching the same filters as get_invoices().
     *
     * @param int   $org_id Organization ID (0 = all).
     * @param array $args   Additional filters.
     * @return int
     */
    public static function count_invoices($org_id, $args = []) {
        global $wpdb;
        $table = FSM_Billing::get_invoices_table();

        $where = 'WHERE 1=1';
        $params = [];

        if ($org_id) {
            $where .= ' AND organization_id = %d';
            $params[] = $org_id;
        }

        if (!empty($args['status'])) {
            $where .= ' AND status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['customer_email'])) {
            $where .= ' AND customer_email = %s';
            $params[] = $args['customer_email'];
        }

        $sql = "SELECT COUNT(*) FROM $table $where";
        if ($params) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
        }
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Create an invoice (without line items — add those separately).
     *
     * @param array $data Invoice data.
     * @return int|false Invoice ID or false on failure.
     */
    public static function create_invoice($data) {
        global $wpdb;
        $table = FSM_Billing::get_invoices_table();

        $result = $wpdb->insert($table, $data);
        if (!$result) {
            return false;
        }

        $id = $wpdb->insert_id;

        // Assign a human-facing invoice number derived from the row ID —
        // matches the WO-000123 convention already used for work orders.
        $number = 'INV-' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $wpdb->update($table, ['invoice_number' => $number], ['id' => $id]);

        return $id;
    }

    /**
     * Update an invoice.
     *
     * @param int   $id   Invoice ID.
     * @param array $data Fields to update.
     * @return bool
     */
    public static function update_invoice($id, $data) {
        global $wpdb;
        $table = FSM_Billing::get_invoices_table();

        return false !== $wpdb->update($table, $data, ['id' => $id]);
    }

    /**
     * Get line items for an invoice, ordered for display.
     *
     * @param int $invoice_id Invoice ID.
     * @return array
     */
    public static function get_line_items($invoice_id) {
        global $wpdb;
        $table = FSM_Billing::get_line_items_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE invoice_id = %d ORDER BY sort_order ASC, id ASC",
            $invoice_id
        ));
    }

    /**
     * Add a line item to an invoice.
     *
     * @param array $data Line item data (invoice_id, organization_id, type, description, quantity, unit_price, taxable, ...).
     * @return int|false Line item ID or false on failure.
     */
    public static function add_line_item($data) {
        global $wpdb;
        $table = FSM_Billing::get_line_items_table();

        $data['line_total'] = round((float) $data['quantity'] * (float) $data['unit_price'], 2);

        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Delete a line item.
     *
     * @param int $id Line item ID.
     * @return bool
     */
    public static function delete_line_item($id) {
        global $wpdb;
        $table = FSM_Billing::get_line_items_table();

        return false !== $wpdb->delete($table, ['id' => $id]);
    }

    /**
     * Recalculate and persist an invoice's subtotal/tax/total from its current line items.
     *
     * @param int $invoice_id Invoice ID.
     * @return object|null The refreshed invoice row.
     */
    public static function recalculate_totals($invoice_id) {
        $invoice = self::get_invoice($invoice_id);
        if (!$invoice) {
            return null;
        }

        $line_items = self::get_line_items($invoice_id);

        $subtotal = 0.0;
        $taxable_subtotal = 0.0;
        foreach ($line_items as $item) {
            $subtotal += (float) $item->line_total;
            if ($item->taxable) {
                $taxable_subtotal += (float) $item->line_total;
            }
        }

        $tax_rate = (float) $invoice->tax_rate;
        $tax_amount = round($taxable_subtotal * $tax_rate / 100, 2);
        $subtotal = round($subtotal, 2);
        $total = round($subtotal + $tax_amount, 2);

        self::update_invoice($invoice_id, [
            'subtotal' => $subtotal,
            'tax_amount' => $tax_amount,
            'total' => $total,
        ]);

        return self::get_invoice($invoice_id);
    }

    /**
     * Look up a payment already recorded for a given idempotency key
     * (e.g. a Stripe/PayPal webhook event ID), so retried webhook deliveries
     * don't double-record the same payment.
     *
     * @param string $idempotency_key Idempotency key.
     * @return object|null
     */
    public static function get_payment_by_idempotency_key($idempotency_key) {
        global $wpdb;
        $table = FSM_Billing::get_payments_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE idempotency_key = %s",
            $idempotency_key
        ));
    }

    /**
     * Record a payment (manual or processor-driven) against an invoice and
     * roll its status forward. If an idempotency_key is provided and already
     * recorded, this is a no-op that returns the existing payment's ID.
     *
     * @param array $data Payment data (invoice_id, organization_id, amount, method, reference, notes,
     *                    processor, processor_payment_id, idempotency_key, recorded_by, paid_at).
     * @return int|false Payment ID or false on failure.
     */
    public static function record_payment($data) {
        global $wpdb;
        $table = FSM_Billing::get_payments_table();

        if (!empty($data['idempotency_key'])) {
            $existing = self::get_payment_by_idempotency_key($data['idempotency_key']);
            if ($existing) {
                return (int) $existing->id;
            }
        }

        $result = $wpdb->insert($table, $data);
        if (!$result) {
            return false;
        }

        $invoice_id = (int) $data['invoice_id'];
        $invoice = self::get_invoice($invoice_id);
        if ($invoice) {
            $amount_paid = round((float) $invoice->amount_paid + (float) $data['amount'], 2);
            $update = ['amount_paid' => $amount_paid];

            if ($amount_paid >= (float) $invoice->total && (float) $invoice->total > 0) {
                $update['status'] = 'paid';
                $update['paid_at'] = current_time('mysql');
            } elseif ($amount_paid > 0) {
                $update['status'] = 'partial';
            }

            self::update_invoice($invoice_id, $update);
        }

        return $wpdb->insert_id;
    }

    /**
     * Get payments recorded against an invoice.
     *
     * @param int $invoice_id Invoice ID.
     * @return array
     */
    public static function get_payments($invoice_id) {
        global $wpdb;
        $table = FSM_Billing::get_payments_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE invoice_id = %d ORDER BY paid_at ASC",
            $invoice_id
        ));
    }
}
