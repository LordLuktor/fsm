<?php
/**
 * FSM Inventory REST API
 *
 * REST API endpoints for inventory management.
 *
 * @package FSM\Inventory
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Inventory REST API class.
 */
class FSM_Inventory_REST_API {

    /**
     * Namespace.
     */
    const NAMESPACE = 'fsm/v1';

    /**
     * Register routes.
     */
    public static function register_routes() {
        // Global inventory
        register_rest_route(self::NAMESPACE, '/inventory', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_inventory'],
                'permission_callback' => function() {
                    return current_user_can('fsm_view_inventory');
                },
                'args' => self::get_collection_params(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'create_item'],
                'permission_callback' => function() {
                    return current_user_can('fsm_manage_inventory');
                },
            ],
        ]);

        // Single item
        register_rest_route(self::NAMESPACE, '/inventory/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_item'],
                'permission_callback' => function() {
                    return current_user_can('fsm_view_inventory');
                },
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [__CLASS__, 'update_item'],
                'permission_callback' => function() {
                    return current_user_can('fsm_manage_inventory');
                },
            ],
        ]);

        // Technician inventory
        register_rest_route(self::NAMESPACE, '/inventory/technician/(?P<technician_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_technician_inventory'],
            'permission_callback' => function($request) {
                if (current_user_can('fsm_view_inventory')) {
                    return true;
                }
                // Technicians can view their own
                if (current_user_can('fsm_view_own_inventory')) {
                    $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());
                    return $tech && (int) $tech->ID === (int) $request->get_param('technician_id');
                }
                return false;
            },
        ]);

        // My inventory (for technicians)
        register_rest_route(self::NAMESPACE, '/inventory/mine', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_my_inventory'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_own_inventory');
            },
        ]);

        // Vehicle inventory
        register_rest_route(self::NAMESPACE, '/inventory/vehicle/(?P<vehicle_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_vehicle_inventory'],
            'permission_callback' => function($request) {
                if (current_user_can('fsm_view_inventory')) {
                    return true;
                }
                // Technicians can view their assigned vehicle's inventory
                if (current_user_can('fsm_view_own_inventory')) {
                    $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());
                    if ($tech) {
                        $assigned_vehicle = get_post_meta($tech->ID, '_fsm_assigned_vehicle_id', true);
                        return (int) $assigned_vehicle === (int) $request->get_param('vehicle_id');
                    }
                }
                return false;
            },
        ]);

        // My vehicle inventory (for technicians)
        register_rest_route(self::NAMESPACE, '/inventory/my-vehicle', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_my_vehicle_inventory'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_own_inventory');
            },
        ]);

        // All vehicles with inventory summary
        register_rest_route(self::NAMESPACE, '/inventory/vehicles', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_all_vehicles_inventory'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_inventory');
            },
        ]);

        // Consume inventory
        register_rest_route(self::NAMESPACE, '/inventory/consume', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'consume_inventory'],
            'permission_callback' => function() {
                return current_user_can('fsm_consume_inventory');
            },
            'args' => [
                'item_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'quantity' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'work_order_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Transfer inventory
        register_rest_route(self::NAMESPACE, '/inventory/transfer', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'transfer_inventory'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_inventory');
            },
        ]);

        // Transaction history
        register_rest_route(self::NAMESPACE, '/inventory/transactions', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_transactions'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_inventory');
            },
        ]);

        // Low stock items
        register_rest_route(self::NAMESPACE, '/inventory/low-stock', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_low_stock'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_inventory');
            },
        ]);
    }

    /**
     * Get collection parameters.
     */
    private static function get_collection_params() {
        return [
            'location_type' => [
                'default' => 'warehouse',
                'enum' => ['warehouse', 'technician', 'vehicle'],
            ],
            'location_id' => [
                'default' => 0,
                'sanitize_callback' => 'absint',
            ],
            'category' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'enum' => ['active', 'discontinued', 'low_stock', 'out_of_stock'],
            ],
            'search' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'per_page' => [
                'default' => 50,
                'sanitize_callback' => 'absint',
            ],
            'page' => [
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Get inventory.
     */
    public static function get_inventory($request) {
        $items = FSM_Inventory_DB::get_location_inventory(
            $request->get_param('location_type'),
            $request->get_param('location_id'),
            [
                'status' => $request->get_param('status'),
                'category' => $request->get_param('category'),
                'search' => $request->get_param('search'),
                'limit' => $request->get_param('per_page'),
                'offset' => ($request->get_param('page') - 1) * $request->get_param('per_page'),
            ]
        );

        return rest_ensure_response(array_map([__CLASS__, 'prepare_item'], $items));
    }

    /**
     * Get single item.
     */
    public static function get_item($request) {
        $item = FSM_Inventory_DB::get_item($request->get_param('id'));

        if (!$item) {
            return FSM_REST_API::error('not_found', __('Item not found.', 'fsm-inventory'), 404);
        }

        $access = FSM_Organizations::verify_inventory_access($item);
        if (is_wp_error($access)) {
            return $access;
        }

        return rest_ensure_response(self::prepare_item($item));
    }

    /**
     * Create item.
     */
    public static function create_item($request) {
        $data = [
            'sku' => $request->get_param('sku'),
            'name' => $request->get_param('name'),
            'description' => $request->get_param('description'),
            'category' => $request->get_param('category'),
            'unit' => $request->get_param('unit') ?: 'each',
            'unit_cost' => $request->get_param('unit_cost') ?: 0,
            'retail_price' => $request->get_param('retail_price'),
            'reorder_point' => $request->get_param('reorder_point') ?: 0,
            'location_type' => $request->get_param('location_type') ?: 'warehouse',
            'location_id' => $request->get_param('location_id') ?: 0,
            'quantity' => $request->get_param('quantity') ?: 0,
        ];

        $id = FSM_Inventory_DB::create_item($data);

        if (!$id) {
            return FSM_REST_API::error('create_failed', __('Failed to create item.', 'fsm-inventory'), 500);
        }

        // Log initial inventory if quantity > 0
        if ($data['quantity'] > 0) {
            FSM_Inventory_DB::log_transaction([
                'inventory_item_id' => $id,
                'transaction_type' => 'receive',
                'quantity' => $data['quantity'],
                'quantity_before' => 0,
                'quantity_after' => $data['quantity'],
                'notes' => 'Initial inventory',
                'user_id' => get_current_user_id(),
            ]);
        }

        $item = FSM_Inventory_DB::get_item($id);
        return rest_ensure_response(self::prepare_item($item));
    }

    /**
     * Update item.
     */
    public static function update_item($request) {
        $id = $request->get_param('id');
        $item = FSM_Inventory_DB::get_item($id);

        if (!$item) {
            return FSM_REST_API::error('not_found', __('Item not found.', 'fsm-inventory'), 404);
        }

        $data = [];
        $fields = ['name', 'description', 'category', 'unit', 'unit_cost', 'retail_price', 'reorder_point', 'status'];

        foreach ($fields as $field) {
            $value = $request->get_param($field);
            if (null !== $value) {
                $data[$field] = $value;
            }
        }

        if (!empty($data)) {
            FSM_Inventory_DB::update_item($id, $data);
        }

        $item = FSM_Inventory_DB::get_item($id);
        return rest_ensure_response(self::prepare_item($item));
    }

    /**
     * Get technician inventory.
     */
    public static function get_technician_inventory($request) {
        $technician_id = $request->get_param('technician_id');

        $items = FSM_Inventory_DB::get_location_inventory('technician', $technician_id);

        return rest_ensure_response(array_map([__CLASS__, 'prepare_item'], $items));
    }

    /**
     * Get my inventory.
     */
    public static function get_my_inventory($request) {
        $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());

        if (!$tech) {
            return rest_ensure_response([]);
        }

        $items = FSM_Inventory_DB::get_location_inventory('technician', $tech->ID);

        return rest_ensure_response(array_map([__CLASS__, 'prepare_item'], $items));
    }

    /**
     * Get vehicle inventory.
     */
    public static function get_vehicle_inventory($request) {
        $vehicle_id = $request->get_param('vehicle_id');

        // Get vehicle details
        $vehicle = get_post($vehicle_id);
        if (!$vehicle || $vehicle->post_type !== 'fsm_vehicle') {
            return FSM_REST_API::error('not_found', __('Vehicle not found.', 'fsm-inventory'), 404);
        }

        $items = FSM_Inventory_DB::get_location_inventory('vehicle', $vehicle_id);

        // Get assigned technician if any
        $assigned_technician = null;
        $technicians = get_posts([
            'post_type' => 'fsm_technician',
            'posts_per_page' => 1,
            'meta_query' => [
                ['key' => '_fsm_assigned_vehicle_id', 'value' => $vehicle_id],
            ],
        ]);
        if (!empty($technicians)) {
            $tech = $technicians[0];
            $assigned_technician = [
                'id' => $tech->ID,
                'name' => $tech->post_title,
            ];
        }

        return rest_ensure_response([
            'vehicle' => [
                'id' => $vehicle->ID,
                'name' => $vehicle->post_title,
                'type' => get_post_meta($vehicle_id, '_fsm_vehicle_type', true),
                'make' => get_post_meta($vehicle_id, '_fsm_vehicle_make', true),
                'model' => get_post_meta($vehicle_id, '_fsm_vehicle_model', true),
                'year' => get_post_meta($vehicle_id, '_fsm_vehicle_year', true),
                'plate' => get_post_meta($vehicle_id, '_fsm_vehicle_plate', true),
                'status' => get_post_meta($vehicle_id, '_fsm_status', true),
                'assigned_technician' => $assigned_technician,
            ],
            'items' => array_map([__CLASS__, 'prepare_item'], $items),
            'summary' => [
                'total_items' => count($items),
                'total_value' => array_reduce($items, function($carry, $item) {
                    return $carry + ($item->quantity * $item->unit_cost);
                }, 0),
            ],
        ]);
    }

    /**
     * Get my vehicle inventory (for technicians).
     */
    public static function get_my_vehicle_inventory($request) {
        $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());

        if (!$tech) {
            return FSM_REST_API::error('not_found', __('Technician profile not found.', 'fsm-inventory'), 404);
        }

        $vehicle_id = get_post_meta($tech->ID, '_fsm_assigned_vehicle_id', true);

        if (!$vehicle_id) {
            return rest_ensure_response([
                'vehicle' => null,
                'items' => [],
                'summary' => ['total_items' => 0, 'total_value' => 0],
            ]);
        }

        // Use the vehicle inventory endpoint
        $request->set_param('vehicle_id', $vehicle_id);
        return self::get_vehicle_inventory($request);
    }

    /**
     * Get all vehicles with inventory summary.
     */
    public static function get_all_vehicles_inventory($request) {
        global $wpdb;

        // Get organization context
        $org_id = FSM_Organizations::get_current_context();

        // Get all vehicles
        $args = [
            'post_type' => 'fsm_vehicle',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        if ($org_id) {
            $args['meta_query'] = [
                ['key' => '_fsm_organization_id', 'value' => $org_id],
            ];
        }

        $vehicles = get_posts($args);
        $result = [];

        $items_table = FSM_Inventory::get_items_table();

        foreach ($vehicles as $vehicle) {
            // Get inventory summary for this vehicle
            $inventory_summary = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) as item_count,
                    COALESCE(SUM(quantity), 0) as total_quantity,
                    COALESCE(SUM(quantity * unit_cost), 0) as total_value
                FROM $items_table
                WHERE location_type = 'vehicle' AND location_id = %d",
                $vehicle->ID
            ));

            // Get assigned technician
            $assigned_technician = null;
            $technicians = get_posts([
                'post_type' => 'fsm_technician',
                'posts_per_page' => 1,
                'meta_query' => [
                    ['key' => '_fsm_assigned_vehicle_id', 'value' => $vehicle->ID],
                ],
            ]);
            if (!empty($technicians)) {
                $tech = $technicians[0];
                $assigned_technician = [
                    'id' => $tech->ID,
                    'name' => $tech->post_title,
                ];
            }

            $result[] = [
                'id' => $vehicle->ID,
                'name' => $vehicle->post_title,
                'type' => get_post_meta($vehicle->ID, '_fsm_vehicle_type', true),
                'make' => get_post_meta($vehicle->ID, '_fsm_vehicle_make', true),
                'model' => get_post_meta($vehicle->ID, '_fsm_vehicle_model', true),
                'year' => get_post_meta($vehicle->ID, '_fsm_vehicle_year', true),
                'plate' => get_post_meta($vehicle->ID, '_fsm_vehicle_plate', true),
                'status' => get_post_meta($vehicle->ID, '_fsm_status', true),
                'assigned_technician' => $assigned_technician,
                'inventory' => [
                    'item_count' => (int) $inventory_summary->item_count,
                    'total_quantity' => (int) $inventory_summary->total_quantity,
                    'total_value' => (float) $inventory_summary->total_value,
                ],
            ];
        }

        return rest_ensure_response($result);
    }

    /**
     * Consume inventory.
     */
    public static function consume_inventory($request) {
        $item_id = $request->get_param('item_id');
        $quantity = $request->get_param('quantity');
        $work_order_id = $request->get_param('work_order_id');
        $notes = $request->get_param('notes');

        $item = FSM_Inventory_DB::get_item($item_id);

        if (!$item) {
            return FSM_REST_API::error('not_found', __('Item not found.', 'fsm-inventory'), 404);
        }

        $access = FSM_Organizations::verify_inventory_access($item);
        if (is_wp_error($access)) {
            return $access;
        }

        // Check available quantity
        $available = $item->quantity - $item->reserved_quantity;
        if ($quantity > $available) {
            return FSM_REST_API::error(
                'insufficient_quantity',
                sprintf(__('Insufficient quantity. Available: %d', 'fsm-inventory'), $available),
                400
            );
        }

        // Get technician ID if applicable
        $technician_id = null;
        if ($item->location_type === 'technician') {
            $technician_id = $item->location_id;
        }

        // Adjust quantity (negative for consumption)
        $result = FSM_Inventory_DB::adjust_quantity($item_id, -$quantity, 'consume', [
            'work_order_id' => $work_order_id,
            'technician_id' => $technician_id,
            'notes' => $notes,
        ]);

        if (!$result) {
            return FSM_REST_API::error('consume_failed', __('Failed to consume inventory.', 'fsm-inventory'), 500);
        }

        FSM_Utilities::log_activity('inventory_consumed', [
            'item_id' => $item_id,
            'quantity' => $quantity,
            'work_order_id' => $work_order_id,
        ]);

        $item = FSM_Inventory_DB::get_item($item_id);
        return rest_ensure_response(self::prepare_item($item));
    }

    /**
     * Transfer inventory.
     */
    public static function transfer_inventory($request) {
        $item_id = $request->get_param('item_id');
        $quantity = $request->get_param('quantity');
        $to_location_type = $request->get_param('to_location_type');
        $to_location_id = $request->get_param('to_location_id');

        $source_item = FSM_Inventory_DB::get_item($item_id);

        if (!$source_item) {
            return FSM_REST_API::error('not_found', __('Source item not found.', 'fsm-inventory'), 404);
        }

        $access = FSM_Organizations::verify_inventory_access($source_item);
        if (is_wp_error($access)) {
            return $access;
        }

        if ($quantity > $source_item->quantity) {
            return FSM_REST_API::error(
                'insufficient_quantity',
                __('Insufficient quantity for transfer.', 'fsm-inventory'),
                400
            );
        }

        // Check if destination item exists
        $dest_item = FSM_Inventory_DB::get_item_by_sku(
            $source_item->sku,
            $to_location_type,
            $to_location_id
        );

        if (!$dest_item) {
            // Create item at destination
            $dest_id = FSM_Inventory_DB::create_item([
                'sku' => $source_item->sku,
                'name' => $source_item->name,
                'description' => $source_item->description,
                'category' => $source_item->category,
                'unit' => $source_item->unit,
                'unit_cost' => $source_item->unit_cost,
                'reorder_point' => $source_item->reorder_point,
                'location_type' => $to_location_type,
                'location_id' => $to_location_id,
                'quantity' => 0,
            ]);
            $dest_item = FSM_Inventory_DB::get_item($dest_id);
        }

        // Reduce source
        FSM_Inventory_DB::adjust_quantity($item_id, -$quantity, 'transfer', [
            'notes' => sprintf('Transferred to %s:%d', $to_location_type, $to_location_id),
        ]);

        // Increase destination
        FSM_Inventory_DB::adjust_quantity($dest_item->id, $quantity, 'transfer', [
            'notes' => sprintf('Received from %s:%d', $source_item->location_type, $source_item->location_id),
        ]);

        FSM_Utilities::log_activity('inventory_transferred', [
            'source_item_id' => $item_id,
            'dest_item_id' => $dest_item->id,
            'quantity' => $quantity,
        ]);

        return rest_ensure_response([
            'success' => true,
            'source' => self::prepare_item(FSM_Inventory_DB::get_item($item_id)),
            'destination' => self::prepare_item(FSM_Inventory_DB::get_item($dest_item->id)),
        ]);
    }

    /**
     * Get transactions.
     */
    public static function get_transactions($request) {
        $org_id = FSM_Organizations::get_current_context();

        $transactions = FSM_Inventory_DB::get_transactions([
            'inventory_item_id' => $request->get_param('item_id'),
            'work_order_id' => $request->get_param('work_order_id'),
            'technician_id' => $request->get_param('technician_id'),
            'organization_id' => $org_id ?: 0,
            'type' => $request->get_param('type'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
            'limit' => $request->get_param('per_page') ?: 50,
            'offset' => (($request->get_param('page') ?: 1) - 1) * ($request->get_param('per_page') ?: 50),
        ]);

        return rest_ensure_response($transactions);
    }

    /**
     * Get low stock items.
     */
    public static function get_low_stock($request) {
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        $org_id = FSM_Organizations::get_current_context();
        if ($org_id) {
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE status IN ('low_stock', 'out_of_stock') AND organization_id = %d ORDER BY status DESC, name ASC",
                $org_id
            ));
        } elseif (FSM_Organizations::is_super_admin()) {
            $items = $wpdb->get_results(
                "SELECT * FROM $table WHERE status IN ('low_stock', 'out_of_stock') ORDER BY status DESC, name ASC"
            );
        } else {
            $items = [];
        }

        return rest_ensure_response(array_map([__CLASS__, 'prepare_item'], $items));
    }

    /**
     * Prepare item for response.
     */
    private static function prepare_item($item) {
        return [
            'id' => (int) $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'description' => $item->description,
            'category' => $item->category,
            'unit' => $item->unit,
            'unit_cost' => (float) $item->unit_cost,
            'retail_price' => null !== $item->retail_price ? (float) $item->retail_price : null,
            'reorder_point' => (int) $item->reorder_point,
            'location_type' => $item->location_type,
            'location_id' => (int) $item->location_id,
            'quantity' => (int) $item->quantity,
            'reserved_quantity' => (int) $item->reserved_quantity,
            'available_quantity' => (int) ($item->quantity - $item->reserved_quantity),
            'status' => $item->status,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
