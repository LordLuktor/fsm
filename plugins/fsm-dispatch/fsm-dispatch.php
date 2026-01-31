<?php
/**
 * Plugin Name: FSM Dispatch & Scheduling
 * Plugin URI: https://github.com/lordluktor/fsm
 * Description: Dispatch and scheduling management for Field Service Management system.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsm-dispatch
 * Domain Path: /languages
 *
 * GitHub Plugin URI: lordluktor/fsm
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 *
 * @package FSM\Dispatch
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure FSM Core is loaded
if (!defined('FSM_CORE_VERSION')) {
    return;
}

// Plugin constants
define('FSM_DISPATCH_VERSION', '1.0.0');
define('FSM_DISPATCH_FILE', __FILE__);
define('FSM_DISPATCH_DIR', plugin_dir_path(__FILE__));
define('FSM_DISPATCH_URL', plugin_dir_url(__FILE__));

/**
 * Main FSM Dispatch class.
 *
 * @since 1.0.0
 */
final class FSM_Dispatch {

    /**
     * Plugin instance.
     *
     * @var FSM_Dispatch
     */
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return FSM_Dispatch
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
        require_once FSM_DISPATCH_DIR . 'includes/class-fsm-dispatch-rest-api.php';
        require_once FSM_DISPATCH_DIR . 'includes/class-fsm-schedule.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action('rest_api_init', [FSM_Dispatch_REST_API::class, 'register_routes']);
    }
}

/**
 * Get FSM Dispatch instance.
 *
 * @return FSM_Dispatch
 */
function fsm_dispatch() {
    return FSM_Dispatch::instance();
}

// Initialize plugin
fsm_dispatch();
