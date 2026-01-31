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
