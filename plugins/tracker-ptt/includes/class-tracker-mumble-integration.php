<?php
/**
 * Tracker Mumble Integration
 *
 * Integration with the Mumble server via the Mumble management backend API.
 * Handles family channel provisioning, user registration, and access control
 * for the GPS Tracker's Ultimate tier PTT feature.
 *
 * @package Tracker\PTT
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class Tracker_Mumble_Integration {

    /**
     * Default Mumble backend API base URL for tracker endpoints.
     *
     * @var string
     */
    private static $api_base = 'http://mumble_backend:3001/api/tracker';

    /**
     * Get API base URL.
     *
     * @return string
     */
    private static function get_api_base() {
        return defined('TRACKER_MUMBLE_API_URL') ? TRACKER_MUMBLE_API_URL : self::$api_base;
    }

    /**
     * Get API key for authentication (shared with FSM integration).
     *
     * @return string
     */
    private static function get_api_key() {
        if (defined('FSM_MUMBLE_API_KEY')) {
            return FSM_MUMBLE_API_KEY;
        }

        $secret_file = '/run/secrets/fsm_mumble_api_key';
        if (file_exists($secret_file)) {
            return trim(file_get_contents($secret_file));
        }

        return '';
    }

    /**
     * Make API request to Mumble backend.
     *
     * @param string $endpoint API endpoint.
     * @param string $method   HTTP method.
     * @param array  $data     Request data.
     * @return array|WP_Error  Response data or error.
     */
    private static function api_request($endpoint, $method = 'GET', $data = []) {
        $url = self::get_api_base() . $endpoint;
        $api_key = self::get_api_key();

        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'Mumble API key not configured.');
        }

        $args = [
            'method'  => $method,
            'headers' => [
                'Content-Type'   => 'application/json',
                'X-FSM-API-Key'  => $api_key,
            ],
            'timeout' => 30,
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('Tracker Mumble API error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $error_message = $body['error']['message'] ?? 'Unknown API error';
            error_log("Tracker Mumble API error ({$endpoint}): {$code} - {$error_message}");
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
     * Provision a family in Mumble.
     *
     * @param int    $family_id   Family post ID.
     * @param string $family_name Family name.
     * @return array|WP_Error     Mumble provisioning data or error.
     */
    public static function provision_family($family_id, $family_name) {
        $response = self::api_request('/families', 'POST', [
            'familyId'   => $family_id,
            'familyName' => $family_name,
        ]);

        if (!is_wp_error($response)) {
            update_post_meta($family_id, '_tracker_mumble_channel_id', $response['mumble']['rootChannelId'] ?? null);
            update_post_meta($family_id, '_tracker_mumble_group_id', $response['mumble']['groupId'] ?? null);
        }

        return $response;
    }

    /**
     * Deprovision a family from Mumble.
     *
     * @param int $family_id Family post ID.
     * @return array|WP_Error Response or error.
     */
    public static function deprovision_family($family_id) {
        $response = self::api_request('/families/' . $family_id, 'DELETE');

        if (!is_wp_error($response)) {
            delete_post_meta($family_id, '_tracker_mumble_channel_id');
            delete_post_meta($family_id, '_tracker_mumble_group_id');

            // Clean up user meta for all family members
            if (class_exists('Tracker_Core')) {
                $members = Tracker_Core::get_family_members($family_id);
                if (is_array($members)) {
                    foreach ($members as $member) {
                        if (!empty($member['user_id'])) {
                            delete_user_meta($member['user_id'], '_tracker_mumble_user_id');
                            delete_user_meta($member['user_id'], '_tracker_mumble_username');
                        }
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Register a user in Mumble and assign to family group.
     *
     * @param int $user_id   WordPress user ID.
     * @param int $family_id Family post ID.
     * @return array|WP_Error Response or error.
     */
    public static function register_user($user_id, $family_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', 'User not found.');
        }

        $username = self::generate_mumble_username($user);

        $response = self::api_request('/users', 'POST', [
            'username' => $username,
            'email'    => $user->user_email,
            'familyId' => $family_id,
        ]);

        if (!is_wp_error($response)) {
            update_user_meta($user_id, '_tracker_mumble_user_id', $response['mumbleUserId'] ?? null);
            update_user_meta($user_id, '_tracker_mumble_username', $username);
        }

        return $response;
    }

    /**
     * Remove a user from a family's Mumble group.
     *
     * @param int $user_id   WordPress user ID.
     * @param int $family_id Family post ID.
     * @return array|WP_Error Response or error.
     */
    public static function remove_user($user_id, $family_id) {
        $mumble_user_id = get_user_meta($user_id, '_tracker_mumble_user_id', true);

        if (!$mumble_user_id) {
            return new WP_Error('not_registered', 'User not registered in Mumble.');
        }

        $response = self::api_request("/users/{$mumble_user_id}/families/{$family_id}", 'DELETE');

        if (!is_wp_error($response)) {
            delete_user_meta($user_id, '_tracker_mumble_user_id');
            delete_user_meta($user_id, '_tracker_mumble_username');
        }

        return $response;
    }

    /**
     * Get Mumble connection info for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return array|WP_Error Connection info or error.
     */
    public static function get_connection_info($user_id) {
        $mumble_username = get_user_meta($user_id, '_tracker_mumble_username', true);

        if (!$mumble_username) {
            return new WP_Error('not_registered', 'User not registered in Mumble.');
        }

        return self::api_request('/connection-info/' . urlencode($mumble_username));
    }

    /**
     * Generate Mumble username from WordPress user.
     *
     * @param WP_User $user WordPress user object.
     * @return string
     */
    private static function generate_mumble_username($user) {
        return 'tracker_' . sanitize_title($user->user_login);
    }

    /**
     * Check if a family is provisioned in Mumble.
     *
     * @param int $family_id Family post ID.
     * @return bool
     */
    public static function is_family_provisioned($family_id) {
        $channel_id = get_post_meta($family_id, '_tracker_mumble_channel_id', true);
        return !empty($channel_id);
    }

    /**
     * Check if a user is registered in Mumble for tracker.
     *
     * @param int $user_id WordPress user ID.
     * @return bool
     */
    public static function is_user_registered($user_id) {
        $mumble_user_id = get_user_meta($user_id, '_tracker_mumble_user_id', true);
        return !empty($mumble_user_id);
    }
}
