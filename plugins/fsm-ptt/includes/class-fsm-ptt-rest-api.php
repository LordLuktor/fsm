<?php
/**
 * FSM PTT REST API
 *
 * REST API endpoints for PTT communication.
 *
 * @package FSM\PTT
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM PTT REST API class.
 */
class FSM_PTT_REST_API {

    /**
     * Namespace.
     */
    const NAMESPACE = 'fsm/v1';

    /**
     * Register routes.
     */
    public static function register_routes() {
        // Get my PTT channels
        register_rest_route(self::NAMESPACE, '/ptt/channels', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_my_channels'],
            'permission_callback' => function() {
                return current_user_can('fsm_use_ptt');
            },
        ]);

        // Get all channels (admin only)
        register_rest_route(self::NAMESPACE, '/ptt/channels/all', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_all_channels'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_ptt');
            },
        ]);

        // Create/update channel
        register_rest_route(self::NAMESPACE, '/ptt/channels', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'create_channel'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_ptt');
            },
            'args' => [
                'channel_name' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'channel_type' => [
                    'required' => true,
                    'enum' => ['dispatch', 'team', 'technician', 'work_order'],
                ],
                'reference_id' => [
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Get PTT connection info
        register_rest_route(self::NAMESPACE, '/ptt/connection', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_connection_info'],
            'permission_callback' => function() {
                return current_user_can('fsm_use_ptt');
            },
        ]);

        // Create work order channel
        register_rest_route(self::NAMESPACE, '/ptt/work-order/(?P<work_order_id>\d+)/channel', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'create_work_order_channel'],
            'permission_callback' => function() {
                return current_user_can('fsm_manage_ptt') || current_user_can('fsm_manage_dispatch');
            },
        ]);

        // Get technician's PTT status
        register_rest_route(self::NAMESPACE, '/ptt/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'get_ptt_status'],
            'permission_callback' => function() {
                return current_user_can('fsm_view_dispatch');
            },
        ]);
    }

    /**
     * Get my channels.
     */
    public static function get_my_channels($request) {
        $user_id = get_current_user_id();

        // Dispatchers/admins get access to all channels
        if (current_user_can('fsm_manage_ptt')) {
            $channels = FSM_PTT_DB::get_channels();
            return rest_ensure_response(array_map([__CLASS__, 'prepare_channel'], $channels));
        }

        // Technicians get their assigned channels
        $tech = FSM_Utilities::get_technician_by_user_id($user_id);
        if (!$tech) {
            return rest_ensure_response([]);
        }

        $channels = FSM_PTT_DB::get_technician_channels($tech->ID);

        // Add work order channels for assigned work orders
        $assigned_wos = get_posts([
            'post_type' => 'fsm_work_order',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_fsm_wo_assigned_technicians',
                    'value' => $tech->ID,
                    'compare' => 'LIKE',
                ],
                [
                    'key' => '_fsm_wo_status',
                    'value' => ['assigned', 'en_route', 'in_progress'],
                    'compare' => 'IN',
                ],
            ],
        ]);

        foreach ($assigned_wos as $wo) {
            $wo_channel = FSM_PTT_DB::get_channel('work_order', $wo->ID);
            if ($wo_channel) {
                $channels[] = $wo_channel;
            }
        }

        return rest_ensure_response(array_map([__CLASS__, 'prepare_channel'], $channels));
    }

    /**
     * Get all channels.
     */
    public static function get_all_channels($request) {
        $channels = FSM_PTT_DB::get_channels();
        return rest_ensure_response(array_map([__CLASS__, 'prepare_channel'], $channels));
    }

    /**
     * Create channel.
     */
    public static function create_channel($request) {
        $data = [
            'channel_name' => $request->get_param('channel_name'),
            'channel_type' => $request->get_param('channel_type'),
            'reference_id' => $request->get_param('reference_id'),
        ];

        $id = FSM_PTT_DB::upsert_channel($data);

        if (!$id) {
            return FSM_REST_API::error('create_failed', __('Failed to create channel.', 'fsm-ptt'), 500);
        }

        // Create channel in Mumble based on type
        $org_id = FSM_Organizations::get_current_context();
        // Mumble integration uses static methods

        if ( $org_id ) {
            $result = null;
            switch ( $data['channel_type'] ) {
                case 'team':
                case 'dispatch':
                    $result = FSM_Mumble_Integration::create_team_channel( $org_id, $data['channel_name'] );
                    break;
                case 'work_order':
                    $result = FSM_Mumble_Integration::create_work_order_channel( $org_id, $data['channel_name'] );
                    break;
            }

            // Link Mumble channel ID if creation succeeded
            if ( $result && ! is_wp_error( $result ) && is_array( $result ) && isset( $result['channelId'] ) ) {
                FSM_PTT_DB::upsert_channel( array_merge( $data, [
                    'mumble_channel_id' => $result['channelId'],
                ] ) );
            } elseif ( is_wp_error( $result ) ) {
                // Mumble is down or org not provisioned — channel saved in WP DB without Mumble link
                error_log( 'FSM PTT: Mumble channel creation failed: ' . $result->get_error_message() );
            }
        }

        $channel = FSM_PTT_DB::get_channel($data['channel_type'], $data['reference_id']);
        return rest_ensure_response(self::prepare_channel($channel));
    }

    /**
     * Get connection info.
     */
    public static function get_connection_info($request) {
        $user = wp_get_current_user();
        $tech = FSM_Utilities::get_technician_by_user_id($user->ID);
        // Mumble integration uses static methods
        $host = defined( 'FSM_MUMBLE_HOST' ) ? FSM_MUMBLE_HOST : 'mumble.steinmetz.ltd';
        $port = FSM_PTT::get_mumble_port();

        // Try to get real connection info from Mumble backend
        $mumble_info = null;
        if ( $integration ) {
            try {
                $mumble_info = FSM_Mumble_Integration::get_user_connection_info( $user->ID );
            } catch ( \Exception $e ) {
                // Mumble might be down — fall back to constructed info
            }
        }

        $username = $mumble_info['username'] ?? get_user_meta( $user->ID, '_fsm_mumble_username', true ) ?: $user->user_login;
        $default_channel = $mumble_info['defaultChannel'] ?? get_user_meta( $user->ID, '_fsm_mumble_default_channel', true ) ?: '';

        return rest_ensure_response([
            'server' => [
                'host' => $host,
                'port' => $port,
            ],
            'user' => [
                'username'       => $username,
                'display_name'   => $user->display_name,
                'technician_id'  => $tech ? $tech->ID : null,
                'mumble_registered' => FSM_Mumble_Integration::is_user_registered( $user->ID ),
                'default_channel' => $default_channel,
            ],
            'instructions' => [
                'web_client'    => "https://{$host}",
                'native_client' => sprintf(
                    'mumble://%s@%s:%d%s',
                    urlencode( $username ),
                    $host,
                    $port,
                    $default_channel ? '/' . urlencode( $default_channel ) : ''
                ),
            ],
            'status' => [
                'mumble_available' => FSM_Mumble_Integration::is_available(),
            ],
        ]);
    }

    /**
     * Create work order channel.
     */
    public static function create_work_order_channel($request) {
        $work_order_id = $request->get_param('work_order_id');

        $work_order = get_post($work_order_id);
        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-ptt'), 404);
        }

        $reference = get_post_meta($work_order_id, '_fsm_wo_reference', true);
        $channel_name = sprintf('WO-%s', $reference ?: $work_order_id);

        $id = FSM_PTT_DB::upsert_channel([
            'channel_name' => $channel_name,
            'channel_type' => 'work_order',
            'reference_id' => $work_order_id,
        ]);

        if (!$id) {
            return FSM_REST_API::error('create_failed', __('Failed to create channel.', 'fsm-ptt'), 500);
        }

        // Create work order channel in Mumble
        $org_id = FSM_Organizations::get_current_context();
        // Mumble integration uses static methods

        if ( $org_id ) {
            $result = FSM_Mumble_Integration::create_work_order_channel( $org_id, $reference ?: (string) $work_order_id );
            if ( $result && ! is_wp_error( $result ) && is_array( $result ) && isset( $result['channelId'] ) ) {
                FSM_PTT_DB::upsert_channel( [
                    'channel_name'     => $channel_name,
                    'channel_type'     => 'work_order',
                    'reference_id'     => $work_order_id,
                    'mumble_channel_id' => $result['channelId'],
                ] );
            } elseif ( is_wp_error( $result ) ) {
                error_log( 'FSM PTT: Mumble WO channel creation failed: ' . $result->get_error_message() );
            }
        }

        FSM_Utilities::log_activity('ptt_wo_channel_created', [
            'work_order_id' => $work_order_id,
            'channel_name' => $channel_name,
        ]);

        $channel = FSM_PTT_DB::get_channel('work_order', $work_order_id);
        return rest_ensure_response(self::prepare_channel($channel));
    }

    /**
     * Get PTT status.
     */
    public static function get_ptt_status($request) {
        // Note: Getting real-time Mumble status would require the Mumble Ice interface
        // For now, return channel information

        $technicians = FSM_Utilities::get_active_technicians();
        $status = [];

        foreach ($technicians as $tech) {
            $user = FSM_Utilities::get_user_by_technician_id($tech->ID);
            $channels = FSM_PTT_DB::get_technician_channels($tech->ID);

            $status[] = [
                'technician_id' => $tech->ID,
                'technician_name' => $tech->post_title,
                'username' => $user ? $user->user_login : null,
                'available_channels' => count($channels),
            ];
        }

        return rest_ensure_response($status);
    }

    /**
     * Prepare channel for response.
     */
    private static function prepare_channel($channel) {
        $data = [
            'id' => (int) $channel->id,
            'name' => $channel->channel_name,
            'type' => $channel->channel_type,
            'reference_id' => (int) $channel->reference_id,
            'is_active' => (bool) $channel->is_active,
        ];

        // Add reference details
        switch ($channel->channel_type) {
            case 'technician':
                $tech = get_post($channel->reference_id);
                $data['reference_name'] = $tech ? $tech->post_title : null;
                break;

            case 'team':
                $term = get_term($channel->reference_id, 'fsm_team');
                $data['reference_name'] = $term && !is_wp_error($term) ? $term->name : null;
                break;

            case 'work_order':
                $wo = get_post($channel->reference_id);
                $data['reference_name'] = $wo ? get_post_meta($wo->ID, '_fsm_wo_reference', true) : null;
                break;
        }

        return $data;
    }
}
