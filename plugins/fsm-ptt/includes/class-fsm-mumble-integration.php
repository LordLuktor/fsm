<?php
/**
 * FSM Mumble Integration
 *
 * Integration with the existing Mumble server.
 * Note: Full Mumble control requires the Ice interface which isn't enabled by default.
 * This class provides the structure for when Ice access is available.
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
     * Check if Mumble server is reachable.
     *
     * @return bool
     */
    public static function is_available() {
        $host = FSM_PTT::get_mumble_host();
        $port = FSM_PTT::get_mumble_port();

        $connection = @fsockopen($host, $port, $errno, $errstr, 2);

        if ($connection) {
            fclose($connection);
            return true;
        }

        return false;
    }

    /**
     * Create a channel in Mumble.
     * Note: Requires Ice interface or manual channel creation.
     *
     * @param string $channel_name Channel name.
     * @param int    $parent_id Parent channel ID (0 for root).
     * @return bool Success status.
     */
    public static function create_channel($channel_name, $parent_id = 0) {
        // Without Ice interface, log the request for manual action
        FSM_Utilities::log_activity('mumble_channel_request', [
            'action' => 'create',
            'channel_name' => $channel_name,
            'parent_id' => $parent_id,
        ]);

        /**
         * Action fired when a Mumble channel creation is requested.
         * External systems can hook into this to create channels via Ice or other methods.
         *
         * @param string $channel_name Requested channel name.
         * @param int    $parent_id Parent channel ID.
         */
        do_action('fsm_mumble_create_channel', $channel_name, $parent_id);

        return true;
    }

    /**
     * Delete a channel in Mumble.
     *
     * @param int $channel_id Mumble channel ID.
     * @return bool Success status.
     */
    public static function delete_channel($channel_id) {
        FSM_Utilities::log_activity('mumble_channel_request', [
            'action' => 'delete',
            'channel_id' => $channel_id,
        ]);

        do_action('fsm_mumble_delete_channel', $channel_id);

        return true;
    }

    /**
     * Get Mumble connection URL for a user.
     *
     * @param string $username Username.
     * @param string $channel Optional channel to join.
     * @return string Mumble URL.
     */
    public static function get_connection_url($username, $channel = null) {
        $host = 'mumble.steinmetz.ltd';
        $port = FSM_PTT::get_mumble_port();

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
     * Generate channel structure based on FSM organization.
     *
     * @return array Recommended channel structure.
     */
    public static function get_recommended_structure() {
        $structure = [
            'FSM' => [
                'Dispatch' => [],
                'Teams' => [],
                'Work Orders' => [],
            ],
        ];

        // Add team channels
        $teams = get_terms([
            'taxonomy' => 'fsm_team',
            'hide_empty' => false,
        ]);

        if (!is_wp_error($teams)) {
            foreach ($teams as $team) {
                $structure['FSM']['Teams'][$team->name] = [];
            }
        }

        return $structure;
    }

    /**
     * Get channel mapping recommendations.
     *
     * @return array Channel mapping recommendations.
     */
    public static function get_channel_mapping() {
        return [
            'dispatch' => [
                'name' => 'Dispatch',
                'description' => 'Main dispatch channel - all technicians and dispatchers',
                'parent' => 'FSM',
            ],
            'team' => [
                'name_pattern' => 'Team - {team_name}',
                'description' => 'Team-specific channel',
                'parent' => 'FSM/Teams',
            ],
            'technician' => [
                'name_pattern' => 'Tech - {technician_name}',
                'description' => 'Personal technician channel for direct communication',
                'parent' => 'FSM',
            ],
            'work_order' => [
                'name_pattern' => 'WO - {reference}',
                'description' => 'Temporary channel for work order collaboration',
                'parent' => 'FSM/Work Orders',
            ],
        ];
    }
}
