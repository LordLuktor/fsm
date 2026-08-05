<?php
/**
 * Tracker Geofence Checker - Checks member locations against geofence boundaries.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Geofence_Checker {

    /**
     * Check a member's location against all active family geofences.
     * Fired on tracker_location_updated action.
     *
     * @param int   $member_id  The member post ID.
     * @param int   $family_id  The family post ID.
     * @param array $location   Array with 'latitude' and 'longitude'.
     */
    public static function check_location( $member_id, $family_id, $location ) {
        // Get all active geofences for this family
        $geofences = Tracker_Geofence_DB::get_family_geofences( $family_id, true );

        if ( empty( $geofences ) ) {
            return;
        }

        // Get member's previous "inside" state for each geofence
        $prev_states = get_post_meta( $member_id, '_tracker_geofence_states', true );
        if ( ! is_array( $prev_states ) ) {
            $prev_states = array();
        }

        $new_states = array();

        foreach ( $geofences as $geofence ) {
            $distance = self::haversine_distance(
                $location['latitude'],
                $location['longitude'],
                (float) $geofence['latitude'],
                (float) $geofence['longitude']
            );

            $is_inside = $distance <= (float) $geofence['radius_meters'];
            $was_inside = isset( $prev_states[ $geofence['id'] ] ) ? $prev_states[ $geofence['id'] ] : null;

            $new_states[ $geofence['id'] ] = $is_inside;

            // Skip if no previous state (first check)
            if ( null === $was_inside ) {
                continue;
            }

            // Check for boundary crossing
            $notify_on = $geofence['notify_on'];

            if ( ! $was_inside && $is_inside && in_array( $notify_on, array( 'enter', 'both' ), true ) ) {
                // Entered geofence
                $member = Tracker_Core::get_member( $member_id );
                Tracker_Geofence_DB::create_alert( array(
                    'family_id'   => $family_id,
                    'member_id'   => $member_id,
                    'type'        => 'geofence_enter',
                    'geofence_id' => $geofence['id'],
                    'latitude'    => $location['latitude'],
                    'longitude'   => $location['longitude'],
                    'message'     => sprintf(
                        '%s entered %s',
                        $member ? $member['display_name'] : 'Unknown',
                        $geofence['name']
                    ),
                ) );
            } elseif ( $was_inside && ! $is_inside && in_array( $notify_on, array( 'exit', 'both' ), true ) ) {
                // Exited geofence
                $member = Tracker_Core::get_member( $member_id );
                Tracker_Geofence_DB::create_alert( array(
                    'family_id'   => $family_id,
                    'member_id'   => $member_id,
                    'type'        => 'geofence_exit',
                    'geofence_id' => $geofence['id'],
                    'latitude'    => $location['latitude'],
                    'longitude'   => $location['longitude'],
                    'message'     => sprintf(
                        '%s left %s',
                        $member ? $member['display_name'] : 'Unknown',
                        $geofence['name']
                    ),
                ) );
            }
        }

        // Save new states
        update_post_meta( $member_id, '_tracker_geofence_states', $new_states );
    }

    /**
     * Calculate distance between two coordinates in meters using Haversine formula.
     */
    private static function haversine_distance( $lat1, $lon1, $lat2, $lon2 ) {
        $earth_radius = 6371000; // meters

        $d_lat = deg2rad( $lat2 - $lat1 );
        $d_lon = deg2rad( $lon2 - $lon1 );

        $a = sin( $d_lat / 2 ) * sin( $d_lat / 2 )
            + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) )
            * sin( $d_lon / 2 ) * sin( $d_lon / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return $earth_radius * $c;
    }
}
