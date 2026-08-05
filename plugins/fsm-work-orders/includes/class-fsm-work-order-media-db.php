<?php
/**
 * FSM Work Order Media DB
 *
 * Custom table for photo/video attachments on work orders.
 * Media attachments are OPTIONAL — a work order can be completed
 * without any media. When media is present, it enables downstream
 * use by CutBot for content production and QA verification.
 *
 * @package FSM\WorkOrders
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

class FSM_Work_Order_Media_DB {

    const TABLE_VERSION = '1.0';
    const VERSION_OPTION = 'fsm_wo_media_db_version';

    /**
     * Get the custom table name.
     */
    public static function get_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_work_order_media';
    }

    /**
     * Create or upgrade the table via dbDelta.
     * Called on plugin activation.
     */
    public static function create_table() {
        global $wpdb;

        $table = self::get_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            work_order_id BIGINT UNSIGNED NOT NULL,
            media_type VARCHAR(16) NOT NULL DEFAULT 'photo',
            mime_type VARCHAR(100) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_url VARCHAR(500) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            duration INT DEFAULT NULL,
            width INT DEFAULT NULL,
            height INT DEFAULT NULL,
            caption TEXT DEFAULT NULL,
            uploaded_by BIGINT UNSIGNED NOT NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_work_order_id (work_order_id),
            KEY idx_media_type (media_type),
            KEY idx_uploaded_at (uploaded_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::VERSION_OPTION, self::TABLE_VERSION);
    }

    /**
     * Insert a media row.
     *
     * @param array $data Keys: work_order_id, media_type, mime_type,
     *                    original_filename, file_path, file_url, file_size,
     *                    duration, width, height, caption, uploaded_by
     * @return int|false Inserted ID or false on failure.
     */
    public static function insert($data) {
        global $wpdb;

        $defaults = [
            'media_type' => 'photo',
            'file_size' => 0,
            'duration' => null,
            'width' => null,
            'height' => null,
            'caption' => null,
            'uploaded_at' => current_time('mysql'),
        ];

        $row = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            self::get_table(),
            [
                'work_order_id' => (int) $row['work_order_id'],
                'media_type' => $row['media_type'],
                'mime_type' => $row['mime_type'],
                'original_filename' => $row['original_filename'],
                'file_path' => $row['file_path'],
                'file_url' => $row['file_url'],
                'file_size' => (int) $row['file_size'],
                'duration' => $row['duration'],
                'width' => $row['width'],
                'height' => $row['height'],
                'caption' => $row['caption'],
                'uploaded_by' => (int) $row['uploaded_by'],
                'uploaded_at' => $row['uploaded_at'],
            ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get a media row by ID.
     */
    public static function get($id) {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Get all media for a work order.
     */
    public static function get_by_work_order($work_order_id) {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE work_order_id = %d ORDER BY uploaded_at ASC",
                $work_order_id
            ),
            ARRAY_A
        );
    }

    /**
     * Count media for a work order.
     */
    public static function count_by_work_order($work_order_id) {
        global $wpdb;
        $table = self::get_table();
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE work_order_id = %d",
                $work_order_id
            )
        );
    }

    /**
     * Get media counts for multiple work orders in one query.
     *
     * @param int[] $work_order_ids
     * @return array Map of work_order_id => count
     */
    public static function count_by_work_orders($work_order_ids) {
        global $wpdb;
        if (empty($work_order_ids)) {
            return [];
        }

        $table = self::get_table();
        $placeholders = implode(',', array_fill(0, count($work_order_ids), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT work_order_id, COUNT(*) AS cnt
                 FROM $table
                 WHERE work_order_id IN ($placeholders)
                 GROUP BY work_order_id",
                $work_order_ids
            ),
            ARRAY_A
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['work_order_id']] = (int) $r['cnt'];
        }
        return $out;
    }

    /**
     * Get work order IDs that have at least one media attachment.
     */
    public static function get_work_order_ids_with_media() {
        global $wpdb;
        $table = self::get_table();
        $ids = $wpdb->get_col("SELECT DISTINCT work_order_id FROM $table");
        return array_map('intval', $ids);
    }

    /**
     * Query media across all work orders with filters.
     *
     * @param array $filters {
     *     @type string $date_from  YYYY-MM-DD (inclusive)
     *     @type string $date_to    YYYY-MM-DD (inclusive)
     *     @type string $type       'photo' | 'video'
     *     @type int    $limit
     *     @type int    $offset
     * }
     * @return array Rows ordered by uploaded_at DESC.
     */
    public static function query($filters = []) {
        global $wpdb;
        $table = self::get_table();

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = 'uploaded_at >= %s';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'uploaded_at <= %s';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['type']) && in_array($filters['type'], ['photo', 'video'], true)) {
            $where[] = 'media_type = %s';
            $params[] = $filters['type'];
        }

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 500;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where)
             . " ORDER BY uploaded_at DESC LIMIT $limit OFFSET $offset";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Delete a media row by ID. Also deletes the file from disk.
     */
    public static function delete($id) {
        global $wpdb;
        $row = self::get($id);
        if (!$row) {
            return false;
        }

        // Delete physical file
        if (!empty($row['file_path']) && file_exists($row['file_path'])) {
            @unlink($row['file_path']);
        }

        return (bool) $wpdb->delete(
            self::get_table(),
            ['id' => (int) $id],
            ['%d']
        );
    }

    /**
     * Delete all media for a work order (cascade on WO delete).
     */
    public static function delete_by_work_order($work_order_id) {
        global $wpdb;

        // Delete physical files first
        $rows = self::get_by_work_order($work_order_id);
        foreach ($rows as $row) {
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                @unlink($row['file_path']);
            }
        }

        return $wpdb->delete(
            self::get_table(),
            ['work_order_id' => (int) $work_order_id],
            ['%d']
        );
    }

    /**
     * Format a DB row for REST API output.
     */
    public static function format_for_api($row) {
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'work_order_id' => (int) $row['work_order_id'],
            'type' => $row['media_type'],
            'mime_type' => $row['mime_type'],
            'filename' => $row['original_filename'],
            'url' => $row['file_url'],
            'size' => (int) $row['file_size'],
            'duration' => $row['duration'] !== null ? (int) $row['duration'] : null,
            'width' => $row['width'] !== null ? (int) $row['width'] : null,
            'height' => $row['height'] !== null ? (int) $row['height'] : null,
            'caption' => $row['caption'],
            'uploaded_by' => (int) $row['uploaded_by'],
            'uploaded_at' => $row['uploaded_at'],
        ];
    }
}
