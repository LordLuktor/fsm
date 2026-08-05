<?php
/**
 * FSM Work Order Post Type
 *
 * Registers the work order custom post type and meta fields.
 *
 * @package FSM\WorkOrders
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Work Order Post Type class.
 */
class FSM_Work_Order_Post_Type {

    /**
     * Register post type.
     */
    public static function register() {
        $labels = [
            'name' => _x('Work Orders', 'Post type general name', 'fsm-work-orders'),
            'singular_name' => _x('Work Order', 'Post type singular name', 'fsm-work-orders'),
            'menu_name' => _x('Work Orders', 'Admin menu', 'fsm-work-orders'),
            'add_new' => _x('Add New', 'work order', 'fsm-work-orders'),
            'add_new_item' => __('Add New Work Order', 'fsm-work-orders'),
            'edit_item' => __('Edit Work Order', 'fsm-work-orders'),
            'new_item' => __('New Work Order', 'fsm-work-orders'),
            'view_item' => __('View Work Order', 'fsm-work-orders'),
            'search_items' => __('Search Work Orders', 'fsm-work-orders'),
            'not_found' => __('No work orders found', 'fsm-work-orders'),
            'not_found_in_trash' => __('No work orders found in trash', 'fsm-work-orders'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'fsm-dashboard',
            'show_in_rest' => true,
            'rest_base' => 'work-orders',
            'rest_namespace' => 'fsm/v1',
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'edit_post' => 'fsm_edit_work_orders',
                'read_post' => 'fsm_view_work_orders',
                'delete_post' => 'fsm_delete_work_orders',
                'edit_posts' => 'fsm_edit_work_orders',
                'edit_others_posts' => 'fsm_edit_work_orders',
                'publish_posts' => 'fsm_create_work_orders',
                'read_private_posts' => 'fsm_view_work_orders',
            ],
            'map_meta_cap' => false,
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'menu_icon' => 'dashicons-clipboard',
            'supports' => ['title', 'editor', 'custom-fields', 'comments'],
        ];

        register_post_type('fsm_work_order', $args);

        // Register meta fields
        self::register_meta();

        // Register taxonomy for work order types
        self::register_taxonomy();
    }

    /**
     * Register meta fields.
     */
    private static function register_meta() {
        $meta_fields = [
            // Reference number
            'reference' => [
                'type' => 'string',
                'description' => 'Unique work order reference number',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            // Status
            'status' => [
                'type' => 'string',
                'description' => 'Work order status',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'new',
            ],
            // Priority
            'priority' => [
                'type' => 'string',
                'description' => 'Priority level (low, normal, high, urgent)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'normal',
            ],
            // Customer info
            'customer_name' => [
                'type' => 'string',
                'description' => 'Customer name',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer_phone' => [
                'type' => 'string',
                'description' => 'Customer phone',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer_email' => [
                'type' => 'string',
                'description' => 'Customer email',
                'single' => true,
                'sanitize_callback' => 'sanitize_email',
            ],
            // Location
            'address_street' => [
                'type' => 'string',
                'description' => 'Street address',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address_city' => [
                'type' => 'string',
                'description' => 'City',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address_state' => [
                'type' => 'string',
                'description' => 'State',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address_zip' => [
                'type' => 'string',
                'description' => 'ZIP code',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'latitude' => [
                'type' => 'number',
                'description' => 'Location latitude',
                'single' => true,
                'sanitize_callback' => [__CLASS__, 'sanitize_float'],
            ],
            'longitude' => [
                'type' => 'number',
                'description' => 'Location longitude',
                'single' => true,
                'sanitize_callback' => [__CLASS__, 'sanitize_float'],
            ],
            // Scheduling
            'scheduled_date' => [
                'type' => 'string',
                'description' => 'Scheduled date (YYYY-MM-DD)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'scheduled_time_start' => [
                'type' => 'string',
                'description' => 'Scheduled start time (HH:MM)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'scheduled_time_end' => [
                'type' => 'string',
                'description' => 'Scheduled end time (HH:MM)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'estimated_duration' => [
                'type' => 'integer',
                'description' => 'Estimated duration in minutes',
                'single' => true,
                'sanitize_callback' => 'absint',
                'default' => 60,
            ],
            // Assignment
            'assigned_technicians' => [
                'type' => 'array',
                'description' => 'Array of assigned technician IDs',
                'single' => true,
                'sanitize_callback' => [__CLASS__, 'sanitize_int_array'],
                'default' => [],
            ],
            'primary_technician_id' => [
                'type' => 'integer',
                'description' => 'Primary assigned technician ID',
                'single' => true,
                'sanitize_callback' => 'absint',
            ],
            // Timestamps
            'started_at' => [
                'type' => 'string',
                'description' => 'When work started',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'completed_at' => [
                'type' => 'string',
                'description' => 'When work completed',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            // Signature
            'customer_signature' => [
                'type' => 'string',
                'description' => 'Customer signature data URL',
                'single' => true,
                'sanitize_callback' => 'esc_url_raw',
            ],
            // Internal notes (not visible to customer)
            'internal_notes' => [
                'type' => 'string',
                'description' => 'Internal notes',
                'single' => true,
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta('fsm_work_order', "_fsm_wo_{$key}", array_merge($args, [
                'show_in_rest' => [
                    'schema' => [
                        'type' => $args['type'],
                        'default' => $args['default'] ?? null,
                    ],
                ],
                'auth_callback' => function() {
                    return current_user_can('fsm_view_work_orders') || current_user_can('fsm_view_own_work_orders');
                },
            ]));
        }
    }

    /**
     * Register taxonomy.
     */
    private static function register_taxonomy() {
        register_taxonomy('fsm_work_order_type', ['fsm_work_order'], [
            'labels' => [
                'name' => _x('Work Order Types', 'taxonomy general name', 'fsm-work-orders'),
                'singular_name' => _x('Work Order Type', 'taxonomy singular name', 'fsm-work-orders'),
                'search_items' => __('Search Types', 'fsm-work-orders'),
                'all_items' => __('All Types', 'fsm-work-orders'),
                'edit_item' => __('Edit Type', 'fsm-work-orders'),
                'update_item' => __('Update Type', 'fsm-work-orders'),
                'add_new_item' => __('Add New Type', 'fsm-work-orders'),
                'new_item_name' => __('New Type Name', 'fsm-work-orders'),
                'menu_name' => __('Types', 'fsm-work-orders'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'rest_base' => 'work-order-types',
            'rest_namespace' => 'fsm/v1',
            'hierarchical' => true,
            'show_admin_column' => true,
            'rewrite' => false,
        ]);

        // Register term meta for type configuration.
        register_term_meta('fsm_work_order_type', '_fsm_wot_default_duration', [
            'type'              => 'integer',
            'description'       => 'Default estimated duration in minutes',
            'single'            => true,
            'default'           => 60,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);

        register_term_meta('fsm_work_order_type', '_fsm_wot_color', [
            'type'              => 'string',
            'description'       => 'Hex color for the type badge',
            'single'            => true,
            'default'           => '#6b7280',
            'sanitize_callback' => 'sanitize_hex_color',
            'show_in_rest'      => true,
        ]);

        register_term_meta('fsm_work_order_type', '_fsm_wot_icon', [
            'type'              => 'string',
            'description'       => 'Icon identifier for the type',
            'single'            => true,
            'default'           => 'wrench',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);
    }

    /**
     * Sanitize integer array.
     *
     * @param mixed $value Value to sanitize.
     * @return array
     */
    public static function sanitize_int_array($value) {
        if (!is_array($value)) {
            return [];
        }
        return array_map('absint', $value);
    }

    /**
     * Sanitize float value.
     * Wrapper for floatval that accepts WordPress's 4-argument sanitize callback signature.
     *
     * @param mixed $value Value to sanitize.
     * @return float
     */
    public static function sanitize_float($value) {
        return floatval($value);
    }
}
