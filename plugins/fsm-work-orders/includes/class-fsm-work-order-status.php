<?php
/**
 * FSM Work Order Status
 *
 * Handles work order status transitions and validation.
 *
 * @package FSM\WorkOrders
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Work Order Status class.
 */
class FSM_Work_Order_Status {

    /**
     * Valid status transitions.
     * Key = current status, Value = array of valid next statuses.
     *
     * @var array
     */
    private static $transitions = [
        'new' => ['assigned', 'cancelled'],
        'assigned' => ['en_route', 'in_progress', 'on_hold', 'cancelled', 'new'],
        'en_route' => ['in_progress', 'on_hold', 'assigned'],
        'in_progress' => ['completed', 'on_hold'],
        'on_hold' => ['assigned', 'in_progress', 'cancelled'],
        'completed' => [], // Cannot transition from completed
        'cancelled' => ['new'], // Can reopen as new
    ];

    /**
     * Transition work order to new status.
     *
     * @param int    $work_order_id Work order ID.
     * @param string $new_status New status.
     * @return bool|WP_Error
     */
    public static function transition($work_order_id, $new_status) {
        $work_order = get_post($work_order_id);

        if (!$work_order || $work_order->post_type !== 'fsm_work_order') {
            return new WP_Error(
                'not_found',
                __('Work order not found.', 'fsm-work-orders'),
                ['status' => 404]
            );
        }

        $current_status = get_post_meta($work_order_id, '_fsm_wo_status', true) ?: 'new';

        // Validate transition
        if (!self::can_transition($current_status, $new_status)) {
            return new WP_Error(
                'invalid_transition',
                sprintf(
                    __('Cannot transition from "%s" to "%s".', 'fsm-work-orders'),
                    $current_status,
                    $new_status
                ),
                ['status' => 400]
            );
        }

        // Additional validation for specific transitions
        $validation = self::validate_transition($work_order_id, $current_status, $new_status);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Update status
        update_post_meta($work_order_id, '_fsm_wo_status', $new_status);

        // Handle transition side effects
        self::handle_transition($work_order_id, $current_status, $new_status);

        // Log the transition
        FSM_Utilities::log_activity('work_order_status_changed', [
            'work_order_id' => $work_order_id,
            'from_status' => $current_status,
            'to_status' => $new_status,
        ]);

        // Add note about status change
        self::add_status_change_note($work_order_id, $current_status, $new_status);

        // Clear cache - status changes should not be cached
        FSM_Utilities::clear_cache('work_orders');

        /**
         * Fires after work order status changes.
         *
         * @param int    $work_order_id Work order ID.
         * @param string $new_status New status.
         * @param string $old_status Previous status.
         */
        do_action('fsm_work_order_status_changed', $work_order_id, $new_status, $current_status);

        return true;
    }

    /**
     * Check if transition is valid.
     *
     * @param string $current_status Current status.
     * @param string $new_status New status.
     * @return bool
     */
    public static function can_transition($current_status, $new_status) {
        if (!isset(self::$transitions[$current_status])) {
            return false;
        }

        return in_array($new_status, self::$transitions[$current_status], true);
    }

    /**
     * Get valid next statuses.
     *
     * @param string $current_status Current status.
     * @return array
     */
    public static function get_valid_transitions($current_status) {
        return self::$transitions[$current_status] ?? [];
    }

    /**
     * Validate specific transition requirements.
     *
     * @param int    $work_order_id Work order ID.
     * @param string $current_status Current status.
     * @param string $new_status New status.
     * @return true|WP_Error
     */
    private static function validate_transition($work_order_id, $current_status, $new_status) {
        // To be assigned, must have technicians
        if ($new_status === 'assigned') {
            $technicians = get_post_meta($work_order_id, '_fsm_wo_assigned_technicians', true);
            if (empty($technicians)) {
                return new WP_Error(
                    'no_technicians',
                    __('Cannot assign work order without technicians.', 'fsm-work-orders'),
                    ['status' => 400]
                );
            }
        }

        return true;
    }

    /**
     * Handle transition side effects.
     *
     * @param int    $work_order_id Work order ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     */
    private static function handle_transition($work_order_id, $old_status, $new_status) {
        switch ($new_status) {
            case 'en_route':
                self::update_assigned_tech_status($work_order_id, 'en_route');
                break;

            case 'in_progress':
                // Record start time if not already set
                if (!get_post_meta($work_order_id, '_fsm_wo_started_at', true)) {
                    update_post_meta($work_order_id, '_fsm_wo_started_at', current_time('mysql'));
                }
                self::update_assigned_tech_status($work_order_id, 'on_job');
                break;

            case 'completed':
                // Record completion time
                update_post_meta($work_order_id, '_fsm_wo_completed_at', current_time('mysql'));
                self::update_assigned_tech_status($work_order_id, 'available');
                break;

            case 'on_hold':
                self::update_assigned_tech_status($work_order_id, 'available');
                break;

            case 'new':
                // Clear assignment when reopening
                if ($old_status === 'cancelled') {
                    update_post_meta($work_order_id, '_fsm_wo_assigned_technicians', []);
                    delete_post_meta($work_order_id, '_fsm_wo_primary_technician_id');
                    delete_post_meta($work_order_id, '_fsm_wo_started_at');
                    delete_post_meta($work_order_id, '_fsm_wo_completed_at');
                }
                break;
        }
    }

    /**
     * Update the status of assigned technicians when a WO transitions.
     *
     * @param int    $work_order_id Work order ID.
     * @param string $tech_status New technician status.
     */
    private static function update_assigned_tech_status($work_order_id, $tech_status) {
        $primary_id = get_post_meta($work_order_id, '_fsm_wo_primary_technician_id', true);
        if ($primary_id) {
            update_post_meta($primary_id, '_fsm_tech_status', sanitize_text_field($tech_status));
            update_post_meta($primary_id, '_fsm_status', sanitize_text_field($tech_status));
        }
    }

    /**
     * Add note about status change.
     *
     * @param int    $work_order_id Work order ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     */
    private static function add_status_change_note($work_order_id, $old_status, $new_status) {
        $user = wp_get_current_user();

        $content = sprintf(
            __('Status changed from "%s" to "%s"', 'fsm-work-orders'),
            FSM_Work_Orders::get_status_label($old_status),
            FSM_Work_Orders::get_status_label($new_status)
        );

        $comment_data = [
            'comment_post_ID' => $work_order_id,
            'comment_content' => $content,
            'comment_type' => 'fsm_note',
            'comment_approved' => 1,
            'user_id' => $user->ID,
            'comment_author' => $user->display_name ?: 'System',
            'comment_author_email' => $user->user_email ?: '',
        ];

        $comment_id = wp_insert_comment($comment_data);

        if ($comment_id) {
            update_comment_meta($comment_id, '_fsm_note_type', 'status_change');
        }
    }

    /**
     * Get status color for UI.
     *
     * @param string $status Status key.
     * @return string Hex color.
     */
    public static function get_status_color($status) {
        $colors = [
            'new' => '#3498db',        // Blue
            'assigned' => '#9b59b6',   // Purple
            'en_route' => '#f39c12',   // Orange
            'in_progress' => '#e67e22', // Dark Orange
            'on_hold' => '#95a5a6',    // Gray
            'completed' => '#27ae60',  // Green
            'cancelled' => '#e74c3c',  // Red
        ];

        return $colors[$status] ?? '#bdc3c7';
    }

    /**
     * Get status icon for UI.
     *
     * @param string $status Status key.
     * @return string Icon name.
     */
    public static function get_status_icon($status) {
        $icons = [
            'new' => 'plus-circle',
            'assigned' => 'user-check',
            'en_route' => 'truck',
            'in_progress' => 'tool',
            'on_hold' => 'pause-circle',
            'completed' => 'check-circle',
            'cancelled' => 'x-circle',
        ];

        return $icons[$status] ?? 'circle';
    }
}
