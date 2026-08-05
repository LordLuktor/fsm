<?php
/**
 * Plugin Name: Tracker Subscription
 * Description: PMPro-based billing and feature gating for the Personal GPS Tracker.
 * Version: 2.0.0
 * Author: Steinmetz Ventures
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TRACKER_SUBSCRIPTION_VERSION', '2.0.0' );
define( 'TRACKER_SUBSCRIPTION_PATH', plugin_dir_path( __FILE__ ) );

class Tracker_Subscription_Plugin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();

        add_action( 'rest_api_init', array( 'Tracker_Subscription_REST_API', 'register_routes' ) );
    }

    private function includes() {
        require_once TRACKER_SUBSCRIPTION_PATH . 'includes/class-tracker-pmpro-hooks.php';
        require_once TRACKER_SUBSCRIPTION_PATH . 'includes/class-tracker-feature-gate.php';
        require_once TRACKER_SUBSCRIPTION_PATH . 'includes/class-tracker-subscription-rest-api.php';
    }
}

Tracker_Subscription_Plugin::instance();
