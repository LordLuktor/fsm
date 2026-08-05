<?php
/**
 * FSM Domain Mapping
 *
 * Public endpoint to resolve a custom hostname to an organization's
 * branding (logo, colors, name). Also handles dynamic CORS for
 * custom portal domains.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FSM_Domain_Mapping {

    const NAMESPACE = 'fsm/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_filter( 'rest_pre_serve_request', array( $this, 'handle_cors' ), 10, 4 );
    }

    public function register_routes() {
        // Public: resolve hostname to org branding
        register_rest_route( self::NAMESPACE, '/portal-branding', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_branding' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'hostname' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Public: resolve org by slug (for fsm.steinmetz.ltd/portal/:slug paths)
        register_rest_route( self::NAMESPACE, '/portal-branding/slug/(?P<slug>[a-zA-Z0-9_-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_branding_by_slug' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Resolve hostname to organization branding.
     */
    public function get_branding( $request ) {
        $hostname = strtolower( sanitize_text_field( $request->get_param( 'hostname' ) ) );

        // Check cache first
        $cache_key = 'fsm_domain_' . md5( $hostname );
        $cached = wp_cache_get( $cache_key, 'fsm_domains' );
        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        $org_id = $this->find_org_by_domain( $hostname );
        if ( ! $org_id ) {
            return new WP_Error( 'not_found', 'No organization found for this domain.', array( 'status' => 404 ) );
        }

        $branding = $this->get_org_branding( $org_id );

        // Cache for 5 minutes
        wp_cache_set( $cache_key, $branding, 'fsm_domains', 300 );

        return rest_ensure_response( $branding );
    }

    /**
     * Resolve org slug to branding (for shared-domain portals).
     */
    public function get_branding_by_slug( $request ) {
        $slug = sanitize_title( $request->get_param( 'slug' ) );

        $orgs = get_posts( array(
            'post_type'   => 'fsm_organization',
            'name'        => $slug,
            'numberposts' => 1,
        ) );

        if ( empty( $orgs ) ) {
            return new WP_Error( 'not_found', 'Organization not found.', array( 'status' => 404 ) );
        }

        return rest_ensure_response( $this->get_org_branding( $orgs[0]->ID ) );
    }

    /**
     * Find organization by custom portal domain.
     */
    private function find_org_by_domain( $hostname ) {
        global $wpdb;

        return $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_fsm_portal_domain'
             AND meta_value = %s
             LIMIT 1",
            $hostname
        ) );
    }

    /**
     * Get organization branding data.
     */
    private function get_org_branding( $org_id ) {
        $post = get_post( $org_id );
        if ( ! $post ) {
            return null;
        }

        $portal_enabled = get_post_meta( $org_id, '_fsm_portal_enabled', true );

        return array(
            'organization_id'   => (int) $org_id,
            'organization_slug' => $post->post_name,
            'company_name'      => $post->post_title,
            'portal_enabled'    => (bool) $portal_enabled,
            'logo_url'          => get_post_meta( $org_id, '_fsm_portal_logo_url', true ) ?: '',
            'primary_color'     => get_post_meta( $org_id, '_fsm_portal_primary_color', true ) ?: '#2563eb',
            'phone'             => get_option( 'fsm_company_phone', '' ),
            'email'             => get_option( 'fsm_company_email', '' ),
            'welcome_message'   => get_post_meta( $org_id, '_fsm_portal_welcome_message', true ) ?: '',
            'custom_domain'     => get_post_meta( $org_id, '_fsm_portal_domain', true ) ?: '',
        );
    }

    /**
     * Dynamic CORS for customer portal custom domains.
     */
    public function handle_cors( $served, $result, $request, $server ) {
        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : '';

        if ( empty( $origin ) ) {
            return $served;
        }

        $parsed = wp_parse_url( $origin );
        $host = $parsed['host'] ?? '';

        // Always allow the main FSM domains
        $allowed = array( 'fsm.steinmetz.ltd', 'tracker.steinmetz.ltd' );
        if ( in_array( $host, $allowed, true ) ) {
            return $served;
        }

        // Check if the origin matches a registered portal domain
        $org_id = $this->find_org_by_domain( $host );
        if ( $org_id ) {
            header( 'Access-Control-Allow-Origin: ' . esc_url( $origin ) );
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With' );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Max-Age: 86400' );
        }

        return $served;
    }
}

new FSM_Domain_Mapping();
