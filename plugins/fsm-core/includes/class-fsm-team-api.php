<?php
/**
 * FSM Team Management REST API
 *
 * Endpoints for managing team members (users), technicians, and vehicles.
 * Admin/dispatcher access only.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FSM_Team_API {

    const NAMESPACE = 'fsm/v1';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        // ── Team Members (Users) ─────────────────────────
        register_rest_route( self::NAMESPACE, '/team', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'list_team' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'add_team_member' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/team/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_team_member' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_team_member' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'deactivate_team_member' ),
                'permission_callback' => array( $this, 'check_admin' ),
            ),
        ) );

        // ── Technicians ──────────────────────────────────
        register_rest_route( self::NAMESPACE, '/technicians', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'list_technicians' ),
                'permission_callback' => array( $this, 'can_view_team' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_technician' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/technicians/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_technician' ),
                'permission_callback' => array( $this, 'can_view_team' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_technician' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
        ) );

        // ── Vehicles ─────────────────────────────────────
        register_rest_route( self::NAMESPACE, '/vehicles', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'list_vehicles' ),
                'permission_callback' => array( $this, 'can_view_team' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_vehicle' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/vehicles/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_vehicle' ),
                'permission_callback' => array( $this, 'can_view_team' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_vehicle' ),
                'permission_callback' => array( $this, 'can_manage_team' ),
            ),
        ) );
    }

    // ─── Permissions ───────────────────────────────────────

    public function check_admin() {
        return current_user_can( 'fsm_manage_settings' ) || current_user_can( 'manage_options' );
    }

    public function can_manage_team() {
        return current_user_can( 'fsm_manage_settings' ) || current_user_can( 'fsm_manage_dispatch' ) || current_user_can( 'manage_options' );
    }

    public function can_view_team() {
        return current_user_can( 'fsm_access' );
    }

    // ─── Team Members ──────────────────────────────────────

    public function list_team( $request ) {
        $org_id = FSM_Organizations::get_current_context();
        $fsm_roles = array( 'fsm_administrator', 'fsm_dispatcher', 'fsm_lead_technician', 'fsm_technician' );

        $args = array(
            'role__in'   => $fsm_roles,
            'orderby'    => 'display_name',
            'order'      => 'ASC',
            'number'     => 100,
        );

        if ( $org_id ) {
            $args['meta_query'] = array(
                array( 'key' => '_fsm_organization_id', 'value' => $org_id ),
            );
        } else {
            // Super admin viewing all orgs: leave out demo-session accounts by default.
            $demo_exclusion = FSM_Organizations::get_demo_exclusion_meta_query();
            if ( ! empty( $demo_exclusion ) ) {
                $args['meta_query'] = $demo_exclusion;
            }
        }

        $users = get_users( $args );
        $members = array();

        foreach ( $users as $user ) {
            $members[] = $this->format_team_member( $user );
        }

        return rest_ensure_response( $members );
    }

    public function add_team_member( $request ) {
        $email      = sanitize_email( $request->get_param( 'email' ) );
        $first_name = sanitize_text_field( $request->get_param( 'first_name' ) );
        $last_name  = sanitize_text_field( $request->get_param( 'last_name' ) );
        $phone      = sanitize_text_field( $request->get_param( 'phone' ) );
        $role       = sanitize_text_field( $request->get_param( 'role' ) );

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', 'Invalid email address.', array( 'status' => 400 ) );
        }

        $valid_roles = array( 'fsm_administrator', 'fsm_dispatcher', 'fsm_lead_technician', 'fsm_technician' );
        if ( ! in_array( $role, $valid_roles, true ) ) {
            return new WP_Error( 'invalid_role', 'Invalid role. Must be fsm_administrator, fsm_dispatcher, fsm_lead_technician, or fsm_technician.', array( 'status' => 400 ) );
        }

        // Only admins can create other admins
        if ( $role === 'fsm_administrator' && ! current_user_can( 'manage_options' ) && ! current_user_can( 'fsm_manage_settings' ) ) {
            return new WP_Error( 'forbidden', 'Only administrators can create administrator accounts.', array( 'status' => 403 ) );
        }

        if ( email_exists( $email ) ) {
            return new WP_Error( 'email_exists', 'A user with this email already exists.', array( 'status' => 409 ) );
        }

        // Generate password
        $password = wp_generate_password( 16, true, true );

        $user_id = wp_create_user( $email, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $user = new WP_User( $user_id );
        $user->set_role( $role );

        wp_update_user( array(
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'display_name' => trim( "$first_name $last_name" ) ?: $email,
        ) );

        if ( $phone ) {
            update_user_meta( $user_id, 'phone', $phone );
        }

        // Flush user cache to ensure fresh credentials are used on login
        clean_user_cache( $user_id );

        // Link to organization
        $org_id = FSM_Organizations::get_current_context();
        if ( $org_id ) {
            update_user_meta( $user_id, '_fsm_organization_id', $org_id );
        }

        // If technician role, also create a technician post
        $technician_id = null;
        if ( $role === 'fsm_technician' || $role === 'fsm_lead_technician' ) {
            $technician_id = $this->create_technician_for_user( $user_id, $first_name, $last_name, $org_id );
        }

        // Send welcome email with credentials
        $login_url = home_url( '/login' );
        wp_mail(
            $email,
            'Welcome to FSM — Your Account',
            sprintf(
                "Hi %s,\n\nYour FSM account has been created.\n\nEmail: %s\nTemporary Password: %s\n\nPlease log in at %s and change your password immediately.\n\nRole: %s",
                $first_name ?: 'there',
                $email,
                $password,
                $login_url,
                ucwords( str_replace( array( 'fsm_', '_' ), array( '', ' ' ), $role ) )
            )
        );

        return rest_ensure_response( array(
            'success'       => true,
            'user_id'       => $user_id,
            'technician_id' => $technician_id,
            'message'       => "Team member added. Login credentials sent to $email.",
        ) );
    }

    public function get_team_member( $request ) {
        $user = get_user_by( 'ID', absint( $request->get_param( 'id' ) ) );
        if ( ! $user ) {
            return new WP_Error( 'not_found', 'User not found.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $this->format_team_member( $user ) );
    }

    public function update_team_member( $request ) {
        $user_id = absint( $request->get_param( 'id' ) );
        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return new WP_Error( 'not_found', 'User not found.', array( 'status' => 404 ) );
        }

        // Update basic info
        $fields = array( 'ID' => $user_id );
        $fn = $request->get_param( 'first_name' );
        $ln = $request->get_param( 'last_name' );
        if ( $fn !== null ) $fields['first_name'] = sanitize_text_field( $fn );
        if ( $ln !== null ) $fields['last_name'] = sanitize_text_field( $ln );
        if ( isset( $fields['first_name'] ) || isset( $fields['last_name'] ) ) {
            $fields['display_name'] = trim( ( $fields['first_name'] ?? $user->first_name ) . ' ' . ( $fields['last_name'] ?? $user->last_name ) );
        }
        wp_update_user( $fields );

        $phone = $request->get_param( 'phone' );
        if ( $phone !== null ) {
            update_user_meta( $user_id, 'phone', sanitize_text_field( $phone ) );
        }

        // Role change
        $new_role = sanitize_text_field( $request->get_param( 'role' ) );
        if ( $new_role && in_array( $new_role, array( 'fsm_administrator', 'fsm_dispatcher', 'fsm_lead_technician', 'fsm_technician' ), true ) ) {
            $user_obj = new WP_User( $user_id );
            $old_role = $user_obj->roles[0] ?? '';

            if ( $new_role !== $old_role ) {
                $user_obj->set_role( $new_role );

                // If promoted to technician, ensure technician record exists
                if ( $new_role === 'fsm_technician' ) {
                    $existing = get_posts( array(
                        'post_type'  => 'fsm_technician',
                        'meta_key'   => '_fsm_user_id',
                        'meta_value' => $user_id,
                        'numberposts' => 1,
                    ) );
                    if ( empty( $existing ) ) {
                        $org_id = FSM_Organizations::get_current_context();
                        $this->create_technician_for_user( $user_id, $user->first_name, $user->last_name, $org_id );
                    }
                }
            }
        }

        // Status
        $status = $request->get_param( 'status' );
        if ( $status !== null ) {
            update_user_meta( $user_id, '_fsm_status', sanitize_text_field( $status ) );
        }

        return rest_ensure_response( array( 'success' => true, 'message' => 'Team member updated.' ) );
    }

    public function deactivate_team_member( $request ) {
        $user_id = absint( $request->get_param( 'id' ) );

        // Don't allow deleting yourself
        if ( $user_id === get_current_user_id() ) {
            return new WP_Error( 'cannot_delete_self', 'You cannot deactivate your own account.', array( 'status' => 400 ) );
        }

        update_user_meta( $user_id, '_fsm_status', 'inactive' );

        // Revoke FSM access
        $user = new WP_User( $user_id );
        $user->set_role( 'subscriber' );

        return rest_ensure_response( array( 'success' => true, 'message' => 'Team member deactivated.' ) );
    }

    // ─── Technicians ───────────────────────────────────────

    public function list_technicians( $request ) {
        $org_id = FSM_Organizations::get_current_context();

        $args = array(
            'post_type'      => 'fsm_technician',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( $org_id ) {
            $args['meta_query'] = array(
                array( 'key' => '_fsm_organization_id', 'value' => $org_id ),
            );
        } else {
            // Super admin viewing all orgs: leave out demo technicians by default.
            $demo_exclusion = FSM_Organizations::get_demo_exclusion_meta_query();
            if ( ! empty( $demo_exclusion ) ) {
                $args['meta_query'] = $demo_exclusion;
            }
        }

        $status = $request->get_param( 'status' );
        if ( $status ) {
            $args['meta_query'][] = array( 'key' => '_fsm_tech_status', 'value' => $status );
        }

        $posts = get_posts( $args );
        $techs = array();

        foreach ( $posts as $post ) {
            $techs[] = $this->format_technician( $post );
        }

        return rest_ensure_response( $techs );
    }

    public function get_technician( $request ) {
        $post = get_post( absint( $request->get_param( 'id' ) ) );
        if ( ! $post || $post->post_type !== 'fsm_technician' ) {
            return new WP_Error( 'not_found', 'Technician not found.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $this->format_technician( $post ) );
    }

    public function create_technician( $request ) {
        $name        = sanitize_text_field( $request->get_param( 'name' ) );
        $employee_id = sanitize_text_field( $request->get_param( 'employee_id' ) );
        $user_id     = absint( $request->get_param( 'user_id' ) );
        $vehicle_id  = absint( $request->get_param( 'vehicle_id' ) );
        $org_id      = FSM_Organizations::get_current_context();

        $post_id = wp_insert_post( array(
            'post_type'   => 'fsm_technician',
            'post_title'  => $name,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) return $post_id;

        update_post_meta( $post_id, '_fsm_tech_status', 'active' );
        if ( $employee_id ) update_post_meta( $post_id, '_fsm_employee_id', $employee_id );
        if ( $user_id ) update_post_meta( $post_id, '_fsm_user_id', $user_id );
        if ( $vehicle_id ) update_post_meta( $post_id, '_fsm_assigned_vehicle_id', $vehicle_id );
        if ( $org_id ) update_post_meta( $post_id, '_fsm_organization_id', $org_id );

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $post_id,
            'message' => 'Technician created.',
        ) );
    }

    public function update_technician( $request ) {
        $id   = absint( $request->get_param( 'id' ) );
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'fsm_technician' ) {
            return new WP_Error( 'not_found', 'Technician not found.', array( 'status' => 404 ) );
        }

        $name = $request->get_param( 'name' );
        if ( $name ) {
            wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $name ) ) );
        }

        $meta_map = array(
            'status'      => '_fsm_tech_status',
            'employee_id' => '_fsm_employee_id',
            'vehicle_id'  => '_fsm_assigned_vehicle_id',
            'user_id'     => '_fsm_user_id',
        );

        foreach ( $meta_map as $param => $meta_key ) {
            $val = $request->get_param( $param );
            if ( $val !== null ) {
                update_post_meta( $id, $meta_key, sanitize_text_field( $val ) );
            }
        }

        return rest_ensure_response( array( 'success' => true, 'message' => 'Technician updated.' ) );
    }

    // ─── Vehicles ──────────────────────────────────────────

    public function list_vehicles( $request ) {
        $org_id = FSM_Organizations::get_current_context();

        $args = array(
            'post_type'      => 'fsm_vehicle',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( $org_id ) {
            $args['meta_query'] = array(
                array( 'key' => '_fsm_organization_id', 'value' => $org_id ),
            );
        } else {
            // Super admin viewing all orgs: leave out demo vehicles by default.
            $demo_exclusion = FSM_Organizations::get_demo_exclusion_meta_query();
            if ( ! empty( $demo_exclusion ) ) {
                $args['meta_query'] = $demo_exclusion;
            }
        }

        $posts = get_posts( $args );
        $vehicles = array();

        foreach ( $posts as $post ) {
            $vehicles[] = $this->format_vehicle( $post );
        }

        return rest_ensure_response( $vehicles );
    }

    public function get_vehicle( $request ) {
        $post = get_post( absint( $request->get_param( 'id' ) ) );
        if ( ! $post || $post->post_type !== 'fsm_vehicle' ) {
            return new WP_Error( 'not_found', 'Vehicle not found.', array( 'status' => 404 ) );
        }
        return rest_ensure_response( $this->format_vehicle( $post ) );
    }

    public function create_vehicle( $request ) {
        $name     = sanitize_text_field( $request->get_param( 'name' ) );
        $plate    = sanitize_text_field( $request->get_param( 'plate' ) );
        $make     = sanitize_text_field( $request->get_param( 'make' ) );
        $model    = sanitize_text_field( $request->get_param( 'model' ) );
        $year     = absint( $request->get_param( 'year' ) );
        $org_id   = FSM_Organizations::get_current_context();

        $title = $name ?: trim( "$year $make $model" );

        $post_id = wp_insert_post( array(
            'post_type'   => 'fsm_vehicle',
            'post_title'  => $title,
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) return $post_id;

        update_post_meta( $post_id, '_fsm_vehicle_status', 'active' );
        if ( $plate ) update_post_meta( $post_id, '_fsm_vehicle_plate', $plate );
        if ( $make ) update_post_meta( $post_id, '_fsm_vehicle_make', $make );
        if ( $model ) update_post_meta( $post_id, '_fsm_vehicle_model', $model );
        if ( $year ) update_post_meta( $post_id, '_fsm_vehicle_year', $year );
        if ( $org_id ) update_post_meta( $post_id, '_fsm_organization_id', $org_id );

        return rest_ensure_response( array(
            'success' => true,
            'id'      => $post_id,
            'message' => 'Vehicle added.',
        ) );
    }

    public function update_vehicle( $request ) {
        $id   = absint( $request->get_param( 'id' ) );
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'fsm_vehicle' ) {
            return new WP_Error( 'not_found', 'Vehicle not found.', array( 'status' => 404 ) );
        }

        $name = $request->get_param( 'name' );
        if ( $name ) {
            wp_update_post( array( 'ID' => $id, 'post_title' => sanitize_text_field( $name ) ) );
        }

        $meta_map = array(
            'plate'  => '_fsm_vehicle_plate',
            'make'   => '_fsm_vehicle_make',
            'model'  => '_fsm_vehicle_model',
            'year'   => '_fsm_vehicle_year',
            'status' => '_fsm_vehicle_status',
        );

        foreach ( $meta_map as $param => $meta_key ) {
            $val = $request->get_param( $param );
            if ( $val !== null ) {
                update_post_meta( $id, $meta_key, sanitize_text_field( $val ) );
            }
        }

        return rest_ensure_response( array( 'success' => true, 'message' => 'Vehicle updated.' ) );
    }

    // ─── Helpers ───────────────────────────────────────────

    private function create_technician_for_user( $user_id, $first_name, $last_name, $org_id ) {
        $name = trim( "$first_name $last_name" );
        $post_id = wp_insert_post( array(
            'post_type'   => 'fsm_technician',
            'post_title'  => $name ?: "Technician #$user_id",
            'post_status' => 'publish',
        ) );

        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, '_fsm_user_id', $user_id );
            update_post_meta( $post_id, '_fsm_status', 'available' );
            if ( $org_id ) update_post_meta( $post_id, '_fsm_organization_id', $org_id );
        }

        return is_wp_error( $post_id ) ? null : $post_id;
    }

    private function format_team_member( $user ) {
        $roles = (array) $user->roles;
        $fsm_role = '';
        foreach ( array( 'fsm_administrator', 'fsm_dispatcher', 'fsm_lead_technician', 'fsm_technician' ) as $r ) {
            if ( in_array( $r, $roles, true ) ) { $fsm_role = $r; break; }
        }

        // Find linked technician record
        $tech_posts = get_posts( array(
            'post_type'  => 'fsm_technician',
            'meta_key'   => '_fsm_user_id',
            'meta_value' => $user->ID,
            'numberposts' => 1,
        ) );
        $tech = ! empty( $tech_posts ) ? $tech_posts[0] : null;

        return array(
            'id'           => $user->ID,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'phone'        => get_user_meta( $user->ID, 'phone', true ),
            'role'         => $fsm_role,
            'role_label'   => ucwords( str_replace( array( 'fsm_', '_' ), array( '', ' ' ), $fsm_role ) ),
            'status'       => get_user_meta( $user->ID, '_fsm_status', true ) ?: 'active',
            'technician_id' => $tech ? $tech->ID : null,
            'vehicle_id'   => $tech ? get_post_meta( $tech->ID, '_fsm_assigned_vehicle_id', true ) : null,
            'created'      => $user->user_registered,
        );
    }

    private function format_technician( $post ) {
        $user_id    = get_post_meta( $post->ID, '_fsm_user_id', true );
        $vehicle_id = get_post_meta( $post->ID, '_fsm_assigned_vehicle_id', true );
        $user       = $user_id ? get_user_by( 'ID', $user_id ) : null;
        $vehicle    = $vehicle_id ? get_post( $vehicle_id ) : null;

        return array(
            'id'          => $post->ID,
            'name'        => $post->post_title,
            'employee_id' => get_post_meta( $post->ID, '_fsm_employee_id', true ),
            'status'      => get_post_meta( $post->ID, '_fsm_tech_status', true ) ?: 'active',
            'user_id'     => $user_id ? (int) $user_id : null,
            'user_email'  => $user ? $user->user_email : null,
            'vehicle_id'  => $vehicle_id ? (int) $vehicle_id : null,
            'vehicle_name' => $vehicle ? $vehicle->post_title : null,
        );
    }

    private function format_vehicle( $post ) {
        // Find assigned technician
        $assigned_techs = get_posts( array(
            'post_type'  => 'fsm_technician',
            'meta_key'   => '_fsm_assigned_vehicle_id',
            'meta_value' => $post->ID,
            'numberposts' => 5,
        ) );

        return array(
            'id'     => $post->ID,
            'name'   => $post->post_title,
            'plate'  => get_post_meta( $post->ID, '_fsm_vehicle_plate', true ),
            'make'   => get_post_meta( $post->ID, '_fsm_vehicle_make', true ),
            'model'  => get_post_meta( $post->ID, '_fsm_vehicle_model', true ),
            'year'   => get_post_meta( $post->ID, '_fsm_vehicle_year', true ),
            'status' => get_post_meta( $post->ID, '_fsm_vehicle_status', true ) ?: 'active',
            'assigned_technicians' => array_map( function( $t ) {
                return array( 'id' => $t->ID, 'name' => $t->post_title );
            }, $assigned_techs ),
        );
    }
}

new FSM_Team_API();
