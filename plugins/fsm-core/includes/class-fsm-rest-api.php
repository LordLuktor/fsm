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
     * Get system info.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function get_system_info($request) {
        return rest_ensure_response([
            'name' => 'FSM - Field Service Management',
            'version' => FSM_CORE_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
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
            'is_dispatcher' => FSM_Roles::is_dispatcher(),
            'is_admin' => FSM_Roles::is_fsm_admin(),
        ];

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
