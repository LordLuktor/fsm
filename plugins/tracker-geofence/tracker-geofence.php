<?php
/**
 * Plugin Name: Tracker Geofence
 * Description: Geofences, safety alerts, and SOS for the Personal GPS Tracker.
 * Version: 1.0.0
 * Author: Steinmetz Ventures
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TRACKER_GEOFENCE_VERSION', '1.0.0' );
define( 'TRACKER_GEOFENCE_PATH', plugin_dir_path( __FILE__ ) );

class Tracker_Geofence {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();

        register_activation_hook( __FILE__, array( 'Tracker_Geofence_DB', 'create_tables' ) );
        add_action( 'rest_api_init', array( 'Tracker_Geofence_REST_API', 'register_routes' ) );

        // Hook into GPS updates to check geofences
        add_action( 'tracker_location_updated', array( 'Tracker_Geofence_Checker', 'check_location' ), 10, 3 );
    }

    private function includes() {
        require_once TRACKER_GEOFENCE_PATH . 'includes/class-tracker-geofence-db.php';
        require_once TRACKER_GEOFENCE_PATH . 'includes/class-tracker-geofence-checker.php';
        require_once TRACKER_GEOFENCE_PATH . 'includes/class-tracker-geofence-rest-api.php';
    }
}

Tracker_Geofence::instance();
