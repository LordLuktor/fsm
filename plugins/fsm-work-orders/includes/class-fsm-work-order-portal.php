<?php
/**
 * FSM Work Order Customer Portal
 *
 * Public REST endpoint that allows customers to check work order status
 * via a unique token link. No authentication required.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FSM_Work_Order_Portal {

    const NAMESPACE = 'fsm/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'save_post_fsm_work_order', array( $this, 'ensure_portal_token' ), 10, 1 );
    }

    /**
     * Register the public portal route.
     */
    public function register_routes() {
        register_rest_route( self::NAMESPACE, '/portal/(?P<token>[a-zA-Z0-9_-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_portal_data' ),
            'permission_callback' => '__return_true', // Public endpoint — token is the auth
            'args'                => array(
                'token' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );
    }

    /**
     * Generate a portal token for a work order on save if it doesn't have one.
     */
    public function ensure_portal_token( $post_id ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        $existing = get_post_meta( $post_id, '_fsm_wo_portal_token', true );
        if ( empty( $existing ) ) {
            $token = wp_generate_password( 32, false, false );
            update_post_meta( $post_id, '_fsm_wo_portal_token', $token );
        }
    }

    /**
     * Serve public work order data via portal token.
     *
     * Only returns non-sensitive information: status, title, address, schedule, tech name.
     */
    public function get_portal_data( $request ) {
        global $wpdb;

        $token = sanitize_text_field( $request->get_param( 'token' ) );

        // Look up work order by portal token
        $post_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fsm_wo_portal_token' AND meta_value = %s LIMIT 1",
            $token
        ) );

        if ( ! $post_id ) {
            return new WP_Error( 'not_found', 'Work order not found.', array( 'status' => 404 ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'fsm_work_order' ) {
            return new WP_Error( 'not_found', 'Work order not found.', array( 'status' => 404 ) );
        }

        $status    = get_post_meta( $post_id, '_fsm_wo_status', true ) ?: 'new';
        $reference = get_post_meta( $post_id, '_fsm_wo_reference', true );

        // Get primary technician name (no sensitive data)
        $tech_id   = get_post_meta( $post_id, '_fsm_wo_primary_technician_id', true );
        $tech_name = '';
        if ( $tech_id ) {
            $tech_post = get_post( $tech_id );
            if ( $tech_post ) {
                $tech_name = $tech_post->post_title;
            }
        }

        $data = array(
            'id'              => (int) $post_id,
            'reference'       => sanitize_text_field( $reference ),
            'title'           => sanitize_text_field( $post->post_title ),
            'status'          => sanitize_text_field( $status ),
            'status_label'    => ucwords( str_replace( '_', ' ', $status ) ),
            'scheduled_date'  => get_post_meta( $post_id, '_fsm_wo_scheduled_date', true ),
            'scheduled_time_start' => get_post_meta( $post_id, '_fsm_wo_scheduled_time_start', true ),
            'scheduled_time_end'   => get_post_meta( $post_id, '_fsm_wo_scheduled_time_end', true ),
            'address'         => array(
                'street' => get_post_meta( $post_id, '_fsm_wo_address_street', true ),
                'city'   => get_post_meta( $post_id, '_fsm_wo_address_city', true ),
                'state'  => get_post_meta( $post_id, '_fsm_wo_address_state', true ),
                'zip'    => get_post_meta( $post_id, '_fsm_wo_address_zip', true ),
            ),
            'technician_name' => sanitize_text_field( $tech_name ),
            'company_phone'   => get_option( 'fsm_company_phone', '' ),
        );

        return rest_ensure_response( $data );
    }
}

new FSM_Work_Order_Portal();
