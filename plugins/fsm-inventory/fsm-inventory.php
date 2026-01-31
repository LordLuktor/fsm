<?php
/**
 * Plugin Name: FSM Inventory Management
 * Plugin URI: https://github.com/lordluktor/fsm
 * Description: Inventory tracking and management for Field Service Management system.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsm-inventory
 * Domain Path: /languages
 *
 * GitHub Plugin URI: lordluktor/fsm
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 *
 * @package FSM\Inventory
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure FSM Core is loaded
if (!defined('FSM_CORE_VERSION')) {
    return;
}

// Plugin constants
define('FSM_INVENTORY_VERSION', '1.0.0');
define('FSM_INVENTORY_FILE', __FILE__);
define('FSM_INVENTORY_DIR', plugin_dir_path(__FILE__));
define('FSM_INVENTORY_URL', plugin_dir_url(__FILE__));

/**
 * Main FSM Inventory class.
 *
 * @since 1.0.0
 */
final class FSM_Inventory {

    /**
     * Plugin instance.
     *
     * @var FSM_Inventory
     */
    private static $instance = null;

    /**
     * Database version.
     *
     * @var string
     */
    const DB_VERSION = '1.0.0';

    /**
     * Get plugin instance.
     *
     * @return FSM_Inventory
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
        require_once FSM_INVENTORY_DIR . 'includes/class-fsm-inventory-db.php';
        require_once FSM_INVENTORY_DIR . 'includes/class-fsm-inventory-rest-api.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(FSM_INVENTORY_FILE, [$this, 'activate']);
        add_action('rest_api_init', [FSM_Inventory_REST_API::class, 'register_routes']);
    }

    /**
     * Activate plugin.
     */
    public function activate() {
        FSM_Inventory_DB::create_tables();
        update_option('fsm_inventory_db_version', self::DB_VERSION);
        update_option('fsm_inventory_version', FSM_INVENTORY_VERSION);
    }

    /**
     * Get inventory table name.
     *
     * @return string
     */
    public static function get_items_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_inventory';
    }

    /**
     * Get transaction log table name.
     *
     * @return string
     */
    public static function get_log_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_inventory_log';
    }
}

/**
 * Get FSM Inventory instance.
 *
 * @return FSM_Inventory
 */
function fsm_inventory() {
    return FSM_Inventory::instance();
}

// Initialize plugin
fsm_inventory();
