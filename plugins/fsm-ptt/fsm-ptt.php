<?php
/**
 * Plugin Name: FSM PTT Communication
 * Plugin URI: https://github.com/lordluktor/fsm
 * Description: Push-to-Talk communication via Mumble for Field Service Management system.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Steinmetz Ltd
 * Author URI: https://steinmetz.ltd
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fsm-ptt
 * Domain Path: /languages
 *
 * GitHub Plugin URI: lordluktor/fsm
 * GitHub Branch: main
 * Primary Branch: main
 * Release Asset: true
 *
 * @package FSM\PTT
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// Ensure FSM Core is loaded
if (!defined('FSM_CORE_VERSION')) {
    return;
}

// Plugin constants
define('FSM_PTT_VERSION', '1.0.0');
define('FSM_PTT_FILE', __FILE__);
define('FSM_PTT_DIR', plugin_dir_path(__FILE__));
define('FSM_PTT_URL', plugin_dir_url(__FILE__));

/**
 * Main FSM PTT class.
 *
 * @since 1.0.0
 */
final class FSM_PTT {

    /**
     * Plugin instance.
     *
     * @var FSM_PTT
     */
    private static $instance = null;

    /**
     * Database version.
     */
    const DB_VERSION = '1.0.0';

    /**
     * Get plugin instance.
     *
     * @return FSM_PTT
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
        require_once FSM_PTT_DIR . 'includes/class-fsm-ptt-db.php';
        require_once FSM_PTT_DIR . 'includes/class-fsm-ptt-rest-api.php';
        require_once FSM_PTT_DIR . 'includes/class-fsm-mumble-integration.php';
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(FSM_PTT_FILE, [$this, 'activate']);
        add_action('rest_api_init', [FSM_PTT_REST_API::class, 'register_routes']);

        // Mumble integration hooks
        add_action('fsm_organization_created', [$this, 'provision_organization_in_mumble'], 10, 3);
        add_action('fsm_user_organization_changed', [$this, 'sync_user_to_mumble'], 10, 3);

        // Clean up on organization delete
        add_action('before_delete_post', [$this, 'deprovision_organization_from_mumble']);
    }

    /**
     * Provision organization in Mumble when created in FSM.
     *
     * @param int    $org_id Organization ID.
     * @param string $name   Organization name.
     * @param string $slug   Organization slug.
     */
    public function provision_organization_in_mumble($org_id, $name, $slug) {
        // Check if Mumble integration is available
        if (!FSM_Mumble_Integration::is_available()) {
            FSM_Utilities::log_activity('mumble_provision_skipped', [
                'org_id' => $org_id,
                'reason' => 'Mumble backend not available',
            ]);
            return;
        }

        // Provision in Mumble
        $result = FSM_Mumble_Integration::provision_organization($org_id, $name, $slug);

        if (is_wp_error($result)) {
            FSM_Utilities::log_activity('mumble_provision_failed', [
                'org_id' => $org_id,
                'error' => $result->get_error_message(),
            ]);
        }
    }

    /**
     * Sync user to Mumble when organization changes.
     *
     * @param int      $user_id      User ID.
     * @param int      $org_id       New organization ID.
     * @param int|null $previous_org Previous organization ID.
     */
    public function sync_user_to_mumble($user_id, $org_id, $previous_org) {
        // Check if Mumble integration is available
        if (!FSM_Mumble_Integration::is_available()) {
            return;
        }

        // Remove from previous org if applicable
        if ($previous_org && FSM_Mumble_Integration::is_user_registered($user_id)) {
            FSM_Mumble_Integration::remove_user_from_organization($user_id, $previous_org);
        }

        // Register in new org
        if ($org_id && FSM_Mumble_Integration::is_organization_provisioned($org_id)) {
            FSM_Mumble_Integration::register_user($user_id, $org_id);
        }
    }

    /**
     * Deprovision organization from Mumble when deleted.
     *
     * @param int $post_id Post ID.
     */
    public function deprovision_organization_from_mumble($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'fsm_organization') {
            return;
        }

        if (!FSM_Mumble_Integration::is_organization_provisioned($post_id)) {
            // Nothing was ever provisioned for this org — nothing to clean up.
            return;
        }

        if (!FSM_Mumble_Integration::is_available()) {
            // Mumble is unreachable right now. Previously this silently gave up,
            // leaving the channel orphaned forever with no record of the failure.
            // Log it so orphaned channels are traceable instead of invisible.
            FSM_Utilities::log_activity('mumble_deprovision_skipped', [
                'org_id' => $post_id,
                'reason' => 'Mumble backend not available',
                'root_channel_id' => get_post_meta($post_id, '_fsm_mumble_root_channel_id', true),
            ]);
            return;
        }

        $result = FSM_Mumble_Integration::deprovision_organization($post_id);

        if (is_wp_error($result)) {
            FSM_Utilities::log_activity('mumble_deprovision_failed', [
                'org_id' => $post_id,
                'error' => $result->get_error_message(),
                'root_channel_id' => get_post_meta($post_id, '_fsm_mumble_root_channel_id', true),
            ]);
        }
    }

    /**
     * Activate plugin.
     */
    public function activate() {
        FSM_PTT_DB::create_tables();
        update_option('fsm_ptt_db_version', self::DB_VERSION);
        update_option('fsm_ptt_version', FSM_PTT_VERSION);
    }

    /**
     * Get Mumble server host.
     *
     * @return string
     */
    public static function get_mumble_host() {
        return defined('FSM_MUMBLE_HOST') ? FSM_MUMBLE_HOST : 'mumble_mumble-server';
    }

    /**
     * Get Mumble server port.
     *
     * @return int
     */
    public static function get_mumble_port() {
        return defined('FSM_MUMBLE_PORT') ? FSM_MUMBLE_PORT : 64738;
    }

    /**
     * Get channels table name.
     *
     * @return string
     */
    public static function get_channels_table() {
        global $wpdb;
        return $wpdb->prefix . 'fsm_ptt_channels';
    }
}

/**
 * Get FSM PTT instance.
 *
 * @return FSM_PTT
 */
function fsm_ptt() {
    return FSM_PTT::instance();
}

// Initialize plugin
fsm_ptt();
