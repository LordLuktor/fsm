<?php
/**
 * FSM Billing Admin
 *
 * wp-admin UI for viewing/editing invoices: list, line items, manual
 * payments, send/void actions. Org-scoped the same way the REST API is.
 *
 * @package FSM\Billing
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Billing Admin class.
 */
class FSM_Billing_Admin {

    /**
     * Register the Invoices submenu under the FSM Dashboard menu.
     */
    public static function register_menu() {
        add_submenu_page(
            'fsm-dashboard',
            __('Invoices', 'fsm-billing'),
            __('Invoices', 'fsm-billing'),
            'fsm_view_billing',
            'fsm-billing',
            [__CLASS__, 'render']
        );
    }

    /**
     * Render the page: detail view if ?invoice=ID is present, otherwise the list.
     */
    public static function render() {
        if (!current_user_can('fsm_view_billing')) {
            wp_die(__('You do not have permission to access this page.', 'fsm-billing'));
        }

        self::render_notice();

        $invoice_id = absint($_GET['invoice'] ?? 0);
        if ($invoice_id) {
            self::render_detail($invoice_id);
        } else {
            self::render_list();
        }
    }

    /**
     * Show a one-time notice based on ?updated= / ?error= query args.
     */
    private static function render_notice() {
        $updated = sanitize_key($_GET['updated'] ?? '');
        $error = sanitize_key($_GET['error'] ?? '');

        $messages = [
            'created' => __('Invoice created.', 'fsm-billing'),
            'saved' => __('Invoice updated.', 'fsm-billing'),
            'line_item_added' => __('Line item added.', 'fsm-billing'),
            'line_item_removed' => __('Line item removed.', 'fsm-billing'),
            'payment_recorded' => __('Payment recorded.', 'fsm-billing'),
            'sent' => __('Invoice marked as sent.', 'fsm-billing'),
            'void' => __('Invoice voided.', 'fsm-billing'),
        ];

        if ($updated && isset($messages[$updated])) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($messages[$updated]));
        }

        if ($error) {
            printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html($error));
        }
    }

    /**
     * List invoices for the current organization context.
     */
    private static function render_list() {
        $org_id = (int) FSM_Organizations::get_current_context();
        $status_filter = sanitize_key($_GET['status'] ?? '');

        $invoices = FSM_Billing_DB::get_invoices($org_id, [
            'status' => $status_filter,
            'limit' => 100,
        ]);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Invoices', 'fsm-billing'); ?></h1>

            <?php if (current_user_can('fsm_manage_billing')) : ?>
                <a href="#fsm-billing-new" class="page-title-action" onclick="document.getElementById('fsm-billing-new-form').style.display='block';return false;">
                    <?php esc_html_e('New Invoice', 'fsm-billing'); ?>
                </a>
            <?php endif; ?>

            <hr class="wp-header-end">

            <ul class="subsubsub">
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=fsm-billing')); ?>" class="<?php echo $status_filter === '' ? 'current' : ''; ?>"><?php esc_html_e('All', 'fsm-billing'); ?></a> |</li>
                <?php foreach (FSM_Billing_DB::STATUSES as $status) : ?>
                    <li>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=fsm-billing&status=' . $status)); ?>" class="<?php echo $status_filter === $status ? 'current' : ''; ?>">
                            <?php echo esc_html(ucfirst($status)); ?>
                        </a> |
                    </li>
                <?php endforeach; ?>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Invoice #', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Customer', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Status', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Total', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Balance Due', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Created', 'fsm-billing'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)) : ?>
                        <tr><td colspan="6"><?php esc_html_e('No invoices found.', 'fsm-billing'); ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($invoices as $invoice) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=fsm-billing&invoice=' . $invoice->id)); ?>">
                                    <?php echo esc_html($invoice->invoice_number); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($invoice->customer_name ?: $invoice->customer_email); ?></td>
                            <td><span class="fsm-billing-status fsm-billing-status--<?php echo esc_attr($invoice->status); ?>"><?php echo esc_html(ucfirst($invoice->status)); ?></span></td>
                            <td><?php echo esc_html(number_format((float) $invoice->total, 2)); ?></td>
                            <td><?php echo esc_html(number_format((float) $invoice->total - (float) $invoice->amount_paid, 2)); ?></td>
                            <td><?php echo esc_html(mysql2date(get_option('date_format'), $invoice->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (current_user_can('fsm_manage_billing')) : ?>
            <div id="fsm-billing-new-form" style="display:none; margin-top: 20px; max-width: 500px;">
                <h2><?php esc_html_e('New Invoice', 'fsm-billing'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('fsm_billing_save_invoice'); ?>
                    <input type="hidden" name="action" value="fsm_billing_save_invoice">
                    <input type="hidden" name="sub_action" value="create">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="customer_name"><?php esc_html_e('Customer Name', 'fsm-billing'); ?></label></th>
                            <td><input type="text" id="customer_name" name="customer_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="customer_email"><?php esc_html_e('Customer Email', 'fsm-billing'); ?></label></th>
                            <td><input type="email" id="customer_email" name="customer_email" class="regular-text" required></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Create Draft Invoice', 'fsm-billing'); ?>">
                    </p>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Detail view for a single invoice: header, line items, payments, actions.
     *
     * @param int $invoice_id Invoice ID.
     */
    private static function render_detail($invoice_id) {
        $invoice = FSM_Billing_DB::get_invoice($invoice_id);
        if (!$invoice) {
            echo '<div class="wrap"><h1>' . esc_html__('Invoice not found.', 'fsm-billing') . '</h1></div>';
            return;
        }

        $access = FSM_Billing_DB::verify_access($invoice);
        if (is_wp_error($access)) {
            wp_die(esc_html($access->get_error_message()));
        }

        $can_manage = current_user_can('fsm_manage_billing');
        $editable = !in_array($invoice->status, ['paid', 'void'], true);
        $line_items = FSM_Billing_DB::get_line_items($invoice_id);
        $payments = FSM_Billing_DB::get_payments($invoice_id);
        $balance_due = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html($invoice->invoice_number); ?>
                <span class="fsm-billing-status fsm-billing-status--<?php echo esc_attr($invoice->status); ?>"><?php echo esc_html(ucfirst($invoice->status)); ?></span>
            </h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=fsm-billing')); ?>">&larr; <?php esc_html_e('Back to Invoices', 'fsm-billing'); ?></a></p>

            <?php if ($invoice->work_order_id) : ?>
                <p>
                    <?php esc_html_e('Work Order:', 'fsm-billing'); ?>
                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $invoice->work_order_id . '&action=edit')); ?>">
                        <?php echo esc_html(get_the_title($invoice->work_order_id) ?: ('#' . $invoice->work_order_id)); ?>
                    </a>
                </p>
            <?php endif; ?>

            <h2><?php esc_html_e('Customer', 'fsm-billing'); ?></h2>
            <p>
                <?php echo esc_html($invoice->customer_name); ?><br>
                <?php echo esc_html($invoice->customer_email); ?>
            </p>

            <h2><?php esc_html_e('Line Items', 'fsm-billing'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Type', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Description', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Qty', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Unit Price', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Total', 'fsm-billing'); ?></th>
                        <?php if ($can_manage && $editable) : ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($line_items as $item) : ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst($item->type)); ?></td>
                            <td><?php echo esc_html($item->description); ?></td>
                            <td><?php echo esc_html(rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.')); ?></td>
                            <td><?php echo esc_html(number_format((float) $item->unit_price, 2)); ?></td>
                            <td><?php echo esc_html(number_format((float) $item->line_total, 2)); ?></td>
                            <?php if ($can_manage && $editable) : ?>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Remove this line item?', 'fsm-billing')); ?>');">
                                        <?php wp_nonce_field('fsm_billing_save_invoice'); ?>
                                        <input type="hidden" name="action" value="fsm_billing_save_invoice">
                                        <input type="hidden" name="sub_action" value="delete_line_item">
                                        <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice_id); ?>">
                                        <input type="hidden" name="line_id" value="<?php echo esc_attr($item->id); ?>">
                                        <button type="submit" class="button-link-delete"><?php esc_html_e('Remove', 'fsm-billing'); ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($line_items)) : ?>
                        <tr><td colspan="6"><?php esc_html_e('No line items yet.', 'fsm-billing'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right;"><strong><?php esc_html_e('Subtotal', 'fsm-billing'); ?></strong></td>
                        <td><?php echo esc_html(number_format((float) $invoice->subtotal, 2)); ?></td>
                        <?php if ($can_manage && $editable) : ?><td></td><?php endif; ?>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:right;"><strong><?php printf(esc_html__('Tax (%s%%)', 'fsm-billing'), esc_html(number_format((float) $invoice->tax_rate, 2))); ?></strong></td>
                        <td><?php echo esc_html(number_format((float) $invoice->tax_amount, 2)); ?></td>
                        <?php if ($can_manage && $editable) : ?><td></td><?php endif; ?>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:right;"><strong><?php esc_html_e('Total', 'fsm-billing'); ?></strong></td>
                        <td><strong><?php echo esc_html(number_format((float) $invoice->total, 2)); ?></strong></td>
                        <?php if ($can_manage && $editable) : ?><td></td><?php endif; ?>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:right;"><strong><?php esc_html_e('Balance Due', 'fsm-billing'); ?></strong></td>
                        <td><strong><?php echo esc_html(number_format($balance_due, 2)); ?></strong></td>
                        <?php if ($can_manage && $editable) : ?><td></td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>

            <?php if ($can_manage && $editable) : ?>
                <h3><?php esc_html_e('Add Line Item', 'fsm-billing'); ?></h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width: 700px;">
                    <?php wp_nonce_field('fsm_billing_save_invoice'); ?>
                    <input type="hidden" name="action" value="fsm_billing_save_invoice">
                    <input type="hidden" name="sub_action" value="add_line_item">
                    <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice_id); ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="li_type"><?php esc_html_e('Type', 'fsm-billing'); ?></label></th>
                            <td>
                                <select id="li_type" name="type">
                                    <option value="fee"><?php esc_html_e('Fee', 'fsm-billing'); ?></option>
                                    <option value="labor"><?php esc_html_e('Labor', 'fsm-billing'); ?></option>
                                    <option value="part"><?php esc_html_e('Part', 'fsm-billing'); ?></option>
                                    <option value="discount"><?php esc_html_e('Discount', 'fsm-billing'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="li_description"><?php esc_html_e('Description', 'fsm-billing'); ?></label></th>
                            <td><input type="text" id="li_description" name="description" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="li_quantity"><?php esc_html_e('Quantity', 'fsm-billing'); ?></label></th>
                            <td><input type="number" id="li_quantity" name="quantity" value="1" step="0.01" min="0.01" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="li_unit_price"><?php esc_html_e('Unit Price', 'fsm-billing'); ?></label></th>
                            <td><input type="number" id="li_unit_price" name="unit_price" value="0.00" step="0.01" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="li_taxable"><?php esc_html_e('Taxable', 'fsm-billing'); ?></label></th>
                            <td><input type="checkbox" id="li_taxable" name="taxable" value="1" checked></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button" value="<?php esc_attr_e('Add Line Item', 'fsm-billing'); ?>">
                    </p>
                </form>
            <?php endif; ?>

            <h2><?php esc_html_e('Payments', 'fsm-billing'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Amount', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Method', 'fsm-billing'); ?></th>
                        <th><?php esc_html_e('Reference', 'fsm-billing'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment) : ?>
                        <tr>
                            <td><?php echo esc_html(mysql2date(get_option('date_format'), $payment->paid_at)); ?></td>
                            <td><?php echo esc_html(number_format((float) $payment->amount, 2)); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $payment->method))); ?></td>
                            <td><?php echo esc_html($payment->reference); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payments)) : ?>
                        <tr><td colspan="4"><?php esc_html_e('No payments recorded.', 'fsm-billing'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($can_manage && $invoice->status !== 'void' && $balance_due > 0) : ?>
                <h3><?php esc_html_e('Record Payment', 'fsm-billing'); ?></h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width: 500px;">
                    <?php wp_nonce_field('fsm_billing_record_payment'); ?>
                    <input type="hidden" name="action" value="fsm_billing_record_payment">
                    <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice_id); ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="pay_amount"><?php esc_html_e('Amount', 'fsm-billing'); ?></label></th>
                            <td><input type="number" id="pay_amount" name="amount" value="<?php echo esc_attr($balance_due); ?>" step="0.01" min="0.01" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="pay_method"><?php esc_html_e('Method', 'fsm-billing'); ?></label></th>
                            <td>
                                <select id="pay_method" name="method">
                                    <option value="cash"><?php esc_html_e('Cash', 'fsm-billing'); ?></option>
                                    <option value="check"><?php esc_html_e('Check', 'fsm-billing'); ?></option>
                                    <option value="card"><?php esc_html_e('Card', 'fsm-billing'); ?></option>
                                    <option value="bank_transfer"><?php esc_html_e('Bank Transfer', 'fsm-billing'); ?></option>
                                    <option value="other"><?php esc_html_e('Other', 'fsm-billing'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="pay_reference"><?php esc_html_e('Reference', 'fsm-billing'); ?></label></th>
                            <td><input type="text" id="pay_reference" name="reference" class="regular-text" placeholder="<?php esc_attr_e('Check #, transaction ID, etc.', 'fsm-billing'); ?>"></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Record Payment', 'fsm-billing'); ?>">
                    </p>
                </form>
            <?php endif; ?>

            <?php if ($can_manage) : ?>
                <h2><?php esc_html_e('Actions', 'fsm-billing'); ?></h2>
                <p>
                    <?php if ($invoice->status === 'draft') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                            <?php wp_nonce_field('fsm_billing_set_status'); ?>
                            <input type="hidden" name="action" value="fsm_billing_set_status">
                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice_id); ?>">
                            <input type="hidden" name="new_status" value="sent">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Mark as Sent', 'fsm-billing'); ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ((float) $invoice->amount_paid <= 0 && $invoice->status !== 'void') : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(__('Void this invoice?', 'fsm-billing')); ?>');">
                            <?php wp_nonce_field('fsm_billing_set_status'); ?>
                            <input type="hidden" name="action" value="fsm_billing_set_status">
                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice_id); ?>">
                            <input type="hidden" name="new_status" value="void">
                            <button type="submit" class="button"><?php esc_html_e('Void Invoice', 'fsm-billing'); ?></button>
                        </form>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * admin-post handler: create invoice, add line item, delete line item.
     */
    public static function handle_save() {
        if (!current_user_can('fsm_manage_billing')) {
            wp_die(__('You do not have permission to do that.', 'fsm-billing'));
        }
        check_admin_referer('fsm_billing_save_invoice');

        $sub_action = sanitize_key($_POST['sub_action'] ?? '');

        if ($sub_action === 'create') {
            self::handle_create();
            return;
        }

        $invoice_id = absint($_POST['invoice_id'] ?? 0);
        $invoice = FSM_Billing_DB::get_invoice($invoice_id);
        if (!$invoice || is_wp_error(FSM_Billing_DB::verify_access($invoice))) {
            wp_die(__('Invoice not found.', 'fsm-billing'));
        }

        if (in_array($invoice->status, ['paid', 'void'], true)) {
            self::redirect_to_invoice($invoice_id, '', __('Cannot edit a paid or void invoice.', 'fsm-billing'));
        }

        if ($sub_action === 'add_line_item') {
            self::handle_add_line_item($invoice);
        } elseif ($sub_action === 'delete_line_item') {
            self::handle_delete_line_item($invoice);
        } else {
            self::redirect_to_invoice($invoice_id);
        }
    }

    /**
     * Create a new standalone draft invoice.
     */
    private static function handle_create() {
        $org_id = (int) FSM_Organizations::get_current_context();
        if (!$org_id) {
            wp_safe_redirect(admin_url('admin.php?page=fsm-billing&error=' . rawurlencode(__('No organization context.', 'fsm-billing'))));
            exit;
        }

        $invoice_id = FSM_Billing_DB::create_invoice([
            'organization_id' => $org_id,
            'customer_name' => sanitize_text_field(wp_unslash($_POST['customer_name'] ?? '')),
            'customer_email' => sanitize_email(wp_unslash($_POST['customer_email'] ?? '')),
            'status' => 'draft',
            'tax_rate' => FSM_Organizations::get_tax_rate($org_id),
            'created_by' => get_current_user_id(),
        ]);

        if (!$invoice_id) {
            wp_safe_redirect(admin_url('admin.php?page=fsm-billing&error=' . rawurlencode(__('Failed to create invoice.', 'fsm-billing'))));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=fsm-billing&invoice=' . $invoice_id . '&updated=created'));
        exit;
    }

    /**
     * Add a line item to an existing invoice.
     *
     * @param object $invoice Invoice row.
     */
    private static function handle_add_line_item($invoice) {
        $type = sanitize_key($_POST['type'] ?? 'fee');
        if (!in_array($type, ['labor', 'part', 'fee', 'discount'], true)) {
            $type = 'fee';
        }

        $description = sanitize_text_field(wp_unslash($_POST['description'] ?? ''));
        if ($description === '') {
            self::redirect_to_invoice($invoice->id, '', __('Description is required.', 'fsm-billing'));
        }

        $quantity = (float) ($_POST['quantity'] ?? 1);
        $unit_price = (float) ($_POST['unit_price'] ?? 0);
        if ($type !== 'discount' && $unit_price < 0) {
            $unit_price = 0;
        }

        FSM_Billing_DB::add_line_item([
            'invoice_id' => $invoice->id,
            'organization_id' => $invoice->organization_id,
            'type' => $type,
            'description' => $description,
            'quantity' => $quantity ?: 1,
            'unit_price' => $unit_price,
            'taxable' => empty($_POST['taxable']) ? 0 : 1,
            'sort_order' => count(FSM_Billing_DB::get_line_items($invoice->id)),
        ]);

        FSM_Billing_DB::recalculate_totals($invoice->id);
        self::redirect_to_invoice($invoice->id, 'line_item_added');
    }

    /**
     * Delete a line item from an invoice.
     *
     * @param object $invoice Invoice row.
     */
    private static function handle_delete_line_item($invoice) {
        $line_id = absint($_POST['line_id'] ?? 0);

        // Confirm the line item actually belongs to this invoice before deleting.
        $owns_line = false;
        foreach (FSM_Billing_DB::get_line_items($invoice->id) as $item) {
            if ((int) $item->id === $line_id) {
                $owns_line = true;
                break;
            }
        }

        if ($owns_line) {
            FSM_Billing_DB::delete_line_item($line_id);
            FSM_Billing_DB::recalculate_totals($invoice->id);
        }

        self::redirect_to_invoice($invoice->id, 'line_item_removed');
    }

    /**
     * admin-post handler: record a manual payment.
     */
    public static function handle_record_payment() {
        if (!current_user_can('fsm_manage_billing')) {
            wp_die(__('You do not have permission to do that.', 'fsm-billing'));
        }
        check_admin_referer('fsm_billing_record_payment');

        $invoice_id = absint($_POST['invoice_id'] ?? 0);
        $invoice = FSM_Billing_DB::get_invoice($invoice_id);
        if (!$invoice || is_wp_error(FSM_Billing_DB::verify_access($invoice))) {
            wp_die(__('Invoice not found.', 'fsm-billing'));
        }

        if ($invoice->status === 'void') {
            self::redirect_to_invoice($invoice_id, '', __('Cannot record a payment on a void invoice.', 'fsm-billing'));
        }

        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            self::redirect_to_invoice($invoice_id, '', __('Payment amount must be greater than zero.', 'fsm-billing'));
        }

        $method = sanitize_key($_POST['method'] ?? 'other');
        if (!in_array($method, ['cash', 'check', 'card', 'bank_transfer', 'other'], true)) {
            $method = 'other';
        }

        FSM_Billing_DB::record_payment([
            'invoice_id' => $invoice_id,
            'organization_id' => $invoice->organization_id,
            'amount' => round($amount, 2),
            'method' => $method,
            'reference' => sanitize_text_field(wp_unslash($_POST['reference'] ?? '')),
            'recorded_by' => get_current_user_id(),
            'paid_at' => current_time('mysql'),
        ]);

        self::redirect_to_invoice($invoice_id, 'payment_recorded');
    }

    /**
     * admin-post handler: send or void an invoice.
     */
    public static function handle_set_status() {
        if (!current_user_can('fsm_manage_billing')) {
            wp_die(__('You do not have permission to do that.', 'fsm-billing'));
        }
        check_admin_referer('fsm_billing_set_status');

        $invoice_id = absint($_POST['invoice_id'] ?? 0);
        $invoice = FSM_Billing_DB::get_invoice($invoice_id);
        if (!$invoice || is_wp_error(FSM_Billing_DB::verify_access($invoice))) {
            wp_die(__('Invoice not found.', 'fsm-billing'));
        }

        $new_status = sanitize_key($_POST['new_status'] ?? '');

        if ($new_status === 'sent' && $invoice->status === 'draft') {
            FSM_Billing_DB::update_invoice($invoice_id, [
                'status' => 'sent',
                'sent_at' => current_time('mysql'),
            ]);
            self::redirect_to_invoice($invoice_id, 'sent');
        }

        if ($new_status === 'void') {
            if ((float) $invoice->amount_paid > 0) {
                self::redirect_to_invoice($invoice_id, '', __('Cannot void an invoice with recorded payments.', 'fsm-billing'));
            }
            FSM_Billing_DB::update_invoice($invoice_id, [
                'status' => 'void',
                'voided_at' => current_time('mysql'),
            ]);
            self::redirect_to_invoice($invoice_id, 'void');
        }

        self::redirect_to_invoice($invoice_id);
    }

    /**
     * Redirect back to an invoice's detail page, optionally with a status/error message, then exit.
     *
     * @param int    $invoice_id Invoice ID.
     * @param string $updated    Key into the notice map in render_notice(), or empty.
     * @param string $error      Raw error message to display, or empty.
     */
    private static function redirect_to_invoice($invoice_id, $updated = '', $error = '') {
        $url = admin_url('admin.php?page=fsm-billing&invoice=' . $invoice_id);
        if ($updated) {
            $url .= '&updated=' . rawurlencode($updated);
        }
        if ($error) {
            $url .= '&error=' . rawurlencode($error);
        }
        wp_safe_redirect($url);
        exit;
    }
}
