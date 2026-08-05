<?php
/**
 * FSM Organizations
 *
 * Handles multi-tenancy through organizations.
 * Each organization has isolated data (technicians, vehicles, work orders, etc.)
 * Super admins can see all organizations.
 *
 * @package FSM\Core
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Organizations class.
 */
class FSM_Organizations {

    /**
     * Organization post type name.
     */
    const POST_TYPE = 'fsm_organization';

    /**
     * User meta key for organization ID.
     */
    const USER_META_KEY = '_fsm_organization_id';

    /**
     * Option key for super admin user IDs.
     */
    const SUPER_ADMINS_OPTION = 'fsm_super_admin_ids';

    /**
     * Register the organization post type.
     */
    public static function register_post_type() {
        $labels = [
            'name'               => __('Organizations', 'fsm-core'),
            'singular_name'      => __('Organization', 'fsm-core'),
            'menu_name'          => __('Organizations', 'fsm-core'),
            'add_new'            => __('Add New', 'fsm-core'),
            'add_new_item'       => __('Add New Organization', 'fsm-core'),
            'edit_item'          => __('Edit Organization', 'fsm-core'),
            'new_item'           => __('New Organization', 'fsm-core'),
            'view_item'          => __('View Organization', 'fsm-core'),
            'search_items'       => __('Search Organizations', 'fsm-core'),
            'not_found'          => __('No organizations found', 'fsm-core'),
            'not_found_in_trash' => __('No organizations found in trash', 'fsm-core'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'fsm-dashboard',
            'show_in_rest'        => true,
            'rest_base'           => 'organizations',
            'rest_namespace'      => 'fsm/v1',
            'capability_type'     => 'post',
            'capabilities'        => [
                'create_posts' => 'manage_options', // Only super admins
            ],
            'map_meta_cap'        => true,
            'hierarchical'        => false,
            'supports'            => ['title', 'thumbnail'],
            'has_archive'         => false,
            'rewrite'             => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register meta fields for organizations.
     */
    public static function register_meta() {
        // Organization status
        register_post_meta(self::POST_TYPE, '_fsm_org_status', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'active',
        ]);

        // Organization slug (for URLs/identification)
        register_post_meta(self::POST_TYPE, '_fsm_org_slug', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_title',
        ]);

        // Contact email
        register_post_meta(self::POST_TYPE, '_fsm_org_email', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_email',
        ]);

        // Contact phone
        register_post_meta(self::POST_TYPE, '_fsm_org_phone', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // Address
        register_post_meta(self::POST_TYPE, '_fsm_org_address', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);

        // Subscription tier
        register_post_meta(self::POST_TYPE, '_fsm_org_tier', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'standard',
        ]);

        // Max users limit
        register_post_meta(self::POST_TYPE, '_fsm_org_max_users', [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'default'           => 10,
        ]);

        // Created date
        register_post_meta(self::POST_TYPE, '_fsm_org_created', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
        ]);

        // Billing: hourly labor rate charged to this org's customers
        register_post_meta(self::POST_TYPE, '_fsm_org_labor_rate', [
            'type'              => 'number',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => [__CLASS__, 'sanitize_money'],
            'default'           => 85,
            'auth_callback'     => function() {
                return current_user_can('fsm_manage_billing');
            },
        ]);

        // Billing: sales tax rate (percent) applied to taxable invoice line items
        register_post_meta(self::POST_TYPE, '_fsm_org_tax_rate', [
            'type'              => 'number',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => [__CLASS__, 'sanitize_money'],
            'default'           => 0,
            'auth_callback'     => function() {
                return current_user_can('fsm_manage_billing');
            },
        ]);
    }

    /**
     * Sanitize a non-negative decimal (money/rate) meta value.
     *
     * @param mixed $value Raw value.
     * @return float
     */
    public static function sanitize_money($value) {
        $value = (float) $value;
        return $value < 0 ? 0.0 : round($value, 2);
    }

    /**
     * Get an organization's hourly labor rate, falling back to the legacy
     * global option for organizations that haven't set one yet.
     *
     * @param int $org_id Organization ID.
     * @return float
     */
    public static function get_labor_rate($org_id) {
        $rate = get_post_meta($org_id, '_fsm_org_labor_rate', true);
        if ($rate === '' || $rate === null) {
            return (float) get_option('fsm_labor_rate', 85);
        }
        return (float) $rate;
    }

    /**
     * Get an organization's tax rate (percent), falling back to the legacy
     * global option for organizations that haven't set one yet.
     *
     * @param int $org_id Organization ID.
     * @return float
     */
    public static function get_tax_rate($org_id) {
        $rate = get_post_meta($org_id, '_fsm_org_tax_rate', true);
        if ($rate === '' || $rate === null) {
            return (float) get_option('fsm_tax_rate', 0);
        }
        return (float) $rate;
    }

    /**
     * Get organization by ID.
     *
     * @param int $org_id Organization ID.
     * @return WP_Post|null
     */
    public static function get($org_id) {
        $org = get_post($org_id);
        if ($org && $org->post_type === self::POST_TYPE) {
            return $org;
        }
        return null;
    }

    /**
     * Get organization by slug.
     *
     * @param string $slug Organization slug.
     * @return WP_Post|null
     */
    public static function get_by_slug($slug) {
        $orgs = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'   => '_fsm_org_slug',
                    'value' => $slug,
                ],
            ],
        ]);

        return !empty($orgs) ? $orgs[0] : null;
    }

    /**
     * Get the post IDs of all organizations flagged as demo data.
     *
     * @return array<int>
     */
    public static function get_demo_organization_ids() {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = get_posts([
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => [
                ['key' => '_fsm_is_demo', 'value' => '1'],
            ],
        ]);

        return $cache;
    }

    /**
     * Build a meta_query clause excluding demo-organization data, for use by
     * any endpoint that lets a super admin view data across all organizations.
     * Returns an empty array when there's no demo data to exclude.
     *
     * @param string $meta_key Meta key that stores the owning organization ID.
     * @return array
     */
    public static function get_demo_exclusion_meta_query($meta_key = '_fsm_organization_id') {
        $demo_ids = self::get_demo_organization_ids();
        if (empty($demo_ids)) {
            return [];
        }

        return [
            [
                'key'     => $meta_key,
                'value'   => $demo_ids,
                'compare' => 'NOT IN',
            ],
        ];
    }

    /**
     * Get all organizations.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function get_all($args = []) {
        $defaults = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        return get_posts(array_merge($defaults, $args));
    }

    /**
     * Create a new organization.
     *
     * @param array $data Organization data.
     * @return int|WP_Error Organization ID or error.
     */
    public static function create($data) {
        $post_data = [
            'post_type'   => self::POST_TYPE,
            'post_title'  => sanitize_text_field($data['name']),
            'post_status' => 'publish',
        ];

        $org_id = wp_insert_post($post_data, true);

        if (is_wp_error($org_id)) {
            return $org_id;
        }

        // Generate slug if not provided
        $slug = !empty($data['slug'])
            ? sanitize_title($data['slug'])
            : sanitize_title($data['name']);

        // Ensure unique slug
        $slug = self::ensure_unique_slug($slug, $org_id);

        // Save meta
        update_post_meta($org_id, '_fsm_org_slug', $slug);
        update_post_meta($org_id, '_fsm_org_status', $data['status'] ?? 'active');
        update_post_meta($org_id, '_fsm_org_email', $data['email'] ?? '');
        update_post_meta($org_id, '_fsm_org_phone', $data['phone'] ?? '');
        update_post_meta($org_id, '_fsm_org_address', $data['address'] ?? '');
        update_post_meta($org_id, '_fsm_org_tier', $data['tier'] ?? 'standard');
        update_post_meta($org_id, '_fsm_org_max_users', $data['max_users'] ?? 10);
        update_post_meta($org_id, '_fsm_org_created', current_time('mysql'));

        /**
         * Action fired when an organization is created.
         * Used by PTT plugin to provision Mumble channels.
         *
         * @param int    $org_id Organization ID.
         * @param string $name Organization name.
         * @param string $slug Organization slug.
         */
        do_action('fsm_organization_created', $org_id, $data['name'], $slug);

        return $org_id;
    }

    /**
     * Ensure organization slug is unique.
     *
     * @param string $slug Base slug.
     * @param int    $exclude_id Organization ID to exclude.
     * @return string Unique slug.
     */
    private static function ensure_unique_slug($slug, $exclude_id = 0) {
        $original_slug = $slug;
        $counter = 1;

        while (true) {
            $existing = get_posts([
                'post_type'      => self::POST_TYPE,
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'post__not_in'   => $exclude_id ? [$exclude_id] : [],
                'meta_query'     => [
                    [
                        'key'   => '_fsm_org_slug',
                        'value' => $slug,
                    ],
                ],
            ]);

            if (empty($existing)) {
                break;
            }

            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get user's organization ID.
     *
     * @param int|null $user_id User ID (default: current user).
     * @return int|null Organization ID or null.
     */
    public static function get_user_organization_id($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return null;
        }

        $org_id = get_user_meta($user_id, self::USER_META_KEY, true);
        return $org_id ? (int) $org_id : null;
    }

    /**
     * Set user's organization ID.
     *
     * @param int $user_id User ID.
     * @param int $org_id  Organization ID.
     * @return bool Success.
     */
    public static function set_user_organization($user_id, $org_id) {
        $previous_org = self::get_user_organization_id($user_id);
        $result = update_user_meta($user_id, self::USER_META_KEY, $org_id);

        if ($result) {
            /**
             * Action fired when a user is assigned to an organization.
             * Used by PTT plugin to register user in Mumble.
             *
             * @param int      $user_id User ID.
             * @param int      $org_id  Organization ID.
             * @param int|null $previous_org Previous organization ID (if any).
             */
            do_action('fsm_user_organization_changed', $user_id, $org_id, $previous_org);
        }

        return $result;
    }

    /**
     * Get users in an organization.
     *
     * @param int   $org_id Organization ID.
     * @param array $args   Additional query args.
     * @return array User objects.
     */
    public static function get_organization_users($org_id, $args = []) {
        $defaults = [
            'meta_key'   => self::USER_META_KEY,
            'meta_value' => $org_id,
        ];

        return get_users(array_merge($defaults, $args));
    }

    /**
     * Check if user is a super admin (can see all organizations).
     *
     * @param int|null $user_id User ID (default: current user).
     * @return bool
     */
    public static function is_super_admin($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        // WordPress administrators are super admins
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Check explicit super admin list
        $super_admins = get_option(self::SUPER_ADMINS_OPTION, []);
        return in_array($user_id, (array) $super_admins, true);
    }

    /**
     * Add user to super admins list.
     *
     * @param int $user_id User ID.
     * @return bool Success.
     */
    public static function add_super_admin($user_id) {
        $super_admins = get_option(self::SUPER_ADMINS_OPTION, []);
        if (!in_array($user_id, $super_admins, true)) {
            $super_admins[] = $user_id;
            return update_option(self::SUPER_ADMINS_OPTION, $super_admins);
        }
        return true;
    }

    /**
     * Verify that the current user has access to a specific post's organization.
     * Prevents cross-organization IDOR by checking the post's org meta against
     * the current user's org context.
     *
     * @param int $post_id Post ID to verify.
     * @return true|WP_Error True if allowed, WP_Error if forbidden.
     */
    public static function verify_post_access($post_id) {
        $post_org = get_post_meta($post_id, '_fsm_organization_id', true);
        $context = self::get_current_context();

        // Super admin with no org filter — allow all
        if ($context === null && self::is_super_admin()) {
            return true;
        }

        // No org context and not super admin — deny
        if ($context === null) {
            return new \WP_Error('forbidden', 'Access denied.', ['status' => 403]);
        }

        // Post has no org (legacy data) — deny for safety
        if (!$post_org) {
            return new \WP_Error('forbidden', 'Access denied.', ['status' => 403]);
        }

        if ((int) $post_org !== (int) $context) {
            return new \WP_Error('forbidden', 'Access denied.', ['status' => 403]);
        }

        return true;
    }

    /**
     * Verify that an inventory item belongs to the current user's organization.
     *
     * @param object $item Inventory item object with organization_id property.
     * @return true|WP_Error True if allowed, WP_Error if forbidden.
     */
    public static function verify_inventory_access($item) {
        $context = self::get_current_context();

        // Super admin with no org filter — allow all
        if ($context === null && self::is_super_admin()) {
            return true;
        }

        if ($context === null) {
            return new \WP_Error('forbidden', 'Access denied.', ['status' => 403]);
        }

        if ((int) $item->organization_id !== (int) $context) {
            return new \WP_Error('forbidden', 'Access denied.', ['status' => 403]);
        }

        return true;
    }

    /**
     * Get the current organization context.
     * Returns the organization ID that should be used for queries.
     * Super admins can optionally filter by a specific org.
     *
     * @return int|null Organization ID or null (for super admin viewing all).
     */
    public static function get_current_context() {
        // Check for explicit org context in request (for super admins)
        if (self::is_super_admin()) {
            // Check for org filter in request
            $requested_org = isset($_GET['organization_id'])
                ? (int) $_GET['organization_id']
                : null;

            if ($requested_org) {
                return $requested_org;
            }

            // Check session/transient for selected org
            $selected_org = get_user_meta(get_current_user_id(), '_fsm_selected_org', true);
            if ($selected_org) {
                return (int) $selected_org;
            }

            // Super admin with no filter sees all
            return null;
        }

        // Regular users see only their organization
        return self::get_user_organization_id();
    }

    /**
     * Set super admin's selected organization context.
     *
     * @param int|null $org_id Organization ID or null for all.
     * @return bool Success.
     */
    public static function set_admin_context($org_id) {
        if (!self::is_super_admin()) {
            return false;
        }

        if ($org_id === null) {
            return delete_user_meta(get_current_user_id(), '_fsm_selected_org');
        }

        return update_user_meta(get_current_user_id(), '_fsm_selected_org', $org_id);
    }

    /**
     * Get organization data formatted for API response.
     *
     * @param int|WP_Post $org Organization ID or post object.
     * @return array|null
     */
    public static function format_for_api($org) {
        if (is_numeric($org)) {
            $org = self::get($org);
        }

        if (!$org) {
            return null;
        }

        return [
            'id'         => $org->ID,
            'name'       => $org->post_title,
            'slug'       => get_post_meta($org->ID, '_fsm_org_slug', true),
            'status'     => get_post_meta($org->ID, '_fsm_org_status', true),
            'email'      => get_post_meta($org->ID, '_fsm_org_email', true),
            'phone'      => get_post_meta($org->ID, '_fsm_org_phone', true),
            'address'    => get_post_meta($org->ID, '_fsm_org_address', true),
            'tier'       => get_post_meta($org->ID, '_fsm_org_tier', true),
            'max_users'  => (int) get_post_meta($org->ID, '_fsm_org_max_users', true),
            'created'    => get_post_meta($org->ID, '_fsm_org_created', true),
            'user_count' => count(self::get_organization_users($org->ID)),
        ];
    }

    /**
     * Add organization filter to post queries.
     *
     * @param WP_Query $query Query object.
     */
    public static function filter_posts_by_organization($query) {
        $post_type = $query->get('post_type');

        // Organizations list itself: hide demo orgs from wp-admin browsing.
        // Doesn't touch REST requests, so the public "Try Demo" flow and any
        // API consumer that needs the full list are unaffected.
        if ($post_type === self::POST_TYPE) {
            if (is_admin() && $query->is_main_query()) {
                $demo_ids = self::get_demo_organization_ids();
                if (!empty($demo_ids)) {
                    $existing_exclude = $query->get('post__not_in') ?: [];
                    $query->set('post__not_in', array_merge($existing_exclude, $demo_ids));
                }
            }
            return;
        }

        // Only filter FSM post types
        $fsm_post_types = ['fsm_technician', 'fsm_vehicle', 'fsm_work_order'];

        if (!in_array($post_type, $fsm_post_types, true)) {
            return;
        }

        // Skip during WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }

        // Skip during AJAX and REST requests if query already has org filter
        $existing_meta = $query->get('meta_query');
        if (is_array($existing_meta)) {
            foreach ($existing_meta as $meta) {
                if (isset($meta['key']) && $meta['key'] === '_fsm_organization_id') {
                    return; // Already filtered by org
                }
            }
        }
        // Also check meta_key directly
        if ($query->get('meta_key') === '_fsm_organization_id') {
            return;
        }

        // Super admin in wp-admin: show everything except demo-org data, so
        // fake technicians/vehicles/work orders from "Try Demo" sessions don't
        // clutter the real platform view.
        if (is_admin() && self::is_super_admin()) {
            if ($query->is_main_query()) {
                $demo_ids = self::get_demo_organization_ids();
                if (!empty($demo_ids)) {
                    $meta_query = $query->get('meta_query') ?: [];
                    $meta_query[] = [
                        'key'     => '_fsm_organization_id',
                        'value'   => $demo_ids,
                        'compare' => 'NOT IN',
                    ];
                    $query->set('meta_query', $meta_query);
                }
            }
            return;
        }

        $org_id = self::get_current_context();

        // If no org context (super admin viewing all), don't filter
        if ($org_id === null && self::is_super_admin()) {
            return;
        }

        // If user has no org and not super admin, show nothing
        if ($org_id === null) {
            $query->set('post__in', [0]); // Return no results
            return;
        }

        // Add organization filter
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key'   => '_fsm_organization_id',
            'value' => $org_id,
        ];
        $query->set('meta_query', $meta_query);
    }

    /**
     * Automatically set organization ID when creating FSM posts.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an update.
     */
    public static function auto_set_organization($post_id, $post, $update) {
        // Skip on updates
        if ($update) {
            return;
        }

        // Only for FSM post types
        $fsm_post_types = ['fsm_technician', 'fsm_vehicle', 'fsm_work_order'];
        if (!in_array($post->post_type, $fsm_post_types, true)) {
            return;
        }

        // Skip if already has organization
        if (get_post_meta($post_id, '_fsm_organization_id', true)) {
            return;
        }

        // Get organization from context
        $org_id = self::get_current_context();

        // If super admin creating without context, require explicit org
        if ($org_id === null) {
            // Check if org_id was passed in request
            if (!empty($_GET['organization_id'])) {
                $org_id = (int) $_GET['organization_id'];
            }
        }

        if ($org_id) {
            update_post_meta($post_id, '_fsm_organization_id', $org_id);
        }
    }
}
