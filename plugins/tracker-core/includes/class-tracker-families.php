<?php
/**
 * Tracker Families - Custom Post Type for family groups.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Families {

    /**
     * Register the tracker_family post type.
     */
    public static function register_post_type() {
        register_post_type( 'tracker_family', array(
            'labels' => array(
                'name'          => 'Families',
                'singular_name' => 'Family',
            ),
            'public'       => false,
            'show_in_rest' => false,
            'supports'     => array( 'title', 'custom-fields' ),
            'capability_type' => 'tracker_family',
            'map_meta_cap'    => true,
        ) );

        // Register meta fields
        $meta_fields = array(
            '_tracker_owner_user_id'     => 'integer',
            '_tracker_status'            => 'string',
            '_tracker_tier'              => 'string',
            '_tracker_max_members'       => 'integer',
            '_tracker_stripe_customer_id'=> 'string',
            '_tracker_invite_code'       => 'string',
        );

        foreach ( $meta_fields as $key => $type ) {
            register_post_meta( 'tracker_family', $key, array(
                'type'          => $type,
                'single'        => true,
                'show_in_rest'  => false,
            ) );
        }
    }

    /**
     * Create a new family.
     */
    public static function create_family( $name, $owner_user_id, $tier = 'free' ) {
        $max_members = ( 'premium' === $tier ) ? 10 : 2;

        $family_id = wp_insert_post( array(
            'post_type'   => 'tracker_family',
            'post_title'  => sanitize_text_field( $name ),
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $family_id ) ) {
            return $family_id;
        }

        update_post_meta( $family_id, '_tracker_owner_user_id', $owner_user_id );
        update_post_meta( $family_id, '_tracker_status', 'active' );
        update_post_meta( $family_id, '_tracker_tier', $tier );
        update_post_meta( $family_id, '_tracker_max_members', $max_members );
        update_post_meta( $family_id, '_tracker_invite_code', wp_generate_password( 12, false ) );

        return $family_id;
    }

    /**
     * Generate a new invite code for a family.
     */
    public static function regenerate_invite_code( $family_id ) {
        $code = wp_generate_password( 12, false );
        update_post_meta( $family_id, '_tracker_invite_code', $code );
        return $code;
    }

    /**
     * Get family by invite code.
     */
    public static function get_family_by_invite_code( $code ) {
        $families = get_posts( array(
            'post_type'   => 'tracker_family',
            'meta_key'    => '_tracker_invite_code',
            'meta_value'  => sanitize_text_field( $code ),
            'numberposts' => 1,
        ) );

        if ( empty( $families ) ) {
            return null;
        }

        return Tracker_Core::get_family( $families[0]->ID );
    }
}
