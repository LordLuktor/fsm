<?php
/**
 * FSM PTT Database
 *
 * Custom table for PTT channel mappings.
 *
 * @package FSM\PTT
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM PTT DB class.
 */
class FSM_PTT_DB {

    /**
     * Create custom tables.
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table = FSM_PTT::get_channels_table();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            channel_name varchar(255) NOT NULL,
            channel_type enum('dispatch', 'team', 'technician', 'work_order') NOT NULL,
            reference_id bigint(20) unsigned DEFAULT NULL,
            mumble_channel_id int(11) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY channel_type_ref (channel_type, reference_id),
            KEY channel_type (channel_type),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Create default dispatch channel
        self::ensure_dispatch_channel();
    }

    /**
     * Ensure dispatch channel exists.
     */
    private static function ensure_dispatch_channel() {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE channel_type = %s AND reference_id = 0",
            'dispatch'
        ));

        if (!$exists) {
            $wpdb->insert($table, [
                'channel_name' => 'Dispatch',
                'channel_type' => 'dispatch',
                'reference_id' => 0,
                'is_active' => 1,
            ]);
        }
    }

    /**
     * Get channel by type and reference.
     *
     * @param string $type Channel type.
     * @param int    $reference_id Reference ID.
     * @return object|null
     */
    public static function get_channel($type, $reference_id = 0) {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE channel_type = %s AND reference_id = %d",
            $type,
            $reference_id
        ));
    }

    /**
     * Get all active channels.
     *
     * @param string $type Optional type filter.
     * @return array
     */
    public static function get_channels($type = null) {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        if ($type) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE channel_type = %s AND is_active = 1",
                $type
            ));
        }

        return $wpdb->get_results(
            "SELECT * FROM $table WHERE is_active = 1"
        );
    }

    /**
     * Create or update channel.
     *
     * @param array $data Channel data.
     * @return int|false Channel ID or false on failure.
     */
    public static function upsert_channel($data) {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        $existing = self::get_channel($data['channel_type'], $data['reference_id'] ?? 0);

        if ($existing) {
            $wpdb->update(
                $table,
                ['channel_name' => $data['channel_name'], 'is_active' => 1],
                ['id' => $existing->id]
            );
            return $existing->id;
        }

        $result = $wpdb->insert($table, [
            'channel_name' => $data['channel_name'],
            'channel_type' => $data['channel_type'],
            'reference_id' => $data['reference_id'] ?? 0,
            'mumble_channel_id' => $data['mumble_channel_id'] ?? null,
            'is_active' => 1,
        ]);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Deactivate channel.
     *
     * @param int $id Channel ID.
     * @return bool
     */
    public static function deactivate_channel($id) {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        return $wpdb->update($table, ['is_active' => 0], ['id' => $id]) !== false;
    }

    /**
     * Get channels for technician.
     *
     * @param int $technician_id Technician ID.
     * @return array
     */
    public static function get_technician_channels($technician_id) {
        $channels = [];

        // Dispatch channel (all technicians have access)
        $dispatch = self::get_channel('dispatch', 0);
        if ($dispatch) {
            $channels[] = $dispatch;
        }

        // Technician's personal channel
        $personal = self::get_channel('technician', $technician_id);
        if ($personal) {
            $channels[] = $personal;
        }

        // Team channels
        $teams = wp_get_post_terms($technician_id, 'fsm_team', ['fields' => 'ids']);
        foreach ($teams as $team_id) {
            $team_channel = self::get_channel('team', $team_id);
            if ($team_channel) {
                $channels[] = $team_channel;
            }
        }

        return $channels;
    }
}
