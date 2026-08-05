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

        // Get all technician locations (for dispatchers/admins)
        register_rest_route(self::NAMESPACE, '/gps/all', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_all_locations'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_all_locations');
            },
        ]);

        // Get group technician locations (for lead technicians)
        register_rest_route(self::NAMESPACE, '/gps/group', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_group_locations'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_group_locations') || current_user_can('fsm_view_all_locations');
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
                    return $tech && (int) $tech->ID === (int) $request->get_param('technician_id');
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

        // Nearby technicians (for technicians to find help)
        register_rest_route(self::NAMESPACE, '/gps/nearby', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_nearby_technicians'],
            'permission_callback' => function() {
                return current_user_can('fsm_access');
            },
            'args' => [
                'limit' => [
                    'default' => 5,
                    'sanitize_callback' => 'absint',
                ],
                'radius' => [
                    'default' => 25,
                    'type' => 'number',
                    'description' => 'Radius in miles',
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

            $user_id = get_post_meta($tech->ID, '_fsm_user_id', true);

            $entry = [
                'technician' => [
                    'id'     => $tech->ID,
                    'name'   => $tech->post_title,
                    'status' => get_post_meta($tech->ID, '_fsm_tech_status', true)
                                ?: get_post_meta($tech->ID, '_fsm_status', true),
                    'phone'  => $user_id ? get_user_meta($user_id, 'phone', true) : '',
                    'email'  => $user_id ? get_userdata($user_id)->user_email : '',
                ],
                'location' => self::prepare_location($location),
            ];

            // Include work order summary for dispatchers/admins
            $wo_id = $location->work_order_id ? (int) $location->work_order_id : null;
            $entry['current_work_order'] = self::prepare_work_order_summary($wo_id);

            // Include schedule adherence status
            if (class_exists('FSM_Schedule')) {
                $entry['schedule_status'] = FSM_Schedule::calculate_schedule_status($tech->ID);
            }

            $result[] = $entry;
        }

        return rest_ensure_response($result);
    }

    /**
     * Get group technician locations (for lead technicians).
     *
     * Returns full location data for technicians in the same group/team
     * as the requesting lead technician.
     */
    public static function get_group_locations($request) {
        $current_user_id = get_current_user_id();
        $my_tech = FSM_Utilities::get_technician_by_user_id($current_user_id);

        // If user has full view access, delegate to get_all_locations
        if (current_user_can('fsm_view_all_locations')) {
            return self::get_all_locations($request);
        }

        if (!$my_tech) {
            return new WP_Error('not_technician', 'User is not a technician.', ['status' => 403]);
        }

        // Get all technicians in the same team/group
        $org_id = FSM_Organizations::get_current_context();
        $my_teams = wp_get_object_terms($my_tech->ID, 'fsm_team', ['fields' => 'ids']);

        // Get all technicians that share any team with this lead
        $group_tech_ids = [$my_tech->ID]; // Include self

        if (!empty($my_teams) && !is_wp_error($my_teams)) {
            $team_techs = get_posts([
                'post_type'      => 'fsm_technician',
                'posts_per_page' => 50,
                'post_status'    => 'publish',
                'tax_query'      => [
                    [
                        'taxonomy' => 'fsm_team',
                        'terms'    => $my_teams,
                        'operator' => 'IN',
                    ],
                ],
                'fields' => 'ids',
            ]);
            if (!empty($team_techs)) {
                $group_tech_ids = array_unique(array_merge($group_tech_ids, $team_techs));
            }
        }

        // If no team taxonomy exists, fall back to all technicians in the same org
        if (count($group_tech_ids) <= 1 && $org_id) {
            $org_techs = get_posts([
                'post_type'      => 'fsm_technician',
                'posts_per_page' => 50,
                'post_status'    => 'publish',
                'meta_query'     => [
                    ['key' => '_fsm_organization_id', 'value' => $org_id],
                ],
                'fields' => 'ids',
            ]);
            if (!empty($org_techs)) {
                $group_tech_ids = $org_techs;
            }
        }

        // Get locations for group members
        $locations = FSM_GPS_DB::get_all_latest($org_id);
        $result = [];

        foreach ($locations as $location) {
            if (!in_array((int)$location->technician_id, $group_tech_ids, true)) {
                continue;
            }

            $tech = get_post($location->technician_id);
            if (!$tech) continue;

            $user_id = get_post_meta($tech->ID, '_fsm_user_id', true);

            $entry = [
                'technician' => [
                    'id'     => $tech->ID,
                    'name'   => $tech->post_title,
                    'status' => get_post_meta($tech->ID, '_fsm_tech_status', true),
                    'phone'  => $user_id ? get_user_meta($user_id, 'phone', true) : '',
                    'email'  => $user_id ? get_userdata($user_id)->user_email : '',
                ],
                'location' => self::prepare_location($location),
            ];

            // Include work order summary for lead technicians
            $wo_id = $location->work_order_id ? (int) $location->work_order_id : null;
            $entry['current_work_order'] = self::prepare_work_order_summary($wo_id);

            // Include schedule adherence status
            if (class_exists('FSM_Schedule')) {
                $entry['schedule_status'] = FSM_Schedule::calculate_schedule_status($tech->ID);
            }

            $result[] = $entry;
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

        $access = FSM_Organizations::verify_post_access($work_order_id);
        if (is_wp_error($access)) {
            return $access;
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
     * Get nearby technicians.
     *
     * Returns the closest N technicians to the requesting user's current location.
     * For technicians: shows only name and phone (limited info).
     * For dispatchers/admins: shows full location data.
     */
    public static function get_nearby_technicians($request) {
        $current_user_id = get_current_user_id();
        $my_tech = FSM_Utilities::get_technician_by_user_id($current_user_id);
        $is_dispatcher = current_user_can('fsm_view_all_locations');
        $limit = min( absint( $request->get_param('limit') ) ?: 5, 20 );
        $radius = (float) ( $request->get_param('radius') ?: 25 );

        // Get my current location
        $my_location = null;
        if ( $my_tech ) {
            $my_location = FSM_GPS_DB::get_latest( $my_tech->ID );
        }

        if ( ! $my_location ) {
            return new WP_Error( 'no_location', 'Your location is not available. Update your location first.', array( 'status' => 400 ) );
        }

        $my_lat = (float) $my_location->latitude;
        $my_lng = (float) $my_location->longitude;

        // Get all technician locations in the org
        $org_id = FSM_Organizations::get_current_context();
        $all_locations = FSM_GPS_DB::get_all_latest( $org_id );

        $nearby = array();

        foreach ( $all_locations as $loc ) {
            // Skip self
            if ( $my_tech && (int) $loc->technician_id === (int) $my_tech->ID ) {
                continue;
            }

            $distance = FSM_Utilities::calculate_distance(
                $my_lat, $my_lng,
                (float) $loc->latitude, (float) $loc->longitude,
                'miles'
            );

            if ( $distance > $radius ) {
                continue;
            }

            $tech_post = get_post( $loc->technician_id );
            if ( ! $tech_post ) {
                continue;
            }

            $entry = array(
                'technician_id' => (int) $loc->technician_id,
                'name'          => sanitize_text_field( $tech_post->post_title ),
                'distance_miles' => round( $distance, 1 ),
                'latitude'      => (float) $loc->latitude,
                'longitude'     => (float) $loc->longitude,
            );

            // Technicians get limited info (name, phone, status, last update)
            if ( ! $is_dispatcher ) {
                $user_id = get_post_meta( $loc->technician_id, '_fsm_user_id', true );
                $entry['phone']       = $user_id ? get_user_meta( $user_id, 'phone', true ) : '';
                $entry['status']      = get_post_meta( $loc->technician_id, '_fsm_tech_status', true );
                $entry['recorded_at'] = $loc->recorded_at;
                // Remove precise coords for technicians — just give distance + approximate pin
                // Round to ~0.01 degree (~0.7 miles) to preserve privacy
                $entry['latitude']  = round( (float) $loc->latitude, 2 );
                $entry['longitude'] = round( (float) $loc->longitude, 2 );
            } else {
                // Dispatchers get full data
                $entry['accuracy']    = $loc->accuracy ? (float) $loc->accuracy : null;
                $entry['speed']       = $loc->speed ? (float) $loc->speed : null;
                $entry['heading']     = $loc->heading ? (float) $loc->heading : null;
                $entry['recorded_at'] = $loc->recorded_at;
                $entry['status']      = get_post_meta( $loc->technician_id, '_fsm_tech_status', true );
                $user_id = get_post_meta( $loc->technician_id, '_fsm_user_id', true );
                $entry['phone'] = $user_id ? get_user_meta( $user_id, 'phone', true ) : '';
                $entry['email'] = $user_id ? get_userdata( $user_id )->user_email : '';
            }

            $nearby[] = $entry;
        }

        // Sort by distance
        usort( $nearby, function( $a, $b ) {
            return $a['distance_miles'] <=> $b['distance_miles'];
        } );

        // Limit results
        $nearby = array_slice( $nearby, 0, $limit );

        return rest_ensure_response( array(
            'my_location' => array(
                'latitude'  => $my_lat,
                'longitude' => $my_lng,
            ),
            'nearby'      => $nearby,
            'count'       => count( $nearby ),
            'radius_miles' => $radius,
        ) );
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

    /**
     * Build a lightweight work order summary for map display.
     *
     * @param int $work_order_id Work order post ID.
     * @return array|null Summary array or null if invalid.
     */
    private static function prepare_work_order_summary($work_order_id) {
        if (!$work_order_id) {
            return null;
        }

        $wo = get_post($work_order_id);
        if (!$wo || $wo->post_type !== 'fsm_work_order') {
            return null;
        }

        $status    = get_post_meta($work_order_id, '_fsm_wo_status', true) ?: 'new';
        $started   = get_post_meta($work_order_id, '_fsm_wo_started_at', true);
        $reference = get_post_meta($work_order_id, '_fsm_wo_reference', true);

        $status_labels = [
            'new'         => 'New',
            'assigned'    => 'Assigned',
            'en_route'    => 'En Route',
            'in_progress' => 'In Progress',
            'on_hold'     => 'On Hold',
            'completed'   => 'Completed',
            'cancelled'   => 'Cancelled',
        ];

        // Get types if the WO REST API is available
        $types = [];
        if (class_exists('FSM_Work_Order_REST_API')) {
            $types = FSM_Work_Order_REST_API::prepare_types($work_order_id);
        }

        return [
            'id'            => (int) $work_order_id,
            'title'         => sanitize_text_field($wo->post_title),
            'reference'     => sanitize_text_field($reference),
            'status'        => $status,
            'status_label'  => $status_labels[$status] ?? ucfirst($status),
            'priority'      => get_post_meta($work_order_id, '_fsm_wo_priority', true) ?: 'normal',
            'customer_name' => sanitize_text_field(get_post_meta($work_order_id, '_fsm_wo_customer_name', true)),
            'address'       => sanitize_text_field(implode(', ', array_filter([
                get_post_meta($work_order_id, '_fsm_wo_address_street', true),
                get_post_meta($work_order_id, '_fsm_wo_address_city', true),
            ]))),
            'started_at'    => $started ?: null,
            'types'         => $types,
        ];
    }
}
