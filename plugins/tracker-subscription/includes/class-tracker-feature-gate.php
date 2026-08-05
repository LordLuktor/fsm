<?php
/**
 * Tracker Feature Gate - Checks subscription tier for feature access.
 * Uses PMPro membership of the family owner to determine tier.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Feature_Gate {

    /**
     * Features available per tier.
     */
    private static $tier_features = array(
        'free' => array(
            'basic_tracking',
            'family_map',
            'sos',
            'places',
        ),
        'premium' => array(
            'basic_tracking',
            'family_map',
            'sos',
            'places',
            'geofences',
            'location_history',
            'sharing',
            'extended_members',
        ),
        'ultimate' => array(
            'basic_tracking',
            'family_map',
            'sos',
            'places',
            'geofences',
            'location_history',
            'sharing',
            'extended_members',
            'ptt',
        ),
    );

    /**
     * Check if a family can access a feature.
     *
     * @param int    $family_id The family post ID.
     * @param string $feature   The feature to check.
     * @return bool
     */
    public static function can( $family_id, $feature ) {
        $tier = self::get_tier( $family_id );
        $features = self::$tier_features[ $tier ] ?? self::$tier_features['free'];

        return in_array( $feature, $features, true );
    }

    /**
     * Get the current tier for a family by checking the owner's PMPro membership.
     *
     * @param int $family_id The family post ID.
     * @return string 'free', 'premium', or 'ultimate'
     */
    public static function get_tier( $family_id ) {
        if ( ! $family_id ) {
            return 'free';
        }

        $owner_id = (int) get_post_meta( $family_id, '_tracker_owner_user_id', true );
        if ( ! $owner_id ) {
            return 'free';
        }

        return Tracker_PMPro_Hooks::get_user_tier( $owner_id );
    }

    /**
     * Get tier limits.
     */
    public static function get_limits( $tier ) {
        $limits = array(
            'free' => array(
                'max_members'       => 2,
                'history_hours'     => 24,
                'max_geofences'     => 0,
                'max_share_links'   => 0,
            ),
            'premium' => array(
                'max_members'       => 8,
                'history_days'      => 14,
                'max_geofences'     => 10,
                'max_share_links'   => 5,
            ),
            'ultimate' => array(
                'max_members'       => 0, // unlimited
                'history_days'      => 30,
                'max_geofences'     => 20,
                'max_share_links'   => 0, // unlimited
            ),
        );

        return $limits[ $tier ] ?? $limits['free'];
    }

    /**
     * Get feature availability for the frontend.
     */
    public static function get_feature_status( $family_id ) {
        $tier   = self::get_tier( $family_id );
        $limits = self::get_limits( $tier );

        return array(
            'tier'     => $tier,
            'limits'   => $limits,
            'features' => array(
                'geofences'        => self::can( $family_id, 'geofences' ),
                'location_history' => self::can( $family_id, 'location_history' ),
                'sharing'          => self::can( $family_id, 'sharing' ),
                'extended_members' => self::can( $family_id, 'extended_members' ),
                'ptt'              => self::can( $family_id, 'ptt' ),
            ),
        );
    }
}
