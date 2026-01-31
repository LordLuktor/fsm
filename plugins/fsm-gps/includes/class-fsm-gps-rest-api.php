<?php
/**
 * FSM GPS REST API
 *
 * REST API endpoints for location tracking.
 * Visibility controls:
 * - Technicians can only see their own location
 * - Dispatchers/admins can see all locations
 *
 * @package FSM\GPS
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM GPS REST API class.
 */
class FSM_GPS_REST_API {

    /**
     * Namespace.
     */
    const NAMESPACE = 'fsm/v1';

    /**
     * Register routes.
     */
    public static function register_routes() {
        // Update own location (for technicians)
        register_rest_route(self::NAMESPACE, '/gps/update', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'update_location'],
            'permission_callback' => function() {
                return current_user_can('fsm_update_location');
            },
            'args' => [
                'latitude' => [
                    'required' => true,
                    'type' => 'number',
                ],
                'longitude' => [
                    'required' => true,
                    'type' => 'number',
                ],
                'accuracy' => [
                    'type' => 'number',
                ],
                'altitude' => [
                    'type' => 'number',
                ],
                'heading' => [
                    'type' => 'number',
                ],
                'speed' => [
                    'type' => 'number',
                ],
                'work_order_id' => [
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get my location history
        register_rest_route(self::NAMESPACE, '/gps/history', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_my_history'],
            'permission_callback' => function() {
                return current_user_can('fsm_update_location');
            },
            'args' => [
                'date' => [
                    'default' => current_time('Y-m-d'),
                ],
            ],
        ]);

        // Get all technician locations (for dispatchers)
        register_rest_route(self::NAMESPACE, '/gps/all', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_all_locations'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_all_locations');
            },
        ]);

        // Get specific technician location (for dispatchers)
        register_rest_route(self::NAMESPACE, '/gps/technician/(?P<technician_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_technician_location'],
            'permission_callback' => function($request) {
                // Dispatchers can view all
                if (current_user_can('fsm_view_all_locations')) {
                    return true;
                }
                // Technicians can view their own
                if (current_user_can('fsm_update_location')) {
                    $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());
                    return $tech && $tech->ID == $request->get_param('technician_id');
                }
                return false;
            },
            'args' => [
                'include_history' => [
                    'default' => false,
                    'type' => 'boolean',
                ],
                'date' => [
                    'default' => current_time('Y-m-d'),
                ],
            ],
        ]);

        // Get locations for work order
        register_rest_route(self::NAMESPACE, '/gps/work-order/(?P<work_order_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_work_order_locations'],
            'permission_callback' => function($request) {
                // Use work order permission check
                if (current_user_can('fsm_view_work_orders')) {
                    return true;
                }
                // Technicians assigned to work order
                if (current_user_can('fsm_view_own_work_orders')) {
                    $wo_id = $request->get_param('work_order_id');
                    $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());
                    if ($tech) {
                        $assigned = get_post_meta($wo_id, '_fsm_wo_assigned_technicians', true);
                        return is_array($assigned) && in_array($tech->ID, $assigned, true);
                    }
                }
                return false;
            },
        ]);

        // Check-in at work order location
        register_rest_route(self::NAMESPACE, '/gps/checkin', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'checkin'],
            'permission_callback' => function() {
                return current_user_can('fsm_update_location');
            },
            'args' => [
                'work_order_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'latitude' => [
                    'required' => true,
                    'type' => 'number',
                ],
                'longitude' => [
                    'required' => true,
                    'type' => 'number',
                ],
            ],
        ]);
    }

    /**
     * Update location.
     */
    public static function update_location($request) {
        $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());

        if (!$tech) {
            return FSM_REST_API::error('not_technician', __('User is not a technician.', 'fsm-gps'), 403);
        }

        $id = FSM_GPS_DB::record([
            'technician_id' => $tech->ID,
            'latitude' => $request->get_param('latitude'),
            'longitude' => $request->get_param('longitude'),
            'accuracy' => $request->get_param('accuracy'),
            'altitude' => $request->get_param('altitude'),
            'heading' => $request->get_param('heading'),
            'speed' => $request->get_param('speed'),
            'work_order_id' => $request->get_param('work_order_id'),
            'source' => 'device',
        ]);

        if (!$id) {
            return FSM_REST_API::error('record_failed', __('Failed to record location.', 'fsm-gps'), 500);
        }

        return rest_ensure_response([
            'success' => true,
            'location_id' => $id,
            'recorded_at' => current_time('c'),
        ]);
    }

    /**
     * Get my location history.
     */
    public static function get_my_history($request) {
        $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());

        if (!$tech) {
            return rest_ensure_response([]);
        }

        $date = $request->get_param('date');
        $date_from = $date . ' 00:00:00';
        $date_to = $date . ' 23:59:59';

        $history = FSM_GPS_DB::get_history($tech->ID, $date_from, $date_to);

        return rest_ensure_response(array_map([__CLASS__, 'prepare_location'], $history));
    }

    /**
     * Get all technician locations.
     */
    public static function get_all_locations($request) {
        $locations = FSM_GPS_DB::get_all_latest();
        $result = [];

        foreach ($locations as $location) {
            $tech = get_post($location->technician_id);
            if (!$tech) {
                continue;
            }

            $result[] = [
                'technician' => [
                    'id' => $tech->ID,
                    'name' => $tech->post_title,
                    'status' => get_post_meta($tech->ID, '_fsm_status', true),
                ],
                'location' => self::prepare_location($location),
            ];
        }

        return rest_ensure_response($result);
    }

    /**
     * Get specific technician location.
     */
    public static function get_technician_location($request) {
        $technician_id = $request->get_param('technician_id');
        $include_history = $request->get_param('include_history');
        $date = $request->get_param('date');

        $tech = get_post($technician_id);
        if (!$tech || $tech->post_type !== 'fsm_technician') {
            return FSM_REST_API::error('not_found', __('Technician not found.', 'fsm-gps'), 404);
        }

        $latest = FSM_GPS_DB::get_latest($technician_id);

        $data = [
            'technician' => [
                'id' => $tech->ID,
                'name' => $tech->post_title,
                'status' => get_post_meta($tech->ID, '_fsm_status', true),
            ],
            'current_location' => $latest ? self::prepare_location($latest) : null,
        ];

        if ($include_history) {
            $date_from = $date . ' 00:00:00';
            $date_to = $date . ' 23:59:59';
            $history = FSM_GPS_DB::get_history($technician_id, $date_from, $date_to);
            $data['history'] = array_map([__CLASS__, 'prepare_location'], $history);
        }

        return rest_ensure_response($data);
    }

    /**
     * Get work order locations.
     */
    public static function get_work_order_locations($request) {
        $work_order_id = $request->get_param('work_order_id');

        $work_order = get_post($work_order_id);
        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-gps'), 404);
        }

        $locations = FSM_GPS_DB::get_for_work_order($work_order_id);

        return rest_ensure_response([
            'work_order_id' => $work_order_id,
            'work_order_address' => [
                'latitude' => get_post_meta($work_order_id, '_fsm_wo_latitude', true),
                'longitude' => get_post_meta($work_order_id, '_fsm_wo_longitude', true),
            ],
            'locations' => array_map([__CLASS__, 'prepare_location'], $locations),
        ]);
    }

    /**
     * Check-in at work order.
     */
    public static function checkin($request) {
        $tech = FSM_Utilities::get_technician_by_user_id(get_current_user_id());

        if (!$tech) {
            return FSM_REST_API::error('not_technician', __('User is not a technician.', 'fsm-gps'), 403);
        }

        $work_order_id = $request->get_param('work_order_id');
        $latitude = $request->get_param('latitude');
        $longitude = $request->get_param('longitude');

        // Verify technician is assigned to this work order
        $assigned = get_post_meta($work_order_id, '_fsm_wo_assigned_technicians', true);
        if (!is_array($assigned) || !in_array($tech->ID, $assigned, true)) {
            return FSM_REST_API::error('not_assigned', __('Not assigned to this work order.', 'fsm-gps'), 403);
        }

        // Calculate distance to work order location
        $wo_lat = get_post_meta($work_order_id, '_fsm_wo_latitude', true);
        $wo_lng = get_post_meta($work_order_id, '_fsm_wo_longitude', true);
        $distance = null;

        if ($wo_lat && $wo_lng) {
            $distance = FSM_Utilities::calculate_distance($latitude, $longitude, $wo_lat, $wo_lng, 'miles');
        }

        // Record check-in location
        $id = FSM_GPS_DB::record([
            'technician_id' => $tech->ID,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'work_order_id' => $work_order_id,
            'source' => 'checkin',
        ]);

        FSM_Utilities::log_activity('technician_checkin', [
            'technician_id' => $tech->ID,
            'work_order_id' => $work_order_id,
            'distance_miles' => $distance,
        ]);

        return rest_ensure_response([
            'success' => true,
            'location_id' => $id,
            'work_order_id' => $work_order_id,
            'distance_to_site' => $distance,
            'recorded_at' => current_time('c'),
        ]);
    }

    /**
     * Prepare location for response.
     */
    private static function prepare_location($location) {
        return [
            'id' => (int) $location->id,
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy' => $location->accuracy ? (float) $location->accuracy : null,
            'altitude' => $location->altitude ? (float) $location->altitude : null,
            'heading' => $location->heading ? (float) $location->heading : null,
            'speed' => $location->speed ? (float) $location->speed : null,
            'source' => $location->source,
            'work_order_id' => $location->work_order_id ? (int) $location->work_order_id : null,
            'address' => $location->address,
            'recorded_at' => $location->recorded_at,
        ];
    }
}
