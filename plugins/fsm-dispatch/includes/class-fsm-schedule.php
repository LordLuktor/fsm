<?php
/**
 * FSM Schedule
 *
 * Schedule management utilities.
 *
 * @package FSM\Dispatch
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Schedule class.
 */
class FSM_Schedule {

    /**
     * Get technician's schedule for a specific day.
     *
     * @param int    $technician_id Technician ID.
     * @param string $date Date (YYYY-MM-DD).
     * @return array
     */
    public static function get_technician_day($technician_id, $date) {
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
                    'value' => $technician_id,
                    'compare' => 'LIKE',
                ],
                [
                    'key' => '_fsm_wo_status',
                    'value' => 'cancelled',
                    'compare' => '!=',
                ],
            ],
            'orderby' => 'meta_value',
            'meta_key' => '_fsm_wo_scheduled_time_start',
            'order' => 'ASC',
        ]);

        $scheduled_items = [];
        $total_scheduled = 0;

        foreach ($work_orders as $wo) {
            $time_start = get_post_meta($wo->ID, '_fsm_wo_scheduled_time_start', true);
            $time_end = get_post_meta($wo->ID, '_fsm_wo_scheduled_time_end', true);
            $duration = get_post_meta($wo->ID, '_fsm_wo_estimated_duration', true) ?: 60;

            // Calculate end time from duration if not set
            if ($time_start && !$time_end) {
                $time_end = date('H:i', strtotime($time_start) + ($duration * 60));
            }

            $scheduled_items[] = [
                'work_order_id' => $wo->ID,
                'reference' => get_post_meta($wo->ID, '_fsm_wo_reference', true),
                'title' => $wo->post_title,
                'status' => get_post_meta($wo->ID, '_fsm_wo_status', true),
                'time_start' => $time_start,
                'time_end' => $time_end,
                'duration' => $duration,
            ];

            $total_scheduled += $duration;
        }

        return [
            'date' => $date,
            'work_orders' => $scheduled_items,
            'total_scheduled' => $total_scheduled,
            'work_order_count' => count($scheduled_items),
        ];
    }

    /**
     * Find available time slots.
     *
     * @param array $schedule Schedule data from get_technician_day().
     * @param int   $required_duration Required duration in minutes.
     * @return array
     */
    public static function find_available_slots($schedule, $required_duration) {
        $business_hours = FSM_Utilities::get_business_hours();
        $day_start = $business_hours['start'];
        $day_end = $business_hours['end'];

        $slots = [];
        $current_time = strtotime($schedule['date'] . ' ' . $day_start);
        $end_time = strtotime($schedule['date'] . ' ' . $day_end);

        // Sort work orders by start time
        $work_orders = $schedule['work_orders'];
        usort($work_orders, function($a, $b) {
            return strcmp($a['time_start'], $b['time_start']);
        });

        foreach ($work_orders as $wo) {
            if (empty($wo['time_start'])) {
                continue;
            }

            $wo_start = strtotime($schedule['date'] . ' ' . $wo['time_start']);
            $wo_end = strtotime($schedule['date'] . ' ' . $wo['time_end']);

            // Check for gap before this work order
            $gap_minutes = ($wo_start - $current_time) / 60;
            if ($gap_minutes >= $required_duration) {
                $slots[] = [
                    'start' => date('H:i', $current_time),
                    'end' => date('H:i', $wo_start),
                    'duration' => $gap_minutes,
                ];
            }

            $current_time = $wo_end;
        }

        // Check for gap after last work order
        $remaining = ($end_time - $current_time) / 60;
        if ($remaining >= $required_duration) {
            $slots[] = [
                'start' => date('H:i', $current_time),
                'end' => date('H:i', $end_time),
                'duration' => $remaining,
            ];
        }

        return $slots;
    }

    /**
     * Check for scheduling conflicts.
     *
     * @param int    $technician_id Technician ID.
     * @param string $date Date (YYYY-MM-DD).
     * @param string $time_start Start time (HH:MM).
     * @param string $time_end End time (HH:MM).
     * @param int    $exclude_wo_id Work order ID to exclude (for updates).
     * @return array|false False if no conflict, array of conflicting WO IDs if conflict.
     */
    public static function check_conflicts($technician_id, $date, $time_start, $time_end, $exclude_wo_id = 0) {
        $schedule = self::get_technician_day($technician_id, $date);
        $conflicts = [];

        $new_start = strtotime($date . ' ' . $time_start);
        $new_end = strtotime($date . ' ' . $time_end);

        foreach ($schedule['work_orders'] as $wo) {
            if ($wo['work_order_id'] == $exclude_wo_id) {
                continue;
            }

            if (empty($wo['time_start']) || empty($wo['time_end'])) {
                continue;
            }

            $wo_start = strtotime($date . ' ' . $wo['time_start']);
            $wo_end = strtotime($date . ' ' . $wo['time_end']);

            // Check for overlap
            if ($new_start < $wo_end && $new_end > $wo_start) {
                $conflicts[] = $wo['work_order_id'];
            }
        }

        return empty($conflicts) ? false : $conflicts;
    }

    /**
     * Get optimal route order for work orders.
     * Simple implementation - sorts by start time.
     * Could be enhanced with actual route optimization.
     *
     * @param array $work_orders Array of work order IDs.
     * @return array Sorted work order IDs.
     */
    public static function optimize_route($work_orders) {
        $with_data = [];

        foreach ($work_orders as $wo_id) {
            $with_data[] = [
                'id' => $wo_id,
                'time_start' => get_post_meta($wo_id, '_fsm_wo_scheduled_time_start', true),
                'lat' => get_post_meta($wo_id, '_fsm_wo_latitude', true),
                'lng' => get_post_meta($wo_id, '_fsm_wo_longitude', true),
            ];
        }

        // Sort by scheduled time
        usort($with_data, function($a, $b) {
            return strcmp($a['time_start'], $b['time_start']);
        });

        return array_column($with_data, 'id');
    }

    /**
     * Calculate estimated travel time between two work orders.
     * Simple estimation based on distance.
     *
     * @param int $wo_id_from Source work order ID.
     * @param int $wo_id_to Destination work order ID.
     * @return int Estimated minutes.
     */
    public static function estimate_travel_time($wo_id_from, $wo_id_to) {
        $lat1 = get_post_meta($wo_id_from, '_fsm_wo_latitude', true);
        $lng1 = get_post_meta($wo_id_from, '_fsm_wo_longitude', true);
        $lat2 = get_post_meta($wo_id_to, '_fsm_wo_latitude', true);
        $lng2 = get_post_meta($wo_id_to, '_fsm_wo_longitude', true);

        if (!$lat1 || !$lng1 || !$lat2 || !$lng2) {
            return 30; // Default estimate
        }

        $distance = FSM_Utilities::calculate_distance($lat1, $lng1, $lat2, $lng2, 'miles');

        // Rough estimate: 30 mph average speed
        $hours = $distance / 30;
        $minutes = ceil($hours * 60);

        return max(5, $minutes); // Minimum 5 minutes
    }
}
