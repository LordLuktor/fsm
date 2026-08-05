<?php
/**
 * Tracker Sharing REST API - Share links and saved places endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Sharing_REST_API {

    public static function register_routes() {
        $ns = TRACKER_API_NAMESPACE;

        // Share links
        register_rest_route( $ns, '/share/create', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'create_share' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_manage_sharing' );
            },
            'args' => array(
                'member_id'  => array( 'type' => 'integer' ),
                'expires_in' => array( 'type' => 'integer', 'default' => 86400, 'description' => 'Expiration in seconds (default 24h)' ),
            ),
        ) );

        register_rest_route( $ns, '/share', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'list_shares' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_manage_sharing' );
            },
        ) );

        register_rest_route( $ns, '/share/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( __CLASS__, 'revoke_share' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_manage_sharing' );
            },
        ) );

        // Public share view (no auth required)
        register_rest_route( $ns, '/share/view/(?P<token>[a-zA-Z0-9]+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'view_share' ),
            'permission_callback' => '__return_true',
        ) );

        // Saved places
        register_rest_route( $ns, '/places', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'list_places' ),
                'permission_callback' => function() {
                    return current_user_can( 'tracker_manage_places' );
                },
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'create_place' ),
                'permission_callback' => function() {
                    return current_user_can( 'tracker_manage_places' );
                },
                'args' => array(
                    'name'      => array( 'required' => true, 'type' => 'string' ),
                    'latitude'  => array( 'required' => true, 'type' => 'number' ),
                    'longitude' => array( 'required' => true, 'type' => 'number' ),
                    'icon'      => array( 'type' => 'string', 'default' => 'pin' ),
                    'address'   => array( 'type' => 'string' ),
                ),
            ),
        ) );

        register_rest_route( $ns, '/places/(?P<id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( __CLASS__, 'delete_place' ),
            'permission_callback' => function() {
                return current_user_can( 'tracker_manage_places' );
            },
        ) );
    }

    // =========================================================================
    // Share Link Endpoints
    // =========================================================================

    public static function create_share( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $expires_in = (int) ( $request->get_param( 'expires_in' ) ?? 86400 );
        $expires_at = $expires_in > 0 ? gmdate( 'Y-m-d H:i:s', time() + $expires_in ) : null;

        $result = Tracker_Sharing_DB::create_share_link( array(
            'family_id'  => $family_id,
            'member_id'  => $request->get_param( 'member_id' ),
            'expires_at' => $expires_at,
            'created_by' => $user_id,
        ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array(
            'id'         => $result['id'],
            'token'      => $result['token'],
            'share_url'  => home_url( '/s/' . $result['token'] ),
            'expires_at' => $result['expires_at'],
        ) );
    }

    public static function list_shares( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $links = Tracker_Sharing_DB::get_family_share_links( $family_id );

        return rest_ensure_response( array_map( function( $l ) {
            return array(
                'id'         => (int) $l['id'],
                'member_id'  => $l['member_id'] ? (int) $l['member_id'] : null,
                'token'      => $l['token'],
                'share_url'  => home_url( '/s/' . $l['token'] ),
                'expires_at' => $l['expires_at'],
                'active'     => (bool) $l['active'],
                'created_at' => $l['created_at'],
            );
        }, $links ) );
    }

    public static function revoke_share( $request ) {
        $id        = (int) $request->get_param( 'id' );
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        $link = Tracker_Sharing_DB::get_share_link( $id );
        if ( ! $link || (int) $link['family_id'] !== (int) $family_id ) {
            return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
        }

        Tracker_Sharing_DB::revoke_share_link( $id );

        return rest_ensure_response( array( 'revoked' => true, 'id' => $id ) );
    }

    /**
     * GET /share/view/:token - Public endpoint for viewing shared location.
     */
    public static function view_share( $request ) {
        $token = sanitize_text_field( $request->get_param( 'token' ) );
        $link  = Tracker_Sharing_DB::get_share_link_by_token( $token );

        if ( ! $link ) {
            return new WP_Error( 'invalid_link', 'This share link is invalid or has expired.', array( 'status' => 404 ) );
        }

        $family_id = (int) $link['family_id'];
        $member_id = $link['member_id'] ? (int) $link['member_id'] : null;

        if ( $member_id ) {
            // Share a single member's location
            $location = Tracker_GPS_DB::get_latest_location( $member_id );
            $member   = Tracker_Core::get_member( $member_id );

            return rest_ensure_response( array(
                'type'    => 'member',
                'members' => array( array(
                    'display_name' => $member ? $member['display_name'] : 'Unknown',
                    'avatar_color' => $member ? $member['avatar_color'] : '#3B82F6',
                    'location'     => $location ? array(
                        'latitude'    => (float) $location['latitude'],
                        'longitude'   => (float) $location['longitude'],
                        'recorded_at' => $location['recorded_at'],
                    ) : null,
                ) ),
            ) );
        }

        // Share all family members' locations
        $locations = Tracker_GPS_DB::get_family_latest_locations( $family_id );
        $members   = Tracker_Core::get_family_members( $family_id );

        $location_map = array();
        foreach ( $locations as $loc ) {
            $location_map[ $loc['member_id'] ] = $loc;
        }

        $result = array();
        foreach ( $members as $m ) {
            $loc = $location_map[ $m['id'] ] ?? null;
            $result[] = array(
                'display_name' => $m['display_name'],
                'avatar_color' => $m['avatar_color'],
                'location'     => $loc ? array(
                    'latitude'    => (float) $loc['latitude'],
                    'longitude'   => (float) $loc['longitude'],
                    'recorded_at' => $loc['recorded_at'],
                ) : null,
            );
        }

        return rest_ensure_response( array(
            'type'    => 'family',
            'members' => $result,
        ) );
    }

    // =========================================================================
    // Places Endpoints
    // =========================================================================

    public static function list_places( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $places = Tracker_Sharing_DB::get_family_places( $family_id );

        return rest_ensure_response( array_map( function( $p ) {
            return array(
                'id'        => (int) $p['id'],
                'name'      => $p['name'],
                'latitude'  => (float) $p['latitude'],
                'longitude' => (float) $p['longitude'],
                'icon'      => $p['icon'],
                'address'   => $p['address'],
            );
        }, $places ) );
    }

    public static function create_place( $request ) {
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        if ( ! $family_id ) {
            return new WP_Error( 'no_family', 'No family found.', array( 'status' => 404 ) );
        }

        $place_id = Tracker_Sharing_DB::create_place( array(
            'family_id'  => $family_id,
            'name'       => sanitize_text_field( $request->get_param( 'name' ) ),
            'latitude'   => (float) $request->get_param( 'latitude' ),
            'longitude'  => (float) $request->get_param( 'longitude' ),
            'icon'       => sanitize_text_field( $request->get_param( 'icon' ) ?? 'pin' ),
            'address'    => sanitize_text_field( $request->get_param( 'address' ) ?? '' ),
            'created_by' => $user_id,
        ) );

        if ( is_wp_error( $place_id ) ) {
            return $place_id;
        }

        $place = Tracker_Sharing_DB::get_place( $place_id );

        return rest_ensure_response( array(
            'id'        => (int) $place['id'],
            'name'      => $place['name'],
            'latitude'  => (float) $place['latitude'],
            'longitude' => (float) $place['longitude'],
            'icon'      => $place['icon'],
            'address'   => $place['address'],
        ) );
    }

    public static function delete_place( $request ) {
        $id        = (int) $request->get_param( 'id' );
        $user_id   = get_current_user_id();
        $family_id = Tracker_Core::get_user_family_id( $user_id );

        $place = Tracker_Sharing_DB::get_place( $id );
        if ( ! $place || (int) $place['family_id'] !== $family_id ) {
            return new WP_Error( 'not_found', 'Place not found.', array( 'status' => 404 ) );
        }

        Tracker_Sharing_DB::delete_place( $id );

        return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
    }
}
