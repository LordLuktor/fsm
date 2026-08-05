<?php
/**
 * Plugin Name: Tracker Core
 * Description: Core plugin for the Personal GPS Tracker - families, members, roles, and authentication.
 * Version: 1.0.0
 * Author: Steinmetz Ventures
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TRACKER_CORE_VERSION', '1.0.0' );
define( 'TRACKER_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TRACKER_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'TRACKER_API_NAMESPACE', 'tracker/v1' );

class Tracker_Core {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();

        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( 'Tracker_Families', 'register_post_type' ), 5 );
        add_action( 'init', array( 'Tracker_Members', 'register_post_type' ), 5 );
        add_action( 'init', array( 'Tracker_Roles', 'register_roles' ), 5 );
        add_action( 'rest_api_init', array( 'Tracker_REST_API', 'register_routes' ) );
    }

    private function includes() {
        require_once TRACKER_CORE_PATH . 'includes/class-tracker-families.php';
        require_once TRACKER_CORE_PATH . 'includes/class-tracker-members.php';
        require_once TRACKER_CORE_PATH . 'includes/class-tracker-roles.php';
        require_once TRACKER_CORE_PATH . 'includes/class-tracker-rest-api.php';
    }

    public function activate() {
        Tracker_Roles::register_roles();
        Tracker_Families::register_post_type();
        Tracker_Members::register_post_type();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Get a family by ID with meta.
     */
    public static function get_family( $family_id ) {
        $post = get_post( $family_id );
        if ( ! $post || 'tracker_family' !== $post->post_type ) {
            return null;
        }
        return array(
            'id'                => $post->ID,
            'name'              => $post->post_title,
            'status'            => get_post_meta( $post->ID, '_tracker_status', true ) ?: 'active',
            'tier'              => get_post_meta( $post->ID, '_tracker_tier', true ) ?: 'free',
            'max_members'       => (int) ( get_post_meta( $post->ID, '_tracker_max_members', true ) ?: 2 ),
            'owner_user_id'     => (int) get_post_meta( $post->ID, '_tracker_owner_user_id', true ),
            'stripe_customer_id'=> get_post_meta( $post->ID, '_tracker_stripe_customer_id', true ) ?: '',
            'created_at'        => $post->post_date,
        );
    }

    /**
     * Get a member by ID with meta.
     */
    public static function get_member( $member_id ) {
        $post = get_post( $member_id );
        if ( ! $post || 'tracker_member' !== $post->post_type ) {
            return null;
        }
        return array(
            'id'            => $post->ID,
            'user_id'       => (int) get_post_meta( $post->ID, '_tracker_user_id', true ),
            'family_id'     => (int) get_post_meta( $post->ID, '_tracker_family_id', true ),
            'display_name'  => $post->post_title,
            'avatar_color'  => get_post_meta( $post->ID, '_tracker_avatar_color', true ) ?: '#3B82F6',
            'role'          => get_post_meta( $post->ID, '_tracker_role', true ) ?: 'member',
            'status'        => get_post_meta( $post->ID, '_tracker_member_status', true ) ?: 'active',
        );
    }

    /**
     * Get the family ID for a user.
     */
    public static function get_user_family_id( $user_id ) {
        $members = get_posts( array(
            'post_type'   => 'tracker_member',
            'meta_key'    => '_tracker_user_id',
            'meta_value'  => $user_id,
            'numberposts' => 1,
            'fields'      => 'ids',
        ) );
        if ( empty( $members ) ) {
            return 0;
        }
        return (int) get_post_meta( $members[0], '_tracker_family_id', true );
    }

    /**
     * Get the member post for a user.
     */
    public static function get_member_by_user_id( $user_id ) {
        $members = get_posts( array(
            'post_type'   => 'tracker_member',
            'meta_key'    => '_tracker_user_id',
            'meta_value'  => $user_id,
            'numberposts' => 1,
        ) );
        if ( empty( $members ) ) {
            return null;
        }
        return self::get_member( $members[0]->ID );
    }

    /**
     * Get all members for a family.
     */
    public static function get_family_members( $family_id ) {
        $posts = get_posts( array(
            'post_type'   => 'tracker_member',
            'meta_key'    => '_tracker_family_id',
            'meta_value'  => $family_id,
            'numberposts' => -1,
        ) );
        $members = array();
        foreach ( $posts as $post ) {
            $members[] = self::get_member( $post->ID );
        }
        return $members;
    }

    /**
     * Check if a user is the owner of a family.
     */
    public static function is_family_owner( $user_id, $family_id ) {
        $owner_id = (int) get_post_meta( $family_id, '_tracker_owner_user_id', true );
        return $owner_id === (int) $user_id;
    }
}

// Initialize
Tracker_Core::instance();
