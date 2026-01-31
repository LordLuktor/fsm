<?php
/**
 * Plugin Name: FSM GPS & Location
 * Plugin URI: https://github.com/lordluktor/fsm
 * Description: GPS location tracking for Field Service Management system.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsm-gps
 * Domain Path: /languages
 *
 * GitHub Plugin URI: lordluktor/fsm
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 *
 * @package FSM\GPS
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure FSM Core is loaded
if (!defined('FSM_CORE_VERSION')) {
    return;
}

// Plugin constants
define('FSM_GPS_VERSION', '1.0.0');
define('FSM_GPS_FILE', __FILE__);
define('FSM_GPS_DIR', plugin_dir_path(__FILE__));
define('FSM_GPS_URL', plugin_dir_url(__FILE__));

/**
 * Main FSM GPS class.
 *
 * @since 1.0.0
 */
final class FSM_GPS {

    /**
     * Plugin instance.
     *
     * @var FSM_GPS
     */
    private static $instance = null;

    /**
     * Database version.
     */
    const DB_VERSION = '1.0.0';

    /**
     * Get plugin instance.
     *
     * @return FSM_GPS
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
        require_once FSM_GPS_DIR . 'includes/class-fsm-gps-db.php';
        require_once FSM_GPS_DIR . 'includes/class-fsm-gps-rest-api.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(FSM_GPS_FILE, [$this, 'activate']);
        add_action('rest_api_init', [FSM_GPS_REST_API::class, 'register_routes']);
    }

    /**
     * Activate plugin.
     */
    public function activate() {
        FSM_GPS_DB::create_tables();
        update_option('fsm_gps_db_version', self::DB_VERSION);
        update_option('fsm_gps_version', FSM_GPS_VERSION);
    }

    /**
     * Get locations table name.
     *
     * @return string
     */
    public static function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_gps_locations';
    }
}

/**
 * Get FSM GPS instance.
 *
 * @return FSM_GPS
 */
function fsm_gps() {
    return FSM_GPS::instance();
}

// Initialize plugin
fsm_gps();
