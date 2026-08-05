<?php
/**
 * FSM Dispatch REST API
 *
 * REST API endpoints for dispatch and scheduling.
 *
 * @package FSM\Dispatch
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Dispatch REST API class.
 */
class FSM_Dispatch_REST_API {

    /**
     * Namespace.
     */
    const NAMESPACE = 'fsm/v1';

    /**
     * Register routes.
     */
    public static function register_routes() {
        // Dispatch board - all technicians with their work orders for a date
        register_rest_route(self::NAMESPACE, '/dispatch/board', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_dispatch_board'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_dispatch');
            },
            'args' => [
                'date' => [
                    'default' => current_time('Y-m-d'),
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Technician availability
        register_rest_route(self::NAMESPACE, '/dispatch/availability', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_availability'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_dispatch');
            },
            'args' => [
                'date' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'duration' => [
                    'default' => 60,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Assign work order to technician
        register_rest_route(self::NAMESPACE, '/dispatch/assign', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'assign_work_order'],
            'permission_callback' => function() {
                return current_user_can('fsm_assign_work_orders');
            },
            'args' => [
                'work_order_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'technician_ids' => [
                    'required' => true,
                    'type' => 'array',
                ],
                'primary_technician_id' => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Unassign work order
        register_rest_route(self::NAMESPACE, '/dispatch/unassign', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'unassign_work_order'],
            'permission_callback' => function() {
                return current_user_can('fsm_assign_work_orders');
            },
            'args' => [
                'work_order_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'technician_id' => [
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Reschedule work order
        register_rest_route(self::NAMESPACE, '/dispatch/reschedule', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'reschedule_work_order'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_dispatch');
            },
            'args' => [
                'work_order_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'date' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'time_start' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'time_end' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Technician schedule for a date range
        register_rest_route(self::NAMESPACE, '/dispatch/schedule/(?P<technician_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_technician_schedule'],
            'permission_callback' => function($request) {
                // Dispatchers can view all, technicians only their own
                if (current_user_can('fsm_view_dispatch')) {
                    return true;
                }
                if (current_user_can('fsm_view_own_work_orders')) {
                    $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());
                    return $tech && (int) $tech->ID === (int) $request->get_param('technician_id');
                }
                return false;
            },
            'args' => [
                'date_from' => [
                    'default' => current_time('Y-m-d'),
                ],
                'date_to' => [
                    'default' => '',
                ],
            ],
        ]);

        // Unscheduled work orders
        register_rest_route(self::NAMESPACE, '/dispatch/unscheduled', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_unscheduled'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_dispatch');
            },
        ]);
    }

    /**
     * Get dispatch board.
     */
    public static function get_dispatch_board($request) {
        $date = $request->get_param('date');

        // Get all active technicians
        $technicians = FSM_Utilities::get_active_technicians();
        $board = [];

        foreach ($technicians as $tech) {
            // Get work orders for this technician on this date
            $work_orders = get_posts([
                'post_type' => 'fsm_work_order',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_fsm_wo_scheduled_date',
                        'value' => $date,
                    ],
                    [
                        'key' => '_fsm_wo_assigned_technicians',
                        'value' => $tech->ID,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key' => '_fsm_wo_status',
                        'value' => ['new', 'assigned', 'en_route', 'in_progress', 'on_hold'],
                        'compare' => 'IN',
                    ],
                ],
                'orderby' => 'meta_value',
                'meta_key' => '_fsm_wo_scheduled_time_start',
                'order' => 'ASC',
            ]);

            $tech_work_orders = [];
            foreach ($work_orders as $wo) {
                $tech_work_orders[] = [
                    'id' => $wo->ID,
                    'reference' => get_post_meta($wo->ID, '_fsm_wo_reference', true),
                    'title' => $wo->post_title,
                    'status' => get_post_meta($wo->ID, '_fsm_wo_status', true),
                    'priority' => get_post_meta($wo->ID, '_fsm_wo_priority', true) ?: 'normal',
                    'time_start' => get_post_meta($wo->ID, '_fsm_wo_scheduled_time_start', true),
                    'time_end' => get_post_meta($wo->ID, '_fsm_wo_scheduled_time_end', true),
                    'duration' => get_post_meta($wo->ID, '_fsm_wo_estimated_duration', true) ?: 60,
                    'customer_name' => get_post_meta($wo->ID, '_fsm_wo_customer_name', true),
                    'address' => [
                        'street' => get_post_meta($wo->ID, '_fsm_wo_address_street', true),
                        'city' => get_post_meta($wo->ID, '_fsm_wo_address_city', true),
                    ],
                    'types' => FSM_Work_Order_REST_API::prepare_types($wo->ID),
                ];
            }

            $board[] = [
                'technician' => [
                    'id' => $tech->ID,
                    'name' => $tech->post_title,
                    'status' => get_post_meta($tech->ID, '_fsm_status', true),
                    'phone' => get_post_meta($tech->ID, '_fsm_phone', true),
                ],
                'work_orders' => $tech_work_orders,
                'total_duration' => array_sum(array_column($tech_work_orders, 'duration')),
            ];
        }

        return rest_ensure_response([
            'date' => $date,
            'technicians' => $board,
        ]);
    }

    /**
     * Get technician availability.
     */
    public static function get_availability($request) {
        $date = $request->get_param('date');
        $required_duration = $request->get_param('duration');

        $technicians = FSM_Utilities::get_active_technicians();
        $availability = [];

        foreach ($technicians as $tech) {
            $schedule = FSM_Schedule::get_technician_day($tech->ID, $date);
            $available_slots = FSM_Schedule::find_available_slots($schedule, $required_duration);

            $availability[] = [
                'technician' => [
                    'id' => $tech->ID,
                    'name' => $tech->post_title,
                ],
                'scheduled_minutes' => $schedule['total_scheduled'],
                'available_slots' => $available_slots,
                'is_available' => !empty($available_slots),
            ];
        }

        // Sort by availability (most available first)
        usort($availability, function($a, $b) {
            return $a['scheduled_minutes'] - $b['scheduled_minutes'];
        });

        return rest_ensure_response([
            'date' => $date,
            'required_duration' => $required_duration,
            'technicians' => $availability,
        ]);
    }

    /**
     * Assign work order.
     */
    public static function assign_work_order($request) {
        $work_order_id = $request->get_param('work_order_id');
        $technician_ids = array_map('absint', $request->get_param('technician_ids'));
        $primary_id = $request->get_param('primary_technician_id');

        $work_order = get_post($work_order_id);
        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-dispatch'), 404);
        }

        $access = FSM_Organizations::verify_post_access($work_order_id);
        if (is_wp_error($access)) {
            return $access;
        }

        // Validate technicians exist
        foreach ($technician_ids as $tech_id) {
            $tech = get_post($tech_id);
            if (!$tech || $tech->post_type !== 'fsm_technician') {
                return FSM_REST_API::error(
                    'invalid_technician',
                    sprintf(__('Technician %d not found.', 'fsm-dispatch'), $tech_id),
                    400
                );
            }
        }

        // Update assignment
        update_post_meta($work_order_id, '_fsm_wo_assigned_technicians', $technician_ids);

        // Set primary technician
        if ($primary_id) {
            update_post_meta($work_order_id, '_fsm_wo_primary_technician_id', $primary_id);
        } elseif (!empty($technician_ids)) {
            update_post_meta($work_order_id, '_fsm_wo_primary_technician_id', $technician_ids[0]);
        }

        // Update status to assigned if currently new
        $current_status = get_post_meta($work_order_id, '_fsm_wo_status', true);
        if ($current_status === 'new') {
            update_post_meta($work_order_id, '_fsm_wo_status', 'assigned');
        }

        FSM_Utilities::log_activity('work_order_assigned', [
            'work_order_id' => $work_order_id,
            'technician_ids' => $technician_ids,
        ]);

        FSM_Utilities::clear_cache('work_orders');
        FSM_Utilities::clear_cache('dispatch');

        return rest_ensure_response([
            'success' => true,
            'work_order_id' => $work_order_id,
            'assigned_technicians' => $technician_ids,
        ]);
    }

    /**
     * Unassign work order.
     */
    public static function unassign_work_order($request) {
        $work_order_id = $request->get_param('work_order_id');
        $technician_id = $request->get_param('technician_id');

        $work_order = get_post($work_order_id);
        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-dispatch'), 404);
        }

        $access = FSM_Organizations::verify_post_access($work_order_id);
        if (is_wp_error($access)) {
            return $access;
        }

        $assigned = get_post_meta($work_order_id, '_fsm_wo_assigned_technicians', true);
        if (!is_array($assigned)) {
            $assigned = [];
        }

        if ($technician_id) {
            // Remove specific technician
            $assigned = array_diff($assigned, [$technician_id]);
        } else {
            // Remove all technicians
            $assigned = [];
        }

        update_post_meta($work_order_id, '_fsm_wo_assigned_technicians', array_values($assigned));

        // If no technicians left, set status back to new
        if (empty($assigned)) {
            $current_status = get_post_meta($work_order_id, '_fsm_wo_status', true);
            if ($current_status === 'assigned') {
                update_post_meta($work_order_id, '_fsm_wo_status', 'new');
            }
            delete_post_meta($work_order_id, '_fsm_wo_primary_technician_id');
        }

        FSM_Utilities::log_activity('work_order_unassigned', [
            'work_order_id' => $work_order_id,
            'technician_id' => $technician_id,
        ]);

        FSM_Utilities::clear_cache('work_orders');
        FSM_Utilities::clear_cache('dispatch');

        return rest_ensure_response([
            'success' => true,
            'work_order_id' => $work_order_id,
            'remaining_technicians' => array_values($assigned),
        ]);
    }

    /**
     * Reschedule work order.
     */
    public static function reschedule_work_order($request) {
        $work_order_id = $request->get_param('work_order_id');
        $date = $request->get_param('date');
        $time_start = $request->get_param('time_start');
        $time_end = $request->get_param('time_end');

        $work_order = get_post($work_order_id);
        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-dispatch'), 404);
        }

        $access = FSM_Organizations::verify_post_access($work_order_id);
        if (is_wp_error($access)) {
            return $access;
        }

        $old_date = get_post_meta($work_order_id, '_fsm_wo_scheduled_date', true);

        update_post_meta($work_order_id, '_fsm_wo_scheduled_date', $date);

        if ($time_start) {
            update_post_meta($work_order_id, '_fsm_wo_scheduled_time_start', $time_start);
        }

        if ($time_end) {
            update_post_meta($work_order_id, '_fsm_wo_scheduled_time_end', $time_end);
        }

        FSM_Utilities::log_activity('work_order_rescheduled', [
            'work_order_id' => $work_order_id,
            'old_date' => $old_date,
            'new_date' => $date,
        ]);

        FSM_Utilities::clear_cache('work_orders');
        FSM_Utilities::clear_cache('dispatch');

        return rest_ensure_response([
            'success' => true,
            'work_order_id' => $work_order_id,
            'scheduled_date' => $date,
            'scheduled_time_start' => $time_start,
            'scheduled_time_end' => $time_end,
        ]);
    }

    /**
     * Get technician schedule.
     */
    public static function get_technician_schedule($request) {
        $technician_id = (int) $request->get_param('technician_id');
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to') ?: $date_from;

        $tech = get_post($technician_id);
        if (!$tech || $tech->post_type !== 'fsm_technician') {
            return FSM_REST_API::error('not_found', __('Technician not found.', 'fsm-dispatch'), 404);
        }

        $schedule = [];
        $current = strtotime($date_from);
        $end = strtotime($date_to);

        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $schedule[$date] = FSM_Schedule::get_technician_day($technician_id, $date);
            $current = strtotime('+1 day', $current);
        }

        return rest_ensure_response([
            'technician_id' => $technician_id,
            'technician_name' => $tech->post_title,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'schedule' => $schedule,
        ]);
    }

    /**
     * Get unscheduled work orders.
     */
    public static function get_unscheduled($request) {
        // Get work orders that are either unscheduled OR unassigned (new status)
        $work_orders = get_posts([
            'post_type' => 'fsm_work_order',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_fsm_wo_status',
                    'value' => ['new', 'assigned'],
                    'compare' => 'IN',
                ],
                [
                    'relation' => 'OR',
                    // No scheduled date
                    [
                        'key' => '_fsm_wo_scheduled_date',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => '_fsm_wo_scheduled_date',
                        'value' => '',
                    ],
                    // Has date but status is still 'new' (unassigned)
                    [
                        'key' => '_fsm_wo_status',
                        'value' => 'new',
                    ],
                ],
            ],
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        $unscheduled = [];
        foreach ($work_orders as $wo) {
            $unscheduled[] = [
                'id' => $wo->ID,
                'reference' => get_post_meta($wo->ID, '_fsm_wo_reference', true),
                'title' => $wo->post_title,
                'status' => get_post_meta($wo->ID, '_fsm_wo_status', true),
                'priority' => get_post_meta($wo->ID, '_fsm_wo_priority', true) ?: 'normal',
                'estimated_duration' => get_post_meta($wo->ID, '_fsm_wo_estimated_duration', true) ?: 60,
                'customer_name' => get_post_meta($wo->ID, '_fsm_wo_customer_name', true),
                'created_at' => $wo->post_date,
                'types' => FSM_Work_Order_REST_API::prepare_types($wo->ID),
            ];
        }

        return rest_ensure_response($unscheduled);
    }
}
