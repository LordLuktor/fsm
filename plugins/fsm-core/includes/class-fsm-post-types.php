<?php
/**
 * FSM Post Types
 *
 * Registers core custom post types:
 * - fsm_technician: Technician profiles linked to users
 * - fsm_vehicle: Company vehicles
 *
 * @package FSM\Core
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Post Types class.
 */
class FSM_Post_Types {

    /**
     * Register all post types.
     */
    public static function register() {
        self::register_technician();
        self::register_vehicle();
        self::register_taxonomies();
    }

    /**
     * Register technician post type.
     */
    private static function register_technician() {
        $labels = [
            'name' => _x('Technicians', 'Post type general name', 'fsm-core'),
            'singular_name' => _x('Technician', 'Post type singular name', 'fsm-core'),
            'menu_name' => _x('Technicians', 'Admin menu', 'fsm-core'),
            'add_new' => _x('Add New', 'technician', 'fsm-core'),
            'add_new_item' => __('Add New Technician', 'fsm-core'),
            'edit_item' => __('Edit Technician', 'fsm-core'),
            'new_item' => __('New Technician', 'fsm-core'),
            'view_item' => __('View Technician', 'fsm-core'),
            'search_items' => __('Search Technicians', 'fsm-core'),
            'not_found' => __('No technicians found', 'fsm-core'),
            'not_found_in_trash' => __('No technicians found in trash', 'fsm-core'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'fsm-dashboard',
            'show_in_rest' => true,
            'rest_base' => 'technicians',
            'rest_namespace' => 'fsm/v1',
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'edit_post' => 'fsm_manage_technicians',
                'read_post' => 'fsm_view_technicians',
                'delete_post' => 'fsm_manage_technicians',
                'edit_posts' => 'fsm_manage_technicians',
                'edit_others_posts' => 'fsm_manage_technicians',
                'publish_posts' => 'fsm_manage_technicians',
                'read_private_posts' => 'fsm_view_technicians',
            ],
            'map_meta_cap' => false,
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'menu_icon' => 'dashicons-id',
            'supports' => ['title', 'thumbnail', 'custom-fields'],
        ];

        register_post_type('fsm_technician', $args);

        // Register meta fields for REST API
        self::register_technician_meta();
    }

    /**
     * Register technician meta fields.
     */
    private static function register_technician_meta() {
        $meta_fields = [
            'user_id' => [
                'type' => 'integer',
                'description' => 'Linked WordPress user ID',
                'single' => true,
                'sanitize_callback' => 'absint',
            ],
            'employee_id' => [
                'type' => 'string',
                'description' => 'Employee ID number',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'phone' => [
                'type' => 'string',
                'description' => 'Phone number',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'email' => [
                'type' => 'string',
                'description' => 'Email address',
                'single' => true,
                'sanitize_callback' => 'sanitize_email',
            ],
            'skills' => [
                'type' => 'array',
                'description' => 'Array of skill IDs',
                'single' => true,
                'sanitize_callback' => [__CLASS__, 'sanitize_array'],
            ],
            'assigned_vehicle_id' => [
                'type' => 'integer',
                'description' => 'Assigned vehicle post ID',
                'single' => true,
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'type' => 'string',
                'description' => 'Current status (available, busy, offline)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'offline',
            ],
            'hire_date' => [
                'type' => 'string',
                'description' => 'Date hired (YYYY-MM-DD)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta('fsm_technician', "_fsm_{$key}", array_merge($args, [
                'show_in_rest' => true,
                'auth_callback' => function() {
                    return current_user_can('fsm_view_technicians');
                },
            ]));
        }
    }

    /**
     * Register vehicle post type.
     */
    private static function register_vehicle() {
        $labels = [
            'name' => _x('Vehicles', 'Post type general name', 'fsm-core'),
            'singular_name' => _x('Vehicle', 'Post type singular name', 'fsm-core'),
            'menu_name' => _x('Vehicles', 'Admin menu', 'fsm-core'),
            'add_new' => _x('Add New', 'vehicle', 'fsm-core'),
            'add_new_item' => __('Add New Vehicle', 'fsm-core'),
            'edit_item' => __('Edit Vehicle', 'fsm-core'),
            'new_item' => __('New Vehicle', 'fsm-core'),
            'view_item' => __('View Vehicle', 'fsm-core'),
            'search_items' => __('Search Vehicles', 'fsm-core'),
            'not_found' => __('No vehicles found', 'fsm-core'),
            'not_found_in_trash' => __('No vehicles found in trash', 'fsm-core'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => 'fsm-dashboard',
            'show_in_rest' => true,
            'rest_base' => 'vehicles',
            'rest_namespace' => 'fsm/v1',
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'edit_post' => 'fsm_manage_vehicles',
                'read_post' => 'fsm_view_vehicles',
                'delete_post' => 'fsm_manage_vehicles',
                'edit_posts' => 'fsm_manage_vehicles',
                'edit_others_posts' => 'fsm_manage_vehicles',
                'publish_posts' => 'fsm_manage_vehicles',
                'read_private_posts' => 'fsm_view_vehicles',
            ],
            'map_meta_cap' => false,
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'menu_icon' => 'dashicons-car',
            'supports' => ['title', 'thumbnail', 'custom-fields'],
        ];

        register_post_type('fsm_vehicle', $args);

        // Register meta fields for REST API
        self::register_vehicle_meta();
    }

    /**
     * Register vehicle meta fields.
     */
    private static function register_vehicle_meta() {
        $meta_fields = [
            'vin' => [
                'type' => 'string',
                'description' => 'Vehicle Identification Number',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'license_plate' => [
                'type' => 'string',
                'description' => 'License plate number',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'make' => [
                'type' => 'string',
                'description' => 'Vehicle make',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'model' => [
                'type' => 'string',
                'description' => 'Vehicle model',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'year' => [
                'type' => 'integer',
                'description' => 'Vehicle year',
                'single' => true,
                'sanitize_callback' => 'absint',
            ],
            'color' => [
                'type' => 'string',
                'description' => 'Vehicle color',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'mileage' => [
                'type' => 'integer',
                'description' => 'Current mileage',
                'single' => true,
                'sanitize_callback' => 'absint',
            ],
            'status' => [
                'type' => 'string',
                'description' => 'Vehicle status (active, maintenance, retired)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'active',
            ],
            'insurance_expiry' => [
                'type' => 'string',
                'description' => 'Insurance expiry date (YYYY-MM-DD)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'registration_expiry' => [
                'type' => 'string',
                'description' => 'Registration expiry date (YYYY-MM-DD)',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta('fsm_vehicle', "_fsm_{$key}", array_merge($args, [
                'show_in_rest' => true,
                'auth_callback' => function() {
                    return current_user_can('fsm_view_vehicles');
                },
            ]));
        }
    }

    /**
     * Register taxonomies.
     */
    private static function register_taxonomies() {
        // Skill taxonomy for technicians
        register_taxonomy('fsm_skill', ['fsm_technician'], [
            'labels' => [
                'name' => _x('Skills', 'taxonomy general name', 'fsm-core'),
                'singular_name' => _x('Skill', 'taxonomy singular name', 'fsm-core'),
                'search_items' => __('Search Skills', 'fsm-core'),
                'all_items' => __('All Skills', 'fsm-core'),
                'edit_item' => __('Edit Skill', 'fsm-core'),
                'update_item' => __('Update Skill', 'fsm-core'),
                'add_new_item' => __('Add New Skill', 'fsm-core'),
                'new_item_name' => __('New Skill Name', 'fsm-core'),
                'menu_name' => __('Skills', 'fsm-core'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'rest_base' => 'skills',
            'rest_namespace' => 'fsm/v1',
            'hierarchical' => false,
            'show_admin_column' => true,
            'rewrite' => false,
        ]);

        // Team taxonomy for technicians
        register_taxonomy('fsm_team', ['fsm_technician'], [
            'labels' => [
                'name' => _x('Teams', 'taxonomy general name', 'fsm-core'),
                'singular_name' => _x('Team', 'taxonomy singular name', 'fsm-core'),
                'search_items' => __('Search Teams', 'fsm-core'),
                'all_items' => __('All Teams', 'fsm-core'),
                'edit_item' => __('Edit Team', 'fsm-core'),
                'update_item' => __('Update Team', 'fsm-core'),
                'add_new_item' => __('Add New Team', 'fsm-core'),
                'new_item_name' => __('New Team Name', 'fsm-core'),
                'menu_name' => __('Teams', 'fsm-core'),
            ],
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'rest_base' => 'teams',
            'rest_namespace' => 'fsm/v1',
            'hierarchical' => true,
            'show_admin_column' => true,
            'rewrite' => false,
        ]);
    }

    /**
     * Sanitize array callback.
     *
     * @param mixed $value Value to sanitize.
     * @return array
     */
    public static function sanitize_array($value) {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return [];
    }
}
