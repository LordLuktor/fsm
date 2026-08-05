<?php
/**
 * Plugin Name: Tracker GPS
 * Description: GPS location tracking for the Personal GPS Tracker.
 * Version: 1.0.0
 * Author: Steinmetz Ventures
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TRACKER_GPS_VERSION', '1.0.0' );
define( 'TRACKER_GPS_PATH', plugin_dir_path( __FILE__ ) );

class Tracker_GPS {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();

        register_activation_hook( __FILE__, array( 'Tracker_GPS_DB', 'create_tables' ) );
        add_action( 'rest_api_init', array( 'Tracker_GPS_REST_API', 'register_routes' ) );
    }

    private function includes() {
        require_once TRACKER_GPS_PATH . 'includes/class-tracker-gps-db.php';
        require_once TRACKER_GPS_PATH . 'includes/class-tracker-gps-rest-api.php';
    }
}

Tracker_GPS::instance();
