<?php
/**
 * FSM Customer Portal REST API
 *
 * Public-facing API for customer accounts: registration, login,
 * work order history, invoices, and service requests.
 * Separate from the staff API — uses fsm_customer role.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FSM_Customer_Portal_API {

    const NAMESPACE = 'fsm/v1/customer-portal';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        $this->ensure_customer_role();
    }

    private function ensure_customer_role() {
        if ( ! get_role( 'fsm_customer' ) ) {
            add_role( 'fsm_customer', 'FSM Customer', array( 'read' => true ) );
        }
    }

    public function register_routes() {
        // ── Auth (public) ──────────────────────────────
        register_rest_route( self::NAMESPACE, '/register', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'register_customer' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NAMESPACE, '/login', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'login_customer' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NAMESPACE, '/forgot-password', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'forgot_password' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NAMESPACE, '/reset-password', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'reset_password' ),
            'permission_callback' => '__return_true',
        ) );

        // ── Profile (authenticated) ────────────────────
        register_rest_route( self::NAMESPACE, '/me', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_profile' ),
                'permission_callback' => array( $this, 'check_customer' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'update_profile' ),
                'permission_callback' => array( $this, 'check_customer' ),
            ),
        ) );

        // ── Work Orders ────────────────────────────────
        register_rest_route( self::NAMESPACE, '/work-orders', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_work_orders' ),
            'permission_callback' => array( $this, 'check_customer' ),
        ) );

        register_rest_route( self::NAMESPACE, '/work-orders/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_work_order' ),
            'permission_callback' => array( $this, 'check_customer' ),
        ) );

        // ── Invoices ───────────────────────────────────
        register_rest_route( self::NAMESPACE, '/invoices', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_invoices' ),
            'permission_callback' => array( $this, 'check_customer' ),
        ) );

        register_rest_route( self::NAMESPACE, '/invoices/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_invoice' ),
            'permission_callback' => array( $this, 'check_customer' ),
        ) );

        // ── Service Request ────────────────────────────
        register_rest_route( self::NAMESPACE, '/service-request', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_service_request' ),
            'permission_callback' => array( $this, 'check_customer' ),
        ) );
    }

    // ─── Permission ────────────────────────────────────────

    public function check_customer() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'not_logged_in', 'Authentication required.', array( 'status' => 401 ) );
        }
        $user = wp_get_current_user();
        if ( ! in_array( 'fsm_customer', (array) $user->roles, true ) ) {
            return new WP_Error( 'not_customer', 'Customer account required.', array( 'status' => 403 ) );
        }
        return true;
    }

    // ─── Auth Endpoints ────────────────────────────────────

    public function register_customer( $request ) {
        $email      = sanitize_email( $request->get_param( 'email' ) );
        $password   = $request->get_param( 'password' );
        $first_name = sanitize_text_field( $request->get_param( 'first_name' ) );
        $last_name  = sanitize_text_field( $request->get_param( 'last_name' ) );
        $phone      = sanitize_text_field( $request->get_param( 'phone' ) );
        $org_code   = sanitize_text_field( $request->get_param( 'organization_code' ) );
        $honeypot   = $request->get_param( 'website' ); // hidden anti-bot field

        // Honeypot check
        if ( ! empty( $honeypot ) ) {
            return new WP_Error( 'registration_failed', 'Registration failed.', array( 'status' => 400 ) );
        }

        // Rate limit: 3 registrations per IP per hour
        $ip = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        if ( strpos( $ip, ',' ) !== false ) { $ip = trim( explode( ',', $ip )[0] ); }
        $rate_key = 'fsm_reg_' . md5( $ip );
        $attempts = (int) get_transient( $rate_key );
        if ( $attempts >= 3 ) {
            return new WP_Error( 'rate_limited', 'Too many registration attempts. Please try again later.', array( 'status' => 429 ) );
        }
        set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'Invalid email address.', array( 'status' => 400 ) );
        }

        if ( strlen( $password ) < 10 ) {
            return new WP_Error( 'weak_password', 'Password must be at least 10 characters.', array( 'status' => 400 ) );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', 'An account with this email already exists.', array( 'status' => 409 ) );
        }

        // Resolve organization from code
        $org_id = 0;
        if ( ! empty( $org_code ) ) {
            $org_id = $this->resolve_org_code( $org_code );
            if ( ! $org_id ) {
                return new WP_Error( 'invalid_org_code', 'Invalid organization code.', array( 'status' => 400 ) );
            }
        }

        // Create user
        $user_id = wp_create_user( $email, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $user = new WP_User( $user_id );
        $user->set_role( 'fsm_customer' );

        wp_update_user( array(
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
        ) );

        if ( $phone ) {
            update_user_meta( $user_id, 'phone', $phone );
        }

        // Link to organization(s)
        if ( $org_id ) {
            update_user_meta( $user_id, '_fsm_customer_organizations', array( $org_id ) );
        }

        // Email verification token
        $verify_token = wp_generate_password( 32, false );
        update_user_meta( $user_id, '_fsm_email_verify_token', $verify_token );
        update_user_meta( $user_id, '_fsm_email_verified', false );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Account created. You can now log in.',
            'user_id' => $user_id,
        ) );
    }

    public function login_customer( $request ) {
        // Rate limit: max 10 login attempts per IP per 15 minutes.
        $ip = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        if ( strpos( $ip, ',' ) !== false ) { $ip = trim( explode( ',', $ip )[0] ); }
        $rate_key = 'fsm_login_' . md5( $ip );
        $attempts = (int) get_transient( $rate_key );
        if ( $attempts >= 10 ) {
            return new WP_Error( 'rate_limited', 'Too many login attempts. Please try again later.', array( 'status' => 429 ) );
        }
        set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );

        $email    = sanitize_email( $request->get_param( 'email' ) );
        $password = $request->get_param( 'password' );

        // Rate limit: 5 attempts per email per 15 minutes
        $rate_key = 'fsm_clogin_' . md5( $email );
        $attempts = (int) get_transient( $rate_key );
        if ( $attempts >= 5 ) {
            return new WP_Error( 'rate_limited', 'Too many login attempts. Please try again in 15 minutes.', array( 'status' => 429 ) );
        }

        $user = wp_authenticate( $email, $password );
        if ( is_wp_error( $user ) ) {
            set_transient( $rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
            return new WP_Error( 'invalid_credentials', 'Invalid email or password.', array( 'status' => 401 ) );
        }

        // Must be a customer
        if ( ! in_array( 'fsm_customer', (array) $user->roles, true ) ) {
            return new WP_Error( 'not_customer', 'This is not a customer account. Please use the staff login.', array( 'status' => 403 ) );
        }

        // Clear rate limit on success
        delete_transient( $rate_key );

        // Generate JWT token
        $token = $this->generate_token( $user );

        $orgs = get_user_meta( $user->ID, '_fsm_customer_organizations', true ) ?: array();

        return rest_ensure_response( array(
            'token'      => $token,
            'user'       => array(
                'id'         => $user->ID,
                'email'      => $user->user_email,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'phone'      => get_user_meta( $user->ID, 'phone', true ),
            ),
            'organizations' => $orgs,
        ) );
    }

    public function forgot_password( $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );
        $user  = get_user_by( 'email', $email );

        // Always return success (don't reveal if email exists)
        if ( ! $user || ! in_array( 'fsm_customer', (array) $user->roles, true ) ) {
            return rest_ensure_response( array(
                'success' => true,
                'message' => 'If an account exists with that email, a reset link has been sent.',
            ) );
        }

        $key = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            return rest_ensure_response( array( 'success' => true, 'message' => 'If an account exists with that email, a reset link has been sent.' ) );
        }

        $reset_url = home_url( "/wp-login.php?action=rp&key={$key}&login=" . rawurlencode( $user->user_login ) );

        wp_mail(
            $email,
            'Password Reset — ' . get_bloginfo( 'name' ),
            sprintf(
                "Hi %s,\n\nYou requested a password reset. Use this link:\n%s\n\nIf you didn't request this, ignore this email.\n\nThis link expires in 24 hours.",
                $user->first_name ?: 'there',
                $reset_url
            )
        );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'If an account exists with that email, a reset link has been sent.',
        ) );
    }

    public function reset_password( $request ) {
        $login    = sanitize_text_field( $request->get_param( 'login' ) );
        $key      = sanitize_text_field( $request->get_param( 'key' ) );
        $password = $request->get_param( 'password' );

        $user = check_password_reset_key( $key, $login );
        if ( is_wp_error( $user ) ) {
            return new WP_Error( 'invalid_key', 'Reset link is invalid or expired.', array( 'status' => 400 ) );
        }

        if ( strlen( $password ) < 10 ) {
            return new WP_Error( 'weak_password', 'Password must be at least 10 characters.', array( 'status' => 400 ) );
        }

        reset_password( $user, $password );

        return rest_ensure_response( array(
            'success' => true,
            'message' => 'Password has been reset. You can now log in.',
        ) );
    }

    // ─── Profile ───────────────────────────────────────────

    public function get_profile() {
        $user = wp_get_current_user();
        $orgs = get_user_meta( $user->ID, '_fsm_customer_organizations', true ) ?: array();

        $org_names = array();
        foreach ( $orgs as $org_id ) {
            $org = get_post( $org_id );
            if ( $org ) {
                $org_names[] = array( 'id' => $org_id, 'name' => $org->post_title );
            }
        }

        return rest_ensure_response( array(
            'id'            => $user->ID,
            'email'         => $user->user_email,
            'first_name'    => $user->first_name,
            'last_name'     => $user->last_name,
            'phone'         => get_user_meta( $user->ID, 'phone', true ),
            'organizations' => $org_names,
        ) );
    }

    public function update_profile( $request ) {
        $user_id = get_current_user_id();

        $fields = array_filter( array(
            'first_name' => sanitize_text_field( $request->get_param( 'first_name' ) ),
            'last_name'  => sanitize_text_field( $request->get_param( 'last_name' ) ),
        ) );

        if ( ! empty( $fields ) ) {
            $fields['ID'] = $user_id;
            wp_update_user( $fields );
        }

        $phone = $request->get_param( 'phone' );
        if ( $phone !== null ) {
            update_user_meta( $user_id, 'phone', sanitize_text_field( $phone ) );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    // ─── Work Orders ───────────────────────────────────────

    public function get_work_orders( $request ) {
        $user   = wp_get_current_user();
        $email  = $user->user_email;
        $orgs   = get_user_meta( $user->ID, '_fsm_customer_organizations', true ) ?: array();
        $status = sanitize_text_field( $request->get_param( 'status' ) );
        $page   = absint( $request->get_param( 'page' ) ) ?: 1;

        $meta_query = array(
            array( 'key' => '_fsm_wo_customer_email', 'value' => $email ),
        );

        if ( ! empty( $orgs ) ) {
            $meta_query[] = array( 'key' => '_fsm_organization_id', 'value' => $orgs, 'compare' => 'IN' );
        }

        if ( $status ) {
            $meta_query[] = array( 'key' => '_fsm_wo_status', 'value' => $status );
        }

        $args = array(
            'post_type'      => 'fsm_work_order',
            'posts_per_page' => 20,
            'paged'          => $page,
            'meta_query'     => $meta_query,
            'orderby'        => 'meta_value',
            'meta_key'       => '_fsm_wo_scheduled_date',
            'order'          => 'DESC',
        );

        $query = new WP_Query( $args );
        $orders = array();

        foreach ( $query->posts as $post ) {
            $orders[] = $this->format_work_order( $post );
        }

        return rest_ensure_response( array(
            'data'       => $orders,
            'total'      => $query->found_posts,
            'pages'      => $query->max_num_pages,
            'page'       => $page,
        ) );
    }

    public function get_work_order( $request ) {
        $id   = absint( $request->get_param( 'id' ) );
        $user = wp_get_current_user();
        $post = get_post( $id );

        if ( ! $post || $post->post_type !== 'fsm_work_order' ) {
            return new WP_Error( 'not_found', 'Work order not found.', array( 'status' => 404 ) );
        }

        // Verify this customer owns this work order
        $wo_email = get_post_meta( $id, '_fsm_wo_customer_email', true );
        if ( strtolower( $wo_email ) !== strtolower( $user->user_email ) ) {
            return new WP_Error( 'forbidden', 'Access denied.', array( 'status' => 403 ) );
        }

        return rest_ensure_response( $this->format_work_order( $post, true ) );
    }

    // ─── Invoices ──────────────────────────────────────────

    /**
     * Invoice statuses a customer is allowed to see. Drafts are internal-only
     * (still being built by the org) and void invoices were cancelled.
     */
    const CUSTOMER_VISIBLE_INVOICE_STATUSES = array( 'sent', 'partial', 'paid', 'overdue' );

    public function get_invoices( $request ) {
        if ( ! class_exists( 'FSM_Billing_DB' ) ) {
            return rest_ensure_response( array( 'data' => array(), 'total' => 0, 'pages' => 0, 'page' => 1 ) );
        }

        global $wpdb;
        $user  = wp_get_current_user();
        $email = $user->user_email;
        $orgs  = get_user_meta( $user->ID, '_fsm_customer_organizations', true ) ?: array();
        $page  = absint( $request->get_param( 'page' ) ) ?: 1;
        $per_page = 20;

        $table = FSM_Billing::get_invoices_table();
        $status_placeholders = implode( ',', array_fill( 0, count( self::CUSTOMER_VISIBLE_INVOICE_STATUSES ), '%s' ) );

        $where = "WHERE customer_email = %s AND status IN ($status_placeholders)";
        $params = array_merge( array( $email ), self::CUSTOMER_VISIBLE_INVOICE_STATUSES );

        if ( ! empty( $orgs ) ) {
            $org_placeholders = implode( ',', array_fill( 0, count( $orgs ), '%d' ) );
            $where .= " AND organization_id IN ($org_placeholders)";
            $params = array_merge( $params, array_map( 'absint', $orgs ) );
        }

        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table $where", $params ) );

        $list_params = array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY id DESC LIMIT %d OFFSET %d",
            $list_params
        ) );

        return rest_ensure_response( array(
            'data'  => array_map( array( $this, 'format_invoice' ), $rows ),
            'total' => $total,
            'pages' => (int) ceil( $total / $per_page ),
            'page'  => $page,
        ) );
    }

    public function get_invoice( $request ) {
        if ( ! class_exists( 'FSM_Billing_DB' ) ) {
            return new WP_Error( 'not_found', 'Invoice not found.', array( 'status' => 404 ) );
        }

        $id      = absint( $request->get_param( 'id' ) );
        $user    = wp_get_current_user();
        $invoice = FSM_Billing_DB::get_invoice( $id );

        if ( ! $invoice || strtolower( $invoice->customer_email ) !== strtolower( $user->user_email ) ) {
            return new WP_Error( 'not_found', 'Invoice not found.', array( 'status' => 404 ) );
        }

        if ( ! in_array( $invoice->status, self::CUSTOMER_VISIBLE_INVOICE_STATUSES, true ) ) {
            return new WP_Error( 'not_invoiced', 'Invoice not available yet.', array( 'status' => 400 ) );
        }

        return rest_ensure_response( $this->format_invoice( $invoice, true ) );
    }

    // ─── Service Request ───────────────────────────────────

    public function create_service_request( $request ) {
        $user = wp_get_current_user();
        $orgs = get_user_meta( $user->ID, '_fsm_customer_organizations', true ) ?: array();

        $org_id = absint( $request->get_param( 'organization_id' ) );
        if ( $org_id && ! in_array( $org_id, $orgs, true ) ) {
            return new WP_Error( 'invalid_org', 'Invalid organization.', array( 'status' => 400 ) );
        }
        if ( ! $org_id && ! empty( $orgs ) ) {
            $org_id = $orgs[0]; // Default to first linked org
        }

        $title       = sanitize_text_field( $request->get_param( 'title' ) );
        $description = sanitize_textarea_field( $request->get_param( 'description' ) );
        $preferred_date = sanitize_text_field( $request->get_param( 'preferred_date' ) );
        $urgency     = sanitize_text_field( $request->get_param( 'urgency' ) );
        $address     = $request->get_param( 'address' );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', 'Please describe the issue.', array( 'status' => 400 ) );
        }

        // Create work order
        $post_id = wp_insert_post( array(
            'post_type'   => 'fsm_work_order',
            'post_title'  => $title,
            'post_content' => $description,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Generate reference number
        $reference = 'WO-' . str_pad( $post_id, 6, '0', STR_PAD_LEFT );

        // Set meta
        $meta = array(
            '_fsm_wo_reference'       => $reference,
            '_fsm_wo_status'          => 'new',
            '_fsm_wo_priority'        => ( $urgency === 'urgent' ) ? 'urgent' : ( ( $urgency === 'high' ) ? 'high' : 'normal' ),
            '_fsm_wo_customer_name'   => trim( $user->first_name . ' ' . $user->last_name ),
            '_fsm_wo_customer_email'  => $user->user_email,
            '_fsm_wo_customer_phone'  => get_user_meta( $user->ID, 'phone', true ),
            '_fsm_organization_id'    => $org_id,
        );

        if ( $preferred_date ) {
            $meta['_fsm_wo_scheduled_date'] = $preferred_date;
        }

        if ( is_array( $address ) ) {
            foreach ( array( 'street', 'city', 'state', 'zip' ) as $field ) {
                if ( ! empty( $address[ $field ] ) ) {
                    $meta[ '_fsm_wo_address_' . $field ] = sanitize_text_field( $address[ $field ] );
                }
            }
        }

        foreach ( $meta as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // Generate portal token
        update_post_meta( $post_id, '_fsm_wo_portal_token', wp_generate_password( 32, false, false ) );

        return rest_ensure_response( array(
            'success'   => true,
            'reference' => $reference,
            'id'        => $post_id,
            'message'   => 'Service request submitted. You will be contacted to schedule.',
        ) );
    }

    // ─── Formatters ────────────────────────────────────────

    private function format_work_order( $post, $detailed = false ) {
        $id = $post->ID;
        $status = get_post_meta( $id, '_fsm_wo_status', true ) ?: 'new';

        $data = array(
            'id'              => $id,
            'reference'       => get_post_meta( $id, '_fsm_wo_reference', true ),
            'title'           => $post->post_title,
            'status'          => $status,
            'status_label'    => ucwords( str_replace( '_', ' ', $status ) ),
            'priority'        => get_post_meta( $id, '_fsm_wo_priority', true ),
            'scheduled_date'  => get_post_meta( $id, '_fsm_wo_scheduled_date', true ),
            'scheduled_time_start' => get_post_meta( $id, '_fsm_wo_scheduled_time_start', true ),
            'scheduled_time_end'   => get_post_meta( $id, '_fsm_wo_scheduled_time_end', true ),
            'address'         => array(
                'street' => get_post_meta( $id, '_fsm_wo_address_street', true ),
                'city'   => get_post_meta( $id, '_fsm_wo_address_city', true ),
                'state'  => get_post_meta( $id, '_fsm_wo_address_state', true ),
                'zip'    => get_post_meta( $id, '_fsm_wo_address_zip', true ),
            ),
            'created_at'      => $post->post_date,
        );

        if ( $detailed ) {
            $data['description'] = $post->post_content;
            $data['estimated_duration'] = get_post_meta( $id, '_fsm_wo_estimated_duration', true );
            $data['started_at']   = get_post_meta( $id, '_fsm_wo_started_at', true );
            $data['completed_at'] = get_post_meta( $id, '_fsm_wo_completed_at', true );

            $tech_id = get_post_meta( $id, '_fsm_wo_primary_technician_id', true );
            $data['technician_name'] = $tech_id ? get_the_title( $tech_id ) : '';
        }

        return $data;
    }

    /**
     * Format a real fsm_invoices row (from FSM_Billing_DB) for the customer portal.
     *
     * @param object $invoice  Invoice row.
     * @param bool   $detailed Include line items and address.
     * @return array
     */
    private function format_invoice( $invoice, $detailed = false ) {
        $work_order_id = $invoice->work_order_id ? (int) $invoice->work_order_id : 0;
        $reference = $work_order_id ? get_post_meta( $work_order_id, '_fsm_wo_reference', true ) : '';
        $title = $work_order_id ? get_the_title( $work_order_id ) : $invoice->customer_name;

        $data = array(
            'id'             => (int) $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'reference'      => $reference,
            'title'          => $title,
            'status'         => $invoice->status,
            'date'           => $invoice->sent_at ?: $invoice->created_at,
            'due_date'       => $invoice->due_date,
            'subtotal'       => (float) $invoice->subtotal,
            'tax'            => (float) $invoice->tax_amount,
            'total'          => (float) $invoice->total,
            'amount_paid'    => (float) $invoice->amount_paid,
            'balance_due'    => round( (float) $invoice->total - (float) $invoice->amount_paid, 2 ),
        );

        if ( $detailed ) {
            $data['line_items'] = array_map( function( $item ) {
                return array(
                    'description' => $item->description,
                    'quantity'    => (float) $item->quantity,
                    'unit_price'  => (float) $item->unit_price,
                    'line_total'  => (float) $item->line_total,
                );
            }, FSM_Billing_DB::get_line_items( $invoice->id ) );

            if ( $work_order_id ) {
                $data['address'] = array(
                    'street' => get_post_meta( $work_order_id, '_fsm_wo_address_street', true ),
                    'city'   => get_post_meta( $work_order_id, '_fsm_wo_address_city', true ),
                    'state'  => get_post_meta( $work_order_id, '_fsm_wo_address_state', true ),
                    'zip'    => get_post_meta( $work_order_id, '_fsm_wo_address_zip', true ),
                );
            }
        }

        return $data;
    }

    // ─── Helpers ───────────────────────────────────────────

    private function generate_token( $user ) {
        $secret = defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : wp_salt( 'auth' );
        $issued = time();
        $expire = $issued + ( 7 * DAY_IN_SECONDS );

        $payload = array(
            'iss'  => get_bloginfo( 'url' ),
            'iat'  => $issued,
            'nbf'  => $issued,
            'exp'  => $expire,
            'data' => array(
                'user' => array(
                    'id' => $user->ID,
                ),
            ),
        );

        return \Firebase\JWT\JWT::encode( $payload, $secret, 'HS256' );
    }

    private function resolve_org_code( $code ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_fsm_org_code' AND meta_value = %s LIMIT 1",
            $code
        ) );
    }
}

new FSM_Customer_Portal_API();
