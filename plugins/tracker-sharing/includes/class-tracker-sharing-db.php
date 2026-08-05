<?php
/**
 * Tracker Sharing Database - Tables for share links and saved places.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tracker_Sharing_DB {

    public static function share_links_table() {
        global $wpdb;
        return $wpdb->prefix . 'tracker_share_links';
    }

    public static function places_table() {
        global $wpdb;
        return $wpdb->prefix . 'tracker_places';
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $shares = self::share_links_table();
        $places = self::places_table();

        $sql = "CREATE TABLE {$shares} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            family_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED DEFAULT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_token (token),
            INDEX idx_family (family_id)
        ) {$charset};

        CREATE TABLE {$places} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            family_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            icon VARCHAR(50) DEFAULT 'pin',
            address TEXT DEFAULT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_family (family_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // =========================================================================
    // Share Links
    // =========================================================================

    public static function create_share_link( $data ) {
        global $wpdb;

        $token = wp_generate_password( 32, false );

        $result = $wpdb->insert(
            self::share_links_table(),
            array(
                'family_id'  => $data['family_id'],
                'member_id'  => $data['member_id'] ?? null,
                'token'      => $token,
                'expires_at' => $data['expires_at'] ?? null,
                'active'     => 1,
                'created_by' => $data['created_by'],
            ),
            array( '%d', '%d', '%s', '%s', '%d', '%d' )
        );

        if ( ! $result ) {
            return new WP_Error( 'db_error', 'Failed to create share link.' );
        }

        return array(
            'id'         => $wpdb->insert_id,
            'token'      => $token,
            'expires_at' => $data['expires_at'] ?? null,
        );
    }

    public static function get_share_link_by_token( $token ) {
        global $wpdb;
        $table = self::share_links_table();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE token = %s AND active = 1 AND (expires_at IS NULL OR expires_at > NOW())",
                $token
            ),
            ARRAY_A
        );
    }

    public static function get_family_share_links( $family_id ) {
        global $wpdb;
        $table = self::share_links_table();

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE family_id = %d ORDER BY created_at DESC", $family_id ),
            ARRAY_A
        );
    }

    public static function get_share_link( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::share_links_table() . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public static function revoke_share_link( $id ) {
        global $wpdb;
        return $wpdb->update(
            self::share_links_table(),
            array( 'active' => 0 ),
            array( 'id' => $id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    // =========================================================================
    // Saved Places
    // =========================================================================

    public static function create_place( $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::places_table(),
            array(
                'family_id'  => $data['family_id'],
                'name'       => sanitize_text_field( $data['name'] ),
                'latitude'   => $data['latitude'],
                'longitude'  => $data['longitude'],
                'icon'       => sanitize_text_field( $data['icon'] ?? 'pin' ),
                'address'    => sanitize_text_field( $data['address'] ?? '' ),
                'created_by' => $data['created_by'],
            ),
            array( '%d', '%s', '%f', '%f', '%s', '%s', '%d' )
        );

        return $result ? $wpdb->insert_id : new WP_Error( 'db_error', 'Failed to create place.' );
    }

    public static function get_family_places( $family_id ) {
        global $wpdb;
        $table = self::places_table();

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE family_id = %d ORDER BY name ASC", $family_id ),
            ARRAY_A
        );
    }

    public static function get_place( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM " . self::places_table() . " WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public static function delete_place( $id ) {
        global $wpdb;
        return $wpdb->delete( self::places_table(), array( 'id' => $id ), array( '%d' ) );
    }
}
