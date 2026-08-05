<?php
/**
 * Tracker PMPro Hooks - Bridges PMPro per-user membership to family-based tier model.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_PMPro_Hooks {

    private static $free_level_id = null;
    private static $premium_level_id = null;
    private static $ultimate_level_id = null;

    public static function init() {
        // Sync family tier after PMPro membership changes
        add_action( 'pmpro_after_checkout', array( __CLASS__, 'after_checkout' ), 10, 2 );
        add_action( 'pmpro_after_change_membership_level', array( __CLASS__, 'after_level_change' ), 10, 2 );
        add_action( 'pmpro_subscription_payment_completed', array( __CLASS__, 'after_payment_completed' ) );
        add_action( 'pmpro_subscription_payment_failed', array( __CLASS__, 'after_payment_failed' ) );

        // Auto-assign Free PMPro level when a user registers
        add_action( 'user_register', array( __CLASS__, 'assign_free_level_on_register' ) );
    }

    /**
     * After checkout completes, sync the family tier.
     */
    public static function after_checkout( $user_id, $morder ) {
        self::sync_family_tier( $user_id );
    }

    /**
     * After membership level changes (upgrade, downgrade, cancel).
     */
    public static function after_level_change( $level_id, $user_id ) {
        $old_tier = self::get_user_tier_from_meta( $user_id );
        self::sync_family_tier( $user_id, $old_tier );
    }

    /**
     * After a recurring payment succeeds.
     */
    public static function after_payment_completed( $morder ) {
        if ( ! empty( $morder->user_id ) ) {
            self::sync_family_tier( $morder->user_id );
        }
    }

    /**
     * After a recurring payment fails.
     */
    public static function after_payment_failed( $morder ) {
        if ( ! empty( $morder->user_id ) ) {
            self::sync_family_tier( $morder->user_id );
        }
    }

    /**
     * Auto-assign the Free PMPro level to new users on registration.
     */
    public static function assign_free_level_on_register( $user_id ) {
        if ( ! function_exists( 'pmpro_changeMembershipLevel' ) ) {
            return;
        }

        $free_level_id = self::get_free_level_id();
        if ( ! $free_level_id ) {
            return;
        }

        // Only assign if user doesn't already have a level
        if ( ! pmpro_hasMembershipLevel( null, $user_id ) ) {
            pmpro_changeMembershipLevel( $free_level_id, $user_id );
        }
    }

    /**
     * Get the previous tier from family meta before the sync.
     *
     * @param int $user_id
     * @return string
     */
    private static function get_user_tier_from_meta( $user_id ) {
        $family_id = Tracker_Core::get_user_family_id( $user_id );
        if ( ! $family_id ) {
            return 'free';
        }
        return get_post_meta( $family_id, '_tracker_tier', true ) ?: 'free';
    }

    /**
     * Sync a user's family tier based on their PMPro membership.
     *
     * Finds the family where this user is the owner and updates
     * _tracker_tier and _tracker_max_members accordingly.
     * Fires upgrade/downgrade actions for PTT provisioning.
     *
     * @param int    $user_id  WordPress user ID.
     * @param string $old_tier Previous tier (optional, for detecting transitions).
     */
    public static function sync_family_tier( $user_id, $old_tier = null ) {
        $family_id = Tracker_Core::get_user_family_id( $user_id );
        if ( ! $family_id ) {
            return;
        }

        // Only sync if this user is the family owner
        if ( ! Tracker_Core::is_family_owner( $user_id, $family_id ) ) {
            return;
        }

        if ( null === $old_tier ) {
            $old_tier = get_post_meta( $family_id, '_tracker_tier', true ) ?: 'free';
        }

        $new_tier = self::get_user_tier( $user_id );

        $tier_limits = array(
            'free'     => 2,
            'premium'  => 8,
            'ultimate' => 0, // 0 = unlimited
        );

        update_post_meta( $family_id, '_tracker_tier', $new_tier );
        update_post_meta( $family_id, '_tracker_max_members', $tier_limits[ $new_tier ] ?? 2 );

        // Fire upgrade/downgrade actions for PTT provisioning
        if ( $old_tier !== $new_tier ) {
            if ( 'ultimate' === $new_tier ) {
                /**
                 * Fires when a family upgrades to Ultimate (or any paid tier).
                 *
                 * @param int    $family_id Family post ID.
                 * @param string $new_tier  The new tier ('ultimate').
                 */
                do_action( 'tracker_subscription_upgraded', $family_id, $new_tier );
            } elseif ( 'ultimate' === $old_tier ) {
                /**
                 * Fires when a family downgrades from Ultimate.
                 *
                 * @param int    $family_id Family post ID.
                 * @param string $old_tier  The old tier ('ultimate').
                 */
                do_action( 'tracker_subscription_downgraded', $family_id, $old_tier );
            }
        }
    }

    /**
     * Determine the user's tier based on their PMPro membership level.
     *
     * @param int $user_id
     * @return string 'free', 'premium', or 'ultimate'
     */
    public static function get_user_tier( $user_id ) {
        if ( self::user_has_ultimate( $user_id ) ) {
            return 'ultimate';
        }
        if ( self::user_has_premium( $user_id ) ) {
            return 'premium';
        }
        return 'free';
    }

    /**
     * Check if a user has the Premium PMPro membership level.
     *
     * @param int $user_id
     * @return bool
     */
    public static function user_has_premium( $user_id ) {
        if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
            return false;
        }

        $level_id = self::get_premium_level_id();
        if ( ! $level_id ) {
            return false;
        }

        return pmpro_hasMembershipLevel( $level_id, $user_id );
    }

    /**
     * Check if a user has the Ultimate PMPro membership level.
     *
     * @param int $user_id
     * @return bool
     */
    public static function user_has_ultimate( $user_id ) {
        if ( ! function_exists( 'pmpro_hasMembershipLevel' ) ) {
            return false;
        }

        $level_id = self::get_ultimate_level_id();
        if ( ! $level_id ) {
            return false;
        }

        return pmpro_hasMembershipLevel( $level_id, $user_id );
    }

    /**
     * Get the PMPro level ID for "Premium", cached in a static var.
     *
     * @return int|false
     */
    public static function get_premium_level_id() {
        if ( null !== self::$premium_level_id ) {
            return self::$premium_level_id;
        }

        // Check option first (set by tracker_ensure_pmpro_levels)
        $level_id = get_option( 'tracker_pmpro_level_created' );
        if ( $level_id ) {
            self::$premium_level_id = (int) $level_id;
            return self::$premium_level_id;
        }

        // Fallback: query by name
        global $wpdb;
        $level_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = %s LIMIT 1",
                'Premium'
            )
        );

        self::$premium_level_id = $level_id ? (int) $level_id : false;
        return self::$premium_level_id;
    }

    /**
     * Get the PMPro level ID for "Ultimate", cached in a static var.
     *
     * @return int|false
     */
    public static function get_ultimate_level_id() {
        if ( null !== self::$ultimate_level_id ) {
            return self::$ultimate_level_id;
        }

        $level_id = get_option( 'tracker_pmpro_ultimate_level_created' );
        if ( $level_id ) {
            self::$ultimate_level_id = (int) $level_id;
            return self::$ultimate_level_id;
        }

        // Fallback: query by name
        global $wpdb;
        $level_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = %s LIMIT 1",
                'Ultimate'
            )
        );

        self::$ultimate_level_id = $level_id ? (int) $level_id : false;
        return self::$ultimate_level_id;
    }

    /**
     * Get the PMPro level ID for "Free", cached in a static var.
     *
     * @return int|false
     */
    public static function get_free_level_id() {
        if ( null !== self::$free_level_id ) {
            return self::$free_level_id;
        }

        $level_id = get_option( 'tracker_pmpro_free_level_created' );
        if ( $level_id ) {
            self::$free_level_id = (int) $level_id;
            return self::$free_level_id;
        }

        // Fallback: query by name
        global $wpdb;
        $level_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = %s LIMIT 1",
                'Free'
            )
        );

        self::$free_level_id = $level_id ? (int) $level_id : false;
        return self::$free_level_id;
    }
}

Tracker_PMPro_Hooks::init();
