<?php
/**
 * FSM Mumble Integration
 *
 * Integration with the Mumble server via the Mumble management backend API.
 * Handles organization channel provisioning, user registration, and access control.
 *
 * @package FSM\PTT
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Mumble Integration class.
 */
class FSM_Mumble_Integration {

    /**
     * Mumble backend API base URL.
     *
     * @var string
     */
    private static $api_base = 'http://mumble_backend:3001/api/fsm';

    /**
     * Get API base URL.
     *
     * @return string
     */
    private static function get_api_base() {
        return defined('FSM_MUMBLE_API_URL') ? FSM_MUMBLE_API_URL : self::$api_base;
    }

    /**
     * Get API key for authentication.
     *
     * @return string
     */
    private static function get_api_key() {
        if (defined('FSM_MUMBLE_API_KEY')) {
            return FSM_MUMBLE_API_KEY;
        }

        // Try to read from Docker secrets
        $secret_file = '/run/secrets/fsm_mumble_api_key';
        if (file_exists($secret_file)) {
            return trim(file_get_contents($secret_file));
        }

        return get_option('fsm_mumble_api_key', '');
    }

    /**
     * Make API request to Mumble backend.
     *
     * @param string $endpoint API endpoint.
     * @param string $method HTTP method.
     * @param array  $data Request data.
     * @return array|WP_Error Response data or error.
     */
    private static function api_request($endpoint, $method = 'GET', $data = []) {
        $url = self::get_api_base() . $endpoint;
        $api_key = self::get_api_key();

        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('Mumble API key not configured.', 'fsm-ptt'));
        }

        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-FSM-API-Key' => $api_key,
            ],
            'timeout' => 30,
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            FSM_Utilities::log_activity('mumble_api_error', [
                'endpoint' => $endpoint,
                'error' => $response->get_error_message(),
            ]);
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $error_message = $body['error']['message'] ?? 'Unknown API error';
            FSM_Utilities::log_activity('mumble_api_error', [
                'endpoint' => $endpoint,
                'code' => $code,
                'error' => $error_message,
            ]);
            return new WP_Error('api_error', $error_message, ['status' => $code]);
        }

        return $body;
    }

    /**
     * Check if Mumble backend is available.
     *
     * @return bool
     */
    public static function is_available() {
        $response = self::api_request('/health');
        return !is_wp_error($response) && ($response['status'] ?? '') === 'ok';
    }

    /**
     * Provision organization in Mumble.
     * Creates channel tree with ACLs for organization isolation.
     *
     * @param int    $org_id Organization ID.
     * @param string $name Organization name.
     * @param string $slug Organization slug.
     * @return array|WP_Error Mumble provisioning data or error.
     */
    public static function provision_organization($org_id, $name, $slug) {
        $response = self::api_request('/organizations', 'POST', [
            'name' => $name,
            'slug' => $slug,
            'fsmOrgId' => $org_id,
        ]);

        if (!is_wp_error($response)) {
            // Store Mumble channel/group IDs in organization meta
            update_post_meta($org_id, '_fsm_mumble_root_channel_id', $response['mumble']['rootChannelId'] ?? null);
            update_post_meta($org_id, '_fsm_mumble_group_id', $response['mumble']['groupId'] ?? null);
            update_post_meta($org_id, '_fsm_mumble_channels', $response['mumble']['channels'] ?? []);

            FSM_Utilities::log_activity('mumble_org_provisioned', [
                'org_id' => $org_id,
                'mumble_data' => $response['mumble'],
            ]);

            /**
             * Action fired when an organization is provisioned in Mumble.
             *
             * @param int   $org_id Organization ID.
             * @param array $mumble_data Mumble provisioning response.
             */
            do_action('fsm_mumble_organization_provisioned', $org_id, $response['mumble']);
        }

        return $response;
    }

    /**
     * Deprovision organization from Mumble.
     *
     * @param int $org_id Organization ID.
     * @return array|WP_Error Response or error.
     */
    public static function deprovision_organization($org_id) {
        // Pass the channel ID we already have on hand as a fallback, in case
        // the backend's own tracking row for this org was lost (e.g. to a
        // prior partial provisioning failure) — deletion should still succeed.
        $root_channel_id = get_post_meta($org_id, '_fsm_mumble_root_channel_id', true);
        $body = $root_channel_id ? ['mumbleChannelId' => $root_channel_id] : [];

        $response = self::api_request('/organizations/' . $org_id, 'DELETE', $body);

        if (!is_wp_error($response)) {
            delete_post_meta($org_id, '_fsm_mumble_root_channel_id');
            delete_post_meta($org_id, '_fsm_mumble_group_id');
            delete_post_meta($org_id, '_fsm_mumble_channels');

            FSM_Utilities::log_activity('mumble_org_deprovisioned', [
                'org_id' => $org_id,
            ]);
        }

        return $response;
    }

    /**
     * Register user in Mumble and assign to organization.
     *
     * @param int    $user_id WordPress user ID.
     * @param int    $org_id Organization ID.
     * @param string $username Mumble username (optional, uses display_name).
     * @return array|WP_Error Response or error.
     */
    public static function register_user($user_id, $org_id, $username = null) {
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('User not found.', 'fsm-ptt'));
        }

        if (!$username) {
            // Generate Mumble username from WordPress user
            $username = self::generate_mumble_username($user);
        }

        $response = self::api_request('/users', 'POST', [
            'username' => $username,
            'email' => $user->user_email,
            'fsmOrgId' => $org_id,
            'fsmUserId' => $user_id,
        ]);

        if (!is_wp_error($response)) {
            update_user_meta($user_id, '_fsm_mumble_user_id', $response['mumbleUserId'] ?? null);
            update_user_meta($user_id, '_fsm_mumble_username', $username);
            update_user_meta($user_id, '_fsm_mumble_default_channel', $response['defaultChannel'] ?? null);

            FSM_Utilities::log_activity('mumble_user_registered', [
                'user_id' => $user_id,
                'org_id' => $org_id,
                'mumble_user_id' => $response['mumbleUserId'],
            ]);
        }

        return $response;
    }

    /**
     * Generate Mumble username from WordPress user.
     *
     * @param WP_User $user WordPress user object.
     * @return string
     */
    private static function generate_mumble_username($user) {
        // Check if user is a technician
        $technician = FSM_Utilities::get_technician_by_user_id($user->ID);
        if ($technician) {
            $employee_id = get_post_meta($technician->ID, '_fsm_employee_id', true);
            if ($employee_id) {
                return 'tech_' . sanitize_title($employee_id);
            }
        }

        // Use display name sanitized for Mumble
        $name = $user->display_name ?: $user->user_login;
        return sanitize_title($name);
    }

    /**
     * Remove user from organization's Mumble group.
     *
     * @param int $user_id WordPress user ID.
     * @param int $org_id Organization ID.
     * @return array|WP_Error Response or error.
     */
    public static function remove_user_from_organization($user_id, $org_id) {
        $mumble_user_id = get_user_meta($user_id, '_fsm_mumble_user_id', true);

        if (!$mumble_user_id) {
            return new WP_Error('not_registered', __('User not registered in Mumble.', 'fsm-ptt'));
        }

        return self::api_request("/users/{$mumble_user_id}/organizations/{$org_id}", 'DELETE');
    }

    /**
     * Create team channel in Mumble.
     *
     * @param int    $org_id Organization ID.
     * @param string $team_name Team name.
     * @return array|WP_Error Response with channelId or error.
     */
    public static function create_team_channel($org_id, $team_name) {
        return self::api_request('/channels/team', 'POST', [
            'fsmOrgId' => $org_id,
            'teamName' => $team_name,
        ]);
    }

    /**
     * Create work order channel in Mumble.
     *
     * @param int    $org_id Organization ID.
     * @param string $work_order_ref Work order reference.
     * @return array|WP_Error Response with channelId or error.
     */
    public static function create_work_order_channel($org_id, $work_order_ref) {
        return self::api_request('/channels/work-order', 'POST', [
            'fsmOrgId' => $org_id,
            'workOrderRef' => $work_order_ref,
        ]);
    }

    /**
     * Delete a Mumble channel.
     *
     * @param int $channel_id Mumble channel ID.
     * @return array|WP_Error Response or error.
     */
    public static function delete_channel($channel_id) {
        return self::api_request('/channels/' . $channel_id, 'DELETE');
    }

    /**
     * Get Mumble connection URL for a user.
     *
     * @param string $username Username.
     * @param string $channel Optional channel to join.
     * @return string Mumble URL.
     */
    public static function get_connection_url($username, $channel = null) {
        $host = defined('FSM_MUMBLE_HOST') ? FSM_MUMBLE_HOST : 'mumble.steinmetz.ltd';
        $port = defined('FSM_MUMBLE_PORT') ? FSM_MUMBLE_PORT : 64738;

        $url = sprintf(
            'mumble://%s@%s:%d/',
            urlencode($username),
            $host,
            $port
        );

        if ($channel) {
            $url .= urlencode($channel);
        }

        return $url;
    }

    /**
     * Get connection info for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error Connection info or error.
     */
    public static function get_user_connection_info($user_id) {
        $mumble_username = get_user_meta($user_id, '_fsm_mumble_username', true);

        if (!$mumble_username) {
            return new WP_Error('not_registered', __('User not registered in Mumble.', 'fsm-ptt'));
        }

        return self::api_request('/connection-info/' . urlencode($mumble_username));
    }

    /**
     * Get recommended channel structure for an organization.
     *
     * @param int $org_id Organization ID.
     * @return array Recommended channel structure.
     */
    public static function get_recommended_structure($org_id) {
        $org = get_post($org_id);
        $org_name = $org ? $org->post_title : 'Organization';

        $structure = [
            $org_name => [
                'Dispatch' => [
                    'description' => 'Main dispatch channel - all technicians and dispatchers',
                ],
                'Teams' => [
                    'description' => 'Team-specific channels',
                    'children' => [],
                ],
                'Work Orders' => [
                    'description' => 'Temporary channels for work order collaboration',
                    'children' => [],
                ],
            ],
        ];

        // Add team channels
        $teams = get_terms([
            'taxonomy' => 'fsm_team',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => '_fsm_organization_id',
                    'value' => $org_id,
                ],
            ],
        ]);

        if (!is_wp_error($teams)) {
            foreach ($teams as $team) {
                $structure[$org_name]['Teams']['children'][$team->name] = [];
            }
        }

        return $structure;
    }

    /**
     * Check if organization is provisioned in Mumble.
     *
     * @param int $org_id Organization ID.
     * @return bool
     */
    public static function is_organization_provisioned($org_id) {
        $root_channel_id = get_post_meta($org_id, '_fsm_mumble_root_channel_id', true);
        return !empty($root_channel_id);
    }

    /**
     * Check if user is registered in Mumble.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function is_user_registered($user_id) {
        $mumble_user_id = get_user_meta($user_id, '_fsm_mumble_user_id', true);
        return !empty($mumble_user_id);
    }
}
