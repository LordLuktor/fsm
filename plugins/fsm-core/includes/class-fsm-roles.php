<?php
/**
 * FSM Roles and Capabilities
 *
 * Defines custom roles and capabilities for FSM system:
 * - FSM Administrator: Full access to all FSM features
 * - FSM Dispatcher: Manages work orders, schedules, views all technicians
 * - FSM Technician: Views assigned work orders, updates status, limited access
 *
 * @package FSM\Core
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Roles class.
 */
class FSM_Roles {

    /**
     * Get all FSM capabilities.
     *
     * @return array
     */
    public static function get_capabilities() {
        return [
            // Core capabilities
            'fsm_access' => __('Access FSM System', 'fsm-core'),

            // Work Order capabilities
            'fsm_view_work_orders' => __('View Work Orders', 'fsm-core'),
            'fsm_view_own_work_orders' => __('View Own Work Orders', 'fsm-core'),
            'fsm_create_work_orders' => __('Create Work Orders', 'fsm-core'),
            'fsm_edit_work_orders' => __('Edit Work Orders', 'fsm-core'),
            'fsm_delete_work_orders' => __('Delete Work Orders', 'fsm-core'),
            'fsm_assign_work_orders' => __('Assign Work Orders', 'fsm-core'),

            // Technician capabilities
            'fsm_view_technicians' => __('View All Technicians', 'fsm-core'),
            'fsm_manage_technicians' => __('Manage Technicians', 'fsm-core'),
            'fsm_view_technician_locations' => __('View Technician Locations', 'fsm-core'),

            // Vehicle capabilities
            'fsm_view_vehicles' => __('View Vehicles', 'fsm-core'),
            'fsm_manage_vehicles' => __('Manage Vehicles', 'fsm-core'),

            // Inventory capabilities
            'fsm_view_inventory' => __('View Inventory', 'fsm-core'),
            'fsm_manage_inventory' => __('Manage Inventory', 'fsm-core'),
            'fsm_view_own_inventory' => __('View Own Inventory', 'fsm-core'),
            'fsm_consume_inventory' => __('Consume Inventory', 'fsm-core'),

            // Dispatch capabilities
            'fsm_view_dispatch' => __('View Dispatch Board', 'fsm-core'),
            'fsm_manage_dispatch' => __('Manage Dispatch', 'fsm-core'),

            // GPS capabilities
            'fsm_update_location' => __('Update Own Location', 'fsm-core'),
            'fsm_view_group_locations' => __('View Group Technician Locations', 'fsm-core'),
            'fsm_view_all_locations' => __('View All Locations', 'fsm-core'),

            // PTT capabilities
            'fsm_use_ptt' => __('Use PTT Communications', 'fsm-core'),
            'fsm_manage_ptt' => __('Manage PTT Channels', 'fsm-core'),

            // Settings
            'fsm_manage_settings' => __('Manage FSM Settings', 'fsm-core'),

            // Billing capabilities
            'fsm_view_billing' => __('View Invoices', 'fsm-core'),
            'fsm_manage_billing' => __('Manage Invoices & Billing Settings', 'fsm-core'),
        ];
    }

    /**
     * Create FSM roles.
     */
    public static function create_roles() {
        // FSM Administrator - Full access
        add_role('fsm_administrator', __('FSM Administrator', 'fsm-core'), [
            // WordPress basics
            'read' => true,
            'upload_files' => true,

            // All FSM capabilities
            'fsm_access' => true,
            'fsm_view_work_orders' => true,
            'fsm_view_own_work_orders' => true,
            'fsm_create_work_orders' => true,
            'fsm_edit_work_orders' => true,
            'fsm_delete_work_orders' => true,
            'fsm_assign_work_orders' => true,
            'fsm_view_technicians' => true,
            'fsm_manage_technicians' => true,
            'fsm_view_technician_locations' => true,
            'fsm_view_vehicles' => true,
            'fsm_manage_vehicles' => true,
            'fsm_view_inventory' => true,
            'fsm_manage_inventory' => true,
            'fsm_view_own_inventory' => true,
            'fsm_consume_inventory' => true,
            'fsm_view_dispatch' => true,
            'fsm_manage_dispatch' => true,
            'fsm_update_location' => true,
            'fsm_view_all_locations' => true,
            'fsm_use_ptt' => true,
            'fsm_manage_ptt' => true,
            'fsm_manage_settings' => true,
            'fsm_view_billing' => true,
            'fsm_manage_billing' => true,
        ]);

        // FSM Dispatcher - Manages work orders and scheduling
        add_role('fsm_dispatcher', __('FSM Dispatcher', 'fsm-core'), [
            'read' => true,
            'upload_files' => true,

            'fsm_access' => true,
            'fsm_view_work_orders' => true,
            'fsm_view_own_work_orders' => true,
            'fsm_create_work_orders' => true,
            'fsm_edit_work_orders' => true,
            'fsm_assign_work_orders' => true,
            'fsm_view_technicians' => true,
            'fsm_manage_technicians' => true,
            'fsm_view_technician_locations' => true,
            'fsm_view_vehicles' => true,
            'fsm_manage_vehicles' => true,
            'fsm_view_inventory' => true,
            'fsm_manage_inventory' => true,
            'fsm_view_own_inventory' => true,
            'fsm_consume_inventory' => true,
            'fsm_view_dispatch' => true,
            'fsm_manage_dispatch' => true,
            'fsm_view_all_locations' => true,
            'fsm_use_ptt' => true,
            'fsm_manage_ptt' => true,
            'fsm_view_billing' => true,
        ]);

        // FSM Lead Technician - Can manage their tech group's jobs, PTT, and view group locations
        add_role('fsm_lead_technician', __('FSM Lead Technician', 'fsm-core'), [
            'read' => true,
            'upload_files' => true,

            'fsm_access' => true,
            'fsm_view_work_orders' => true,
            'fsm_view_own_work_orders' => true,
            'fsm_create_work_orders' => true,
            'fsm_edit_work_orders' => true,
            'fsm_assign_work_orders' => true,         // Move jobs within their group
            'fsm_view_technicians' => true,            // See their group's techs
            'fsm_view_technician_locations' => true,   // See group locations on map
            'fsm_view_vehicles' => true,
            'fsm_view_inventory' => true,
            'fsm_view_own_inventory' => true,
            'fsm_consume_inventory' => true,
            'fsm_view_dispatch' => true,               // View dispatch board
            'fsm_manage_dispatch' => true,             // Reassign within group
            'fsm_update_location' => true,
            'fsm_view_group_locations' => true,        // New: view own group's GPS
            'fsm_use_ptt' => true,
            'fsm_manage_ptt' => true,                  // Create/manage group talk groups
        ]);

        // FSM Technician - Can view own work + create work orders + limited GPS
        add_role('fsm_technician', __('FSM Technician', 'fsm-core'), [
            'read' => true,
            'upload_files' => true,

            'fsm_access' => true,
            'fsm_view_own_work_orders' => true,
            'fsm_create_work_orders' => true,
            'fsm_view_own_inventory' => true,
            'fsm_consume_inventory' => true,
            'fsm_update_location' => true,
            'fsm_use_ptt' => true,
        ]);

        // Grant FSM capabilities to WordPress administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (array_keys(self::get_capabilities()) as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }

    /**
     * Remove FSM roles.
     * Called on uninstall.
     */
    public static function remove_roles() {
        remove_role('fsm_administrator');
        remove_role('fsm_dispatcher');
        remove_role('fsm_lead_technician');
        remove_role('fsm_technician');

        // Remove capabilities from administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (array_keys(self::get_capabilities()) as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }

    /**
     * Check if user has FSM access.
     *
     * @param int|null $user_id User ID (default: current user).
     * @return bool
     */
    public static function user_can_access($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        return user_can($user_id, 'fsm_access');
    }

    /**
     * Check if user is a technician.
     *
     * @param int|null $user_id User ID (default: current user).
     * @return bool
     */
    /**
     * Check if user is a lead technician.
     */
    public static function is_lead_technician($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata($user_id);
        return $user && in_array('fsm_lead_technician', $user->roles, true);
    }

    public static function is_technician($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata($user_id);
        return $user && in_array('fsm_technician', $user->roles, true);
    }

    /**
     * Check if user is a dispatcher.
     *
     * @param int|null $user_id User ID (default: current user).
     * @return bool
     */
    public static function is_dispatcher($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata($user_id);
        return $user && (
            in_array('fsm_dispatcher', $user->roles, true) ||
            in_array('fsm_administrator', $user->roles, true) ||
            in_array('administrator', $user->roles, true)
        );
    }

    /**
     * Check if user is an FSM administrator.
     *
     * @param int|null $user_id User ID (default: current user).
     * @return bool
     */
    public static function is_fsm_admin($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        $user = get_userdata($user_id);
        return $user && (
            in_array('fsm_administrator', $user->roles, true) ||
            in_array('administrator', $user->roles, true)
        );
    }
}
