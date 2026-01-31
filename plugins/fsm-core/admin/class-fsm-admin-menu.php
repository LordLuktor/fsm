<?php
/**
 * FSM Admin Menu
 *
 * Creates the FSM admin menu structure.
 *
 * @package FSM\Core
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Admin Menu class.
 */
class FSM_Admin_Menu {

    /**
     * Initialize admin menu.
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    /**
     * Register admin menu.
     */
    public static function register_menu() {
        // Main FSM menu
        add_menu_page(
            __('FSM Dashboard', 'fsm-core'),
            __('FSM', 'fsm-core'),
            'fsm_access',
            'fsm-dashboard',
            [__CLASS__, 'render_dashboard'],
            'dashicons-clipboard',
            25
        );

        // Dashboard submenu (same as parent)
        add_submenu_page(
            'fsm-dashboard',
            __('Dashboard', 'fsm-core'),
            __('Dashboard', 'fsm-core'),
            'fsm_access',
            'fsm-dashboard',
            [__CLASS__, 'render_dashboard']
        );

        // Settings submenu
        add_submenu_page(
            'fsm-dashboard',
            __('FSM Settings', 'fsm-core'),
            __('Settings', 'fsm-core'),
            'fsm_manage_settings',
            'fsm-settings',
            [__CLASS__, 'render_settings']
        );
    }

    /**
     * Render dashboard page.
     */
    public static function render_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('FSM Dashboard', 'fsm-core'); ?></h1>

            <div class="fsm-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">

                <div class="card">
                    <h2><?php esc_html_e('Quick Stats', 'fsm-core'); ?></h2>
                    <?php self::render_quick_stats(); ?>
                </div>

                <div class="card">
                    <h2><?php esc_html_e('System Status', 'fsm-core'); ?></h2>
                    <?php self::render_system_status(); ?>
                </div>

                <div class="card">
                    <h2><?php esc_html_e('Quick Actions', 'fsm-core'); ?></h2>
                    <p>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=fsm_work_order')); ?>" class="button button-primary">
                            <?php esc_html_e('New Work Order', 'fsm-core'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=fsm_work_order')); ?>" class="button">
                            <?php esc_html_e('View All Work Orders', 'fsm-core'); ?>
                        </a>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=fsm_technician')); ?>" class="button">
                            <?php esc_html_e('Manage Technicians', 'fsm-core'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=fsm_vehicle')); ?>" class="button">
                            <?php esc_html_e('Manage Vehicles', 'fsm-core'); ?>
                        </a>
                    </p>
                </div>

                <div class="card">
                    <h2><?php esc_html_e('Frontend Access', 'fsm-core'); ?></h2>
                    <p><?php esc_html_e('Access the React frontend dashboard:', 'fsm-core'); ?></p>
                    <p>
                        <a href="https://fsm.steinmetz.ltd" target="_blank" class="button button-primary">
                            <?php esc_html_e('Open FSM Frontend', 'fsm-core'); ?>
                        </a>
                    </p>
                    <p class="description">
                        <?php esc_html_e('The frontend provides technician and dispatch dashboards.', 'fsm-core'); ?>
                    </p>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Render quick stats.
     */
    private static function render_quick_stats() {
        $work_order_count = wp_count_posts('fsm_work_order');
        $technician_count = wp_count_posts('fsm_technician');
        $vehicle_count = wp_count_posts('fsm_vehicle');

        ?>
        <ul>
            <li>
                <strong><?php esc_html_e('Work Orders:', 'fsm-core'); ?></strong>
                <?php
                if (isset($work_order_count->publish)) {
                    printf(
                        esc_html__('%d active', 'fsm-core'),
                        $work_order_count->publish
                    );
                } else {
                    esc_html_e('0 active', 'fsm-core');
                }
                ?>
            </li>
            <li>
                <strong><?php esc_html_e('Technicians:', 'fsm-core'); ?></strong>
                <?php
                printf(
                    esc_html__('%d total', 'fsm-core'),
                    isset($technician_count->publish) ? $technician_count->publish : 0
                );
                ?>
            </li>
            <li>
                <strong><?php esc_html_e('Vehicles:', 'fsm-core'); ?></strong>
                <?php
                printf(
                    esc_html__('%d total', 'fsm-core'),
                    isset($vehicle_count->publish) ? $vehicle_count->publish : 0
                );
                ?>
            </li>
        </ul>
        <?php
    }

    /**
     * Render system status.
     */
    private static function render_system_status() {
        $plugins = [
            'fsm-core' => defined('FSM_CORE_VERSION') ? FSM_CORE_VERSION : false,
            'fsm-work-orders' => defined('FSM_WORK_ORDERS_VERSION') ? FSM_WORK_ORDERS_VERSION : false,
            'fsm-dispatch' => defined('FSM_DISPATCH_VERSION') ? FSM_DISPATCH_VERSION : false,
            'fsm-inventory' => defined('FSM_INVENTORY_VERSION') ? FSM_INVENTORY_VERSION : false,
            'fsm-gps' => defined('FSM_GPS_VERSION') ? FSM_GPS_VERSION : false,
            'fsm-ptt' => defined('FSM_PTT_VERSION') ? FSM_PTT_VERSION : false,
        ];

        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'fsm-core'); ?></th>
                    <th><?php esc_html_e('Status', 'fsm-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $name => $version) : ?>
                <tr>
                    <td><code><?php echo esc_html($name); ?></code></td>
                    <td>
                        <?php if ($version) : ?>
                            <span style="color: green;">&#10003;</span>
                            <?php echo esc_html($version); ?>
                        <?php else : ?>
                            <span style="color: gray;">&#8212;</span>
                            <?php esc_html_e('Not loaded', 'fsm-core'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render settings page.
     */
    public static function render_settings() {
        if (!current_user_can('fsm_manage_settings')) {
            wp_die(__('You do not have permission to access this page.', 'fsm-core'));
        }

        // Handle settings save
        if (isset($_POST['fsm_save_settings']) && check_admin_referer('fsm_settings_nonce')) {
            self::save_settings();
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'fsm-core') . '</p></div>';
        }

        $settings = get_option('fsm_settings', []);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('FSM Settings', 'fsm-core'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('fsm_settings_nonce'); ?>

                <h2><?php esc_html_e('Business Settings', 'fsm-core'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="business_name"><?php esc_html_e('Business Name', 'fsm-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="business_name" name="fsm_settings[business_name]"
                                   value="<?php echo esc_attr($settings['business_name'] ?? ''); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="business_phone"><?php esc_html_e('Business Phone', 'fsm-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="business_phone" name="fsm_settings[business_phone]"
                                   value="<?php echo esc_attr($settings['business_phone'] ?? ''); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="timezone"><?php esc_html_e('Timezone', 'fsm-core'); ?></label>
                        </th>
                        <td>
                            <select id="timezone" name="fsm_settings[timezone]">
                                <?php echo wp_timezone_choice($settings['timezone'] ?? wp_timezone_string()); ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Work Order Settings', 'fsm-core'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wo_prefix"><?php esc_html_e('Work Order Prefix', 'fsm-core'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="wo_prefix" name="fsm_settings[wo_prefix]"
                                   value="<?php echo esc_attr($settings['wo_prefix'] ?? 'WO'); ?>"
                                   class="small-text">
                            <p class="description">
                                <?php esc_html_e('Prefix for work order reference numbers (e.g., WO-20240101-ABC123)', 'fsm-core'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default_duration"><?php esc_html_e('Default Duration (minutes)', 'fsm-core'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="default_duration" name="fsm_settings[default_duration]"
                                   value="<?php echo esc_attr($settings['default_duration'] ?? '60'); ?>"
                                   class="small-text" min="15" step="15">
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="fsm_save_settings" class="button button-primary"
                           value="<?php esc_attr_e('Save Settings', 'fsm-core'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Save settings.
     */
    private static function save_settings() {
        if (!isset($_POST['fsm_settings'])) {
            return;
        }

        $settings = array_map('sanitize_text_field', wp_unslash($_POST['fsm_settings']));
        update_option('fsm_settings', $settings);

        FSM_Utilities::clear_cache('all');
    }
}

// Initialize admin menu
FSM_Admin_Menu::init();
