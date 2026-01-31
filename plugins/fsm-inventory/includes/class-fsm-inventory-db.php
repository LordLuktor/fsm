<?php
/**
 * FSM Inventory Database
 *
 * Custom table creation and management for inventory.
 *
 * @package FSM\Inventory
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Inventory DB class.
 */
class FSM_Inventory_DB {

    /**
     * Create custom tables.
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $items_table = FSM_Inventory::get_items_table();
        $log_table = FSM_Inventory::get_log_table();

        // Inventory items table
        $sql_items = "CREATE TABLE $items_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sku varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            category varchar(100),
            unit varchar(50) DEFAULT 'each',
            unit_cost decimal(10,2) DEFAULT 0.00,
            reorder_point int(11) DEFAULT 0,
            location_type enum('warehouse', 'technician', 'vehicle') DEFAULT 'warehouse',
            location_id bigint(20) unsigned DEFAULT 0,
            quantity int(11) DEFAULT 0,
            reserved_quantity int(11) DEFAULT 0,
            status enum('active', 'discontinued', 'low_stock', 'out_of_stock') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sku_location (sku, location_type, location_id),
            KEY location_type (location_type),
            KEY location_id (location_id),
            KEY status (status),
            KEY category (category)
        ) $charset_collate;";

        // Inventory transaction log table
        $sql_log = "CREATE TABLE $log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            inventory_item_id bigint(20) unsigned NOT NULL,
            transaction_type enum('receive', 'consume', 'transfer', 'adjust', 'return') NOT NULL,
            quantity int(11) NOT NULL,
            quantity_before int(11) NOT NULL,
            quantity_after int(11) NOT NULL,
            work_order_id bigint(20) unsigned DEFAULT NULL,
            technician_id bigint(20) unsigned DEFAULT NULL,
            from_location_type varchar(50),
            from_location_id bigint(20) unsigned,
            to_location_type varchar(50),
            to_location_id bigint(20) unsigned,
            notes text,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY inventory_item_id (inventory_item_id),
            KEY work_order_id (work_order_id),
            KEY technician_id (technician_id),
            KEY transaction_type (transaction_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_items);
        dbDelta($sql_log);
    }

    /**
     * Get inventory item by ID.
     *
     * @param int $id Item ID.
     * @return object|null
     */
    public static function get_item($id) {
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }

    /**
     * Get inventory item by SKU and location.
     *
     * @param string $sku SKU.
     * @param string $location_type Location type.
     * @param int    $location_id Location ID.
     * @return object|null
     */
    public static function get_item_by_sku($sku, $location_type = 'warehouse', $location_id = 0) {
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE sku = %s AND location_type = %s AND location_id = %d",
            $sku,
            $location_type,
            $location_id
        ));
    }

    /**
     * Get inventory items for a location.
     *
     * @param string $location_type Location type.
     * @param int    $location_id Location ID.
     * @param array  $args Additional args.
     * @return array
     */
    public static function get_location_inventory($location_type, $location_id, $args = []) {
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        $defaults = [
            'status' => '',
            'category' => '',
            'search' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => 100,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = "WHERE location_type = %s AND location_id = %d";
        $params = [$location_type, $location_id];

        if ($args['status']) {
            $where .= " AND status = %s";
            $params[] = $args['status'];
        }

        if ($args['category']) {
            $where .= " AND category = %s";
            $params[] = $args['category'];
        }

        if ($args['search']) {
            $where .= " AND (name LIKE %s OR sku LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $orderby = sanitize_sql_orderby("{$args['orderby']} {$args['order']}");
        $sql = "SELECT * FROM $table $where ORDER BY $orderby LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Create inventory item.
     *
     * @param array $data Item data.
     * @return int|false Item ID or false on failure.
     */
    public static function create_item($data) {
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        $result = $wpdb->insert($table, $data);

        if ($result) {
            FSM_Utilities::clear_cache('inventory');
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Update inventory item.
     *
     * @param int   $id Item ID.
     * @param array $data Data to update.
     * @return bool
     */
    public static function update_item($id, $data) {
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        $result = $wpdb->update($table, $data, ['id' => $id]);

        if ($result !== false) {
            self::check_stock_status($id);
            FSM_Utilities::clear_cache('inventory');
            return true;
        }

        return false;
    }

    /**
     * Adjust inventory quantity.
     *
     * @param int    $id Item ID.
     * @param int    $quantity Quantity change (positive or negative).
     * @param string $type Transaction type.
     * @param array  $meta Additional metadata.
     * @return bool
     */
    public static function adjust_quantity($id, $quantity, $type, $meta = []) {
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        $item = self::get_item($id);
        if (!$item) {
            return false;
        }

        $quantity_before = $item->quantity;
        $quantity_after = $quantity_before + $quantity;

        // Prevent negative inventory
        if ($quantity_after < 0) {
            $quantity_after = 0;
        }

        // Update quantity
        $wpdb->update(
            $table,
            ['quantity' => $quantity_after],
            ['id' => $id]
        );

        // Log transaction
        self::log_transaction([
            'inventory_item_id' => $id,
            'transaction_type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantity_before,
            'quantity_after' => $quantity_after,
            'work_order_id' => $meta['work_order_id'] ?? null,
            'technician_id' => $meta['technician_id'] ?? null,
            'notes' => $meta['notes'] ?? '',
            'user_id' => get_current_user_id(),
        ]);

        self::check_stock_status($id);
        FSM_Utilities::clear_cache('inventory');

        return true;
    }

    /**
     * Check and update stock status.
     *
     * @param int $id Item ID.
     */
    private static function check_stock_status($id) {
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        $item = self::get_item($id);
        if (!$item) {
            return;
        }

        $new_status = 'active';

        if ($item->quantity <= 0) {
            $new_status = 'out_of_stock';
        } elseif ($item->quantity <= $item->reorder_point) {
            $new_status = 'low_stock';
        }

        if ($item->status !== 'discontinued' && $item->status !== $new_status) {
            $wpdb->update($table, ['status' => $new_status], ['id' => $id]);

            // Fire action for low stock alerts
            if ($new_status === 'low_stock' || $new_status === 'out_of_stock') {
                do_action('fsm_inventory_low_stock', $item, $new_status);
            }
        }
    }

    /**
     * Log inventory transaction.
     *
     * @param array $data Transaction data.
     * @return int|false
     */
    public static function log_transaction($data) {
        global $wpdb;
        $table = FSM_Inventory::get_log_table();

        $result = $wpdb->insert($table, $data);

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get transaction history.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_transactions($args = []) {
        global $wpdb;
        $table = FSM_Inventory::get_log_table();

        $defaults = [
            'inventory_item_id' => 0,
            'work_order_id' => 0,
            'technician_id' => 0,
            'type' => '',
            'date_from' => '',
            'date_to' => '',
            'limit' => 50,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = "WHERE 1=1";
        $params = [];

        if ($args['inventory_item_id']) {
            $where .= " AND inventory_item_id = %d";
            $params[] = $args['inventory_item_id'];
        }

        if ($args['work_order_id']) {
            $where .= " AND work_order_id = %d";
            $params[] = $args['work_order_id'];
        }

        if ($args['technician_id']) {
            $where .= " AND technician_id = %d";
            $params[] = $args['technician_id'];
        }

        if ($args['type']) {
            $where .= " AND transaction_type = %s";
            $params[] = $args['type'];
        }

        if ($args['date_from']) {
            $where .= " AND created_at >= %s";
            $params[] = $args['date_from'];
        }

        if ($args['date_to']) {
            $where .= " AND created_at <= %s";
            $params[] = $args['date_to'];
        }

        $sql = "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
}
