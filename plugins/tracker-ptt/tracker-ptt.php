<?php
/**
 * Plugin Name: Tracker PTT
 * Description: Push-to-Talk (Mumble) integration for the Personal GPS Tracker Ultimate tier.
 * Version: 1.0.0
 * Author: Steinmetz Ventures
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Require tracker-core to be loaded
if ( ! defined( 'TRACKER_CORE_VERSION' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>Tracker PTT requires the Tracker Core plugin to be active.</p></div>';
    } );
    return;
}

define( 'TRACKER_PTT_VERSION', '1.0.0' );
define( 'TRACKER_PTT_PATH', plugin_dir_path( __FILE__ ) );

class Tracker_PTT_Plugin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();

        add_action( 'rest_api_init', array( 'Tracker_PTT_REST_API', 'register_routes' ) );

        // Hook into subscription changes for Mumble provisioning
        add_action( 'tracker_subscription_upgraded', array( $this, 'handle_subscription_upgraded' ), 10, 2 );
        add_action( 'tracker_member_added', array( $this, 'handle_member_added' ), 10, 2 );
        add_action( 'tracker_subscription_downgraded', array( $this, 'handle_subscription_downgraded' ), 10, 2 );
    }

    private function includes() {
        require_once TRACKER_PTT_PATH . 'includes/class-tracker-mumble-integration.php';
        require_once TRACKER_PTT_PATH . 'includes/class-tracker-ptt-rest-api.php';
    }

    /**
     * When a family upgrades to Ultimate, provision Mumble and register all members.
     *
     * @param int    $family_id Family post ID.
     * @param string $plan      The new plan ('ultimate').
     */
    public function handle_subscription_upgraded( $family_id, $plan ) {
        if ( 'ultimate' !== $plan ) {
            return;
        }

        $family = get_post( $family_id );
        if ( ! $family ) {
            return;
        }

        // Provision family channel in Mumble
        $result = Tracker_Mumble_Integration::provision_family( $family_id, $family->post_title );
        if ( is_wp_error( $result ) ) {
            error_log( 'Tracker PTT: Failed to provision family ' . $family_id . ': ' . $result->get_error_message() );
            return;
        }

        // Register all existing family members in Mumble
        if ( class_exists( 'Tracker_Core' ) ) {
            $members = Tracker_Core::get_family_members( $family_id );
            if ( is_array( $members ) ) {
                foreach ( $members as $member ) {
                    if ( ! empty( $member['user_id'] ) ) {
                        Tracker_Mumble_Integration::register_user( $member['user_id'], $family_id );
                    }
                }
            }
        }
    }

    /**
     * When a new member joins an Ultimate family, register them in Mumble.
     *
     * @param int $user_id   WordPress user ID.
     * @param int $family_id Family post ID.
     */
    public function handle_member_added( $user_id, $family_id ) {
        $tier = Tracker_Feature_Gate::get_tier( $family_id );
        if ( 'ultimate' !== $tier ) {
            return;
        }

        // Only register if family is already provisioned
        if ( ! Tracker_Mumble_Integration::is_family_provisioned( $family_id ) ) {
            return;
        }

        Tracker_Mumble_Integration::register_user( $user_id, $family_id );
    }

    /**
     * When a family downgrades from Ultimate, deprovision Mumble.
     *
     * @param int    $family_id Family post ID.
     * @param string $old_plan  The previous plan ('ultimate').
     */
    public function handle_subscription_downgraded( $family_id, $old_plan ) {
        if ( 'ultimate' !== $old_plan ) {
            return;
        }

        Tracker_Mumble_Integration::deprovision_family( $family_id );
    }
}

Tracker_PTT_Plugin::instance();
