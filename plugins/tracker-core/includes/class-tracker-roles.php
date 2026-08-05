<?php
/**
 * Tracker Roles - Custom roles and capabilities for the tracker.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Roles {

    /**
     * Register custom roles.
     */
    public static function register_roles() {
        // Owner role - manages family, sees all members, manages geofences, manages subscription
        add_role( 'tracker_owner', 'Tracker Owner', array(
            'read'                        => true,
            'tracker_access'              => true,
            'tracker_manage_family'       => true,
            'tracker_view_members'        => true,
            'tracker_manage_members'      => true,
            'tracker_update_location'     => true,
            'tracker_view_family_locations'=> true,
            'tracker_manage_geofences'    => true,
            'tracker_view_alerts'         => true,
            'tracker_manage_sharing'      => true,
            'tracker_manage_places'       => true,
            'tracker_manage_subscription' => true,
            'tracker_use_sos'             => true,
        ) );

        // Member role - update own location, view family map
        add_role( 'tracker_member', 'Tracker Member', array(
            'read'                        => true,
            'tracker_access'              => true,
            'tracker_update_location'     => true,
            'tracker_view_family_locations'=> true,
            'tracker_view_alerts'         => true,
            'tracker_manage_places'       => true,
            'tracker_use_sos'             => true,
        ) );

        // Grant admin all tracker capabilities
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $caps = array(
                'tracker_access',
                'tracker_manage_family',
                'tracker_view_members',
                'tracker_manage_members',
                'tracker_update_location',
                'tracker_view_family_locations',
                'tracker_manage_geofences',
                'tracker_view_alerts',
                'tracker_manage_sharing',
                'tracker_manage_places',
                'tracker_manage_subscription',
                'tracker_use_sos',
            );
            foreach ( $caps as $cap ) {
                $admin->add_cap( $cap );
            }
        }
    }

    /**
     * Remove custom roles on deactivation (optional).
     */
    public static function remove_roles() {
        remove_role( 'tracker_owner' );
        remove_role( 'tracker_member' );
    }
}
