<?php
/**
 * FSM Work Order REST API
 *
 * REST API endpoints for work order management.
 *
 * @package FSM\WorkOrders
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Work Order REST API class.
 */
class FSM_Work_Order_REST_API {

    /**
     * Namespace.
     */
    const NAMESPACE = 'fsm/v1';

    /**
     * Register routes.
     */
    public static function register_routes() {
        // Get work orders (with filtering)
        register_rest_route(self::NAMESPACE, '/work-orders', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_work_orders'],
                'permission_callback' => [__CLASS__, 'can_view_work_orders'],
                'args' => self::get_collection_params(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'create_work_order'],
                'permission_callback' => [__CLASS__, 'can_create_work_orders'],
                'args' => self::get_create_params(),
            ],
        ]);

        // Single work order
        register_rest_route(self::NAMESPACE, '/work-orders/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_work_order'],
                'permission_callback' => [__CLASS__, 'can_view_work_order'],
                'args' => [
                    'id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [__CLASS__, 'update_work_order'],
                'permission_callback' => [__CLASS__, 'can_edit_work_order'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [__CLASS__, 'delete_work_order'],
                'permission_callback' => [__CLASS__, 'can_delete_work_orders'],
            ],
        ]);

        // Work order status update
        register_rest_route(self::NAMESPACE, '/work-orders/(?P<id>\d+)/status', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [__CLASS__, 'update_status'],
            'permission_callback' => [__CLASS__, 'can_update_status'],
            'args' => [
                'status' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => array_keys(FSM_Work_Orders::get_statuses()),
                ],
            ],
        ]);

        // Work order notes
        register_rest_route(self::NAMESPACE, '/work-orders/(?P<id>\d+)/notes', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'get_notes'],
                'permission_callback' => [__CLASS__, 'can_view_work_order'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'add_note'],
                'permission_callback' => [__CLASS__, 'can_add_note'],
                'args' => [
                    'content' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                    'type' => [
                        'type' => 'string',
                        'default' => 'note',
                        'enum' => ['note', 'status_change', 'photo', 'system'],
                    ],
                ],
            ],
        ]);

        // My work orders (for technicians)
        register_rest_route(self::NAMESPACE, '/work-orders/mine', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_my_work_orders'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_own_work_orders');
            },
            'args' => self::get_collection_params(),
        ]);

        // Work order types (enriched with term meta)
        register_rest_route(self::NAMESPACE, '/work-order-types/configured', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_configured_types'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);

        // Create work order type
        register_rest_route(self::NAMESPACE, '/work-order-types', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'create_work_order_type'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_settings') || current_user_can('manage_options');
            },
            'args' => [
                'name' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'default_duration' => ['type' => 'integer', 'default' => 60, 'sanitize_callback' => 'absint'],
                'color' => ['type' => 'string', 'default' => '#6b7280', 'sanitize_callback' => 'sanitize_hex_color'],
                'icon' => ['type' => 'string', 'default' => 'wrench', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Update work order type
        register_rest_route(self::NAMESPACE, '/work-order-types/(?P<type_id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [__CLASS__, 'update_work_order_type'],
                'permission_callback' => function() {
                    return current_user_can('fsm_manage_settings') || current_user_can('manage_options');
                },
                'args' => [
                    'name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'default_duration' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
                    'color' => ['type' => 'string', 'sanitize_callback' => 'sanitize_hex_color'],
                    'icon' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [__CLASS__, 'delete_work_order_type'],
                'permission_callback' => function() {
                    return current_user_can('fsm_manage_settings') || current_user_can('manage_options');
                },
            ],
        ]);

        // Today's work orders
        register_rest_route(self::NAMESPACE, '/work-orders/today', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_todays_work_orders'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_own_work_orders') || current_user_can('fsm_view_work_orders');
            },
        ]);
    }

    /**
     * Get collection parameters.
     *
     * @return array
     */
    private static function get_collection_params() {
        return [
            'page' => [
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default' => 20,
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'type' => 'string',
            ],
            'technician_id' => [
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'date' => [
                'type' => 'string',
            ],
            'date_from' => [
                'type' => 'string',
            ],
            'date_to' => [
                'type' => 'string',
            ],
            'search' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'has_media' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'orderby' => [
                'default' => 'date',
                'enum' => ['date', 'title', 'status', 'scheduled_date'],
            ],
            'order' => [
                'default' => 'DESC',
                'enum' => ['ASC', 'DESC'],
            ],
        ];
    }

    /**
     * Get create parameters.
     *
     * @return array
     */
    private static function get_create_params() {
        return [
            'title' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'type' => 'string',
            ],
            'customer_name' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer_phone' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer_email' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
            ],
            'address_street' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address_city' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address_state' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'address_zip' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'scheduled_date' => [
                'type' => 'string',
            ],
            'priority' => [
                'type' => 'string',
                'default' => 'normal',
                'enum' => ['low', 'normal', 'high', 'urgent'],
            ],
            'work_order_types' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'default' => [],
                'sanitize_callback' => function($value) {
                    return array_map('absint', (array) $value);
                },
            ],
            'estimated_duration' => [
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'scheduled_time_start' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'scheduled_time_end' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Permission check: can view work orders.
     */
    public static function can_view_work_orders() {
        return current_user_can('fsm_view_work_orders');
    }

    /**
     * Permission check: can view single work order.
     */
    public static function can_view_work_order($request) {
        $work_order_id = (int) $request->get_param('id');

        // Dispatchers/admins can view all
        if (current_user_can('fsm_view_work_orders')) {
            return true;
        }

        // Technicians can view their assigned work orders
        if (current_user_can('fsm_view_own_work_orders')) {
            return self::is_assigned_to_work_order(get_current_user_id(), $work_order_id);
        }

        return false;
    }

    /**
     * Permission check: can create work orders.
     */
    public static function can_create_work_orders() {
        return current_user_can('fsm_create_work_orders');
    }

    /**
     * Permission check: can edit work order.
     */
    public static function can_edit_work_order($request) {
        $work_order_id = (int) $request->get_param('id');

        // Dispatchers/admins can edit all
        if (current_user_can('fsm_edit_work_orders')) {
            return true;
        }

        // Technicians can only update certain fields on their assigned work orders
        if (current_user_can('fsm_view_own_work_orders')) {
            return self::is_assigned_to_work_order(get_current_user_id(), $work_order_id);
        }

        return false;
    }

    /**
     * Permission check: can delete work orders.
     */
    public static function can_delete_work_orders() {
        return current_user_can('fsm_delete_work_orders');
    }

    /**
     * Permission check: can update status.
     */
    public static function can_update_status($request) {
        return self::can_edit_work_order($request);
    }

    /**
     * Permission check: can add note.
     */
    public static function can_add_note($request) {
        return self::can_view_work_order($request);
    }

    /**
     * Check if user is assigned to work order.
     */
    private static function is_assigned_to_work_order($user_id, $work_order_id) {
        $technician = FSM_Utilities::get_technician_by_user_id($user_id);
        if (!$technician) {
            return false;
        }

        $assigned = get_post_meta($work_order_id, '_fsm_wo_assigned_technicians', true);
        if (!is_array($assigned)) {
            $assigned = [];
        }

        return in_array($technician->ID, $assigned, true);
    }

    /**
     * Get work orders.
     */
    public static function get_work_orders($request) {
        $args = [
            'post_type' => 'fsm_work_order',
            'posts_per_page' => $request->get_param('per_page'),
            'paged' => $request->get_param('page'),
            'orderby' => $request->get_param('orderby') === 'scheduled_date' ? 'meta_value' : $request->get_param('orderby'),
            'order' => $request->get_param('order'),
            'post_status' => 'publish',
        ];

        // Meta query conditions
        $meta_query = [];

        // Filter by status
        if ($status = $request->get_param('status')) {
            $meta_query[] = [
                'key' => '_fsm_wo_status',
                'value' => $status,
            ];
        }

        // Filter by technician
        if ($technician_id = $request->get_param('technician_id')) {
            $meta_query[] = [
                'key' => '_fsm_wo_assigned_technicians',
                'value' => $technician_id,
                'compare' => 'LIKE',
            ];
        }

        // Filter by date
        if ($date = $request->get_param('date')) {
            $meta_query[] = [
                'key' => '_fsm_wo_scheduled_date',
                'value' => $date,
            ];
        }

        // Filter by date range
        if ($date_from = $request->get_param('date_from')) {
            $meta_query[] = [
                'key' => '_fsm_wo_scheduled_date',
                'value' => $date_from,
                'compare' => '>=',
            ];
        }

        if ($date_to = $request->get_param('date_to')) {
            $meta_query[] = [
                'key' => '_fsm_wo_scheduled_date',
                'value' => $date_to,
                'compare' => '<=',
            ];
        }

        // Extended search: match title, content, reference, customer name/phone,
        // and address fields. We resolve matching post IDs ourselves and pass
        // them through post__in so WP_Query's default 's' (title/content only)
        // doesn't miss meta fields.
        $search_matched_ids = null;
        if ($search = $request->get_param('search')) {
            global $wpdb;
            $search_like = '%' . $wpdb->esc_like($search) . '%';

            $title_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'fsm_work_order'
                   AND post_status = 'publish'
                   AND (post_title LIKE %s OR post_content LIKE %s)",
                $search_like,
                $search_like
            ));

            $meta_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key IN (
                    '_fsm_wo_reference',
                    '_fsm_wo_customer_name',
                    '_fsm_wo_customer_phone',
                    '_fsm_wo_customer_email',
                    '_fsm_wo_address_street',
                    '_fsm_wo_address_city',
                    '_fsm_wo_address_zip'
                 )
                 AND meta_value LIKE %s",
                $search_like
            ));

            $search_matched_ids = array_unique(array_map('intval', array_merge($title_ids, $meta_ids)));
            if (empty($search_matched_ids)) {
                // No matches — force empty result
                $search_matched_ids = [0];
            }
        }

        // Filter: only work orders with uploaded media
        $media_ids = null;
        if ($request->get_param('has_media') && class_exists('FSM_Work_Order_Media_DB')) {
            $media_ids = FSM_Work_Order_Media_DB::get_work_order_ids_with_media();
            if (empty($media_ids)) {
                $response = rest_ensure_response([]);
                $response->header('X-WP-Total', 0);
                $response->header('X-WP-TotalPages', 0);
                return $response;
            }
        }

        // Combine search + media filters (intersect if both present)
        if ($search_matched_ids !== null && $media_ids !== null) {
            $combined = array_values(array_intersect($search_matched_ids, $media_ids));
            $args['post__in'] = empty($combined) ? [0] : $combined;
        } elseif ($search_matched_ids !== null) {
            $args['post__in'] = $search_matched_ids;
        } elseif ($media_ids !== null) {
            $args['post__in'] = $media_ids;
        }

        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        // Order by scheduled date
        if ($request->get_param('orderby') === 'scheduled_date') {
            $args['meta_key'] = '_fsm_wo_scheduled_date';
        }

        $query = new WP_Query($args);
        $work_orders = [];

        foreach ($query->posts as $post) {
            $work_orders[] = self::prepare_work_order($post);
        }

        $response = rest_ensure_response($work_orders);
        $response->header('X-WP-Total', $query->found_posts);
        $response->header('X-WP-TotalPages', $query->max_num_pages);

        return $response;
    }

    /**
     * Get single work order.
     */
    public static function get_work_order($request) {
        $work_order = get_post($request->get_param('id'));

        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-work-orders'), 404);
        }

        $access = FSM_Organizations::verify_post_access($work_order->ID);
        if (is_wp_error($access)) {
            return $access;
        }

        return rest_ensure_response(self::prepare_work_order($work_order, true));
    }

    /**
     * Create work order.
     */
    public static function create_work_order($request) {
        // Generate reference number
        $settings = get_option('fsm_settings', []);
        $prefix = $settings['wo_prefix'] ?? 'WO';
        $reference = FSM_Utilities::generate_reference($prefix);

        $post_data = [
            'post_type' => 'fsm_work_order',
            'post_title' => $request->get_param('title'),
            'post_content' => $request->get_param('description') ?? '',
            'post_status' => 'publish',
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set meta fields
        update_post_meta($post_id, '_fsm_wo_reference', $reference);
        update_post_meta($post_id, '_fsm_wo_status', 'new');
        update_post_meta($post_id, '_fsm_wo_priority', $request->get_param('priority') ?? 'normal');

        // Customer info
        $customer_fields = ['customer_name', 'customer_phone', 'customer_email'];
        foreach ($customer_fields as $field) {
            if ($value = $request->get_param($field)) {
                update_post_meta($post_id, "_fsm_wo_{$field}", $value);
            }
        }

        // Address
        $address_fields = ['address_street', 'address_city', 'address_state', 'address_zip'];
        foreach ($address_fields as $field) {
            if ($value = $request->get_param($field)) {
                update_post_meta($post_id, "_fsm_wo_{$field}", $value);
            }
        }

        // Scheduling
        if ($scheduled_date = $request->get_param('scheduled_date')) {
            update_post_meta($post_id, '_fsm_wo_scheduled_date', $scheduled_date);
        }

        // Scheduling fields
        foreach (['scheduled_time_start', 'scheduled_time_end'] as $field) {
            if ($value = $request->get_param($field)) {
                update_post_meta($post_id, "_fsm_wo_{$field}", sanitize_text_field($value));
            }
        }
        if ($duration = $request->get_param('estimated_duration')) {
            update_post_meta($post_id, '_fsm_wo_estimated_duration', absint($duration));
        }

        // Work order types (taxonomy terms)
        $type_ids = $request->get_param('work_order_types');
        if (!empty($type_ids)) {
            // Validate each term exists in the taxonomy
            $valid_ids = [];
            foreach ($type_ids as $term_id) {
                if (term_exists((int) $term_id, 'fsm_work_order_type')) {
                    $valid_ids[] = (int) $term_id;
                }
            }
            if (!empty($valid_ids)) {
                wp_set_object_terms($post_id, $valid_ids, 'fsm_work_order_type');
            }
        }

        // Initialize empty arrays
        update_post_meta($post_id, '_fsm_wo_assigned_technicians', []);

        // Log creation
        FSM_Utilities::log_activity('work_order_created', [
            'work_order_id' => $post_id,
            'reference' => $reference,
        ]);

        // Clear cache
        FSM_Utilities::clear_cache('work_orders');

        $work_order = get_post($post_id);
        return rest_ensure_response(self::prepare_work_order($work_order, true));
    }

    /**
     * Update work order.
     */
    public static function update_work_order($request) {
        $work_order_id = (int) $request->get_param('id');
        $work_order = get_post($work_order_id);

        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-work-orders'), 404);
        }

        $access = FSM_Organizations::verify_post_access($work_order_id);
        if (is_wp_error($access)) {
            return $access;
        }

        // Update post data
        $post_data = ['ID' => $work_order_id];

        if ($title = $request->get_param('title')) {
            $post_data['post_title'] = $title;
        }

        if ($description = $request->get_param('description')) {
            $post_data['post_content'] = $description;
        }

        if (count($post_data) > 1) {
            wp_update_post($post_data);
        }

        // Update meta fields (sanitized)
        $text_fields = [
            'priority', 'customer_name', 'customer_phone',
            'address_street', 'address_city', 'address_state', 'address_zip',
            'scheduled_date', 'scheduled_time_start', 'scheduled_time_end',
            'estimated_duration',
        ];

        foreach ($text_fields as $field) {
            $value = $request->get_param($field);
            if (null !== $value) {
                update_post_meta($work_order_id, "_fsm_wo_{$field}", sanitize_text_field($value));
            }
        }

        // Email field
        $email = $request->get_param('customer_email');
        if (null !== $email) {
            update_post_meta($work_order_id, '_fsm_wo_customer_email', sanitize_email($email));
        }

        // Textarea field
        $notes = $request->get_param('internal_notes');
        if (null !== $notes) {
            update_post_meta($work_order_id, '_fsm_wo_internal_notes', sanitize_textarea_field($notes));
        }

        // Numeric fields
        foreach (['latitude', 'longitude'] as $field) {
            $value = $request->get_param($field);
            if (null !== $value) {
                update_post_meta($work_order_id, "_fsm_wo_{$field}", (float) $value);
            }
        }

        // Work order types (taxonomy terms)
        $type_ids = $request->get_param('work_order_types');
        if (null !== $type_ids) {
            $valid_ids = [];
            foreach ((array) $type_ids as $term_id) {
                if (term_exists((int) $term_id, 'fsm_work_order_type')) {
                    $valid_ids[] = (int) $term_id;
                }
            }
            wp_set_object_terms($work_order_id, $valid_ids, 'fsm_work_order_type');
        }

        // Handle technician assignment (only for dispatchers)
        if (current_user_can('fsm_assign_work_orders')) {
            $assigned = $request->get_param('assigned_technicians');
            if (null !== $assigned) {
                update_post_meta($work_order_id, '_fsm_wo_assigned_technicians', array_map('absint', $assigned));

                // Update status to assigned if technicians are added
                $current_status = get_post_meta($work_order_id, '_fsm_wo_status', true);
                if ($current_status === 'new' && !empty($assigned)) {
                    FSM_Work_Order_Status::transition($work_order_id, 'assigned');
                }
            }

            $primary = $request->get_param('primary_technician_id');
            if (null !== $primary) {
                update_post_meta($work_order_id, '_fsm_wo_primary_technician_id', absint($primary));
            }
        }

        // Log update
        FSM_Utilities::log_activity('work_order_updated', [
            'work_order_id' => $work_order_id,
        ]);

        // Clear cache
        FSM_Utilities::clear_cache('work_orders');

        $work_order = get_post($work_order_id);
        return rest_ensure_response(self::prepare_work_order($work_order, true));
    }

    /**
     * Delete work order.
     */
    public static function delete_work_order($request) {
        $work_order_id = (int) $request->get_param('id');
        $work_order = get_post($work_order_id);

        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-work-orders'), 404);
        }

        $access = FSM_Organizations::verify_post_access($work_order_id);
        if (is_wp_error($access)) {
            return $access;
        }

        $reference = get_post_meta($work_order_id, '_fsm_wo_reference', true);

        wp_delete_post($work_order_id, true);

        FSM_Utilities::log_activity('work_order_deleted', [
            'work_order_id' => $work_order_id,
            'reference' => $reference,
        ]);

        FSM_Utilities::clear_cache('work_orders');

        return rest_ensure_response(['deleted' => true, 'id' => $work_order_id]);
    }

    /**
     * Update work order status.
     */
    public static function update_status($request) {
        $work_order_id = (int) $request->get_param('id');
        $new_status = $request->get_param('status');

        $access = FSM_Organizations::verify_post_access($work_order_id);
        if (is_wp_error($access)) {
            return $access;
        }

        $result = FSM_Work_Order_Status::transition($work_order_id, $new_status);

        if (is_wp_error($result)) {
            return $result;
        }

        $work_order = get_post($work_order_id);
        return rest_ensure_response(self::prepare_work_order($work_order, true));
    }

    /**
     * Get work order notes.
     */
    public static function get_notes($request) {
        $work_order_id = (int) $request->get_param('id');

        $comments = get_comments([
            'post_id' => $work_order_id,
            'type' => 'fsm_note',
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'DESC',
        ]);

        $notes = [];
        foreach ($comments as $comment) {
            $notes[] = [
                'id' => $comment->comment_ID,
                'content' => $comment->comment_content,
                'type' => get_comment_meta($comment->comment_ID, '_fsm_note_type', true) ?: 'note',
                'author' => [
                    'id' => $comment->user_id,
                    'name' => $comment->comment_author,
                ],
                'created_at' => $comment->comment_date,
                'attachments' => get_comment_meta($comment->comment_ID, '_fsm_attachments', true) ?: [],
            ];
        }

        return rest_ensure_response($notes);
    }

    /**
     * Add note to work order.
     */
    public static function add_note($request) {
        $work_order_id = (int) $request->get_param('id');
        $content = $request->get_param('content');
        $type = $request->get_param('type');

        $user = wp_get_current_user();

        $comment_data = [
            'comment_post_ID' => $work_order_id,
            'comment_content' => sanitize_textarea_field($content),
            'comment_type' => 'fsm_note',
            'comment_approved' => 1,
            'user_id' => $user->ID,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
        ];

        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            return FSM_REST_API::error('note_failed', __('Failed to add note.', 'fsm-work-orders'), 500);
        }

        update_comment_meta($comment_id, '_fsm_note_type', $type);

        FSM_Utilities::log_activity('work_order_note_added', [
            'work_order_id' => $work_order_id,
            'note_id' => $comment_id,
        ]);

        return rest_ensure_response([
            'id' => $comment_id,
            'content' => $content,
            'type' => $type,
            'author' => [
                'id' => $user->ID,
                'name' => $user->display_name,
            ],
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Get my work orders (for technicians).
     */
    public static function get_my_work_orders($request) {
        $user_id = get_current_user_id();
        $technician = FSM_Utilities::get_technician_by_user_id($user_id);

        if (!$technician) {
            return rest_ensure_response([]);
        }

        // Override technician_id in request
        $request->set_param('technician_id', $technician->ID);

        return self::get_work_orders($request);
    }

    /**
     * Get today's work orders.
     */
    public static function get_todays_work_orders($request) {
        $today = current_time('Y-m-d');
        $request->set_param('date', $today);

        // If technician, only show their work orders
        if (!current_user_can('fsm_view_work_orders')) {
            return self::get_my_work_orders($request);
        }

        return self::get_work_orders($request);
    }

    /**
     * Get configured work order types with term meta.
     */
    public static function get_configured_types() {
        $terms = get_terms([
            'taxonomy'   => 'fsm_work_order_type',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return rest_ensure_response([]);
        }

        $types = [];
        foreach ($terms as $term) {
            $types[] = [
                'id'               => $term->term_id,
                'name'             => esc_html($term->name),
                'slug'             => $term->slug,
                'default_duration' => absint(get_term_meta($term->term_id, '_fsm_wot_default_duration', true)) ?: 60,
                'color'            => sanitize_hex_color(get_term_meta($term->term_id, '_fsm_wot_color', true)) ?: '#6b7280',
                'icon'             => esc_attr(get_term_meta($term->term_id, '_fsm_wot_icon', true)) ?: 'wrench',
            ];
        }

        return rest_ensure_response($types);
    }

    /**
     * Prepare work order type terms with meta for API responses.
     *
     * @param int $post_id Work order post ID.
     * @return array
     */
    /**
     * Create a new work order type.
     */
    public static function create_work_order_type($request) {
        $name = $request->get_param('name');

        // Check if term with this name already exists
        $existing = term_exists($name, 'fsm_work_order_type');
        if ($existing) {
            return new WP_Error('type_exists', __('A work order type with this name already exists.', 'fsm-work-orders'), ['status' => 400]);
        }

        $result = wp_insert_term($name, 'fsm_work_order_type');
        if (is_wp_error($result)) {
            return $result;
        }

        $term_id = $result['term_id'];

        update_term_meta($term_id, '_fsm_wot_default_duration', $request->get_param('default_duration'));
        update_term_meta($term_id, '_fsm_wot_color', $request->get_param('color'));
        update_term_meta($term_id, '_fsm_wot_icon', $request->get_param('icon'));

        return rest_ensure_response([
            'id'               => (int) $term_id,
            'name'             => esc_html($name),
            'slug'             => get_term($term_id, 'fsm_work_order_type')->slug,
            'default_duration' => absint($request->get_param('default_duration')),
            'color'            => $request->get_param('color'),
            'icon'             => $request->get_param('icon'),
        ]);
    }

    /**
     * Update a work order type.
     */
    public static function update_work_order_type($request) {
        $term_id = (int) $request->get_param('type_id');
        $term = get_term($term_id, 'fsm_work_order_type');

        if (!$term || is_wp_error($term)) {
            return new WP_Error('not_found', __('Work order type not found.', 'fsm-work-orders'), ['status' => 404]);
        }

        // Update name if provided
        $name = $request->get_param('name');
        if ($name && $name !== $term->name) {
            $result = wp_update_term($term_id, 'fsm_work_order_type', ['name' => $name]);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // Update term meta
        $duration = $request->get_param('default_duration');
        if (null !== $duration) {
            update_term_meta($term_id, '_fsm_wot_default_duration', absint($duration));
        }

        $color = $request->get_param('color');
        if (null !== $color) {
            update_term_meta($term_id, '_fsm_wot_color', sanitize_hex_color($color));
        }

        $icon = $request->get_param('icon');
        if (null !== $icon) {
            update_term_meta($term_id, '_fsm_wot_icon', sanitize_text_field($icon));
        }

        $updated = get_term($term_id, 'fsm_work_order_type');

        return rest_ensure_response([
            'id'               => (int) $term_id,
            'name'             => esc_html($updated->name),
            'slug'             => $updated->slug,
            'default_duration' => absint(get_term_meta($term_id, '_fsm_wot_default_duration', true)) ?: 60,
            'color'            => sanitize_hex_color(get_term_meta($term_id, '_fsm_wot_color', true)) ?: '#6b7280',
            'icon'             => esc_attr(get_term_meta($term_id, '_fsm_wot_icon', true)) ?: 'wrench',
        ]);
    }

    /**
     * Delete a work order type.
     */
    public static function delete_work_order_type($request) {
        $term_id = (int) $request->get_param('type_id');
        $term = get_term($term_id, 'fsm_work_order_type');

        if (!$term || is_wp_error($term)) {
            return new WP_Error('not_found', __('Work order type not found.', 'fsm-work-orders'), ['status' => 404]);
        }

        // Check if any work orders use this type
        $count = $term->count;
        if ($count > 0) {
            return new WP_Error(
                'type_in_use',
                sprintf(__('Cannot delete: %d work order(s) use this type. Reassign them first.', 'fsm-work-orders'), $count),
                ['status' => 400]
            );
        }

        // Delete term meta first
        delete_term_meta($term_id, '_fsm_wot_default_duration');
        delete_term_meta($term_id, '_fsm_wot_color');
        delete_term_meta($term_id, '_fsm_wot_icon');

        $deleted = wp_delete_term($term_id, 'fsm_work_order_type');
        if (is_wp_error($deleted)) {
            return $deleted;
        }

        return rest_ensure_response(['deleted' => true, 'id' => $term_id]);
    }

    public static function prepare_types($post_id) {
        $terms = wp_get_post_terms($post_id, 'fsm_work_order_type', ['fields' => 'all']);
        if (is_wp_error($terms)) {
            return [];
        }
        return array_map(function($term) {
            return [
                'id'    => $term->term_id,
                'name'  => esc_html($term->name),
                'slug'  => $term->slug,
                'color' => sanitize_hex_color(get_term_meta($term->term_id, '_fsm_wot_color', true)) ?: '#6b7280',
                'icon'  => esc_attr(get_term_meta($term->term_id, '_fsm_wot_icon', true)) ?: 'wrench',
            ];
        }, $terms);
    }

    /**
     * Prepare work order for response.
     */
    private static function prepare_work_order($post, $include_details = false) {
        $data = [
            'id' => $post->ID,
            'reference' => get_post_meta($post->ID, '_fsm_wo_reference', true),
            'title' => $post->post_title,
            'status' => get_post_meta($post->ID, '_fsm_wo_status', true) ?: 'new',
            'status_label' => FSM_Work_Orders::get_status_label(
                get_post_meta($post->ID, '_fsm_wo_status', true) ?: 'new'
            ),
            'priority' => get_post_meta($post->ID, '_fsm_wo_priority', true) ?: 'normal',
            'scheduled_date' => get_post_meta($post->ID, '_fsm_wo_scheduled_date', true),
            'scheduled_time_start' => get_post_meta($post->ID, '_fsm_wo_scheduled_time_start', true),
            'created_at' => $post->post_date,
            'modified_at' => $post->post_modified,
        ];

        // Include customer info
        $data['customer'] = [
            'name' => get_post_meta($post->ID, '_fsm_wo_customer_name', true),
            'phone' => get_post_meta($post->ID, '_fsm_wo_customer_phone', true),
            'email' => get_post_meta($post->ID, '_fsm_wo_customer_email', true),
        ];

        // Include address
        $data['address'] = [
            'street' => get_post_meta($post->ID, '_fsm_wo_address_street', true),
            'city' => get_post_meta($post->ID, '_fsm_wo_address_city', true),
            'state' => get_post_meta($post->ID, '_fsm_wo_address_state', true),
            'zip' => get_post_meta($post->ID, '_fsm_wo_address_zip', true),
            'latitude' => get_post_meta($post->ID, '_fsm_wo_latitude', true),
            'longitude' => get_post_meta($post->ID, '_fsm_wo_longitude', true),
        ];

        // Include assigned technicians
        $assigned = get_post_meta($post->ID, '_fsm_wo_assigned_technicians', true);
        $data['assigned_technicians'] = is_array($assigned) ? $assigned : [];
        $data['primary_technician_id'] = get_post_meta($post->ID, '_fsm_wo_primary_technician_id', true);

        // Always include types (with color/icon meta)
        $data['types'] = self::prepare_types($post->ID);

        // Always include estimated_duration for scheduling displays
        $data['estimated_duration'] = absint(get_post_meta($post->ID, '_fsm_wo_estimated_duration', true)) ?: 60;

        // Media count (optional attachments for content production)
        if (class_exists('FSM_Work_Order_Media_DB')) {
            $data['media_count'] = FSM_Work_Order_Media_DB::count_by_work_order($post->ID);
        } else {
            $data['media_count'] = 0;
        }

        if ($include_details) {
            $data['description'] = $post->post_content;
            $data['scheduled_time_end'] = get_post_meta($post->ID, '_fsm_wo_scheduled_time_end', true);
            $data['started_at'] = get_post_meta($post->ID, '_fsm_wo_started_at', true);
            $data['completed_at'] = get_post_meta($post->ID, '_fsm_wo_completed_at', true);
            $data['internal_notes'] = get_post_meta($post->ID, '_fsm_wo_internal_notes', true);

            // Get note count
            $data['note_count'] = get_comments([
                'post_id' => $post->ID,
                'type' => 'fsm_note',
                'count' => true,
            ]);
        }

        return $data;
    }
}
