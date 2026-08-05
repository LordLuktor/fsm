<?php
/**
 * FSM Headless Mode
 *
 * Configures WordPress to operate in headless mode:
 * - Disables frontend theme rendering
 * - Redirects frontend requests to API documentation
 * - Enables CORS for REST API
 * - Disables unnecessary frontend features
 *
 * @package FSM
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Only run in headless mode
if (!defined('FSM_HEADLESS_MODE') || !FSM_HEADLESS_MODE) {
    return;
}

/**
 * Redirect frontend requests to REST API root or admin.
 * Keep /wp-admin and /wp-json accessible.
 */
function fsm_headless_redirect() {
    // Allow admin, login, REST API, and cron
    if (
        is_admin() ||
        strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/wp-login') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/wp-json') !== false ||
        strpos($_SERVER['REQUEST_URI'], '/wp-cron') !== false ||
        defined('REST_REQUEST') ||
        defined('DOING_CRON') ||
        defined('DOING_AJAX')
    ) {
        return;
    }

    // Redirect root to REST API discovery
    if ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '') {
        wp_redirect(rest_url());
        exit;
    }

    // Return 404 JSON for other frontend requests
    status_header(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => 'rest_no_route',
        'message' => 'FSM operates in headless mode. Use the REST API at /wp-json/',
        'data' => [
            'status' => 404,
            'api_root' => rest_url(),
            'documentation' => rest_url('fsm/v1'),
        ],
    ]);
    exit;
}
add_action('template_redirect', 'fsm_headless_redirect', 1);

/**
 * Add CORS headers for REST API requests.
 */
function fsm_cors_headers() {
    // Only for REST API requests
    if (!defined('REST_REQUEST') || !REST_REQUEST) {
        return;
    }

    // Allow the React frontend origins
    $allowed_origins = [
        'https://fsm.steinmetz.ltd',
        'https://tracker.steinmetz.ltd',
    ];
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $allowed_origins[] = 'http://localhost:5173'; // Vite dev server
        $allowed_origins[] = 'http://localhost:3000'; // Alternative dev server
    }

    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

    if (in_array($origin, $allowed_origins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);
        exit;
    }
}
add_action('rest_api_init', 'fsm_cors_headers', 1);
add_action('send_headers', 'fsm_cors_headers', 1);

/**
 * Disable oEmbed discovery (not needed in headless).
 */
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_oembed_add_host_js');

/**
 * Disable REST API link in headers (we handle this ourselves).
 */
// Keep REST API link for discoverability
// remove_action('template_redirect', 'rest_output_link_header', 11);

/**
 * Disable XML-RPC (security).
 */
add_filter('xmlrpc_enabled', '__return_false');

/**
 * Remove WordPress version from REST API.
 */
function fsm_remove_wp_version($response) {
    if (isset($response->data['generator'])) {
        unset($response->data['generator']);
    }
    return $response;
}
add_filter('rest_index', 'fsm_remove_wp_version');

/**
 * Disable emoji scripts (not needed in headless).
 */
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');

/**
 * Log REST API errors for debugging.
 */
function fsm_log_rest_errors($response, $handler, $request) {
    if (is_wp_error($response)) {
        error_log(sprintf(
            'FSM REST API Error: %s - %s - %s',
            $request->get_method(),
            $request->get_route(),
            $response->get_error_message()
        ));
    }
    return $response;
}
add_filter('rest_request_after_callbacks', 'fsm_log_rest_errors', 10, 3);
