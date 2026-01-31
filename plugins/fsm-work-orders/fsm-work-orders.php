<?php
/**
 * Plugin Name: FSM Work Orders
 * Plugin URI: https://github.com/lordluktor/fsm
 * Description: Work order management for Field Service Management system.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsm-work-orders
 * Domain Path: /languages
 *
 * GitHub Plugin URI: lordluktor/fsm
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 *
 * @package FSM\WorkOrders
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure FSM Core is loaded
if (!defined('FSM_CORE_VERSION')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('FSM Work Orders requires FSM Core plugin to be active.', 'fsm-work-orders'); ?></p>
        </div>
        <?php
    });
    return;
}

// Plugin constants
define('FSM_WORK_ORDERS_VERSION', '1.0.0');
define('FSM_WORK_ORDERS_FILE', __FILE__);
define('FSM_WORK_ORDERS_DIR', plugin_dir_path(__FILE__));
define('FSM_WORK_ORDERS_URL', plugin_dir_url(__FILE__));

/**
 * Main FSM Work Orders class.
 *
 * @since 1.0.0
 */
final class FSM_Work_Orders {

    /**
     * Plugin instance.
     *
     * @var FSM_Work_Orders
     */
    private static $instance = null;

    /**
     * Work order statuses.
     *
     * @var array
     */
    public static $statuses = [
        'new' => 'New',
        'assigned' => 'Assigned',
        'en_route' => 'En Route',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Get plugin instance.
     *
     * @return FSM_Work_Orders
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
        require_once FSM_WORK_ORDERS_DIR . 'includes/class-fsm-work-order-post-type.php';
        require_once FSM_WORK_ORDERS_DIR . 'includes/class-fsm-work-order-rest-api.php';
        require_once FSM_WORK_ORDERS_DIR . 'includes/class-fsm-work-order-status.php';

        if (is_admin()) {
            require_once FSM_WORK_ORDERS_DIR . 'admin/class-fsm-work-order-admin.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(FSM_WORK_ORDERS_FILE, [$this, 'activate']);
        register_deactivation_hook(FSM_WORK_ORDERS_FILE, [$this, 'deactivate']);

        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [FSM_Work_Order_Post_Type::class, 'register'], 6);
        add_action('rest_api_init', [FSM_Work_Order_REST_API::class, 'register_routes']);
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'fsm-work-orders',
            false,
            dirname(plugin_basename(FSM_WORK_ORDERS_FILE)) . '/languages'
        );
    }

    /**
     * Activate plugin.
     */
    public function activate() {
        // Register post type to flush rewrite rules
        FSM_Work_Order_Post_Type::register();
        flush_rewrite_rules();

        update_option('fsm_work_orders_version', FSM_WORK_ORDERS_VERSION);
    }

    /**
     * Deactivate plugin.
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Get status label.
     *
     * @param string $status Status key.
     * @return string
     */
    public static function get_status_label($status) {
        return self::$statuses[$status] ?? $status;
    }

    /**
     * Get all statuses.
     *
     * @return array
     */
    public static function get_statuses() {
        return self::$statuses;
    }
}

/**
 * Get FSM Work Orders instance.
 *
 * @return FSM_Work_Orders
 */
function fsm_work_orders() {
    return FSM_Work_Orders::instance();
}

// Initialize plugin
fsm_work_orders();
