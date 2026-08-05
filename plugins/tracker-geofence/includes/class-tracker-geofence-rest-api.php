<?php
/**
 * Tracker Geofence REST API - Geofence CRUD, alerts, and SOS endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Geofence_REST_API {

    public static function register_routes() {
        $ns = TRACKER_API_NAMESPACE;

        // Geofences CRUD
        register_rest_route( $ns, '/geofences', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'list_geofences' ),
                'permission_callback' => function() {
                    return current_user_can( 'tracker_access' );
                },
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'create_geofence' ),
                'permission_callback' => function() {
                    return current_user_can( 'tracker_manage_geofences' );
                },
                'args' => array(
                    'name'          => array( 'required' => true, 'type' => 'string' ),
                    'latitude'      => array( 'required' => true, 'type' => 'number' ),
                    'longitude'     => array( 'required' => true, 'type' => 'number' ),
                    'radius_meters' => array( 'type' => 'integer', 'default' => 100 ),
                    'type'          => array( 'type' => 'string', 'enum' => array( 'safe_zone', 'alert_zone' ), 'default' => 'safe_zone' ),
                    'notify_on'     => array( 'type' => 'string', 'enum' => array( 'enter', 'exit', 'both' ), 'default' => 'both' ),
                ),
            ),
        ) );

        register_rest_route( $ns, '/geofences/(?P<id>\d+)', array(
            array(
                'methods'             => 'PATCH',
                'callback'            => array( __CLASS__, 'update_geofence' ),
                'permission_callback' => function() {
                    return current_user_can( 'tracker_manage_geofences' );
                },
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array( __CLASS__, 'delete_geofence' ),
                'permission_callback' => function() {
                    return current_user_can( 'tracker_manage_geofences' );
                },
            ),
        ) );

        // Alerts
        register_rest_route( $ns, '/alerts', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'list_alerts' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_view_alerts' );
            },
            'args' => array(
                'limit'  => array( 'type' => 'integer', 'default' => 50 ),
                'offset' => array( 'type' => 'integer', 'default' => 0 ),
            ),
        ) );

        register_rest_route( $ns, '/alerts/unread-count', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_unread_count' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_view_alerts' );
            },
        ) );

        register_rest_route( $ns, '/alerts/(?P<id>\d+)/read', array(
            'methods'             => 'PATCH',
            'callback'            => array( __CLASS__, 'mark_alert_read' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_view_alerts' );
            },
        ) );

        register_rest_route( $ns, '/alerts/read-all', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'mark_all_read' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_view_alerts' );
            },
        ) );

        // SOS
        register_rest_route( $ns, '/sos', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'trigger_sos' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_use_sos' );
            },
            'args' => array(
                'latitude'  => array( 'type' => 'number' ),
                'longitude' => array( 'type' => 'number' ),
                'message'   => array( 'type' => 'string' ),
            ),
        ) );
    }

    // =========================================================================
    // Geofence Endpoints
    // =========================================================================

    public static function list_geofences( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $geofences = Tracker_Geofence_DB::get_family_geofences( $family_id, false );

        return rest_ensure_response( array_map( function( $g ) {
            return array(
                'id'            => (int) $g['id'],
                'name'          => $g['name'],
                'latitude'      => (float) $g['latitude'],
                'longitude'     => (float) $g['longitude'],
                'radius_meters' => (int) $g['radius_meters'],
                'type'          => $g['type'],
                'notify_on'     => $g['notify_on'],
                'active'        => (bool) $g['active'],
                'created_at'    => $g['created_at'],
            );
        }, $geofences ) );
    }

    public static function create_geofence( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $geofence_id = Tracker_Geofence_DB::create_geofence( array(
            'family_id'     => $family_id,
            'name'          => $request->get_param( 'name' ),
            'latitude'      => (float) $request->get_param( 'latitude' ),
            'longitude'     => (float) $request->get_param( 'longitude' ),
            'radius_meters' => (int) ( $request->get_param( 'radius_meters' ) ?? 100 ),
            'type'          => $request->get_param( 'type' ) ?? 'safe_zone',
            'notify_on'     => $request->get_param( 'notify_on' ) ?? 'both',
            'created_by'    => $user_id,
        ) );

        if ( is_wp_error( $geofence_id ) ) {
            return $geofence_id;
        }

        $geofence = Tracker_Geofence_DB::get_geofence( $geofence_id );

        return rest_ensure_response( array(
            'id'            => (int) $geofence['id'],
            'name'          => $geofence['name'],
            'latitude'      => (float) $geofence['latitude'],
            'longitude'     => (float) $geofence['longitude'],
            'radius_meters' => (int) $geofence['radius_meters'],
            'type'          => $geofence['type'],
            'notify_on'     => $geofence['notify_on'],
            'active'        => (bool) $geofence['active'],
            'created_at'    => $geofence['created_at'],
        ) );
    }

    public static function update_geofence( $request ) {
        $id        = (int) $request->get_param( 'id' );
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        $geofence = Tracker_Geofence_DB::get_geofence( $id );
        if ( ! $geofence || (int) $geofence['family_id'] !== $family_id ) {
            return new WP_Error( 'not_found', 'Geofence not found.', array( 'status' => 404 ) );
        }

        $data = array();
        foreach ( array( 'name', 'latitude', 'longitude', 'radius_meters', 'type', 'notify_on', 'active' ) as $field ) {
            $val = $request->get_param( $field );
            if ( null !== $val ) {
                $data[ $field ] = $val;
            }
        }

        Tracker_Geofence_DB::update_geofence( $id, $data );
        $updated = Tracker_Geofence_DB::get_geofence( $id );

        return rest_ensure_response( array(
            'id'            => (int) $updated['id'],
            'name'          => $updated['name'],
            'latitude'      => (float) $updated['latitude'],
            'longitude'     => (float) $updated['longitude'],
            'radius_meters' => (int) $updated['radius_meters'],
            'type'          => $updated['type'],
            'notify_on'     => $updated['notify_on'],
            'active'        => (bool) $updated['active'],
            'created_at'    => $updated['created_at'],
        ) );
    }

    public static function delete_geofence( $request ) {
        $id        = (int) $request->get_param( 'id' );
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        $geofence = Tracker_Geofence_DB::get_geofence( $id );
        if ( ! $geofence || (int) $geofence['family_id'] !== $family_id ) {
            return new WP_Error( 'not_found', 'Geofence not found.', array( 'status' => 404 ) );
        }

        Tracker_Geofence_DB::delete_geofence( $id );

        return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
    }

    // =========================================================================
    // Alert Endpoints
    // =========================================================================

    public static function list_alerts( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $limit  = (int) ( $request->get_param( 'limit' ) ?? 50 );
        $offset = (int) ( $request->get_param( 'offset' ) ?? 0 );

        $alerts       = Tracker_Geofence_DB::get_family_alerts( $family_id, $limit, $offset );
        $unread_count = Tracker_Geofence_DB::get_unread_alerts_count( $family_id );

        $formatted = array_map( function( $a ) {
            return array(
                'id'          => (int) $a['id'],
                'member_id'   => (int) $a['member_id'],
                'type'        => $a['type'],
                'geofence_id' => $a['geofence_id'] ? (int) $a['geofence_id'] : null,
                'latitude'    => $a['latitude'] ? (float) $a['latitude'] : null,
                'longitude'   => $a['longitude'] ? (float) $a['longitude'] : null,
                'message'     => $a['message'],
                'read'        => ! empty( $a['read_at'] ),
                'created_at'  => $a['created_at'],
            );
        }, $alerts );

        return rest_ensure_response( array(
            'alerts'       => $formatted,
            'unread_count' => $unread_count,
        ) );
    }

    public static function get_unread_count( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return rest_ensure_response( array( 'unread_count' => 0 ) );
        }

        return rest_ensure_response( array(
            'unread_count' => Tracker_Geofence_DB::get_unread_alerts_count( $family_id ),
        ) );
    }

    public static function mark_alert_read( $request ) {
        $id        = (int) $request->get_param( 'id' );
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        $alert = Tracker_Geofence_DB::get_alert( $id );
        if ( ! $alert || (int) $alert['family_id'] !== (int) $family_id ) {
            return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
        }

        Tracker_Geofence_DB::mark_alert_read( $id );

        return rest_ensure_response( array( 'id' => $id, 'read' => true ) );
    }

    public static function mark_all_read( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        Tracker_Geofence_DB::mark_all_alerts_read( $family_id );

        return rest_ensure_response( array( 'success' => true ) );
    }

    // =========================================================================
    // SOS Endpoint
    // =========================================================================

    public static function trigger_sos( $request ) {
        $user_id = get_current_user_id();
        $member  = Tracker_Core::get_member_by_user_id( $user_id );

        if ( ! $member ) {
            return new WP_Error( 'no_member', 'Not a tracker member.', array( 'status' => 403 ) );
        }

        $message = $request->get_param( 'message' ) ?: sprintf( 'SOS from %s!', $member['display_name'] );

        $alert_id = Tracker_Geofence_DB::create_alert( array(
            'family_id' => $member['family_id'],
            'member_id' => $member['id'],
            'type'      => 'sos',
            'latitude'  => $request->get_param( 'latitude' ),
            'longitude' => $request->get_param( 'longitude' ),
            'message'   => sanitize_text_field( $message ),
        ) );

        if ( is_wp_error( $alert_id ) ) {
            return $alert_id;
        }

        return rest_ensure_response( array(
            'alert_id'   => $alert_id,
            'type'       => 'sos',
            'message'    => $message,
            'created_at' => current_time( 'mysql' ),
        ) );
    }
}
