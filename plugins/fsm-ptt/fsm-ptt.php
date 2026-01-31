<?php
/**
 * Plugin Name: FSM PTT Communication
 * Plugin URI: https://github.com/lordluktor/fsm
 * Description: Push-to-Talk communication via Mumble for Field Service Management system.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsm-ptt
 * Domain Path: /languages
 *
 * GitHub Plugin URI: lordluktor/fsm
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 *
 * @package FSM\PTT
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure FSM Core is loaded
if (!defined('FSM_CORE_VERSION')) {
    return;
}

// Plugin constants
define('FSM_PTT_VERSION', '1.0.0');
define('FSM_PTT_FILE', __FILE__);
define('FSM_PTT_DIR', plugin_dir_path(__FILE__));
define('FSM_PTT_URL', plugin_dir_url(__FILE__));

/**
 * Main FSM PTT class.
 *
 * @since 1.0.0
 */
final class FSM_PTT {

    /**
     * Plugin instance.
     *
     * @var FSM_PTT
     */
    private static $instance = null;

    /**
     * Database version.
     */
    const DB_VERSION = '1.0.0';

    /**
     * Get plugin instance.
     *
     * @return FSM_PTT
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
        require_once FSM_PTT_DIR . 'includes/class-fsm-ptt-db.php';
        require_once FSM_PTT_DIR . 'includes/class-fsm-ptt-rest-api.php';
        require_once FSM_PTT_DIR . 'includes/class-fsm-mumble-integration.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(FSM_PTT_FILE, [$this, 'activate']);
        add_action('rest_api_init', [FSM_PTT_REST_API::class, 'register_routes']);
    }

    /**
     * Activate plugin.
     */
    public function activate() {
        FSM_PTT_DB::create_tables();
        update_option('fsm_ptt_db_version', self::DB_VERSION);
        update_option('fsm_ptt_version', FSM_PTT_VERSION);
    }

    /**
     * Get Mumble server host.
     *
     * @return string
     */
    public static function get_mumble_host() {
        return defined('FSM_MUMBLE_HOST') ? FSM_MUMBLE_HOST : 'mumble_mumble-server';
    }

    /**
     * Get Mumble server port.
     *
     * @return int
     */
    public static function get_mumble_port() {
        return defined('FSM_MUMBLE_PORT') ? FSM_MUMBLE_PORT : 64738;
    }

    /**
     * Get channels table name.
     *
     * @return string
     */
    public static function get_channels_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_ptt_channels';
    }
}

/**
 * Get FSM PTT instance.
 *
 * @return FSM_PTT
 */
function fsm_ptt() {
    return FSM_PTT::instance();
}

// Initialize plugin
fsm_ptt();
