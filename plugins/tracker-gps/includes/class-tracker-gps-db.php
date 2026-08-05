<?php
/**
 * Tracker GPS Database - Custom table for GPS location data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_GPS_DB {

    /**
     * Get the table name.
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'tracker_gps_locations';
    }

    /**
     * Create the GPS locations table.
     */
    public static function create_tables() {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            family_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            accuracy FLOAT DEFAULT NULL,
            altitude FLOAT DEFAULT NULL,
            heading FLOAT DEFAULT NULL,
            speed FLOAT DEFAULT NULL,
            battery_level TINYINT UNSIGNED DEFAULT NULL,
            source ENUM('device', 'manual') DEFAULT 'device',
            recorded_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_family (family_id),
            INDEX idx_member (member_id),
            INDEX idx_recorded (recorded_at),
            INDEX idx_member_time (member_id, recorded_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Insert a GPS location record.
     */
    public static function insert_location( $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::table_name(),
            array(
                'family_id'     => $data['family_id'],
                'member_id'     => $data['member_id'],
                'latitude'      => $data['latitude'],
                'longitude'     => $data['longitude'],
                'accuracy'      => $data['accuracy'] ?? null,
                'altitude'      => $data['altitude'] ?? null,
                'heading'       => $data['heading'] ?? null,
                'speed'         => $data['speed'] ?? null,
                'battery_level' => $data['battery_level'] ?? null,
                'source'        => $data['source'] ?? 'device',
                'recorded_at'   => $data['recorded_at'] ?? current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%d', '%s', '%s' )
        );

        if ( false === $result ) {
            return new WP_Error( 'db_error', 'Failed to insert location.' );
        }

        return $wpdb->insert_id;
    }

    /**
     * Get the latest location for a member.
     */
    public static function get_latest_location( $member_id ) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE member_id = %d ORDER BY recorded_at DESC LIMIT 1",
                $member_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get latest locations for all members of a family.
     */
    public static function get_family_latest_locations( $family_id ) {
        global $wpdb;
        $table = self::table_name();

        // Subquery to get the max recorded_at for each member
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t1.* FROM {$table} t1
                INNER JOIN (
                    SELECT member_id, MAX(recorded_at) as max_recorded
                    FROM {$table}
                    WHERE family_id = %d
                    GROUP BY member_id
                ) t2 ON t1.member_id = t2.member_id AND t1.recorded_at = t2.max_recorded
                WHERE t1.family_id = %d",
                $family_id,
                $family_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get location history for a member within a date range.
     */
    public static function get_member_history( $member_id, $date_from, $date_to, $limit = 1000 ) {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE member_id = %d
                AND recorded_at BETWEEN %s AND %s
                ORDER BY recorded_at ASC
                LIMIT %d",
                $member_id,
                $date_from,
                $date_to,
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Clean up old location data based on tier.
     * Free: keep 24h, Premium: keep 30 days.
     */
    public static function cleanup_old_locations( $family_id, $tier = 'free' ) {
        global $wpdb;
        $table = self::table_name();

        $retention = ( 'premium' === $tier ) ? '30 DAY' : '1 DAY';

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table}
                WHERE family_id = %d
                AND recorded_at < DATE_SUB(NOW(), INTERVAL {$retention})",
                $family_id
            )
        );
    }
}
