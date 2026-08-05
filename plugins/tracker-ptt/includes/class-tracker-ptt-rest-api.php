<?php
/**
 * Tracker PTT REST API - Mumble connection info and PTT status endpoints.
 *
 * @package Tracker\PTT
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_PTT_REST_API {

    public static function register_routes() {
        $ns = TRACKER_API_NAMESPACE;

        // Get Mumble connection info for current user
        register_rest_route( $ns, '/ptt/connection', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_connection' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_access' );
            },
        ) );

        // Get PTT feature status
        register_rest_route( $ns, '/ptt/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_status' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_access' );
            },
        ) );
    }

    /**
     * GET /ptt/connection - Mumble connection info for current user.
     */
    public static function get_connection( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        // Check Ultimate plan
        $tier = Tracker_Feature_Gate::get_tier( $family_id );
        if ( 'ultimate' !== $tier ) {
            return new WP_Error( 'not_ultimate', 'PTT requires an Ultimate subscription.', array( 'status' => 403 ) );
        }

        // Check if user is registered in Mumble
        if ( ! Tracker_Mumble_Integration::is_user_registered( $user_id ) ) {
            return new WP_Error( 'not_registered', 'User is not yet registered for PTT. Please wait a moment and try again.', array( 'status' => 404 ) );
        }

        $connection = Tracker_Mumble_Integration::get_connection_info( $user_id );
        if ( is_wp_error( $connection ) ) {
            return $connection;
        }

        return rest_ensure_response( $connection );
    }

    /**
     * GET /ptt/status - PTT feature availability status.
     */
    public static function get_status( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return rest_ensure_response( array(
                'available'          => false,
                'family_provisioned' => false,
                'user_registered'    => false,
            ) );
        }

        $tier = Tracker_Feature_Gate::get_tier( $family_id );

        return rest_ensure_response( array(
            'available'          => 'ultimate' === $tier,
            'family_provisioned' => Tracker_Mumble_Integration::is_family_provisioned( $family_id ),
            'user_registered'    => Tracker_Mumble_Integration::is_user_registered( $user_id ),
        ) );
    }
}
