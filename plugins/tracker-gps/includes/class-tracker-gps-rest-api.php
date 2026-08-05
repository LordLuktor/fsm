<?php
/**
 * Tracker GPS REST API - Location update and retrieval endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_GPS_REST_API {

    public static function register_routes() {
        $ns = TRACKER_API_NAMESPACE;

        // Update own location
        register_rest_route( $ns, '/gps/update', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'update_location' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_update_location' );
            },
            'args' => array(
                'latitude'      => array( 'required' => true, 'type' => 'number' ),
                'longitude'     => array( 'required' => true, 'type' => 'number' ),
                'accuracy'      => array( 'type' => 'number' ),
                'altitude'      => array( 'type' => 'number' ),
                'heading'       => array( 'type' => 'number' ),
                'speed'         => array( 'type' => 'number' ),
                'battery_level' => array( 'type' => 'integer' ),
            ),
        ) );

        // Get all family members' latest locations
        register_rest_route( $ns, '/gps/family', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_family_locations' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_view_family_locations' );
            },
        ) );

        // Get specific member's current location + optional history
        register_rest_route( $ns, '/gps/member/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_member_location' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_view_family_locations' );
            },
            'args' => array(
                'id' => array( 'required' => true, 'type' => 'integer' ),
            ),
        ) );

        // Get location history for a member (premium)
        register_rest_route( $ns, '/gps/history/(?P<member_id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_member_history' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_view_family_locations' );
            },
            'args' => array(
                'member_id' => array( 'required' => true, 'type' => 'integer' ),
                'date_from' => array( 'type' => 'string' ),
                'date_to'   => array( 'type' => 'string' ),
            ),
        ) );
    }

    /**
     * POST /gps/update - Record current location.
     */
    public static function update_location( $request ) {
        $user_id = get_current_user_id();
        $member  = Tracker_Core::get_member_by_user_id( $user_id );

        if ( ! $member ) {
            return new WP_Error( 'no_member', 'You are not a tracker member.', array( 'status' => 403 ) );
        }

        $location_id = Tracker_GPS_DB::insert_location( array(
            'family_id'     => $member['family_id'],
            'member_id'     => $member['id'],
            'latitude'      => (float) $request->get_param( 'latitude' ),
            'longitude'     => (float) $request->get_param( 'longitude' ),
            'accuracy'      => $request->get_param( 'accuracy' ),
            'altitude'      => $request->get_param( 'altitude' ),
            'heading'       => $request->get_param( 'heading' ),
            'speed'         => $request->get_param( 'speed' ),
            'battery_level' => $request->get_param( 'battery_level' ),
            'source'        => 'device',
            'recorded_at'   => current_time( 'mysql' ),
        ) );

        if ( is_wp_error( $location_id ) ) {
            return $location_id;
        }

        // Fire action for geofence checking
        do_action( 'tracker_location_updated', $member['id'], $member['family_id'], array(
            'latitude'  => (float) $request->get_param( 'latitude' ),
            'longitude' => (float) $request->get_param( 'longitude' ),
        ) );

        return rest_ensure_response( array(
            'location_id' => $location_id,
            'recorded_at' => current_time( 'mysql' ),
        ) );
    }

    /**
     * GET /gps/family - All family members' latest locations.
     */
    public static function get_family_locations( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $locations = Tracker_GPS_DB::get_family_latest_locations( $family_id );
        $members   = Tracker_Core::get_family_members( $family_id );

        // Merge member info with location data
        $result = array();
        $location_map = array();
        foreach ( $locations as $loc ) {
            $location_map[ $loc['member_id'] ] = $loc;
        }

        foreach ( $members as $member ) {
            $loc = $location_map[ $member['id'] ] ?? null;
            $result[] = array(
                'member'   => $member,
                'location' => $loc ? array(
                    'latitude'      => (float) $loc['latitude'],
                    'longitude'     => (float) $loc['longitude'],
                    'accuracy'      => $loc['accuracy'] ? (float) $loc['accuracy'] : null,
                    'altitude'      => $loc['altitude'] ? (float) $loc['altitude'] : null,
                    'heading'       => $loc['heading'] ? (float) $loc['heading'] : null,
                    'speed'         => $loc['speed'] ? (float) $loc['speed'] : null,
                    'battery_level' => $loc['battery_level'] ? (int) $loc['battery_level'] : null,
                    'recorded_at'   => $loc['recorded_at'],
                ) : null,
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * GET /gps/member/:id - Specific member's current location.
     */
    public static function get_member_location( $request ) {
        $member_id = (int) $request->get_param( 'id' );
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        $member = Tracker_Core::get_member( $member_id );
        if ( ! $member || $member['family_id'] !== $family_id ) {
            return new WP_Error( 'not_found', 'Member not found in your family.', array( 'status' => 404 ) );
        }

        $location = Tracker_GPS_DB::get_latest_location( $member_id );

        return rest_ensure_response( array(
            'member'   => $member,
            'location' => $location ? array(
                'latitude'      => (float) $location['latitude'],
                'longitude'     => (float) $location['longitude'],
                'accuracy'      => $location['accuracy'] ? (float) $location['accuracy'] : null,
                'altitude'      => $location['altitude'] ? (float) $location['altitude'] : null,
                'heading'       => $location['heading'] ? (float) $location['heading'] : null,
                'speed'         => $location['speed'] ? (float) $location['speed'] : null,
                'battery_level' => $location['battery_level'] ? (int) $location['battery_level'] : null,
                'recorded_at'   => $location['recorded_at'],
            ) : null,
        ) );
    }

    /**
     * GET /gps/history/:member_id - Location history for a member.
     */
    public static function get_member_history( $request ) {
        $member_id = (int) $request->get_param( 'member_id' );
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        $member = Tracker_Core::get_member( $member_id );
        if ( ! $member || $member['family_id'] !== $family_id ) {
            return new WP_Error( 'not_found', 'Member not found in your family.', array( 'status' => 404 ) );
        }

        // Check tier for history access
        $family = Tracker_Core::get_family( $family_id );
        $tier   = $family['tier'] ?? 'free';

        if ( 'free' === $tier ) {
            // Free tier: only last 24 hours
            $date_from = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
            $date_to   = current_time( 'mysql' );
        } else {
            // Premium: custom date range up to 30 days
            $date_from = $request->get_param( 'date_from' ) ?: gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
            $date_to   = $request->get_param( 'date_to' ) ?: current_time( 'mysql' );

            // Cap at 30 days
            $max_from = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
            if ( $date_from < $max_from ) {
                $date_from = $max_from;
            }
        }

        $history = Tracker_GPS_DB::get_member_history( $member_id, $date_from, $date_to );

        // Format locations
        $formatted = array_map( function( $loc ) {
            return array(
                'latitude'      => (float) $loc['latitude'],
                'longitude'     => (float) $loc['longitude'],
                'accuracy'      => $loc['accuracy'] ? (float) $loc['accuracy'] : null,
                'speed'         => $loc['speed'] ? (float) $loc['speed'] : null,
                'heading'       => $loc['heading'] ? (float) $loc['heading'] : null,
                'battery_level' => $loc['battery_level'] ? (int) $loc['battery_level'] : null,
                'recorded_at'   => $loc['recorded_at'],
            );
        }, $history );

        return rest_ensure_response( array(
            'member'    => $member,
            'tier'      => $tier,
            'date_from' => $date_from,
            'date_to'   => $date_to,
            'locations' => $formatted,
        ) );
    }
}
