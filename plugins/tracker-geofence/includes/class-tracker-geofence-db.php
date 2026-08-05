<?php
/**
 * Tracker Geofence Database - Tables for geofences and alerts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Geofence_DB {

    public static function geofences_table() {
        global $wpdb;
        return $wpdb->prefix . 'tracker_geofences';
    }

    public static function alerts_table() {
        global $wpdb;
        return $wpdb->prefix . 'tracker_alerts';
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $geofences = self::geofences_table();
        $alerts    = self::alerts_table();

        $sql = "CREATE TABLE {$geofences} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            family_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            radius_meters INT UNSIGNED NOT NULL DEFAULT 100,
            type ENUM('safe_zone', 'alert_zone') DEFAULT 'safe_zone',
            notify_on ENUM('enter', 'exit', 'both') DEFAULT 'both',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_family (family_id),
            INDEX idx_active (family_id, active)
        ) {$charset};

        CREATE TABLE {$alerts} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            family_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NOT NULL,
            type ENUM('geofence_enter', 'geofence_exit', 'sos', 'low_battery') NOT NULL,
            geofence_id BIGINT UNSIGNED DEFAULT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            read_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_family (family_id),
            INDEX idx_member (member_id),
            INDEX idx_created (created_at),
            INDEX idx_unread (family_id, read_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // =========================================================================
    // Geofence CRUD
    // =========================================================================

    public static function create_geofence( $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::geofences_table(),
            array(
                'family_id'     => $data['family_id'],
                'name'          => sanitize_text_field( $data['name'] ),
                'latitude'      => $data['latitude'],
                'longitude'     => $data['longitude'],
                'radius_meters' => $data['radius_meters'] ?? 100,
                'type'          => $data['type'] ?? 'safe_zone',
                'notify_on'     => $data['notify_on'] ?? 'both',
                'active'        => 1,
                'created_by'    => $data['created_by'],
            ),
            array( '%d', '%s', '%f', '%f', '%d', '%s', '%s', '%d', '%d' )
        );

        return $result ? $wpdb->insert_id : new WP_Error( 'db_error', 'Failed to create geofence.' );
    }

    public static function get_geofence( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::geofences_table() . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public static function get_family_geofences( $family_id, $active_only = true ) {
        global $wpdb;
        $table = self::geofences_table();

        $where = $active_only ? "AND active = 1" : "";

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE family_id = %d {$where} ORDER BY created_at DESC", $family_id ),
            ARRAY_A
        );
    }

    public static function update_geofence( $id, $data ) {
        global $wpdb;
        $allowed = array( 'name', 'latitude', 'longitude', 'radius_meters', 'type', 'notify_on', 'active' );
        $update  = array();
        $format  = array();

        foreach ( $allowed as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $update[ $field ] = $field === 'name' ? sanitize_text_field( $data[ $field ] ) : $data[ $field ];
                $format[] = in_array( $field, array( 'latitude', 'longitude' ), true ) ? '%f'
                    : ( in_array( $field, array( 'radius_meters', 'active' ), true ) ? '%d' : '%s' );
            }
        }

        if ( empty( $update ) ) {
            return false;
        }

        return $wpdb->update( self::geofences_table(), $update, array( 'id' => $id ), $format, array( '%d' ) );
    }

    public static function delete_geofence( $id ) {
        global $wpdb;
        return $wpdb->delete( self::geofences_table(), array( 'id' => $id ), array( '%d' ) );
    }

    // =========================================================================
    // Alert CRUD
    // =========================================================================

    public static function create_alert( $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::alerts_table(),
            array(
                'family_id'   => $data['family_id'],
                'member_id'   => $data['member_id'],
                'type'        => $data['type'],
                'geofence_id' => $data['geofence_id'] ?? null,
                'latitude'    => $data['latitude'] ?? null,
                'longitude'   => $data['longitude'] ?? null,
                'message'     => $data['message'] ?? null,
            ),
            array( '%d', '%d', '%s', '%d', '%f', '%f', '%s' )
        );

        return $result ? $wpdb->insert_id : new WP_Error( 'db_error', 'Failed to create alert.' );
    }

    public static function get_family_alerts( $family_id, $limit = 50, $offset = 0 ) {
        global $wpdb;
        $table = self::alerts_table();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE family_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $family_id, $limit, $offset
            ),
            ARRAY_A
        );
    }

    public static function get_unread_alerts_count( $family_id ) {
        global $wpdb;
        $table = self::alerts_table();

        return (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE family_id = %d AND read_at IS NULL", $family_id )
        );
    }

    public static function get_alert( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::alerts_table() . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public static function mark_alert_read( $id ) {
        global $wpdb;
        return $wpdb->update(
            self::alerts_table(),
            array( 'read_at' => current_time( 'mysql' ) ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public static function mark_all_alerts_read( $family_id ) {
        global $wpdb;
        $table = self::alerts_table();

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET read_at = %s WHERE family_id = %d AND read_at IS NULL",
                current_time( 'mysql' ),
                $family_id
            )
        );
    }
}
