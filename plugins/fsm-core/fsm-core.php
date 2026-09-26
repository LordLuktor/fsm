<?php
/**
 * Plugin Name: FSM Core
 * Plugin URI: https://github.com/lordluktor/fsm
 * Description: Core plugin for Field Service Management system. Provides shared CPTs, roles, capabilities, and utilities.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsm-core
 * Domain Path: /languages
 *
 * GitHub Plugin URI: lordluktor/fsm
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 *
 * @package FSM\Core
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Plugin constants
define('FSM_CORE_VERSION', '1.1.0');
define('FSM_CORE_FILE', __FILE__);
define('FSM_CORE_DIR', plugin_dir_path(__FILE__));
define('FSM_CORE_URL', plugin_dir_url(__FILE__));


/**
 * Restrict REST API CORS to known origins for FSM endpoints.
 */
add_action( 'rest_api_init', function() {
    add_filter( 'rest_pre_serve_request', function( $value ) {
        $origin = get_http_origin();
        $allowed_origins = array(
            'https://fsm.steinmetz.ltd',
            'https://apexweb.us',
            'https://www.apexweb.us',
        );

        if ( $origin && in_array( $origin, $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
            header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type' );
            header( 'Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link' );
            header( 'Vary: Origin' );
        }

        return $value;
    }, 15 );
}, 15 );

/**
 * Main FSM Core class.
 *
 * @since 1.0.0
 */
final class FSM_Core {

    /**
     * Plugin instance.
     *
     * @var FSM_Core
     */
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return FSM_Core
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once FSM_CORE_DIR . 'includes/class-fsm-roles.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-post-types.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-rest-api.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-account-api.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-customer-portal-api.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-domain-mapping.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-team-api.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-utilities.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-crypto.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-organizations.php';
        require_once FSM_CORE_DIR . 'includes/class-fsm-demo-data.php';

        // Admin includes
        if (is_admin()) {
            require_once FSM_CORE_DIR . 'admin/class-fsm-admin-menu.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(FSM_CORE_FILE, [$this, 'activate']);
        register_deactivation_hook(FSM_CORE_FILE, [$this, 'deactivate']);

        add_action('init', [$this, 'load_textdomain']);
        add_action('init', [FSM_Post_Types::class, 'register'], 5);
        add_action('init', [FSM_Organizations::class, 'register_post_type'], 5);
        add_action('init', [FSM_Organizations::class, 'register_meta'], 10);
        add_action('rest_api_init', [FSM_REST_API::class, 'register_routes']);

        // Organization filtering
        add_action('pre_get_posts', [FSM_Organizations::class, 'filter_posts_by_organization']);
        add_action('save_post', [FSM_Organizations::class, 'auto_set_organization'], 10, 3);

        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    /**
     * Load plugin text domain.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'fsm-core',
            false,
            dirname(plugin_basename(FSM_CORE_FILE)) . '/languages'
        );
    }

    /**
     * Activate plugin.
     */
    public function activate() {
        // Create roles and capabilities
        FSM_Roles::create_roles();

        // Register post types to flush rewrite rules properly
        FSM_Post_Types::register();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option('fsm_core_activated', true);
        update_option('fsm_core_version', FSM_CORE_VERSION);
    }

    /**
     * Deactivate plugin.
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Note: We don't remove roles/capabilities on deactivation
        // to preserve data. Use uninstall.php for complete removal.
    }

    /**
     * Display admin notices.
     */
    public function admin_notices() {
        // Show activation notice once
        if (get_option('fsm_core_activated')) {
            delete_option('fsm_core_activated');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php esc_html_e('FSM Core activated!', 'fsm-core'); ?></strong>
                    <?php esc_html_e('Field Service Management system is ready.', 'fsm-core'); ?>
                </p>
            </div>
            <?php
        }
    }
}

/**
 * Get FSM Core instance.
 *
 * @return FSM_Core
 */
function fsm_core() {
    return FSM_Core::instance();
}

// Initialize plugin
fsm_core();
