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
            organization_id bigint(20) unsigned NOT NULL DEFAULT 0,
            channel_name varchar(255) NOT NULL,
            channel_type enum('dispatch', 'team', 'technician', 'work_order') NOT NULL,
            reference_id bigint(20) unsigned DEFAULT NULL,
            mumble_channel_id int(11) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY org_channel_type_ref (organization_id, channel_type, reference_id),
            KEY channel_type (channel_type),
            KEY organization_id (organization_id),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Ensure dispatch channel exists for an organization.
     *
     * @param int $org_id Organization ID.
     */
    public static function ensure_dispatch_channel($org_id) {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE organization_id = %d AND channel_type = %s AND reference_id = 0",
            $org_id,
            'dispatch'
        ));

        if (!$exists) {
            $org = FSM_Organizations::get($org_id);
            $org_name = $org ? $org->post_title : 'Organization';

            $wpdb->insert($table, [
                'organization_id' => $org_id,
                'channel_name' => $org_name . ' - Dispatch',
                'channel_type' => 'dispatch',
                'reference_id' => 0,
                'is_active' => 1,
            ]);
        }
    }

    /**
     * Get channel by type and reference within organization context.
     *
     * @param string   $type Channel type.
     * @param int      $reference_id Reference ID.
     * @param int|null $org_id Organization ID (null for current context).
     * @return object|null
     */
    public static function get_channel($type, $reference_id = 0, $org_id = null) {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        if ($org_id === null) {
            $org_id = FSM_Organizations::get_current_context();
        }

        // If no org context (super admin viewing all), return null
        if ($org_id === null) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE organization_id = %d AND channel_type = %s AND reference_id = %d",
            $org_id,
            $type,
            $reference_id
        ));
    }

    /**
     * Get all active channels within organization context.
     *
     * @param string   $type Optional type filter.
     * @param int|null $org_id Organization ID (null for current context).
     * @return array
     */
    public static function get_channels($type = null, $org_id = null) {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        if ($org_id === null) {
            $org_id = FSM_Organizations::get_current_context();
        }

        // Super admin viewing all orgs
        if ($org_id === null && FSM_Organizations::is_super_admin()) {
            if ($type) {
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table WHERE channel_type = %s AND is_active = 1",
                    $type
                ));
            }
            return $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1");
        }

        // No org context for non-super admin
        if ($org_id === null) {
            return [];
        }

        if ($type) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE organization_id = %d AND channel_type = %s AND is_active = 1",
                $org_id,
                $type
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE organization_id = %d AND is_active = 1",
            $org_id
        ));
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

        $org_id = $data['organization_id'] ?? FSM_Organizations::get_current_context();
        if (!$org_id) {
            return false; // Cannot create channel without organization
        }

        $existing = self::get_channel($data['channel_type'], $data['reference_id'] ?? 0, $org_id);

        if ($existing) {
            $wpdb->update(
                $table,
                ['channel_name' => $data['channel_name'], 'is_active' => 1],
                ['id' => $existing->id]
            );
            return $existing->id;
        }

        $result = $wpdb->insert($table, [
            'organization_id' => $org_id,
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

        // Get technician's organization
        $org_id = get_post_meta($technician_id, '_fsm_organization_id', true);
        if (!$org_id) {
            return $channels;
        }

        // Dispatch channel (all technicians in org have access)
        $dispatch = self::get_channel('dispatch', 0, $org_id);
        if ($dispatch) {
            $channels[] = $dispatch;
        }

        // Technician's personal channel
        $personal = self::get_channel('technician', $technician_id, $org_id);
        if ($personal) {
            $channels[] = $personal;
        }

        // Team channels
        $teams = wp_get_post_terms($technician_id, 'fsm_team', ['fields' => 'ids']);
        foreach ($teams as $team_id) {
            $team_channel = self::get_channel('team', $team_id, $org_id);
            if ($team_channel) {
                $channels[] = $team_channel;
            }
        }

        return $channels;
    }

    /**
     * Add organization_id column to existing table if missing.
     * Called during plugin update.
     */
    public static function maybe_upgrade_table() {
        global $wpdb;
        $table = FSM_PTT::get_channels_table();

        // Check if organization_id column exists
        $columns = $wpdb->get_col("DESCRIBE $table");
        if (!in_array('organization_id', $columns)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN organization_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER id");
            $wpdb->query("ALTER TABLE $table DROP INDEX channel_type_ref");
            $wpdb->query("ALTER TABLE $table ADD UNIQUE INDEX org_channel_type_ref (organization_id, channel_type, reference_id)");
            $wpdb->query("ALTER TABLE $table ADD INDEX organization_id (organization_id)");
        }
    }
}
