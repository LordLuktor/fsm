<?php
/**
 * FSM Account & Settings REST API
 *
 * Endpoints for password change, session management, profile updates,
 * and notification configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FSM_Account_API {

    const NAMESPACE = 'fsm/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // Change password
        register_rest_route( self::NAMESPACE, '/account/password', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'change_password' ),
            'permission_callback' => array( $this, 'check_logged_in' ),
            'args'                => array(
                'current_password' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => function( $val ) { return $val; }, // Don't sanitize passwords
                ),
                'new_password'     => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => function( $val ) { return $val; },
                ),
            ),
        ) );

        // Update profile
        register_rest_route( self::NAMESPACE, '/account/profile', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'update_profile' ),
            'permission_callback' => array( $this, 'check_logged_in' ),
        ) );

        // List active sessions
        register_rest_route( self::NAMESPACE, '/account/sessions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_sessions' ),
            'permission_callback' => array( $this, 'check_logged_in' ),
        ) );

        // Destroy all other sessions
        register_rest_route( self::NAMESPACE, '/account/sessions/destroy-others', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'destroy_other_sessions' ),
            'permission_callback' => array( $this, 'check_logged_in' ),
        ) );

        // Notification settings (admin/dispatcher only)
        register_rest_route( self::NAMESPACE, '/settings/notifications', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_notification_settings' ),
                'permission_callback' => array( $this, 'check_admin' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'update_notification_settings' ),
                'permission_callback' => array( $this, 'check_admin' ),
            ),
        ) );

        // Company settings (admin only)
        register_rest_route( self::NAMESPACE, '/settings/company', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_company_settings' ),
                'permission_callback' => array( $this, 'check_admin' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'update_company_settings' ),
                'permission_callback' => array( $this, 'check_admin' ),
            ),
        ) );
    }

    // ─── Permission Callbacks ──────────────────────────────

    public function check_logged_in() {
        return is_user_logged_in();
    }

    public function check_admin() {
        return current_user_can( 'fsm_manage_settings' ) || current_user_can( 'manage_options' );
    }

    // ─── Password Change ───────────────────────────────────

    public function change_password( $request ) {
        $user = wp_get_current_user();
        $current = $request->get_param( 'current_password' );
        $new     = $request->get_param( 'new_password' );

        // Verify current password
        if ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
            return new WP_Error( 'invalid_password', 'Current password is incorrect.', array( 'status' => 400 ) );
        }

        // Validate new password
        if ( strlen( $new ) < 10 ) {
            return new WP_Error( 'weak_password', 'New password must be at least 10 characters.', array( 'status' => 400 ) );
        }

        // Update password
        wp_set_password( $new, $user->ID );

        // Generate new auth cookie since password change invalidates sessions
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Password changed successfully. Please log in again.',
        ) );
    }

    // ─── Profile Update ────────────────────────────────────

    public function update_profile( $request ) {
        $user_id = get_current_user_id();

        $fields = array(
            'first_name' => sanitize_text_field( $request->get_param( 'first_name' ) ),
            'last_name'  => sanitize_text_field( $request->get_param( 'last_name' ) ),
        );

        // Remove empty fields
        $fields = array_filter( $fields, function( $v ) { return $v !== null && $v !== ''; } );

        if ( ! empty( $fields ) ) {
            $fields['ID'] = $user_id;
            $result = wp_update_user( $fields );

            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        // Phone stored in user meta
        $phone = $request->get_param( 'phone' );
        if ( $phone !== null ) {
            update_user_meta( $user_id, 'phone', sanitize_text_field( $phone ) );
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Profile updated.',
        ) );
    }

    // ─── Session Management ────────────────────────────────

    public function get_sessions( $request ) {
        $user_id = get_current_user_id();
        $manager = WP_Session_Tokens::get_instance( $user_id );
        $sessions = $manager->get_all();

        $current_token = wp_get_session_token();
        $output = array();

        foreach ( $sessions as $token_hash => $session ) {
            $output[] = array(
                'token_hash' => substr( $token_hash, 0, 8 ) . '...',
                'ip'         => sanitize_text_field( $session['ip'] ?? 'Unknown' ),
                'ua'         => sanitize_text_field( $session['ua'] ?? 'Unknown' ),
                'login'      => $session['login'] ?? 0,
                'expiration' => $session['expiration'] ?? 0,
                'is_current' => ( $token_hash === $current_token ),
                'browser'    => self::parse_browser( $session['ua'] ?? '' ),
            );
        }

        return rest_ensure_response( array(
            'sessions' => $output,
            'count'    => count( $output ),
        ) );
    }

    public function destroy_other_sessions( $request ) {
        $user_id = get_current_user_id();
        $token   = wp_get_session_token();

        // Destroy all sessions except current
        $manager = WP_Session_Tokens::get_instance( $user_id );
        $manager->destroy_others( $token );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'All other sessions have been terminated.',
        ) );
    }

    // ─── Notification Settings ─────────────────────────────

    public function get_notification_settings( $request ) {
        $defaults = array(
            'enabled'               => true,
            'send_on_assigned'      => true,
            'send_on_en_route'      => true,
            'send_on_in_progress'   => true,
            'send_on_completed'     => true,
            'send_on_on_hold'       => true,
            'send_on_cancelled'     => true,
            'from_name'             => get_option( 'fsm_email_from_name', get_bloginfo( 'name' ) ),
            'from_email'            => get_option( 'fsm_email_from_email', get_option( 'admin_email' ) ),
            'reply_to'              => get_option( 'fsm_email_reply_to', '' ),
        );

        $stored = get_option( 'fsm_notification_settings', array() );

        return rest_ensure_response( wp_parse_args( $stored, $defaults ) );
    }

    public function update_notification_settings( $request ) {
        $settings = array(
            'enabled'               => (bool) $request->get_param( 'enabled' ),
            'send_on_assigned'      => (bool) $request->get_param( 'send_on_assigned' ),
            'send_on_en_route'      => (bool) $request->get_param( 'send_on_en_route' ),
            'send_on_in_progress'   => (bool) $request->get_param( 'send_on_in_progress' ),
            'send_on_completed'     => (bool) $request->get_param( 'send_on_completed' ),
            'send_on_on_hold'       => (bool) $request->get_param( 'send_on_on_hold' ),
            'send_on_cancelled'     => (bool) $request->get_param( 'send_on_cancelled' ),
            'from_name'             => sanitize_text_field( $request->get_param( 'from_name' ) ?: '' ),
            'from_email'            => sanitize_email( $request->get_param( 'from_email' ) ?: '' ),
            'reply_to'              => sanitize_email( $request->get_param( 'reply_to' ) ?: '' ),
        );

        update_option( 'fsm_notification_settings', $settings );

        return rest_ensure_response( array(
            'success'  => true,
            'message'  => 'Notification settings saved.',
            'settings' => $settings,
        ) );
    }

    // ─── Company Settings ──────────────────────────────────

    public function get_company_settings( $request ) {
        // Get organization ID for portal settings
        $org_id = FSM_Organizations::get_current_context();

        $data = array(
            'company_name'    => get_option( 'fsm_company_name', '' ),
            'company_phone'   => get_option( 'fsm_company_phone', '' ),
            'company_email'   => get_option( 'fsm_company_email', get_option( 'admin_email' ) ),
            'company_address' => get_option( 'fsm_company_address', '' ),
            'labor_rate'      => (float) get_option( 'fsm_labor_rate', 85 ),
            'tax_rate'        => (float) get_option( 'fsm_tax_rate', 8 ),
        );

        // Portal settings (per-organization)
        if ( $org_id ) {
            $data['portal_enabled']         = (bool) get_post_meta( $org_id, '_fsm_portal_enabled', true );
            $data['portal_domain']          = get_post_meta( $org_id, '_fsm_portal_domain', true ) ?: '';
            $data['portal_logo_url']        = get_post_meta( $org_id, '_fsm_portal_logo_url', true ) ?: '';
            $data['portal_primary_color']   = get_post_meta( $org_id, '_fsm_portal_primary_color', true ) ?: '#2563eb';
            $data['portal_welcome_message'] = get_post_meta( $org_id, '_fsm_portal_welcome_message', true ) ?: '';
            $data['portal_org_code']        = get_post_meta( $org_id, '_fsm_org_code', true ) ?: '';
        }

        return rest_ensure_response( $data );
    }

    public function update_company_settings( $request ) {
        $fields = array(
            'fsm_company_name'    => sanitize_text_field( $request->get_param( 'company_name' ) ?: '' ),
            'fsm_company_phone'   => sanitize_text_field( $request->get_param( 'company_phone' ) ?: '' ),
            'fsm_company_email'   => sanitize_email( $request->get_param( 'company_email' ) ?: '' ),
            'fsm_company_address' => sanitize_textarea_field( $request->get_param( 'company_address' ) ?: '' ),
            'fsm_labor_rate'      => (float) ( $request->get_param( 'labor_rate' ) ?: 85 ),
            'fsm_tax_rate'        => (float) ( $request->get_param( 'tax_rate' ) ?: 8 ),
        );

        foreach ( $fields as $key => $value ) {
            update_option( $key, $value );
        }

        // Portal settings (per-organization)
        $org_id = FSM_Organizations::get_current_context();
        if ( $org_id ) {
            $portal_fields = array(
                '_fsm_portal_enabled'         => (bool) $request->get_param( 'portal_enabled' ),
                '_fsm_portal_domain'          => strtolower( sanitize_text_field( $request->get_param( 'portal_domain' ) ?: '' ) ),
                '_fsm_portal_logo_url'        => esc_url_raw( $request->get_param( 'portal_logo_url' ) ?: '' ),
                '_fsm_portal_primary_color'   => sanitize_hex_color( $request->get_param( 'portal_primary_color' ) ?: '#2563eb' ) ?: '#2563eb',
                '_fsm_portal_welcome_message' => sanitize_textarea_field( $request->get_param( 'portal_welcome_message' ) ?: '' ),
            );

            // Auto-generate org code if not set
            $existing_code = get_post_meta( $org_id, '_fsm_org_code', true );
            if ( empty( $existing_code ) ) {
                $org = get_post( $org_id );
                $code = strtoupper( substr( preg_replace( '/[^a-zA-Z0-9]/', '', $org->post_title ), 0, 6 ) );
                update_post_meta( $org_id, '_fsm_org_code', $code );
            }

            foreach ( $portal_fields as $key => $value ) {
                update_post_meta( $org_id, $key, $value );
            }

            // Clear domain mapping cache
            $domain = $portal_fields['_fsm_portal_domain'];
            if ( $domain ) {
                wp_cache_delete( 'fsm_domain_' . md5( $domain ), 'fsm_domains' );
            }
        }

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Company settings saved.',
        ) );
    }

    // ─── Helpers ───────────────────────────────────────────

    private static function parse_browser( $ua ) {
        if ( stripos( $ua, 'Chrome' ) !== false && stripos( $ua, 'Edg' ) !== false ) return 'Edge';
        if ( stripos( $ua, 'Chrome' ) !== false ) return 'Chrome';
        if ( stripos( $ua, 'Firefox' ) !== false ) return 'Firefox';
        if ( stripos( $ua, 'Safari' ) !== false ) return 'Safari';
        if ( stripos( $ua, 'Mobile' ) !== false ) return 'Mobile';
        return 'Unknown';
    }
}

new FSM_Account_API();
