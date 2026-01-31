<?php
/**
 * FSM Work Order Admin
 *
 * Admin interface customizations for work orders.
 *
 * @package FSM\WorkOrders
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Work Order Admin class.
 */
class FSM_Work_Order_Admin {

    /**
     * Initialize admin hooks.
     */
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_fsm_work_order', [__CLASS__, 'save_meta_boxes'], 10, 2);
        add_filter('manage_fsm_work_order_posts_columns', [__CLASS__, 'add_columns']);
        add_action('manage_fsm_work_order_posts_custom_column', [__CLASS__, 'render_columns'], 10, 2);
        add_filter('manage_edit-fsm_work_order_sortable_columns', [__CLASS__, 'sortable_columns']);
    }

    /**
     * Add meta boxes.
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'fsm_work_order_details',
            __('Work Order Details', 'fsm-work-orders'),
            [__CLASS__, 'render_details_meta_box'],
            'fsm_work_order',
            'normal',
            'high'
        );

        add_meta_box(
            'fsm_work_order_customer',
            __('Customer Information', 'fsm-work-orders'),
            [__CLASS__, 'render_customer_meta_box'],
            'fsm_work_order',
            'normal',
            'default'
        );

        add_meta_box(
            'fsm_work_order_assignment',
            __('Assignment', 'fsm-work-orders'),
            [__CLASS__, 'render_assignment_meta_box'],
            'fsm_work_order',
            'side',
            'default'
        );
    }

    /**
     * Render details meta box.
     *
     * @param WP_Post $post Post object.
     */
    public static function render_details_meta_box($post) {
        wp_nonce_field('fsm_work_order_nonce', 'fsm_work_order_nonce');

        $reference = get_post_meta($post->ID, '_fsm_wo_reference', true);
        $status = get_post_meta($post->ID, '_fsm_wo_status', true) ?: 'new';
        $priority = get_post_meta($post->ID, '_fsm_wo_priority', true) ?: 'normal';
        $scheduled_date = get_post_meta($post->ID, '_fsm_wo_scheduled_date', true);
        $scheduled_time_start = get_post_meta($post->ID, '_fsm_wo_scheduled_time_start', true);
        $scheduled_time_end = get_post_meta($post->ID, '_fsm_wo_scheduled_time_end', true);
        $estimated_duration = get_post_meta($post->ID, '_fsm_wo_estimated_duration', true) ?: 60;

        ?>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('Reference', 'fsm-work-orders'); ?></label></th>
                <td>
                    <code><?php echo esc_html($reference ?: __('Auto-generated on save', 'fsm-work-orders')); ?></code>
                </td>
            </tr>
            <tr>
                <th><label for="fsm_wo_status"><?php esc_html_e('Status', 'fsm-work-orders'); ?></label></th>
                <td>
                    <select name="fsm_wo_status" id="fsm_wo_status">
                        <?php foreach (FSM_Work_Orders::get_statuses() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="fsm_wo_priority"><?php esc_html_e('Priority', 'fsm-work-orders'); ?></label></th>
                <td>
                    <select name="fsm_wo_priority" id="fsm_wo_priority">
                        <option value="low" <?php selected($priority, 'low'); ?>><?php esc_html_e('Low', 'fsm-work-orders'); ?></option>
                        <option value="normal" <?php selected($priority, 'normal'); ?>><?php esc_html_e('Normal', 'fsm-work-orders'); ?></option>
                        <option value="high" <?php selected($priority, 'high'); ?>><?php esc_html_e('High', 'fsm-work-orders'); ?></option>
                        <option value="urgent" <?php selected($priority, 'urgent'); ?>><?php esc_html_e('Urgent', 'fsm-work-orders'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="fsm_wo_scheduled_date"><?php esc_html_e('Scheduled Date', 'fsm-work-orders'); ?></label></th>
                <td>
                    <input type="date" name="fsm_wo_scheduled_date" id="fsm_wo_scheduled_date"
                           value="<?php echo esc_attr($scheduled_date); ?>">
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Scheduled Time', 'fsm-work-orders'); ?></label></th>
                <td>
                    <input type="time" name="fsm_wo_scheduled_time_start" id="fsm_wo_scheduled_time_start"
                           value="<?php echo esc_attr($scheduled_time_start); ?>">
                    <?php esc_html_e('to', 'fsm-work-orders'); ?>
                    <input type="time" name="fsm_wo_scheduled_time_end" id="fsm_wo_scheduled_time_end"
                           value="<?php echo esc_attr($scheduled_time_end); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="fsm_wo_estimated_duration"><?php esc_html_e('Est. Duration (minutes)', 'fsm-work-orders'); ?></label></th>
                <td>
                    <input type="number" name="fsm_wo_estimated_duration" id="fsm_wo_estimated_duration"
                           value="<?php echo esc_attr($estimated_duration); ?>" min="15" step="15" class="small-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render customer meta box.
     *
     * @param WP_Post $post Post object.
     */
    public static function render_customer_meta_box($post) {
        $customer_name = get_post_meta($post->ID, '_fsm_wo_customer_name', true);
        $customer_phone = get_post_meta($post->ID, '_fsm_wo_customer_phone', true);
        $customer_email = get_post_meta($post->ID, '_fsm_wo_customer_email', true);
        $address_street = get_post_meta($post->ID, '_fsm_wo_address_street', true);
        $address_city = get_post_meta($post->ID, '_fsm_wo_address_city', true);
        $address_state = get_post_meta($post->ID, '_fsm_wo_address_state', true);
        $address_zip = get_post_meta($post->ID, '_fsm_wo_address_zip', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="fsm_wo_customer_name"><?php esc_html_e('Customer Name', 'fsm-work-orders'); ?></label></th>
                <td>
                    <input type="text" name="fsm_wo_customer_name" id="fsm_wo_customer_name"
                           value="<?php echo esc_attr($customer_name); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="fsm_wo_customer_phone"><?php esc_html_e('Phone', 'fsm-work-orders'); ?></label></th>
                <td>
                    <input type="tel" name="fsm_wo_customer_phone" id="fsm_wo_customer_phone"
                           value="<?php echo esc_attr($customer_phone); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="fsm_wo_customer_email"><?php esc_html_e('Email', 'fsm-work-orders'); ?></label></th>
                <td>
                    <input type="email" name="fsm_wo_customer_email" id="fsm_wo_customer_email"
                           value="<?php echo esc_attr($customer_email); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="fsm_wo_address_street"><?php esc_html_e('Street Address', 'fsm-work-orders'); ?></label></th>
                <td>
                    <input type="text" name="fsm_wo_address_street" id="fsm_wo_address_street"
                           value="<?php echo esc_attr($address_street); ?>" class="large-text">
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('City, State, ZIP', 'fsm-work-orders'); ?></label></th>
                <td>
                    <input type="text" name="fsm_wo_address_city" id="fsm_wo_address_city"
                           value="<?php echo esc_attr($address_city); ?>" placeholder="<?php esc_attr_e('City', 'fsm-work-orders'); ?>" style="width: 150px;">
                    <input type="text" name="fsm_wo_address_state" id="fsm_wo_address_state"
                           value="<?php echo esc_attr($address_state); ?>" placeholder="<?php esc_attr_e('State', 'fsm-work-orders'); ?>" style="width: 60px;">
                    <input type="text" name="fsm_wo_address_zip" id="fsm_wo_address_zip"
                           value="<?php echo esc_attr($address_zip); ?>" placeholder="<?php esc_attr_e('ZIP', 'fsm-work-orders'); ?>" style="width: 80px;">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render assignment meta box.
     *
     * @param WP_Post $post Post object.
     */
    public static function render_assignment_meta_box($post) {
        $assigned = get_post_meta($post->ID, '_fsm_wo_assigned_technicians', true);
        if (!is_array($assigned)) {
            $assigned = [];
        }
        $primary = get_post_meta($post->ID, '_fsm_wo_primary_technician_id', true);

        // Get all technicians
        $technicians = get_posts([
            'post_type' => 'fsm_technician',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        ?>
        <p>
            <label for="fsm_wo_assigned_technicians"><strong><?php esc_html_e('Assigned Technicians', 'fsm-work-orders'); ?></strong></label>
        </p>

        <?php if (empty($technicians)) : ?>
            <p class="description"><?php esc_html_e('No technicians available.', 'fsm-work-orders'); ?></p>
        <?php else : ?>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                <?php foreach ($technicians as $tech) : ?>
                    <label style="display: block; margin-bottom: 5px;">
                        <input type="checkbox" name="fsm_wo_assigned_technicians[]"
                               value="<?php echo esc_attr($tech->ID); ?>"
                               <?php checked(in_array($tech->ID, $assigned, true)); ?>>
                        <?php echo esc_html($tech->post_title); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <p style="margin-top: 15px;">
                <label for="fsm_wo_primary_technician"><strong><?php esc_html_e('Primary Technician', 'fsm-work-orders'); ?></strong></label>
            </p>
            <select name="fsm_wo_primary_technician" id="fsm_wo_primary_technician" style="width: 100%;">
                <option value=""><?php esc_html_e('Select...', 'fsm-work-orders'); ?></option>
                <?php foreach ($technicians as $tech) : ?>
                    <option value="<?php echo esc_attr($tech->ID); ?>" <?php selected($primary, $tech->ID); ?>>
                        <?php echo esc_html($tech->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php
    }

    /**
     * Save meta boxes.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post Post object.
     */
    public static function save_meta_boxes($post_id, $post) {
        if (!isset($_POST['fsm_work_order_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['fsm_work_order_nonce'], 'fsm_work_order_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Generate reference if new
        if (!get_post_meta($post_id, '_fsm_wo_reference', true)) {
            $settings = get_option('fsm_settings', []);
            $prefix = $settings['wo_prefix'] ?? 'WO';
            update_post_meta($post_id, '_fsm_wo_reference', FSM_Utilities::generate_reference($prefix));
        }

        // Save fields
        $fields = [
            'fsm_wo_status' => '_fsm_wo_status',
            'fsm_wo_priority' => '_fsm_wo_priority',
            'fsm_wo_scheduled_date' => '_fsm_wo_scheduled_date',
            'fsm_wo_scheduled_time_start' => '_fsm_wo_scheduled_time_start',
            'fsm_wo_scheduled_time_end' => '_fsm_wo_scheduled_time_end',
            'fsm_wo_estimated_duration' => '_fsm_wo_estimated_duration',
            'fsm_wo_customer_name' => '_fsm_wo_customer_name',
            'fsm_wo_customer_phone' => '_fsm_wo_customer_phone',
            'fsm_wo_customer_email' => '_fsm_wo_customer_email',
            'fsm_wo_address_street' => '_fsm_wo_address_street',
            'fsm_wo_address_city' => '_fsm_wo_address_city',
            'fsm_wo_address_state' => '_fsm_wo_address_state',
            'fsm_wo_address_zip' => '_fsm_wo_address_zip',
        ];

        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field(wp_unslash($_POST[$field])));
            }
        }

        // Save assigned technicians
        if (isset($_POST['fsm_wo_assigned_technicians'])) {
            $assigned = array_map('absint', (array) $_POST['fsm_wo_assigned_technicians']);
            update_post_meta($post_id, '_fsm_wo_assigned_technicians', $assigned);
        } else {
            update_post_meta($post_id, '_fsm_wo_assigned_technicians', []);
        }

        // Save primary technician
        if (isset($_POST['fsm_wo_primary_technician'])) {
            update_post_meta($post_id, '_fsm_wo_primary_technician_id', absint($_POST['fsm_wo_primary_technician']));
        }

        FSM_Utilities::clear_cache('work_orders');
    }

    /**
     * Add custom columns.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public static function add_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['reference'] = __('Reference', 'fsm-work-orders');
                $new_columns['wo_status'] = __('Status', 'fsm-work-orders');
                $new_columns['priority'] = __('Priority', 'fsm-work-orders');
                $new_columns['customer'] = __('Customer', 'fsm-work-orders');
                $new_columns['scheduled'] = __('Scheduled', 'fsm-work-orders');
                $new_columns['technicians'] = __('Technicians', 'fsm-work-orders');
            }
        }

        return $new_columns;
    }

    /**
     * Render custom columns.
     *
     * @param string $column Column name.
     * @param int    $post_id Post ID.
     */
    public static function render_columns($column, $post_id) {
        switch ($column) {
            case 'reference':
                echo '<code>' . esc_html(get_post_meta($post_id, '_fsm_wo_reference', true)) . '</code>';
                break;

            case 'wo_status':
                $status = get_post_meta($post_id, '_fsm_wo_status', true) ?: 'new';
                $color = FSM_Work_Order_Status::get_status_color($status);
                printf(
                    '<span style="background: %s; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;">%s</span>',
                    esc_attr($color),
                    esc_html(FSM_Work_Orders::get_status_label($status))
                );
                break;

            case 'priority':
                $priority = get_post_meta($post_id, '_fsm_wo_priority', true) ?: 'normal';
                $colors = [
                    'low' => '#95a5a6',
                    'normal' => '#3498db',
                    'high' => '#e67e22',
                    'urgent' => '#e74c3c',
                ];
                printf(
                    '<span style="color: %s; font-weight: bold;">%s</span>',
                    esc_attr($colors[$priority] ?? '#000'),
                    esc_html(ucfirst($priority))
                );
                break;

            case 'customer':
                $name = get_post_meta($post_id, '_fsm_wo_customer_name', true);
                $phone = get_post_meta($post_id, '_fsm_wo_customer_phone', true);
                echo esc_html($name);
                if ($phone) {
                    echo '<br><small>' . esc_html($phone) . '</small>';
                }
                break;

            case 'scheduled':
                $date = get_post_meta($post_id, '_fsm_wo_scheduled_date', true);
                $time = get_post_meta($post_id, '_fsm_wo_scheduled_time_start', true);
                if ($date) {
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($date)));
                    if ($time) {
                        echo '<br><small>' . esc_html($time) . '</small>';
                    }
                } else {
                    echo '<span style="color: #999;">' . esc_html__('Not scheduled', 'fsm-work-orders') . '</span>';
                }
                break;

            case 'technicians':
                $assigned = get_post_meta($post_id, '_fsm_wo_assigned_technicians', true);
                if (is_array($assigned) && !empty($assigned)) {
                    $names = [];
                    foreach ($assigned as $tech_id) {
                        $tech = get_post($tech_id);
                        if ($tech) {
                            $names[] = $tech->post_title;
                        }
                    }
                    echo esc_html(implode(', ', $names));
                } else {
                    echo '<span style="color: #999;">' . esc_html__('Unassigned', 'fsm-work-orders') . '</span>';
                }
                break;
        }
    }

    /**
     * Make columns sortable.
     *
     * @param array $columns Sortable columns.
     * @return array
     */
    public static function sortable_columns($columns) {
        $columns['reference'] = 'reference';
        $columns['wo_status'] = 'status';
        $columns['scheduled'] = 'scheduled_date';
        return $columns;
    }
}

// Initialize admin
FSM_Work_Order_Admin::init();
