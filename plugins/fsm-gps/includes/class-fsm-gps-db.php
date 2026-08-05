<?php
/**
 * FSM GPS Database
 *
 * Custom table for GPS location snapshots.
 * Note: WordPress stores authoritative snapshots only - no live polling.
 *
 * @package FSM\GPS
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM GPS DB class.
 */
class FSM_GPS_DB {

    /**
     * Create custom tables.
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table = FSM_GPS::get_table();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            organization_id bigint(20) unsigned NOT NULL DEFAULT 0,
            technician_id bigint(20) unsigned NOT NULL,
            latitude decimal(10,8) NOT NULL,
            longitude decimal(11,8) NOT NULL,
            accuracy decimal(10,2) DEFAULT NULL,
            altitude decimal(10,2) DEFAULT NULL,
            heading decimal(5,2) DEFAULT NULL,
            speed decimal(10,2) DEFAULT NULL,
            source enum('device', 'manual', 'checkin') DEFAULT 'device',
            work_order_id bigint(20) unsigned DEFAULT NULL,
            address text,
            recorded_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY organization_id (organization_id),
            KEY technician_id (technician_id),
            KEY recorded_at (recorded_at),
            KEY work_order_id (work_order_id),
            KEY technician_time (technician_id, recorded_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Record location.
     *
     * @param array $data Location data.
     * @return int|false Location ID or false on failure.
     */
    public static function record($data) {
        global $wpdb;
        $table = FSM_GPS::get_table();

        // Get organization_id from technician if not provided
        $org_id = $data['organization_id'] ?? null;
        if (!$org_id && !empty($data['technician_id'])) {
            $org_id = get_post_meta($data['technician_id'], '_fsm_organization_id', true);
        }

        $result = $wpdb->insert($table, [
            'organization_id' => $org_id ?: 0,
            'technician_id' => $data['technician_id'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'altitude' => $data['altitude'] ?? null,
            'heading' => $data['heading'] ?? null,
            'speed' => $data['speed'] ?? null,
            'source' => $data['source'] ?? 'device',
            'work_order_id' => $data['work_order_id'] ?? null,
            'address' => $data['address'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? current_time('mysql'),
        ]);

        if ($result) {
            // Update technician's last known location (in post meta for quick access)
            update_post_meta($data['technician_id'], '_fsm_last_latitude', $data['latitude']);
            update_post_meta($data['technician_id'], '_fsm_last_longitude', $data['longitude']);
            update_post_meta($data['technician_id'], '_fsm_last_location_time', $data['recorded_at'] ?? current_time('mysql'));

            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get latest location for technician.
     *
     * @param int $technician_id Technician ID.
     * @return object|null
     */
    public static function get_latest($technician_id) {
        global $wpdb;
        $table = FSM_GPS::get_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE technician_id = %d ORDER BY recorded_at DESC LIMIT 1",
            $technician_id
        ));
    }

    /**
     * Get location history for technician.
     *
     * @param int    $technician_id Technician ID.
     * @param string $date_from Start date.
     * @param string $date_to End date.
     * @param int    $limit Max records.
     * @return array
     */
    public static function get_history($technician_id, $date_from = null, $date_to = null, $limit = 100) {
        global $wpdb;
        $table = FSM_GPS::get_table();

        $where = "WHERE technician_id = %d";
        $params = [$technician_id];

        if ($date_from) {
            $where .= " AND recorded_at >= %s";
            $params[] = $date_from;
        }

        if ($date_to) {
            $where .= " AND recorded_at <= %s";
            $params[] = $date_to;
        }

        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY recorded_at DESC LIMIT %d",
            $params
        ));
    }

    /**
     * Get all technicians' latest locations within organization context.
     *
     * @param int|null $org_id Organization ID (null for current context).
     * @return array
     */
    public static function get_all_latest($org_id = null) {
        global $wpdb;
        $table = FSM_GPS::get_table();

        if ($org_id === null) {
            $org_id = FSM_Organizations::get_current_context();
        }

        // Super admin viewing all orgs
        if ($org_id === null && FSM_Organizations::is_super_admin()) {
            return $wpdb->get_results("
                SELECT l.*
                FROM $table l
                INNER JOIN (
                    SELECT technician_id, MAX(recorded_at) as max_time
                    FROM $table
                    GROUP BY technician_id
                ) latest ON l.technician_id = latest.technician_id AND l.recorded_at = latest.max_time
            ");
        }

        // No org context for non-super admin
        if ($org_id === null) {
            return [];
        }

        // Filter by organization
        return $wpdb->get_results($wpdb->prepare("
            SELECT l.*
            FROM $table l
            INNER JOIN (
                SELECT technician_id, MAX(recorded_at) as max_time
                FROM $table
                WHERE organization_id = %d
                GROUP BY technician_id
            ) latest ON l.technician_id = latest.technician_id AND l.recorded_at = latest.max_time
            WHERE l.organization_id = %d
        ", $org_id, $org_id));
    }

    /**
     * Get locations for a work order.
     *
     * @param int $work_order_id Work order ID.
     * @return array
     */
    public static function get_for_work_order($work_order_id) {
        global $wpdb;
        $table = FSM_GPS::get_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE work_order_id = %d ORDER BY recorded_at ASC",
            $work_order_id
        ));
    }

    /**
     * Clean up old location data.
     * Called by scheduled event.
     *
     * @param int $days Days to retain.
     * @return int Number of deleted rows.
     */
    public static function cleanup($days = 30) {
        global $wpdb;
        $table = FSM_GPS::get_table();

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE recorded_at < %s",
            $cutoff
        ));
    }
}
