<?php
/**
 * Plugin Name: FSM Billing
 * Plugin URI: https://github.com/lordluktor/fsm
 * Description: Invoicing for Field Service Management work orders — line items, per-org labor/tax rates, and payment tracking.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsm-billing
 * Domain Path: /languages
 *
 * GitHub Plugin URI: lordluktor/fsm
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 *
 * @package FSM\Billing
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure FSM Core is loaded
if (!defined('FSM_CORE_VERSION')) {
    return;
}

// Plugin constants
define('FSM_BILLING_VERSION', '1.0.0');
define('FSM_BILLING_FILE', __FILE__);
define('FSM_BILLING_DIR', plugin_dir_path(__FILE__));
define('FSM_BILLING_URL', plugin_dir_url(__FILE__));

/**
 * Main FSM Billing class.
 *
 * @since 1.0.0
 */
final class FSM_Billing {

    /**
     * Plugin instance.
     *
     * @var FSM_Billing
     */
    private static $instance = null;

    /**
     * Database version. Bump this to trigger dbDelta on the next request.
     *
     * @var string
     */
    const DB_VERSION = '1.2.0';

    /**
     * Get plugin instance.
     *
     * @return FSM_Billing
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once FSM_BILLING_DIR . 'includes/class-fsm-billing-db.php';
        require_once FSM_BILLING_DIR . 'includes/class-fsm-billing-payment-settings.php';
        require_once FSM_BILLING_DIR . 'includes/class-fsm-billing-generator.php';
        require_once FSM_BILLING_DIR . 'includes/class-fsm-billing-stripe-gateway.php';
        require_once FSM_BILLING_DIR . 'includes/class-fsm-billing-paypal-gateway.php';
        require_once FSM_BILLING_DIR . 'includes/class-fsm-billing-rest-api.php';

        if (is_admin()) {
            require_once FSM_BILLING_DIR . 'admin/class-fsm-billing-admin.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // fsm-* plugins are loaded via mu-fsm-loader.php's require_once, not
        // WP's normal plugin-activation flow, so register_activation_hook()
        // never fires here. Create/upgrade tables on init instead, guarded
        // by a version check so it's a no-op after the first successful run
        // (same pattern mu-fsm-loader.php already uses for tracker's tables).
        add_action('init', [__CLASS__, 'maybe_create_tables'], 5);
        // Priority 15: after FSM_Organizations::register_post_type() (priority 5) registers fsm_organization.
        add_action('init', [FSM_Billing_Payment_Settings::class, 'register_meta'], 15);

        add_action('fsm_work_order_status_changed', [FSM_Billing_Generator::class, 'on_work_order_completed'], 10, 3);
        add_action('rest_api_init', [FSM_Billing_REST_API::class, 'register_routes']);

        if (is_admin()) {
            add_action('admin_menu', [FSM_Billing_Admin::class, 'register_menu']);
            add_action('admin_post_fsm_billing_save_invoice', [FSM_Billing_Admin::class, 'handle_save']);
            add_action('admin_post_fsm_billing_record_payment', [FSM_Billing_Admin::class, 'handle_record_payment']);
            add_action('admin_post_fsm_billing_set_status', [FSM_Billing_Admin::class, 'handle_set_status']);
            add_action('admin_post_fsm_billing_send_pay_link', [FSM_Billing_Admin::class, 'handle_send_pay_link']);
            add_action('admin_post_fsm_billing_save_payment_settings', [FSM_Billing_Admin::class, 'handle_save_payment_settings']);
        }
    }

    /**
     * Create or upgrade custom tables if the stored DB version is behind.
     */
    public static function maybe_create_tables() {
        if (get_option('fsm_billing_db_version') === self::DB_VERSION) {
            return;
        }

        FSM_Billing_DB::create_tables();
        update_option('fsm_billing_db_version', self::DB_VERSION);
        update_option('fsm_billing_version', FSM_BILLING_VERSION);
    }

    /**
     * Get invoices table name.
     *
     * @return string
     */
    public static function get_invoices_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_invoices';
    }

    /**
     * Get invoice line items table name.
     *
     * @return string
     */
    public static function get_line_items_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_invoice_line_items';
    }

    /**
     * Get invoice payments table name.
     *
     * @return string
     */
    public static function get_payments_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_invoice_payments';
    }
}

/**
 * Get FSM Billing instance.
 *
 * @return FSM_Billing
 */
function fsm_billing() {
    return FSM_Billing::instance();
}

// Initialize plugin
fsm_billing();
