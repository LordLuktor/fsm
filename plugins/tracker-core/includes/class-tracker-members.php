<?php
/**
 * Tracker Members - Custom Post Type for family members.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Members {

    private static $avatar_colors = array(
        '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
        '#8B5CF6', '#EC4899', '#06B6D4', '#F97316',
    );

    /**
     * Register the tracker_member post type.
     */
    public static function register_post_type() {
        register_post_type( 'tracker_member', array(
            'labels' => array(
                'name'          => 'Members',
                'singular_name' => 'Member',
            ),
            'public'       => false,
            'show_in_rest' => false,
            'supports'     => array( 'title', 'custom-fields' ),
            'capability_type' => 'tracker_member',
            'map_meta_cap'    => true,
        ) );

        $meta_fields = array(
            '_tracker_user_id'        => 'integer',
            '_tracker_family_id'      => 'integer',
            '_tracker_avatar_color'   => 'string',
            '_tracker_role'           => 'string',
            '_tracker_member_status'  => 'string',
        );

        foreach ( $meta_fields as $key => $type ) {
            register_post_meta( 'tracker_member', $key, array(
                'type'         => $type,
                'single'       => true,
                'show_in_rest' => false,
            ) );
        }
    }

    /**
     * Create a member in a family.
     */
    public static function create_member( $user_id, $family_id, $display_name, $role = 'member' ) {
        // Check if user already belongs to a family
        $existing = Tracker_Core::get_member_by_user_id( $user_id );
        if ( $existing ) {
            return new WP_Error( 'already_member', 'User already belongs to a family.' );
        }

        // Check family member limit
        $family = Tracker_Core::get_family( $family_id );
        if ( ! $family ) {
            return new WP_Error( 'invalid_family', 'Family not found.' );
        }

        $current_members = Tracker_Core::get_family_members( $family_id );
        if ( count( $current_members ) >= $family['max_members'] ) {
            return new WP_Error( 'family_full', 'Family has reached its member limit. Upgrade to add more members.' );
        }

        // Pick an avatar color not yet used in this family
        $used_colors = array_column( $current_members, 'avatar_color' );
        $available   = array_diff( self::$avatar_colors, $used_colors );
        $color       = ! empty( $available ) ? reset( $available ) : self::$avatar_colors[ array_rand( self::$avatar_colors ) ];

        $member_id = wp_insert_post( array(
            'post_type'   => 'tracker_member',
            'post_title'  => sanitize_text_field( $display_name ),
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $member_id ) ) {
            return $member_id;
        }

        update_post_meta( $member_id, '_tracker_user_id', $user_id );
        update_post_meta( $member_id, '_tracker_family_id', $family_id );
        update_post_meta( $member_id, '_tracker_avatar_color', $color );
        update_post_meta( $member_id, '_tracker_role', $role );
        update_post_meta( $member_id, '_tracker_member_status', 'active' );

        // Assign the appropriate WordPress role
        $user = get_user_by( 'id', $user_id );
        if ( $user ) {
            $wp_role = ( 'owner' === $role ) ? 'tracker_owner' : 'tracker_member';
            $user->set_role( $wp_role );
        }

        return $member_id;
    }

    /**
     * Remove a member from a family.
     */
    public static function remove_member( $member_id ) {
        $member = Tracker_Core::get_member( $member_id );
        if ( ! $member ) {
            return new WP_Error( 'not_found', 'Member not found.' );
        }

        // Reset user role to subscriber
        $user = get_user_by( 'id', $member['user_id'] );
        if ( $user ) {
            $user->set_role( 'subscriber' );
        }

        return wp_delete_post( $member_id, true );
    }

    /**
     * Update a member's settings.
     */
    public static function update_member( $member_id, $data ) {
        $allowed = array(
            'display_name'  => 'post_title',
            'avatar_color'  => '_tracker_avatar_color',
            'status'        => '_tracker_member_status',
        );

        foreach ( $allowed as $key => $meta_or_field ) {
            if ( ! isset( $data[ $key ] ) ) {
                continue;
            }
            if ( 'post_title' === $meta_or_field ) {
                wp_update_post( array(
                    'ID'         => $member_id,
                    'post_title' => sanitize_text_field( $data[ $key ] ),
                ) );
            } else {
                update_post_meta( $member_id, $meta_or_field, sanitize_text_field( $data[ $key ] ) );
            }
        }

        return Tracker_Core::get_member( $member_id );
    }
}
