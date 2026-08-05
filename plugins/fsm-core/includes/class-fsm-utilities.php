<?php
/**
 * FSM Utilities
 *
 * Shared helper functions for FSM plugins.
 *
 * @package FSM\Core
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Utilities class.
 */
class FSM_Utilities {

    /**
     * Get technician post by user ID.
     *
     * @param int $user_id WordPress user ID.
     * @return WP_Post|null
     */
    public static function get_technician_by_user_id($user_id) {
        $technicians = get_posts([
            'post_type' => 'fsm_technician',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_fsm_user_id',
                    'value' => $user_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        return !empty($technicians) ? $technicians[0] : null;
    }

    /**
     * Get user by technician post ID.
     *
     * @param int $technician_id Technician post ID.
     * @return WP_User|null
     */
    public static function get_user_by_technician_id($technician_id) {
        $user_id = get_post_meta($technician_id, '_fsm_user_id', true);
        if (!$user_id) {
            return null;
        }
        return get_userdata($user_id) ?: null;
    }

    /**
     * Get all active technicians.
     *
     * @param array $args Additional query args.
     * @return array Array of technician posts.
     */
    public static function get_active_technicians($args = []) {
        $defaults = [
            'post_type' => 'fsm_technician',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_fsm_status',
                    'value' => ['available', 'busy', 'on_job', 'en_route'],
                    'compare' => 'IN',
                ],
            ],
        ];

        return get_posts(array_merge($defaults, $args));
    }

    /**
     * Get vehicle by technician.
     *
     * @param int $technician_id Technician post ID.
     * @return WP_Post|null
     */
    public static function get_technician_vehicle($technician_id) {
        $vehicle_id = get_post_meta($technician_id, '_fsm_assigned_vehicle_id', true);
        if (!$vehicle_id) {
            return null;
        }
        return get_post($vehicle_id);
    }

    /**
     * Format address for display.
     *
     * @param array $address Address components.
     * @return string
     */
    public static function format_address($address) {
        $parts = [];

        if (!empty($address['street'])) {
            $parts[] = $address['street'];
        }
        if (!empty($address['city'])) {
            $city_line = $address['city'];
            if (!empty($address['state'])) {
                $city_line .= ', ' . $address['state'];
            }
            if (!empty($address['zip'])) {
                $city_line .= ' ' . $address['zip'];
            }
            $parts[] = $city_line;
        }

        return implode("\n", $parts);
    }

    /**
     * Calculate distance between two coordinates (Haversine formula).
     *
     * @param float $lat1 Latitude 1.
     * @param float $lon1 Longitude 1.
     * @param float $lat2 Latitude 2.
     * @param float $lon2 Longitude 2.
     * @param string $unit Unit ('km' or 'miles').
     * @return float Distance.
     */
    public static function calculate_distance($lat1, $lon1, $lat2, $lon2, $unit = 'miles') {
        $earth_radius = $unit === 'km' ? 6371 : 3959;

        $lat1_rad = deg2rad($lat1);
        $lat2_rad = deg2rad($lat2);
        $delta_lat = deg2rad($lat2 - $lat1);
        $delta_lon = deg2rad($lon2 - $lon1);

        $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
             cos($lat1_rad) * cos($lat2_rad) *
             sin($delta_lon / 2) * sin($delta_lon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earth_radius * $c;
    }

    /**
     * Format duration in human-readable format.
     *
     * @param int $minutes Duration in minutes.
     * @return string
     */
    public static function format_duration($minutes) {
        if ($minutes < 60) {
            return sprintf(_n('%d minute', '%d minutes', $minutes, 'fsm-core'), $minutes);
        }

        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;

        if ($remaining_minutes === 0) {
            return sprintf(_n('%d hour', '%d hours', $hours, 'fsm-core'), $hours);
        }

        return sprintf(
            __('%d hr %d min', 'fsm-core'),
            $hours,
            $remaining_minutes
        );
    }

    /**
     * Generate unique reference number.
     *
     * @param string $prefix Prefix for the reference.
     * @return string
     */
    public static function generate_reference($prefix = 'WO') {
        return $prefix . '-' . date('Ymd') . '-' . strtoupper(wp_generate_password(6, false));
    }

    /**
     * Sanitize phone number.
     *
     * @param string $phone Phone number.
     * @return string
     */
    public static function sanitize_phone($phone) {
        // Remove all non-numeric characters
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Format as US phone if 10 digits
        if (strlen($digits) === 10) {
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6)
            );
        }

        return $phone;
    }

    /**
     * Check if date is today.
     *
     * @param string $date Date string.
     * @return bool
     */
    public static function is_today($date) {
        return date('Y-m-d', strtotime($date)) === current_time('Y-m-d');
    }

    /**
     * Get business hours.
     *
     * @return array
     */
    public static function get_business_hours() {
        return apply_filters('fsm_business_hours', [
            'start' => '08:00',
            'end' => '17:00',
            'days' => [1, 2, 3, 4, 5], // Monday - Friday
            'timezone' => wp_timezone_string(),
        ]);
    }

    /**
     * Check if current time is within business hours.
     *
     * @return bool
     */
    public static function is_business_hours() {
        $hours = self::get_business_hours();
        $now = current_time('H:i');
        $day = (int) current_time('w');

        return in_array($day, $hours['days'], true) &&
               $now >= $hours['start'] &&
               $now <= $hours['end'];
    }

    /**
     * Log FSM activity.
     *
     * @param string $action Action performed.
     * @param array  $data Additional data.
     * @param int    $user_id User who performed action.
     */
    public static function log_activity($action, $data = [], $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        $log_entry = [
            'action' => $action,
            'user_id' => $user_id,
            'data' => $data,
            'timestamp' => current_time('mysql'),
            'ip' => self::get_client_ip(),
        ];

        /**
         * Fires when FSM activity is logged.
         *
         * @param array $log_entry Log entry data.
         */
        do_action('fsm_activity_logged', $log_entry);

        // Also write to error log in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FSM Activity: ' . wp_json_encode($log_entry));
        }
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    public static function get_client_ip() {
        $ip = '';

        // Trust X-Forwarded-For (set by Traefik), then fall back to REMOTE_ADDR.
        // Do NOT trust HTTP_CLIENT_IP as it is attacker-controlled.
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Clear FSM caches.
     *
     * @param string $type Cache type to clear (all, work_orders, technicians, etc.).
     */
    public static function clear_cache($type = 'all') {
        $cache_groups = [
            'fsm_work_orders',
            'fsm_technicians',
            'fsm_vehicles',
            'fsm_inventory',
            'fsm_dispatch',
        ];

        if ($type === 'all') {
            foreach ($cache_groups as $group) {
                wp_cache_flush_group($group);
            }
        } else {
            wp_cache_flush_group("fsm_{$type}");
        }

        /**
         * Fires when FSM cache is cleared.
         *
         * @param string $type Cache type cleared.
         */
        do_action('fsm_cache_cleared', $type);
    }

    /**
     * Get cached data or compute and cache.
     *
     * @param string   $key Cache key.
     * @param callable $callback Function to compute value if not cached.
     * @param string   $group Cache group.
     * @param int      $expiration Cache expiration in seconds.
     * @return mixed
     */
    public static function get_cached($key, $callback, $group = 'fsm', $expiration = HOUR_IN_SECONDS) {
        $cached = wp_cache_get($key, $group);

        if (false !== $cached) {
            return $cached;
        }

        $value = call_user_func($callback);
        wp_cache_set($key, $value, $group, $expiration);

        return $value;
    }
}
