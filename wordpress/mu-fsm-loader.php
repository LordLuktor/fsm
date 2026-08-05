<?php
/**
 * FSM Plugin Loader
 *
 * Must-use plugin that loads all FSM plugins from the mounted volume.
 * This ensures FSM plugins are always active and loaded in the correct order.
 *
 * @package FSM
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Load secrets from Docker secrets files.
 * This runs early to define constants before plugins load.
 */
function fsm_load_docker_secrets() {
    // Load JWT secret
    $jwt_secret_file = '/run/secrets/jwt_secret';
    if (file_exists($jwt_secret_file) && !defined('JWT_AUTH_SECRET_KEY')) {
        $jwt_secret = trim(file_get_contents($jwt_secret_file));
        if (!empty($jwt_secret)) {
            define('JWT_AUTH_SECRET_KEY', $jwt_secret);
        }
    }

    // Load Mumble API key
    $mumble_key_file = '/run/secrets/fsm_mumble_api_key';
    if (file_exists($mumble_key_file) && !defined('FSM_MUMBLE_API_KEY')) {
        $mumble_key = trim(file_get_contents($mumble_key_file));
        if (!empty($mumble_key)) {
            define('FSM_MUMBLE_API_KEY', $mumble_key);
        }
    }

    // Also check environment variables (for fallback/testing)
    if (!defined('FSM_MUMBLE_API_KEY') && getenv('FSM_MUMBLE_API_KEY')) {
        define('FSM_MUMBLE_API_KEY', getenv('FSM_MUMBLE_API_KEY'));
    }
}

// Load secrets immediately
fsm_load_docker_secrets();

/**
 * Load FSM plugins in dependency order.
 */
function fsm_load_plugins() {
    $fsm_plugin_dir = WP_CONTENT_DIR . '/plugins/fsm';

    if (!is_dir($fsm_plugin_dir)) {
        return;
    }

    // Define load order (core must load first)
    $load_order = [
        'fsm-core/fsm-core.php',
        'fsm-work-orders/fsm-work-orders.php',
        'fsm-dispatch/fsm-dispatch.php',
        'fsm-inventory/fsm-inventory.php',
        'fsm-billing/fsm-billing.php',
        'fsm-gps/fsm-gps.php',
        'fsm-ptt/fsm-ptt.php',
    ];

    foreach ($load_order as $plugin_file) {
        $full_path = $fsm_plugin_dir . '/' . $plugin_file;
        if (file_exists($full_path)) {
            require_once $full_path;
        }
    }
}

// Load FSM plugins after WordPress is fully initialized
add_action('plugins_loaded', 'fsm_load_plugins', 1);

/**
 * Load Tracker plugins in dependency order.
 * Tracker plugins are mounted individually at wp-content/plugins/tracker-*
 */
function tracker_load_plugins() {
    $plugin_dir = WP_CONTENT_DIR . '/plugins';

    // Define load order (core must load first, subscription before feature-gated plugins)
    $load_order = [
        'tracker-core/tracker-core.php',
        'tracker-subscription/tracker-subscription.php',
        'tracker-gps/tracker-gps.php',
        'tracker-geofence/tracker-geofence.php',
        'tracker-sharing/tracker-sharing.php',
    ];

    foreach ($load_order as $plugin_file) {
        $full_path = $plugin_dir . '/' . $plugin_file;
        if (file_exists($full_path)) {
            require_once $full_path;
        }
    }
}

// Load Tracker plugins after FSM plugins (priority 2)
add_action('plugins_loaded', 'tracker_load_plugins', 2);

/**
 * Create tracker custom database tables on first load.
 * Runs once via a transient check so activation hooks aren't needed.
 */
function tracker_maybe_create_tables() {
    $installed_version = get_option('tracker_db_version', '0');
    $current_version = '1.0.0';

    if (version_compare($installed_version, $current_version, '>=')) {
        return;
    }

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // GPS locations table (tracker-gps)
    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}tracker_gps_locations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        family_id BIGINT UNSIGNED NOT NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        altitude DECIMAL(10,2) DEFAULT NULL,
        accuracy DECIMAL(10,2) DEFAULT NULL,
        speed DECIMAL(10,2) DEFAULT NULL,
        heading DECIMAL(5,2) DEFAULT NULL,
        battery_level TINYINT UNSIGNED DEFAULT NULL,
        recorded_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_user_family (user_id, family_id),
        KEY idx_recorded_at (recorded_at),
        KEY idx_family_recorded (family_id, recorded_at)
    ) $charset_collate;";
    dbDelta($sql);

    // Geofences table (tracker-geofence)
    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}tracker_geofences (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        family_id BIGINT UNSIGNED NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        name VARCHAR(100) NOT NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        radius INT UNSIGNED NOT NULL DEFAULT 200,
        notify_on_enter TINYINT(1) NOT NULL DEFAULT 1,
        notify_on_exit TINYINT(1) NOT NULL DEFAULT 1,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_family (family_id)
    ) $charset_collate;";
    dbDelta($sql);

    // Geofence alerts table (tracker-geofence)
    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}tracker_alerts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        family_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        geofence_id BIGINT UNSIGNED DEFAULT NULL,
        alert_type VARCHAR(20) NOT NULL DEFAULT 'geofence',
        message TEXT NOT NULL,
        latitude DECIMAL(10,7) DEFAULT NULL,
        longitude DECIMAL(10,7) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_family_read (family_id, is_read),
        KEY idx_created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql);

    // Share links table (tracker-sharing)
    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}tracker_shares (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        family_id BIGINT UNSIGNED NOT NULL,
        token VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_token (token),
        KEY idx_user (user_id),
        KEY idx_expires (expires_at)
    ) $charset_collate;";
    dbDelta($sql);

    // Saved places table (tracker-sharing)
    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}tracker_places (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        family_id BIGINT UNSIGNED NOT NULL,
        created_by BIGINT UNSIGNED NOT NULL,
        name VARCHAR(100) NOT NULL,
        latitude DECIMAL(10,7) NOT NULL,
        longitude DECIMAL(10,7) NOT NULL,
        icon VARCHAR(50) DEFAULT 'pin',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_family (family_id)
    ) $charset_collate;";
    dbDelta($sql);

    update_option('tracker_db_version', $current_version);
}

// Create tables on init (only runs when version changes)
add_action('init', 'tracker_maybe_create_tables');

/**
 * Ensure PMPro "Premium" and "Ultimate" membership levels exist.
 * Creates them programmatically if missing (idempotent).
 */
function tracker_ensure_pmpro_levels() {
    // PMPro must be active
    if (!function_exists('pmpro_getLevel')) {
        return;
    }

    global $wpdb;

    // --- Free level: $0, no billing ---
    if (!get_option('tracker_pmpro_free_level_created')) {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = %s LIMIT 1",
                'Free'
            )
        );

        if ($existing) {
            update_option('tracker_pmpro_free_level_created', $existing);
        } else {
            $wpdb->insert(
                $wpdb->pmpro_membership_levels,
                array(
                    'name'                => 'Free',
                    'description'         => 'Free GPS Tracker membership with basic tracking, live map, SOS, and up to 2 family members.',
                    'allow_signups'       => 1,
                    'initial_payment'     => 0,
                    'billing_amount'      => 0,
                    'cycle_number'        => 0,
                    'cycle_period'        => '',
                    'billing_limit'       => 0,
                    'trial_amount'        => 0,
                    'trial_limit'         => 0,
                    'expiration_number'   => 0,
                    'expiration_period'   => '',
                ),
                array('%s', '%s', '%d', '%f', '%f', '%d', '%s', '%d', '%f', '%d', '%d', '%s')
            );

            $level_id = $wpdb->insert_id;
            if ($level_id) {
                update_option('tracker_pmpro_free_level_created', $level_id);
            }
        }
    }

    // --- Premium level: $9.99/mo ---
    if (!get_option('tracker_pmpro_level_created')) {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = %s LIMIT 1",
                'Premium'
            )
        );

        if ($existing) {
            update_option('tracker_pmpro_level_created', $existing);
        } else {
            $wpdb->insert(
                $wpdb->pmpro_membership_levels,
                array(
                    'name'                => 'Premium',
                    'description'         => 'Premium GPS Tracker membership with geofences, location history, sharing, and up to 8 family members.',
                    'allow_signups'       => 1,
                    'initial_payment'     => 9.99,
                    'billing_amount'      => 9.99,
                    'cycle_number'        => 1,
                    'cycle_period'        => 'Month',
                    'billing_limit'       => 0,
                    'trial_amount'        => 0,
                    'trial_limit'         => 0,
                    'expiration_number'   => 0,
                    'expiration_period'   => '',
                ),
                array('%s', '%s', '%d', '%f', '%f', '%d', '%s', '%d', '%f', '%d', '%d', '%s')
            );

            $level_id = $wpdb->insert_id;
            if ($level_id) {
                update_option('tracker_pmpro_level_created', $level_id);
            }
        }
    }

    // --- Ultimate level: $24.95/mo ---
    if (!get_option('tracker_pmpro_ultimate_level_created')) {
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->pmpro_membership_levels} WHERE name = %s LIMIT 1",
                'Ultimate'
            )
        );

        if ($existing) {
            update_option('tracker_pmpro_ultimate_level_created', $existing);
        } else {
            $wpdb->insert(
                $wpdb->pmpro_membership_levels,
                array(
                    'name'                => 'Ultimate',
                    'description'         => 'Ultimate GPS Tracker membership with all Premium features plus Push-to-Talk and unlimited members.',
                    'allow_signups'       => 1,
                    'initial_payment'     => 24.95,
                    'billing_amount'      => 24.95,
                    'cycle_number'        => 1,
                    'cycle_period'        => 'Month',
                    'billing_limit'       => 0,
                    'trial_amount'        => 0,
                    'trial_limit'         => 0,
                    'expiration_number'   => 0,
                    'expiration_period'   => '',
                ),
                array('%s', '%s', '%d', '%f', '%f', '%d', '%s', '%d', '%f', '%d', '%d', '%s')
            );

            $level_id = $wpdb->insert_id;
            if ($level_id) {
                update_option('tracker_pmpro_ultimate_level_created', $level_id);
            }
        }
    }
}

add_action('init', 'tracker_ensure_pmpro_levels', 20);
