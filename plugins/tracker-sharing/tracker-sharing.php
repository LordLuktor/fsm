<?php
/**
 * Plugin Name: Tracker Sharing
 * Description: Location sharing links and saved places for the Personal GPS Tracker.
 * Version: 1.0.0
 * Author: Steinmetz Ventures
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TRACKER_SHARING_VERSION', '1.0.0' );
define( 'TRACKER_SHARING_PATH', plugin_dir_path( __FILE__ ) );

class Tracker_Sharing {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();

        register_activation_hook( __FILE__, array( 'Tracker_Sharing_DB', 'create_tables' ) );
        add_action( 'rest_api_init', array( 'Tracker_Sharing_REST_API', 'register_routes' ) );
    }

    private function includes() {
        require_once TRACKER_SHARING_PATH . 'includes/class-tracker-sharing-db.php';
        require_once TRACKER_SHARING_PATH . 'includes/class-tracker-sharing-rest-api.php';
    }
}

Tracker_Sharing::instance();
