<?php
/**
 * FSM REST API Base
 *
 * Registers core REST API endpoints and provides
 * authentication/authorization helpers for FSM plugins.
 *
 * @package FSM\Core
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM REST API class.
 */
class FSM_REST_API {

    /**
     * Namespace for FSM API.
     */
    const NAMESPACE = 'fsm/v1';

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        // System info endpoint
        register_rest_route(self::NAMESPACE, '/info', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_system_info'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);

        // Current user endpoint
        register_rest_route(self::NAMESPACE, '/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_current_user'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);

        // User capabilities endpoint
        register_rest_route(self::NAMESPACE, '/me/capabilities', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_user_capabilities'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);

        // Organizations endpoints
        register_rest_route(self::NAMESPACE, '/organizations', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'get_organizations'],
                'permission_callback' => [__CLASS__, 'check_super_admin'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'create_organization'],
                'permission_callback' => [__CLASS__, 'check_super_admin'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/organizations/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_organization'],
            'permission_callback' => [__CLASS__, 'check_organization_access'],
        ]);

        register_rest_route(self::NAMESPACE, '/organizations/current', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_current_organization'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);

        register_rest_route(self::NAMESPACE, '/organizations/switch/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'switch_organization'],
            'permission_callback' => [__CLASS__, 'check_super_admin'],
        ]);

        // Self-service registration (public)
        register_rest_route(self::NAMESPACE, '/register', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'register_user'],
            'permission_callback' => '__return_true',
        ]);

        // Demo data endpoint (super admin only)
        register_rest_route(self::NAMESPACE, '/demo/create', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'create_demo_data'],
            'permission_callback' => [__CLASS__, 'check_super_admin'],
        ]);

        // Demo templates endpoint (public)
        register_rest_route(self::NAMESPACE, '/demo/templates', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'get_demo_templates'],
            'permission_callback' => '__return_true',
        ]);

        // Demo login endpoint (public - creates/resets demo and returns token)
        register_rest_route(self::NAMESPACE, '/demo/login', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'demo_login'],
            'permission_callback' => '__return_true',
        ]);

        // Demo logout/reset endpoint
        register_rest_route(self::NAMESPACE, '/demo/logout', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'demo_logout'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);

        // Demo reset endpoint (for current demo user)
        register_rest_route(self::NAMESPACE, '/demo/reset', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'reset_demo'],
            'permission_callback' => [__CLASS__, 'check_access'],
        ]);
    }

    /**
     * Check if user has FSM access.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public static function check_access($request) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to access FSM.', 'fsm-core'),
                ['status' => 401]
            );
        }

        if (!current_user_can('fsm_access')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access FSM.', 'fsm-core'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check specific capability.
     *
     * @param string $capability Capability to check.
     * @return bool|WP_Error
     */
    public static function check_capability($capability) {
        $access_check = self::check_access(null);
        if (is_wp_error($access_check)) {
            return $access_check;
        }

        if (!current_user_can($capability)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission for this action.', 'fsm-core'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check if user is super admin.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public static function check_super_admin($request) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in.', 'fsm-core'),
                ['status' => 401]
            );
        }

        if (!FSM_Organizations::is_super_admin()) {
            return new WP_Error(
                'rest_forbidden',
                __('You must be a super administrator.', 'fsm-core'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check organization access.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public static function check_organization_access($request) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in.', 'fsm-core'),
                ['status' => 401]
            );
        }

        $org_id = (int) $request->get_param('id');
        $user_org = FSM_Organizations::get_user_organization_id();

        // Super admins can access any org
        if (FSM_Organizations::is_super_admin()) {
            return true;
        }

        // Users can only access their own org
        if ($user_org !== $org_id) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have access to this organization.', 'fsm-core'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Get system info.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_system_info($request) {
        return rest_ensure_response([
            'name' => 'FSM - Field Service Management',
            'version' => FSM_CORE_VERSION,
            'headless_mode' => defined('FSM_HEADLESS_MODE') && FSM_HEADLESS_MODE,
            'endpoints' => [
                'info' => rest_url(self::NAMESPACE . '/info'),
                'me' => rest_url(self::NAMESPACE . '/me'),
                'technicians' => rest_url(self::NAMESPACE . '/technicians'),
                'vehicles' => rest_url(self::NAMESPACE . '/vehicles'),
                'work_orders' => rest_url(self::NAMESPACE . '/work-orders'),
                'dispatch' => rest_url(self::NAMESPACE . '/dispatch'),
                'inventory' => rest_url(self::NAMESPACE . '/inventory'),
                'gps' => rest_url(self::NAMESPACE . '/gps'),
            ],
            'timestamp' => current_time('c'),
        ]);
    }

    /**
     * Get current user info.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_current_user($request) {
        $user = wp_get_current_user();

        $data = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'roles' => $user->roles,
            'avatar_url' => get_avatar_url($user->ID),
            'is_technician' => FSM_Roles::is_technician(),
            'is_lead_technician' => FSM_Roles::is_lead_technician(),
            'is_dispatcher' => FSM_Roles::is_dispatcher(),
            'is_admin' => FSM_Roles::is_fsm_admin(),
            'is_super_admin' => FSM_Organizations::is_super_admin(),
        ];

        // Include organization info
        $org_id = FSM_Organizations::get_user_organization_id();
        if ($org_id) {
            $data['organization'] = FSM_Organizations::format_for_api($org_id);
        } else {
            $data['organization'] = null;
        }

        // For super admins, include current context
        if (FSM_Organizations::is_super_admin()) {
            $context_org = FSM_Organizations::get_current_context();
            $data['organization_context'] = $context_org ? FSM_Organizations::format_for_api($context_org) : null;
        }

        // If user is a technician, include technician profile
        if (FSM_Roles::is_technician()) {
            $technician = FSM_Utilities::get_technician_by_user_id($user->ID);
            if ($technician) {
                $data['technician'] = [
                    'id' => $technician->ID,
                    'employee_id' => get_post_meta($technician->ID, '_fsm_employee_id', true),
                    'status' => get_post_meta($technician->ID, '_fsm_status', true),
                    'vehicle_id' => get_post_meta($technician->ID, '_fsm_assigned_vehicle_id', true),
                ];
            }
        }

        return rest_ensure_response($data);
    }

    /**
     * Get user capabilities.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_user_capabilities($request) {
        $user = wp_get_current_user();
        $all_caps = FSM_Roles::get_capabilities();
        $user_caps = [];

        foreach (array_keys($all_caps) as $cap) {
            $user_caps[$cap] = current_user_can($cap);
        }

        return rest_ensure_response([
            'user_id' => $user->ID,
            'capabilities' => $user_caps,
        ]);
    }

    /**
     * Get all organizations (super admin only).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_organizations($request) {
        $include_demo = $request->get_param('include_demo');
        $args = [];

        if (!$include_demo) {
            $demo_ids = FSM_Organizations::get_demo_organization_ids();
            if (!empty($demo_ids)) {
                $args['post__not_in'] = $demo_ids;
            }
        }

        $orgs = FSM_Organizations::get_all($args);
        $data = [];

        foreach ($orgs as $org) {
            $data[] = FSM_Organizations::format_for_api($org);
        }

        return rest_ensure_response($data);
    }

    /**
     * Create organization (super admin only).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function create_organization($request) {
        $name = $request->get_param('name');

        if (empty($name)) {
            return self::error('missing_name', __('Organization name is required.', 'fsm-core'), 400);
        }

        $data = [
            'name'      => $name,
            'slug'      => $request->get_param('slug'),
            'email'     => sanitize_email($request->get_param('email') ?? ''),
            'phone'     => sanitize_text_field($request->get_param('phone') ?? ''),
            'address'   => sanitize_textarea_field($request->get_param('address') ?? ''),
            'tier'      => sanitize_text_field($request->get_param('tier') ?: 'standard'),
            'max_users' => absint($request->get_param('max_users') ?: 10),
        ];

        $org_id = FSM_Organizations::create($data);

        if (is_wp_error($org_id)) {
            return $org_id;
        }

        return self::success(FSM_Organizations::format_for_api($org_id), 201);
    }

    /**
     * Get single organization.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function get_organization($request) {
        $org_id = (int) $request->get_param('id');
        $org = FSM_Organizations::get($org_id);

        if (!$org) {
            return self::error('not_found', __('Organization not found.', 'fsm-core'), 404);
        }

        return rest_ensure_response(FSM_Organizations::format_for_api($org));
    }

    /**
     * Get current user's organization.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_current_organization($request) {
        $org_id = FSM_Organizations::get_user_organization_id();

        if (!$org_id) {
            return rest_ensure_response([
                'organization' => null,
                'is_super_admin' => FSM_Organizations::is_super_admin(),
            ]);
        }

        return rest_ensure_response([
            'organization' => FSM_Organizations::format_for_api($org_id),
            'is_super_admin' => FSM_Organizations::is_super_admin(),
        ]);
    }

    /**
     * Switch organization context (super admin only).
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function switch_organization($request) {
        $org_id = (int) $request->get_param('id');

        // Verify org exists (0 means view all)
        if ($org_id > 0) {
            $org = FSM_Organizations::get($org_id);
            if (!$org) {
                return self::error('not_found', __('Organization not found.', 'fsm-core'), 404);
            }
        }

        FSM_Organizations::set_admin_context($org_id > 0 ? $org_id : null);

        return rest_ensure_response([
            'success' => true,
            'organization_id' => $org_id > 0 ? $org_id : null,
            'message' => $org_id > 0
                ? sprintf(__('Switched to %s', 'fsm-core'), $org->post_title)
                : __('Viewing all organizations', 'fsm-core'),
        ]);
    }

    /**
     * Create demo organization with sample data.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function create_demo_data($request) {
        $template = $request->get_param('template') ?: 'plumbing';
        $org_name = $request->get_param('name');

        $org_id = FSM_Demo_Data::create_demo_organization($template, $org_name);

        if (is_wp_error($org_id)) {
            return $org_id;
        }

        return self::success([
            'message'      => sprintf(__('Demo organization created successfully.', 'fsm-core')),
            'organization' => FSM_Organizations::format_for_api($org_id),
        ], 201);
    }

    /**
     * Self-service registration - creates organization + admin user.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function register_user($request) {
        // Honeypot check
        $honeypot = $request->get_param('website');
        if (!empty($honeypot)) {
            // Bot detected — return fake success
            return self::success(['message' => 'Registration successful.'], 201);
        }

        // Rate limiting: max 5 registrations per IP per hour
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_key = 'fsm_reg_' . md5($ip);
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 5) {
            return self::error('rate_limited', __('Too many registration attempts. Please try again later.', 'fsm-core'), 429);
        }

        // Validate required fields
        $required = self::validate_required_fields($request, [
            'company_name', 'first_name', 'last_name', 'email', 'password',
        ]);
        if (is_wp_error($required)) {
            return $required;
        }

        $company_name = sanitize_text_field($request->get_param('company_name'));
        $first_name   = sanitize_text_field($request->get_param('first_name'));
        $last_name    = sanitize_text_field($request->get_param('last_name'));
        $email        = sanitize_email($request->get_param('email'));
        $password     = $request->get_param('password');
        $phone        = sanitize_text_field($request->get_param('phone') ?? '');
        $industry     = sanitize_text_field($request->get_param('industry') ?? '');

        // Validate email format
        if (!is_email($email)) {
            return self::error('invalid_email', __('Please provide a valid email address.', 'fsm-core'), 400);
        }

        // Check email uniqueness
        if (email_exists($email)) {
            return self::error('email_exists', __('An account with this email already exists.', 'fsm-core'), 409);
        }

        // Check username (derived from email) uniqueness
        $username = sanitize_user(strtolower(explode('@', $email)[0]), true);
        $base_username = $username;
        $suffix = 1;
        while (username_exists($username)) {
            $username = $base_username . $suffix;
            $suffix++;
        }

        // Validate password strength
        if (strlen($password) < 8) {
            return self::error('weak_password', __('Password must be at least 8 characters.', 'fsm-core'), 400);
        }

        // Create organization
        $org_data = [
            'name'      => $company_name,
            'email'     => $email,
            'phone'     => $phone,
            'tier'      => 'standard',
            'max_users' => 10,
        ];
        $org_id = FSM_Organizations::create($org_data);
        if (is_wp_error($org_id)) {
            return $org_id;
        }

        // Store industry on the org if provided
        if (!empty($industry)) {
            update_post_meta($org_id, '_fsm_org_industry', $industry);
        }

        // Create admin user
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            // Clean up the org we just created
            wp_delete_post($org_id, true);
            return self::error('user_creation_failed', $user_id->get_error_message(), 500);
        }

        $user = new \WP_User($user_id);
        $user->set_role('fsm_administrator');
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $first_name . ' ' . $last_name,
        ]);

        // Link user to organization
        FSM_Organizations::set_user_organization($user_id, $org_id);

        // Generate JWT token for immediate login
        $token = self::generate_demo_token($user);
        if (is_wp_error($token)) {
            return $token;
        }

        // Increment rate limit counter
        set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

        return self::success([
            'token'        => $token,
            'user'         => [
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'display_name' => $first_name . ' ' . $last_name,
                'email'        => $email,
                'role'         => 'fsm_administrator',
            ],
            'organization' => FSM_Organizations::format_for_api($org_id),
        ], 201);
    }

    /**
     * Get available demo templates.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_demo_templates($request) {
        return rest_ensure_response([
            'templates' => FSM_Demo_Data::get_templates(),
        ]);
    }

    /**
     * Demo login - creates or resets demo organization and returns auth token.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function demo_login($request) {
        $template = $request->get_param('template') ?: 'plumbing';
        $role = $request->get_param('role') ?: 'dispatcher';

        // Validate template
        $templates = FSM_Demo_Data::get_templates();
        if (!isset($templates[$template])) {
            return self::error('invalid_template', __('Invalid demo template.', 'fsm-core'), 400);
        }

        // Check if demo org already exists for this template
        $demo_slug = sanitize_title($templates[$template]['company']);
        $existing_org = get_posts([
            'post_type'      => 'fsm_organization',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_fsm_org_slug',
                    'value' => $demo_slug,
                ],
            ],
        ]);

        if (!empty($existing_org)) {
            $org_id = $existing_org[0]->ID;
            // Reset the existing demo
            FSM_Demo_Data::reset_demo_organization($org_id);
            // Re-fetch org_id since it was recreated
            $existing_org = get_posts([
                'post_type'      => 'fsm_organization',
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'   => '_fsm_org_slug',
                        'value' => $demo_slug,
                    ],
                ],
            ]);
            $org_id = $existing_org[0]->ID;
        } else {
            // Create new demo org
            $org_id = FSM_Demo_Data::create_demo_organization($template);
            if (is_wp_error($org_id)) {
                return $org_id;
            }
        }

        // Get the org slug for username construction
        $org_slug = get_post_meta($org_id, '_fsm_org_slug', true);

        // Determine which user to log in as
        if ($role === 'admin') {
            $username = 'admin_' . $org_slug;
        } else {
            $username = 'dispatcher_' . $org_slug;
        }

        // Get the user
        $user = get_user_by('login', $username);
        if (!$user) {
            return self::error('demo_user_not_found', __('Demo user not found.', 'fsm-core'), 500);
        }

        // Generate JWT token
        $token = self::generate_demo_token($user);
        if (is_wp_error($token)) {
            return $token;
        }

        // Mark this as a demo session
        update_user_meta($user->ID, '_fsm_demo_session', true);
        update_user_meta($user->ID, '_fsm_demo_session_start', current_time('mysql'));

        return self::success([
            'token'        => $token,
            'user'         => [
                'id'           => $user->ID,
                'username'     => $user->user_login,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'role'         => $role,
            ],
            'organization' => FSM_Organizations::format_for_api($org_id),
            'template'     => $template,
            'is_demo'      => true,
        ]);
    }

    /**
     * Generate a JWT token for demo user.
     *
     * @param WP_User $user User object.
     * @return string JWT token.
     */
    private static function generate_demo_token($user) {
        if (!defined('JWT_AUTH_SECRET_KEY') || empty(JWT_AUTH_SECRET_KEY)) {
            return new \WP_Error(
                'auth_unavailable',
                'Authentication service unavailable.',
                ['status' => 500]
            );
        }

        // Use Firebase JWT library (used by jwt-authentication-for-wp-rest-api plugin)
        if (!class_exists('Firebase\JWT\JWT')) {
            // Try to load from the JWT plugin
            $jwt_plugin_path = WP_PLUGIN_DIR . '/jwt-authentication-for-wp-rest-api/includes/vendor/firebase/php-jwt/src/JWT.php';
            if (file_exists($jwt_plugin_path)) {
                require_once $jwt_plugin_path;
            }
        }

        if (!class_exists('Firebase\JWT\JWT')) {
            return new \WP_Error(
                'auth_unavailable',
                'Authentication service unavailable.',
                ['status' => 500]
            );
        }

        $secret_key = JWT_AUTH_SECRET_KEY;
        $issued_at = time();
        $expire = $issued_at + (DAY_IN_SECONDS * 1); // 1 day expiry for demo

        $token_data = [
            'iss'  => get_bloginfo('url'),
            'iat'  => $issued_at,
            'nbf'  => $issued_at,
            'exp'  => $expire,
            'aud'  => 'fsm',
            'data' => [
                'user' => [
                    'id' => $user->ID,
                ],
            ],
        ];

        return \Firebase\JWT\JWT::encode($token_data, $secret_key, 'HS256');
    }

    /**
     * Demo logout - resets demo data and invalidates session.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function demo_logout($request) {
        $user_id = get_current_user_id();
        $org_id = FSM_Organizations::get_user_organization_id();

        // Check if this is a demo session
        $is_demo = get_user_meta($user_id, '_fsm_demo_session', true);
        $is_demo_org = $org_id ? get_post_meta($org_id, '_fsm_is_demo', true) : false;

        if ($is_demo || $is_demo_org) {
            // Clear demo session markers
            delete_user_meta($user_id, '_fsm_demo_session');
            delete_user_meta($user_id, '_fsm_demo_session_start');

            // Reset demo data if org is a demo
            if ($is_demo_org && $org_id) {
                FSM_Demo_Data::reset_demo_organization($org_id);
            }
        }

        return self::success([
            'message' => __('Demo session ended and data reset.', 'fsm-core'),
            'reset'   => (bool) $is_demo_org,
        ]);
    }

    /**
     * Reset current demo data without logging out.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function reset_demo($request) {
        $org_id = FSM_Organizations::get_user_organization_id();

        if (!$org_id) {
            return self::error('no_organization', __('No organization found.', 'fsm-core'), 400);
        }

        $is_demo = get_post_meta($org_id, '_fsm_is_demo', true);
        if (!$is_demo) {
            return self::error('not_demo', __('This is not a demo organization.', 'fsm-core'), 403);
        }

        $template = get_post_meta($org_id, '_fsm_demo_template', true) ?: 'plumbing';

        // Reset the demo
        $new_org_id = FSM_Demo_Data::reset_demo_organization($org_id);

        if (is_wp_error($new_org_id)) {
            return $new_org_id;
        }

        return self::success([
            'message'      => __('Demo data has been reset.', 'fsm-core'),
            'organization' => FSM_Organizations::format_for_api($new_org_id),
            'template'     => $template,
        ]);
    }

    /**
     * Standard error response.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @param int    $status HTTP status code.
     * @return WP_Error
     */
    public static function error($code, $message, $status = 400) {
        return new WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * Standard success response.
     *
     * @param mixed $data Response data.
     * @param int   $status HTTP status code.
     * @return WP_REST_Response
     */
    public static function success($data, $status = 200) {
        $response = rest_ensure_response($data);
        $response->set_status($status);
        return $response;
    }

    /**
     * Validate required fields in request.
     *
     * @param WP_REST_Request $request Request object.
     * @param array           $fields Required field names.
     * @return true|WP_Error
     */
    public static function validate_required_fields($request, $fields) {
        $missing = [];

        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if (null === $value || '' === $value) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return self::error(
                'missing_required_fields',
                sprintf(
                    __('Missing required fields: %s', 'fsm-core'),
                    implode(', ', $missing)
                ),
                400
            );
        }

        return true;
    }
}
