<?php
/**
 * FSM Demo Data Generator
 *
 * Creates sample data for demonstration and testing purposes.
 * Supports multiple trade templates: Plumbing, Electrical, HVAC.
 *
 * @package FSM\Core
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Demo Data class.
 */
class FSM_Demo_Data {

    /**
     * Available demo templates.
     */
    const TEMPLATES = ['plumbing', 'electrical', 'hvac'];

    /**
     * Create a complete demo organization with sample data.
     *
     * @param string $template Template type: 'plumbing', 'electrical', or 'hvac'.
     * @param string $org_name Optional custom organization name.
     * @return int|WP_Error Organization ID or error.
     */
    public static function create_demo_organization($template = 'plumbing', $org_name = null) {
        $template = strtolower($template);
        if (!in_array($template, self::TEMPLATES)) {
            return new WP_Error('invalid_template', __('Invalid demo template type.', 'fsm-core'));
        }

        // Get template-specific company info
        $company_info = self::get_company_info($template);
        $name = $org_name ?: $company_info['name'];

        // Create organization
        $org_id = FSM_Organizations::create([
            'name'      => $name,
            'slug'      => sanitize_title($name),
            'email'     => $company_info['email'],
            'phone'     => $company_info['phone'],
            'address'   => $company_info['address'],
            'tier'      => 'professional',
            'max_users' => 25,
        ]);

        if (is_wp_error($org_id)) {
            return $org_id;
        }

        // Store template type for reference
        update_post_meta($org_id, '_fsm_demo_template', $template);
        update_post_meta($org_id, '_fsm_is_demo', true);

        // Create demo users
        self::create_demo_users($org_id);

        // Create demo vehicles
        $vehicles = self::create_demo_vehicles($org_id, $template);

        // Create demo technicians
        $technicians = self::create_demo_technicians($org_id, $vehicles, $template);

        // Create work order types with term meta
        $type_map = self::create_demo_work_order_types($template);

        // Create demo work orders (with type assignments)
        self::create_demo_work_orders($org_id, $technicians, $template, $type_map);

        // Create demo inventory (per vehicle and warehouse)
        self::create_demo_inventory($org_id, $vehicles, $template);

        // Create simulated GPS locations for technicians
        self::create_demo_gps_locations($org_id, $technicians, $template);

        // Create dispatch channel for organization
        if (class_exists('FSM_PTT_DB')) {
            FSM_PTT_DB::ensure_dispatch_channel($org_id);
        }

        return $org_id;
    }

    /**
     * Get company info for template type.
     *
     * @param string $template Template type.
     * @return array Company info.
     */
    private static function get_company_info($template) {
        $companies = [
            'plumbing' => [
                'name'    => 'Reliable Plumbing Co.',
                'email'   => 'office@reliableplumbing.demo',
                'phone'   => '(555) 247-5862',
                'address' => "1847 Pipe Drive\nSpringfield, IL 62701",
            ],
            'electrical' => [
                'name'    => 'PowerPro Electric',
                'email'   => 'dispatch@powerproelectric.demo',
                'phone'   => '(555) 392-8472',
                'address' => "2100 Volt Avenue\nSpringfield, IL 62702",
            ],
            'hvac' => [
                'name'    => 'ComfortZone HVAC Services',
                'email'   => 'service@comfortzonehvac.demo',
                'phone'   => '(555) 482-2665',
                'address' => "3500 Climate Control Blvd\nSpringfield, IL 62703",
            ],
        ];

        return $companies[$template];
    }

    /**
     * Create demo users for organization.
     *
     * @param int $org_id Organization ID.
     * @return array Created user IDs.
     */
    public static function create_demo_users($org_id) {
        $users = [];
        $org = FSM_Organizations::get($org_id);
        $org_slug = get_post_meta($org_id, '_fsm_org_slug', true);

        // Create dispatcher
        $dispatcher_login = 'dispatcher_' . $org_slug;
        if (!username_exists($dispatcher_login)) {
            $dispatcher_id = wp_create_user(
                $dispatcher_login,
                wp_generate_password(24, true, true),
                $dispatcher_login . '@demo.example.com'
            );

            if (!is_wp_error($dispatcher_id)) {
                $user = new WP_User($dispatcher_id);
                $user->set_role('fsm_dispatcher');
                update_user_meta($dispatcher_id, 'first_name', 'Sarah');
                update_user_meta($dispatcher_id, 'last_name', 'Dispatcher');
                FSM_Organizations::set_user_organization($dispatcher_id, $org_id);
                $users['dispatcher'] = $dispatcher_id;
            }
        }

        // Create admin for this org
        $admin_login = 'admin_' . $org_slug;
        if (!username_exists($admin_login)) {
            $admin_id = wp_create_user(
                $admin_login,
                wp_generate_password(24, true, true),
                $admin_login . '@demo.example.com'
            );

            if (!is_wp_error($admin_id)) {
                $user = new WP_User($admin_id);
                $user->set_role('fsm_administrator');
                update_user_meta($admin_id, 'first_name', 'Admin');
                update_user_meta($admin_id, 'last_name', $org->post_title);
                FSM_Organizations::set_user_organization($admin_id, $org_id);
                $users['admin'] = $admin_id;
            }
        }

        return $users;
    }

    /**
     * Create demo vehicles for organization.
     *
     * @param int    $org_id Organization ID.
     * @param string $template Template type.
     * @return array Created vehicle IDs.
     */
    public static function create_demo_vehicles($org_id, $template = 'plumbing') {
        $vehicles = [];
        $vehicle_data = self::get_vehicle_data($template);

        foreach ($vehicle_data as $data) {
            $post_id = wp_insert_post([
                'post_type'   => 'fsm_vehicle',
                'post_title'  => $data['name'],
                'post_status' => 'publish',
            ]);

            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_fsm_organization_id', $org_id);
                update_post_meta($post_id, '_fsm_vehicle_type', $data['type']);
                update_post_meta($post_id, '_fsm_vehicle_make', $data['make']);
                update_post_meta($post_id, '_fsm_vehicle_model', $data['model']);
                update_post_meta($post_id, '_fsm_vehicle_year', $data['year']);
                update_post_meta($post_id, '_fsm_vehicle_vin', $data['vin']);
                update_post_meta($post_id, '_fsm_vehicle_plate', $data['plate']);
                update_post_meta($post_id, '_fsm_status', $data['status']);
                $vehicles[] = $post_id;
            }
        }

        return $vehicles;
    }

    /**
     * Get vehicle data for template.
     *
     * @param string $template Template type.
     * @return array Vehicle data.
     */
    private static function get_vehicle_data($template) {
        $vehicles = [
            'plumbing' => [
                [
                    'name'    => 'Plumbing Van #1',
                    'type'    => 'van',
                    'make'    => 'Ford',
                    'model'   => 'E-350 Cutaway',
                    'year'    => '2023',
                    'vin'     => 'PLMB1234567890101',
                    'plate'   => 'PLB-101',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Plumbing Van #2',
                    'type'    => 'van',
                    'make'    => 'Ford',
                    'model'   => 'Transit 350',
                    'year'    => '2022',
                    'vin'     => 'PLMB1234567890102',
                    'plate'   => 'PLB-102',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Plumbing Van #3',
                    'type'    => 'van',
                    'make'    => 'RAM',
                    'model'   => 'ProMaster 2500',
                    'year'    => '2023',
                    'vin'     => 'PLMB1234567890103',
                    'plate'   => 'PLB-103',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Drain Cleaning Truck',
                    'type'    => 'truck',
                    'make'    => 'Isuzu',
                    'model'   => 'NPR HD',
                    'year'    => '2021',
                    'vin'     => 'PLMB1234567890201',
                    'plate'   => 'DRN-201',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Sewer/Excavation Truck',
                    'type'    => 'truck',
                    'make'    => 'Ford',
                    'model'   => 'F-550 Super Duty',
                    'year'    => '2022',
                    'vin'     => 'PLMB1234567890202',
                    'plate'   => 'SEW-202',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Water Heater Van',
                    'type'    => 'van',
                    'make'    => 'Chevrolet',
                    'model'   => 'Express 3500',
                    'year'    => '2021',
                    'vin'     => 'PLMB1234567890301',
                    'plate'   => 'WTR-301',
                    'status'  => 'maintenance',
                ],
            ],
            'electrical' => [
                [
                    'name'    => 'Service Van #1',
                    'type'    => 'van',
                    'make'    => 'Ford',
                    'model'   => 'Transit 250',
                    'year'    => '2023',
                    'vin'     => 'ELEC1234567890101',
                    'plate'   => 'ELC-101',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Service Van #2',
                    'type'    => 'van',
                    'make'    => 'Mercedes-Benz',
                    'model'   => 'Sprinter 2500',
                    'year'    => '2022',
                    'vin'     => 'ELEC1234567890102',
                    'plate'   => 'ELC-102',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Service Van #3',
                    'type'    => 'van',
                    'make'    => 'RAM',
                    'model'   => 'ProMaster 1500',
                    'year'    => '2023',
                    'vin'     => 'ELEC1234567890103',
                    'plate'   => 'ELC-103',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Panel Upgrade Truck',
                    'type'    => 'truck',
                    'make'    => 'Ford',
                    'model'   => 'F-350 Super Duty',
                    'year'    => '2022',
                    'vin'     => 'ELEC1234567890201',
                    'plate'   => 'PNL-201',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Bucket Truck',
                    'type'    => 'truck',
                    'make'    => 'International',
                    'model'   => '4300 Bucket',
                    'year'    => '2020',
                    'vin'     => 'ELEC1234567890202',
                    'plate'   => 'BKT-202',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Service Van #4',
                    'type'    => 'van',
                    'make'    => 'Chevrolet',
                    'model'   => 'Express 2500',
                    'year'    => '2021',
                    'vin'     => 'ELEC1234567890104',
                    'plate'   => 'ELC-104',
                    'status'  => 'maintenance',
                ],
            ],
            'hvac' => [
                [
                    'name'    => 'HVAC Service Van #1',
                    'type'    => 'van',
                    'make'    => 'Ford',
                    'model'   => 'Transit 350 HD',
                    'year'    => '2023',
                    'vin'     => 'HVAC1234567890101',
                    'plate'   => 'HVC-101',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'HVAC Service Van #2',
                    'type'    => 'van',
                    'make'    => 'Ford',
                    'model'   => 'Transit 350 HD',
                    'year'    => '2022',
                    'vin'     => 'HVAC1234567890102',
                    'plate'   => 'HVC-102',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'HVAC Service Van #3',
                    'type'    => 'van',
                    'make'    => 'RAM',
                    'model'   => 'ProMaster 3500',
                    'year'    => '2023',
                    'vin'     => 'HVAC1234567890103',
                    'plate'   => 'HVC-103',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Install Truck #1',
                    'type'    => 'truck',
                    'make'    => 'Isuzu',
                    'model'   => 'NPR-XD',
                    'year'    => '2022',
                    'vin'     => 'HVAC1234567890201',
                    'plate'   => 'INS-201',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Install Truck #2',
                    'type'    => 'truck',
                    'make'    => 'Hino',
                    'model'   => '155',
                    'year'    => '2021',
                    'vin'     => 'HVAC1234567890202',
                    'plate'   => 'INS-202',
                    'status'  => 'active',
                ],
                [
                    'name'    => 'Refrigeration Van',
                    'type'    => 'van',
                    'make'    => 'Chevrolet',
                    'model'   => 'Express 3500',
                    'year'    => '2020',
                    'vin'     => 'HVAC1234567890301',
                    'plate'   => 'REF-301',
                    'status'  => 'maintenance',
                ],
            ],
        ];

        return $vehicles[$template] ?? $vehicles['plumbing'];
    }

    /**
     * Create demo technicians for organization.
     *
     * @param int    $org_id Organization ID.
     * @param array  $vehicles Available vehicle IDs.
     * @param string $template Template type.
     * @return array Created technician data with IDs.
     */
    public static function create_demo_technicians($org_id, $vehicles, $template = 'plumbing') {
        $technicians = [];
        $org_slug = get_post_meta($org_id, '_fsm_org_slug', true);
        $tech_data = self::get_technician_data($template);

        foreach ($tech_data as $index => $data) {
            $username = strtolower($data['first_name']) . '_' . $org_slug;

            // Create WordPress user
            $user_id = null;
            if (!username_exists($username)) {
                $user_id = wp_create_user(
                    $username,
                    wp_generate_password(24, true, true),
                    $username . '@demo.example.com'
                );

                if (!is_wp_error($user_id)) {
                    $user = new WP_User($user_id);
                    $user->set_role('fsm_technician');
                    update_user_meta($user_id, 'first_name', $data['first_name']);
                    update_user_meta($user_id, 'last_name', $data['last_name']);
                    FSM_Organizations::set_user_organization($user_id, $org_id);
                }
            } else {
                $user = get_user_by('login', $username);
                $user_id = $user ? $user->ID : null;
            }

            // Create technician post
            $tech_name = $data['first_name'] . ' ' . $data['last_name'];
            $post_id = wp_insert_post([
                'post_type'   => 'fsm_technician',
                'post_title'  => $tech_name,
                'post_status' => 'publish',
            ]);

            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_fsm_organization_id', $org_id);
                update_post_meta($post_id, '_fsm_employee_id', $data['employee_id']);
                update_post_meta($post_id, '_fsm_phone', $data['phone']);
                update_post_meta($post_id, '_fsm_status', $data['status']);
                update_post_meta($post_id, '_fsm_skills', $data['skills']);

                if ($user_id) {
                    update_post_meta($post_id, '_fsm_user_id', $user_id);
                }

                // Assign vehicle if available
                if (isset($vehicles[$index])) {
                    update_post_meta($post_id, '_fsm_assigned_vehicle_id', $vehicles[$index]);
                    update_post_meta($vehicles[$index], '_fsm_assigned_technician_id', $post_id);
                }

                $technicians[] = [
                    'post_id'  => $post_id,
                    'user_id'  => $user_id,
                    'name'     => $tech_name,
                    'status'   => $data['status'],
                ];
            }
        }

        return $technicians;
    }

    /**
     * Get technician data for template.
     *
     * @param string $template Template type.
     * @return array Technician data.
     */
    private static function get_technician_data($template) {
        $technicians = [
            'plumbing' => [
                [
                    'first_name'  => 'Mike',
                    'last_name'   => 'Reynolds',
                    'employee_id' => 'PLB001',
                    'phone'       => '(555) 247-5001',
                    'skills'      => ['Master Plumber', 'Water Heater', 'Gas Piping', 'Sewer Line'],
                    'status'      => 'available',
                ],
                [
                    'first_name'  => 'Carlos',
                    'last_name'   => 'Mendez',
                    'employee_id' => 'PLB002',
                    'phone'       => '(555) 247-5002',
                    'skills'      => ['Journeyman Plumber', 'Drain Cleaning', 'Pipe Repair', 'Fixture Install'],
                    'status'      => 'on_job',
                ],
                [
                    'first_name'  => 'Sarah',
                    'last_name'   => 'Chen',
                    'employee_id' => 'PLB003',
                    'phone'       => '(555) 247-5003',
                    'skills'      => ['Journeyman Plumber', 'Water Heater', 'Tankless Systems', 'Repiping'],
                    'status'      => 'available',
                ],
                [
                    'first_name'  => 'Derek',
                    'last_name'   => 'Thompson',
                    'employee_id' => 'PLB004',
                    'phone'       => '(555) 247-5004',
                    'skills'      => ['Drain Specialist', 'Camera Inspection', 'Hydro Jetting', 'Sewer Repair'],
                    'status'      => 'en_route',
                ],
                [
                    'first_name'  => 'Jessica',
                    'last_name'   => 'Martinez',
                    'employee_id' => 'PLB005',
                    'phone'       => '(555) 247-5005',
                    'skills'      => ['Apprentice Plumber', 'Fixture Install', 'Basic Repairs'],
                    'status'      => 'on_job',
                ],
                [
                    'first_name'  => 'Brandon',
                    'last_name'   => 'Walker',
                    'employee_id' => 'PLB006',
                    'phone'       => '(555) 247-5006',
                    'skills'      => ['Master Plumber', 'Commercial Plumbing', 'Backflow Prevention', 'Gas Piping'],
                    'status'      => 'off_duty',
                ],
            ],
            'electrical' => [
                [
                    'first_name'  => 'David',
                    'last_name'   => 'Morrison',
                    'employee_id' => 'ELC001',
                    'phone'       => '(555) 392-8001',
                    'skills'      => ['Master Electrician', 'Panel Upgrades', 'Commercial Wiring', 'Troubleshooting'],
                    'status'      => 'available',
                ],
                [
                    'first_name'  => 'Lisa',
                    'last_name'   => 'Nguyen',
                    'employee_id' => 'ELC002',
                    'phone'       => '(555) 392-8002',
                    'skills'      => ['Journeyman Electrician', 'Residential Wiring', 'Lighting', 'Ceiling Fans'],
                    'status'      => 'on_job',
                ],
                [
                    'first_name'  => 'Robert',
                    'last_name'   => 'Jackson',
                    'employee_id' => 'ELC003',
                    'phone'       => '(555) 392-8003',
                    'skills'      => ['Journeyman Electrician', 'EV Chargers', 'Smart Home', 'Generator Install'],
                    'status'      => 'available',
                ],
                [
                    'first_name'  => 'Amanda',
                    'last_name'   => 'Foster',
                    'employee_id' => 'ELC004',
                    'phone'       => '(555) 392-8004',
                    'skills'      => ['Journeyman Electrician', 'Panel Upgrades', 'Rewiring', 'Code Compliance'],
                    'status'      => 'en_route',
                ],
                [
                    'first_name'  => 'Marcus',
                    'last_name'   => 'Williams',
                    'employee_id' => 'ELC005',
                    'phone'       => '(555) 392-8005',
                    'skills'      => ['Apprentice Electrician', 'Outlet Install', 'Basic Wiring'],
                    'status'      => 'on_job',
                ],
                [
                    'first_name'  => 'Kevin',
                    'last_name'   => 'O\'Brien',
                    'employee_id' => 'ELC006',
                    'phone'       => '(555) 392-8006',
                    'skills'      => ['Master Electrician', 'Industrial', 'Three-Phase', 'Motor Controls'],
                    'status'      => 'off_duty',
                ],
            ],
            'hvac' => [
                [
                    'first_name'  => 'James',
                    'last_name'   => 'Patterson',
                    'employee_id' => 'HVC001',
                    'phone'       => '(555) 482-2001',
                    'skills'      => ['HVAC Master Tech', 'Commercial Systems', 'Refrigeration', 'Controls'],
                    'status'      => 'available',
                ],
                [
                    'first_name'  => 'Maria',
                    'last_name'   => 'Garcia',
                    'employee_id' => 'HVC002',
                    'phone'       => '(555) 482-2002',
                    'skills'      => ['HVAC Technician', 'AC Repair', 'Heat Pumps', 'Ductwork'],
                    'status'      => 'on_job',
                ],
                [
                    'first_name'  => 'Chris',
                    'last_name'   => 'Anderson',
                    'employee_id' => 'HVC003',
                    'phone'       => '(555) 482-2003',
                    'skills'      => ['HVAC Technician', 'Furnace Repair', 'Boilers', 'Radiant Heat'],
                    'status'      => 'available',
                ],
                [
                    'first_name'  => 'Emily',
                    'last_name'   => 'Wright',
                    'employee_id' => 'HVC004',
                    'phone'       => '(555) 482-2004',
                    'skills'      => ['Install Technician', 'New Construction', 'System Replacement', 'Ductwork'],
                    'status'      => 'en_route',
                ],
                [
                    'first_name'  => 'Tyler',
                    'last_name'   => 'Brooks',
                    'employee_id' => 'HVC005',
                    'phone'       => '(555) 482-2005',
                    'skills'      => ['Apprentice Tech', 'Maintenance', 'Filter Changes', 'Basic Repairs'],
                    'status'      => 'on_job',
                ],
                [
                    'first_name'  => 'Rachel',
                    'last_name'   => 'Kim',
                    'employee_id' => 'HVC006',
                    'phone'       => '(555) 482-2006',
                    'skills'      => ['Refrigeration Specialist', 'Walk-in Coolers', 'Ice Machines', 'Commercial AC'],
                    'status'      => 'off_duty',
                ],
            ],
        ];

        return $technicians[$template] ?? $technicians['plumbing'];
    }

    /**
     * Create work order type taxonomy terms with term meta.
     *
     * @param string $template Template type.
     * @return array Map of slug => term_id.
     */
    public static function create_demo_work_order_types($template) {
        $type_defs = [
            'plumbing' => [
                ['name' => 'Repair',       'slug' => 'repair',       'duration' => 60,  'color' => '#f97316', 'icon' => 'wrench'],
                ['name' => 'Installation', 'slug' => 'installation', 'duration' => 180, 'color' => '#3b82f6', 'icon' => 'package'],
                ['name' => 'Maintenance',  'slug' => 'maintenance',  'duration' => 90,  'color' => '#8b5cf6', 'icon' => 'tool'],
                ['name' => 'Inspection',   'slug' => 'inspection',   'duration' => 45,  'color' => '#06b6d4', 'icon' => 'search'],
                ['name' => 'Emergency',    'slug' => 'emergency',    'duration' => 120, 'color' => '#ef4444', 'icon' => 'alert-triangle'],
                ['name' => 'Consultation', 'slug' => 'consultation', 'duration' => 30,  'color' => '#10b981', 'icon' => 'message-circle'],
            ],
            'electrical' => [
                ['name' => 'Repair',       'slug' => 'repair',       'duration' => 60,  'color' => '#f97316', 'icon' => 'wrench'],
                ['name' => 'Installation', 'slug' => 'installation', 'duration' => 180, 'color' => '#3b82f6', 'icon' => 'package'],
                ['name' => 'Maintenance',  'slug' => 'maintenance',  'duration' => 90,  'color' => '#8b5cf6', 'icon' => 'tool'],
                ['name' => 'Inspection',   'slug' => 'inspection',   'duration' => 45,  'color' => '#06b6d4', 'icon' => 'search'],
                ['name' => 'Emergency',    'slug' => 'emergency',    'duration' => 120, 'color' => '#ef4444', 'icon' => 'alert-triangle'],
                ['name' => 'Consultation', 'slug' => 'consultation', 'duration' => 30,  'color' => '#10b981', 'icon' => 'message-circle'],
            ],
            'hvac' => [
                ['name' => 'Repair',       'slug' => 'repair',       'duration' => 90,  'color' => '#f97316', 'icon' => 'wrench'],
                ['name' => 'Installation', 'slug' => 'installation', 'duration' => 240, 'color' => '#3b82f6', 'icon' => 'package'],
                ['name' => 'Maintenance',  'slug' => 'maintenance',  'duration' => 60,  'color' => '#8b5cf6', 'icon' => 'tool'],
                ['name' => 'Inspection',   'slug' => 'inspection',   'duration' => 45,  'color' => '#06b6d4', 'icon' => 'search'],
                ['name' => 'Emergency',    'slug' => 'emergency',    'duration' => 120, 'color' => '#ef4444', 'icon' => 'alert-triangle'],
                ['name' => 'Consultation', 'slug' => 'consultation', 'duration' => 30,  'color' => '#10b981', 'icon' => 'message-circle'],
            ],
        ];

        $defs = $type_defs[$template] ?? $type_defs['plumbing'];
        $type_map = [];

        foreach ($defs as $def) {
            // Check if the term already exists (shared taxonomy across orgs)
            $existing = term_exists($def['slug'], 'fsm_work_order_type');
            if ($existing) {
                $term_id = is_array($existing) ? $existing['term_id'] : $existing;
            } else {
                $result = wp_insert_term($def['name'], 'fsm_work_order_type', ['slug' => $def['slug']]);
                if (is_wp_error($result)) {
                    continue;
                }
                $term_id = $result['term_id'];
            }

            // Set term meta (update even if term existed to ensure correct values)
            update_term_meta($term_id, '_fsm_wot_default_duration', absint($def['duration']));
            update_term_meta($term_id, '_fsm_wot_color', sanitize_hex_color($def['color']));
            update_term_meta($term_id, '_fsm_wot_icon', sanitize_text_field($def['icon']));

            $type_map[$def['slug']] = (int) $term_id;
        }

        return $type_map;
    }

    /**
     * Create demo work orders for organization.
     *
     * @param int    $org_id Organization ID.
     * @param array  $technicians Available technicians.
     * @param string $template Template type.
     * @param array  $type_map Map of slug => term_id.
     * @return array Created work order IDs.
     */
    public static function create_demo_work_orders($org_id, $technicians, $template = 'plumbing', $type_map = []) {
        $work_orders = [];
        $wo_data = self::get_work_order_data($template);

        foreach ($wo_data as $data) {
            $post_id = wp_insert_post([
                'post_type'   => 'fsm_work_order',
                'post_title'  => $data['title'],
                'post_content'=> $data['description'],
                'post_status' => 'publish',
            ]);

            if (!is_wp_error($post_id)) {
                // Organization tracking
                update_post_meta($post_id, '_fsm_organization_id', $org_id);

                // Work order fields use _fsm_wo_ prefix to match REST API
                update_post_meta($post_id, '_fsm_wo_customer_name', $data['customer']);
                update_post_meta($post_id, '_fsm_wo_customer_phone', $data['phone']);
                update_post_meta($post_id, '_fsm_wo_priority', $data['priority']);
                update_post_meta($post_id, '_fsm_wo_status', $data['status']);
                update_post_meta($post_id, '_fsm_wo_scheduled_date', $data['scheduled']);

                // Adjust time windows for today's WOs so they're always relevant
                $time_start = $data['time_start'];
                $time_end   = $data['time_end'];
                if ($data['scheduled'] === date('Y-m-d')) {
                    $adjusted = self::adjust_demo_time_window($data['status'], $data['time_start'], $data['time_end']);
                    $time_start = $adjusted['start'];
                    $time_end   = $adjusted['end'];
                }
                update_post_meta($post_id, '_fsm_wo_scheduled_time_start', $time_start);
                update_post_meta($post_id, '_fsm_wo_scheduled_time_end', $time_end);

                // Parse and store address components
                $address_parts = self::parse_demo_address($data['address']);
                update_post_meta($post_id, '_fsm_wo_address_street', $address_parts['street']);
                update_post_meta($post_id, '_fsm_wo_address_city', $address_parts['city']);
                update_post_meta($post_id, '_fsm_wo_address_state', $address_parts['state']);
                update_post_meta($post_id, '_fsm_wo_address_zip', $address_parts['zip']);

                // Generate simulated GPS coordinates for the address (Springfield, IL area)
                $coords = self::generate_demo_coordinates($address_parts['street']);
                update_post_meta($post_id, '_fsm_wo_latitude', (string) $coords['lat']);
                update_post_meta($post_id, '_fsm_wo_longitude', (string) $coords['lng']);

                // Generate a work order reference number
                $wo_reference = 'WO-' . date('Ymd') . '-' . str_pad($post_id, 4, '0', STR_PAD_LEFT);
                update_post_meta($post_id, '_fsm_wo_reference', $wo_reference);

                // Assign technician if specified
                if ($data['tech_index'] !== null && isset($technicians[$data['tech_index']])) {
                    $tech = $technicians[$data['tech_index']];
                    update_post_meta($post_id, '_fsm_wo_assigned_technicians', [$tech['post_id']]);
                    update_post_meta($post_id, '_fsm_wo_primary_technician_id', $tech['post_id']);
                } else {
                    update_post_meta($post_id, '_fsm_wo_assigned_technicians', []);
                }

                // Set started_at for in-progress work orders so time-on-site displays
                if ($data['status'] === 'in_progress') {
                    $started_offset = rand(20, 90); // 20-90 minutes ago
                    $started_time = date('Y-m-d H:i:s', strtotime("-{$started_offset} minutes"));
                    update_post_meta($post_id, '_fsm_wo_started_at', $started_time);
                }

                // Assign taxonomy type(s) if type_map is available
                if (!empty($type_map) && !empty($data['type'])) {
                    $type_slugs = is_array($data['type']) ? $data['type'] : [$data['type']];
                    $term_ids = [];
                    foreach ($type_slugs as $slug) {
                        if (isset($type_map[$slug])) {
                            $term_ids[] = (int) $type_map[$slug];
                        }
                    }
                    if (!empty($term_ids)) {
                        wp_set_object_terms($post_id, $term_ids, 'fsm_work_order_type');
                    }
                }

                $work_orders[] = $post_id;
            }
        }

        return $work_orders;
    }

    /**
     * Adjust a demo WO's time window so it's relevant to "now".
     *
     * - completed: ended 1-3h ago
     * - in_progress: started 20-60min ago, ends 30-90min from now
     * - en_route: starts now-ish, ends 1-2h from now
     * - scheduled: starts 30min-4h from now
     *
     * @param string $status WO status.
     * @param string $orig_start Original HH:MM.
     * @param string $orig_end Original HH:MM.
     * @return array {start: HH:MM, end: HH:MM}
     */
    private static function adjust_demo_time_window($status, $orig_start, $orig_end) {
        // Track an offset counter so multiple WOs of the same status spread out
        static $offset_counter = 0;
        $offset_counter++;
        $spread = ($offset_counter % 5) * 15; // 0, 15, 30, 45, 60 min spread

        $now = current_time('timestamp');

        switch ($status) {
            case 'completed':
                $start = $now - (180 + $spread) * 60; // 3-4h ago
                $dur = (int) (strtotime($orig_end) - strtotime($orig_start)); // original duration
                if ($dur <= 0) $dur = 3600;
                return [
                    'start' => date('H:i', $start),
                    'end'   => date('H:i', $start + $dur),
                ];

            case 'in_progress':
                $start = $now - (20 + $spread) * 60; // 20-80min ago
                $end = $now + (60 + $spread) * 60;   // 60-120min from now
                return [
                    'start' => date('H:i', $start),
                    'end'   => date('H:i', $end),
                ];

            case 'en_route':
                $start = $now - (5 + $spread) * 60;
                $end = $now + (90 + $spread) * 60;
                return [
                    'start' => date('H:i', $start),
                    'end'   => date('H:i', $end),
                ];

            case 'scheduled':
                $start = $now + (30 + $spread) * 60; // 30min-1.5h from now
                $dur = (int) (strtotime($orig_end) - strtotime($orig_start));
                if ($dur <= 0) $dur = 3600;
                return [
                    'start' => date('H:i', $start),
                    'end'   => date('H:i', $start + $dur),
                ];

            default:
                return ['start' => $orig_start, 'end' => $orig_end];
        }
    }

    /**
     * Get work order data for template.
     * Includes multiple work orders scheduled for "today" to demonstrate real-time dispatching.
     *
     * @param string $template Template type.
     * @return array Work order data.
     */
    private static function get_work_order_data($template) {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $in_2_days = date('Y-m-d', strtotime('+2 days'));
        $in_3_days = date('Y-m-d', strtotime('+3 days'));
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $work_orders = [
            'plumbing' => [
                // TODAY - Morning jobs
                [
                    'title'       => 'Kitchen Sink Clogged - Anderson Residence',
                    'customer'    => 'Jennifer Anderson',
                    'address'     => '456 Oak Street, Springfield, IL 62701',
                    'phone'       => '(555) 111-2234',
                    'description' => 'Kitchen sink draining slowly. Garbage disposal working but water backs up. Customer suspects grease buildup.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'completed',
                    'scheduled'   => $today,
                    'time_start'  => '08:00',
                    'time_end'    => '09:30',
                    'tech_index'  => 1, // Carlos
                ],
                [
                    'title'       => 'Leaking Faucet Repair - Williams Home',
                    'customer'    => 'Patricia Williams',
                    'address'     => '1523 Riverside Dr, Springfield, IL 62702',
                    'phone'       => '(555) 111-2235',
                    'description' => 'Bathroom faucet dripping constantly. Customer reports it started last week and is getting worse.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '10:00',
                    'time_end'    => '11:00',
                    'tech_index'  => 1, // Carlos
                ],
                [
                    'title'       => 'Water Heater Inspection - Morris Property',
                    'customer'    => 'David Morris',
                    'address'     => '892 Lincoln Ave, Springfield, IL 62701',
                    'phone'       => '(555) 111-2236',
                    'description' => 'Annual water heater inspection. 40-gallon gas unit, 5 years old. Customer reports slight rust color in hot water.',
                    'type'        => 'inspection',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '11:30',
                    'time_end'    => '12:30',
                    'tech_index'  => 1, // Carlos - next job
                ],
                // TODAY - Mike (Master Plumber)
                [
                    'title'       => 'Gas Line Pressure Test - New Build',
                    'customer'    => 'Springfield Home Builders',
                    'address'     => '450 New Development Way, Lot 8, Springfield, IL 62704',
                    'phone'       => '(555) 666-7789',
                    'description' => 'Gas line pressure test for new construction. Inspector scheduled for 10am. Must have system pressurized and ready.',
                    'type'        => 'inspection',
                    'priority'    => 'high',
                    'status'      => 'completed',
                    'scheduled'   => $today,
                    'time_start'  => '07:30',
                    'time_end'    => '09:30',
                    'tech_index'  => 0, // Mike
                ],
                [
                    'title'       => 'Tankless Water Heater Install - Peterson Home',
                    'customer'    => 'Michael Peterson',
                    'address'     => '2847 Summit Rd, Springfield, IL 62702',
                    'phone'       => '(555) 111-2238',
                    'description' => 'Install Rinnai RU199iN tankless water heater. Remove existing 50-gal tank. Gas line already sized appropriately.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '10:00',
                    'time_end'    => '14:00',
                    'tech_index'  => 0, // Mike
                ],
                // TODAY - Sarah
                [
                    'title'       => 'Water Softener Installation - Baker Residence',
                    'customer'    => 'Susan Baker',
                    'address'     => '734 Meadow Lane, Springfield, IL 62703',
                    'phone'       => '(555) 111-2239',
                    'description' => 'Install new Fleck 5600SXT water softener system. Customer has well water with high iron content.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '08:00',
                    'time_end'    => '11:30',
                    'tech_index'  => 2, // Sarah
                ],
                [
                    'title'       => 'Garbage Disposal Replacement - Kim Home',
                    'customer'    => 'Jennifer Kim',
                    'address'     => '1156 Cherry Blossom Way, Springfield, IL 62701',
                    'phone'       => '(555) 111-2240',
                    'description' => 'Replace failed garbage disposal. Customer wants InSinkErator Evolution Excel. Standard single bowl sink.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '13:00',
                    'time_end'    => '14:30',
                    'tech_index'  => 2, // Sarah
                ],
                // TODAY - Derek (Drain Specialist)
                [
                    'title'       => 'Main Line Sewer Backup - URGENT',
                    'customer'    => 'Robert Chen',
                    'address'     => '321 Pine Road, Springfield, IL 62701',
                    'phone'       => '(555) 333-4456',
                    'description' => 'URGENT: Main sewer line backing up into basement. Multiple drains affected. Customer has shut off water usage.',
                    'type'        => ['repair', 'emergency'],
                    'priority'    => 'urgent',
                    'status'      => 'en_route',
                    'scheduled'   => $today,
                    'time_start'  => '11:00',
                    'time_end'    => '13:30',
                    'tech_index'  => 3, // Derek
                ],
                [
                    'title'       => 'Camera Inspection Follow-up - Adams Property',
                    'customer'    => 'Williams Property Mgmt',
                    'address'     => '890 Commerce Blvd, Springfield, IL 62702',
                    'phone'       => '(555) 555-6678',
                    'description' => 'Follow-up camera inspection after hydro-jetting last week. Document condition and provide report for property manager.',
                    'type'        => 'inspection',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '14:30',
                    'time_end'    => '16:00',
                    'tech_index'  => 3, // Derek
                ],
                // TODAY - Jessica (Apprentice)
                [
                    'title'       => 'Toilet Replacement - Davis Apartment',
                    'customer'    => 'Sarah Davis',
                    'address'     => '1200 Cedar Lane, Apt 4B, Springfield, IL 62703',
                    'phone'       => '(555) 444-5567',
                    'description' => 'Replace old toilet with new TOTO Drake II. Include new wax ring and supply line. Customer provided toilet.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '09:00',
                    'time_end'    => '11:00',
                    'tech_index'  => 4, // Jessica
                ],
                [
                    'title'       => 'Bathroom Faucet Install - Garcia Home',
                    'customer'    => 'Maria Garcia',
                    'address'     => '567 Birch Street, Springfield, IL 62701',
                    'phone'       => '(555) 777-8890',
                    'description' => 'Install customer-provided Delta faucet in master bathroom. Remove old Moen faucet. Check supply lines.',
                    'type'        => 'installation',
                    'priority'    => 'low',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '13:00',
                    'time_end'    => '14:30',
                    'tech_index'  => 4, // Jessica
                ],
                [
                    'title'       => 'Outdoor Faucet Repair - Nelson Home',
                    'customer'    => 'Gary Nelson',
                    'address'     => '2341 Garden View Ct, Springfield, IL 62702',
                    'phone'       => '(555) 111-2245',
                    'description' => 'Outdoor hose bib leaking from handle. Likely needs stem packing or full replacement. Frost-free type.',
                    'type'        => 'repair',
                    'priority'    => 'low',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '15:00',
                    'time_end'    => '16:00',
                    'tech_index'  => 4, // Jessica
                ],
                // TOMORROW
                [
                    'title'       => 'Water Heater Replacement - Thompson Home',
                    'customer'    => 'Mark Thompson',
                    'address'     => '789 Maple Avenue, Springfield, IL 62702',
                    'phone'       => '(555) 222-3345',
                    'description' => 'Replace 50 gallon gas water heater. Current unit is 15 years old and leaking from bottom. Customer approved Rheem ProTerra hybrid.',
                    'type'        => 'installation',
                    'priority'    => 'high',
                    'status'      => 'scheduled',
                    'scheduled'   => $tomorrow,
                    'time_start'  => '08:00',
                    'time_end'    => '12:00',
                    'tech_index'  => 2, // Sarah
                ],
                [
                    'title'       => 'Tankless Water Heater Flush',
                    'customer'    => 'James Wilson',
                    'address'     => '234 Elm Drive, Springfield, IL 62702',
                    'phone'       => '(555) 888-9901',
                    'description' => 'Annual maintenance flush for Navien NPE-240A tankless water heater. Unit is 3 years old. Customer reports occasional temperature fluctuation.',
                    'type'        => 'maintenance',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $tomorrow,
                    'time_start'  => '15:00',
                    'time_end'    => '16:30',
                    'tech_index'  => 2, // Sarah
                ],
                // Future jobs
                [
                    'title'       => 'Main Sewer Line Camera Inspection',
                    'customer'    => 'Springfield Plaza Management',
                    'address'     => '1500 Main Street, Springfield, IL 62702',
                    'phone'       => '(555) 555-6679',
                    'description' => 'Annual camera inspection of main sewer line for commercial plaza. Previous inspection showed early root intrusion.',
                    'type'        => 'inspection',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $in_2_days,
                    'time_start'  => '10:00',
                    'time_end'    => '12:00',
                    'tech_index'  => 3, // Derek
                ],
                [
                    'title'       => 'New Construction Rough-In',
                    'customer'    => 'Springfield Home Builders',
                    'address'     => '450 New Development Way, Lot 12, Springfield, IL 62704',
                    'phone'       => '(555) 666-7789',
                    'description' => 'Rough-in plumbing for new 3BR/2BA single family home. Plans on file. Coordinate with GC for inspection scheduling.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $in_3_days,
                    'time_start'  => '07:00',
                    'time_end'    => '15:00',
                    'tech_index'  => 0, // Mike
                ],
                [
                    'title'       => 'Gas Line for Outdoor Grill',
                    'customer'    => 'Tom & Linda Brown',
                    'address'     => '678 Willow Court, Springfield, IL 62703',
                    'phone'       => '(555) 999-0012',
                    'description' => 'Install new gas line from basement meter to backyard patio for built-in grill. Approx 40ft run. Customer has permit.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'pending',
                    'scheduled'   => date('Y-m-d', strtotime('+4 days')),
                    'time_start'  => '08:00',
                    'time_end'    => '14:00',
                    'tech_index'  => null, // Unassigned
                ],
                // Yesterday - completed
                [
                    'title'       => 'Completed - Garbage Disposal Replace',
                    'customer'    => 'Kevin O\'Malley',
                    'address'     => '901 Spruce Lane, Springfield, IL 62701',
                    'phone'       => '(555) 123-4567',
                    'description' => 'Replaced failed InSinkErator Badger 5 with Evolution Excel. Tested operation and drainage. Customer signed off.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'completed',
                    'scheduled'   => $yesterday,
                    'time_start'  => '10:00',
                    'time_end'    => '11:30',
                    'tech_index'  => 1, // Carlos
                ],
            ],
            'electrical' => [
                // TODAY - Morning
                [
                    'title'       => 'Panel Upgrade 100A to 200A - Miller Home',
                    'customer'    => 'David Miller',
                    'address'     => '345 Voltage Way, Springfield, IL 62701',
                    'phone'       => '(555) 111-2001',
                    'description' => 'Upgrade electrical panel from 100A to 200A. Current panel is full and customer adding EV charger. Permit approved.',
                    'type'        => 'installation',
                    'priority'    => 'high',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '07:30',
                    'time_end'    => '12:30',
                    'tech_index'  => 0, // David (Master)
                ],
                [
                    'title'       => 'Smoke Detector Replacement - Rental Property',
                    'customer'    => 'Martin Rentals LLC',
                    'address'     => '567 Tenant Road, Unit A, Springfield, IL 62702',
                    'phone'       => '(555) 999-0009',
                    'description' => 'Replace 6 smoke detectors with hardwired interconnected units. Code compliance upgrade for rental property.',
                    'type'        => 'maintenance',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '13:30',
                    'time_end'    => '15:30',
                    'tech_index'  => 0, // David
                ],
                // TODAY - Lisa
                [
                    'title'       => 'Ceiling Fan Installation - Harper Home',
                    'customer'    => 'Amanda Harper',
                    'address'     => '1892 Breeze Way, Springfield, IL 62703',
                    'phone'       => '(555) 111-2002',
                    'description' => 'Install 2 ceiling fans in bedrooms. Customer provided Hunter fans. Existing ceiling boxes need verification.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'completed',
                    'scheduled'   => $today,
                    'time_start'  => '08:00',
                    'time_end'    => '10:30',
                    'tech_index'  => 1, // Lisa
                ],
                [
                    'title'       => 'Outlet Repair - Office Suite',
                    'customer'    => 'Downtown Business Center',
                    'address'     => '400 Main Street, Suite 302, Springfield, IL 62701',
                    'phone'       => '(555) 111-2003',
                    'description' => 'Three outlets not working in corner office. Circuit breaker not tripped. Check for loose connections.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '11:00',
                    'time_end'    => '12:30',
                    'tech_index'  => 1, // Lisa
                ],
                [
                    'title'       => 'Recessed Lighting Install - Kitchen Remodel',
                    'customer'    => 'Brian & Amy Taylor',
                    'address'     => '123 Watt Lane, Springfield, IL 62703',
                    'phone'       => '(555) 444-5004',
                    'description' => 'Install 8 LED recessed lights in kitchen ceiling. Include dimmer switch. Customer provided lights.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '14:00',
                    'time_end'    => '17:30',
                    'tech_index'  => 1, // Lisa
                ],
                // TODAY - Robert
                [
                    'title'       => 'EV Charger Installation - Patel Residence',
                    'customer'    => 'Raj Patel',
                    'address'     => '567 Circuit Drive, Springfield, IL 62702',
                    'phone'       => '(555) 222-3002',
                    'description' => 'Install ChargePoint Home Flex 50A EV charger in garage. Run dedicated 60A circuit from panel. Wall-mount installation.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '08:00',
                    'time_end'    => '11:30',
                    'tech_index'  => 2, // Robert
                ],
                [
                    'title'       => 'Smart Switch Installation - Modern Home',
                    'customer'    => 'Tech Solutions Inc',
                    'address'     => '1000 Innovation Blvd, Suite 200, Springfield, IL 62704',
                    'phone'       => '(555) 777-8007',
                    'description' => 'Install Lutron Caseta smart switches throughout office (8 switches). Program with existing Alexa system.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '13:00',
                    'time_end'    => '16:00',
                    'tech_index'  => 2, // Robert
                ],
                // TODAY - Amanda
                [
                    'title'       => 'Emergency - No Power to Half House',
                    'customer'    => 'Susan Chang',
                    'address'     => '890 Amp Street, Springfield, IL 62701',
                    'phone'       => '(555) 333-4003',
                    'description' => 'URGENT: Lost power to entire second floor and some first floor outlets. Breakers not tripped. Possible main feed issue.',
                    'type'        => ['repair', 'emergency'],
                    'priority'    => 'urgent',
                    'status'      => 'en_route',
                    'scheduled'   => $today,
                    'time_start'  => '10:30',
                    'time_end'    => '13:00',
                    'tech_index'  => 3, // Amanda
                ],
                [
                    'title'       => 'GFCI Installation - Bathroom Update',
                    'customer'    => 'Nancy Roberts',
                    'address'     => '789 Ohm Court, Springfield, IL 62701',
                    'phone'       => '(555) 666-7006',
                    'description' => 'Replace 4 standard outlets with GFCI outlets in bathrooms. Code compliance for home sale.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '14:30',
                    'time_end'    => '16:00',
                    'tech_index'  => 3, // Amanda
                ],
                // TODAY - Marcus (Apprentice)
                [
                    'title'       => 'Light Fixture Replacement - Foyer',
                    'customer'    => 'Helen Mitchell',
                    'address'     => '445 Bright St, Springfield, IL 62702',
                    'phone'       => '(555) 111-2008',
                    'description' => 'Replace old chandelier with new LED fixture in two-story foyer. Customer has scaffolding available.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '09:00',
                    'time_end'    => '11:30',
                    'tech_index'  => 4, // Marcus
                ],
                [
                    'title'       => 'Outdoor Lighting Install - Patio',
                    'customer'    => 'George Wilson',
                    'address'     => '456 Power Road, Springfield, IL 62702',
                    'phone'       => '(555) 555-6005',
                    'description' => 'Install 4 outdoor wall sconces on back patio. Run conduit from existing junction box. All-weather rated fixtures.',
                    'type'        => 'installation',
                    'priority'    => 'low',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '13:00',
                    'time_end'    => '15:30',
                    'tech_index'  => 4, // Marcus
                ],
                // Tomorrow and future
                [
                    'title'       => 'Whole House Generator Install',
                    'customer'    => 'George Wilson',
                    'address'     => '456 Power Road, Springfield, IL 62702',
                    'phone'       => '(555) 555-6005',
                    'description' => 'Install Generac 22kW whole house generator with automatic transfer switch. Concrete pad already poured.',
                    'type'        => 'installation',
                    'priority'    => 'high',
                    'status'      => 'scheduled',
                    'scheduled'   => $tomorrow,
                    'time_start'  => '07:00',
                    'time_end'    => '16:00',
                    'tech_index'  => 2, // Robert
                ],
                [
                    'title'       => 'Annual Inspection - Martin Property',
                    'customer'    => 'Martin Rentals LLC',
                    'address'     => '567 Tenant Road, Springfield, IL 62702',
                    'phone'       => '(555) 999-0009',
                    'description' => 'Annual electrical safety inspection for rental property. Check all outlets, switches, panel, smoke detectors.',
                    'type'        => 'inspection',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $in_2_days,
                    'time_start'  => '15:00',
                    'time_end'    => '17:00',
                    'tech_index'  => 3, // Amanda
                ],
                [
                    'title'       => 'Ceiling Fan Installation x3',
                    'customer'    => 'Michelle Adams',
                    'address'     => '234 Current Way, Springfield, IL 62703',
                    'phone'       => '(555) 888-9008',
                    'description' => 'Install 3 ceiling fans (customer provided) - master bedroom, two kids rooms. All rooms have existing ceiling boxes.',
                    'type'        => 'installation',
                    'priority'    => 'low',
                    'status'      => 'pending',
                    'scheduled'   => date('Y-m-d', strtotime('+4 days')),
                    'time_start'  => '09:00',
                    'time_end'    => '14:00',
                    'tech_index'  => null, // Unassigned
                ],
                // Yesterday - completed
                [
                    'title'       => 'Completed - GFCI Outlets Bathroom',
                    'customer'    => 'Paul Henderson',
                    'address'     => '890 Safety Lane, Springfield, IL 62701',
                    'phone'       => '(555) 234-5678',
                    'description' => 'Replaced 4 standard outlets with GFCI outlets in bathrooms per inspection requirement. All tested and functional.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'completed',
                    'scheduled'   => $yesterday,
                    'time_start'  => '10:00',
                    'time_end'    => '12:00',
                    'tech_index'  => 4, // Marcus
                ],
            ],
            'hvac' => [
                // TODAY - James (Master Tech)
                [
                    'title'       => 'Commercial RTU Repair - Office Building',
                    'customer'    => 'Williams & Associates',
                    'address'     => '500 Business Park Drive, Springfield, IL 62704',
                    'phone'       => '(555) 444-6004',
                    'description' => 'Rooftop unit #2 not cooling properly. Compressor running but no cold air. Check refrigerant and compressor.',
                    'type'        => 'repair',
                    'priority'    => 'high',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '07:00',
                    'time_end'    => '10:30',
                    'tech_index'  => 0, // James
                ],
                [
                    'title'       => 'Furnace Tune-Up - Annual Service',
                    'customer'    => 'Catherine Moore',
                    'address'     => '1678 Cozy Lane, Springfield, IL 62701',
                    'phone'       => '(555) 111-3002',
                    'description' => 'Annual furnace maintenance. Clean burners, check heat exchanger, replace filter, test safety controls.',
                    'type'        => 'maintenance',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '11:30',
                    'time_end'    => '13:00',
                    'tech_index'  => 0, // James
                ],
                [
                    'title'       => 'Ductwork Repair - Damaged Section',
                    'customer'    => 'Robert Freeman',
                    'address'     => '2345 Airflow Dr, Springfield, IL 62702',
                    'phone'       => '(555) 111-3003',
                    'description' => 'Flex duct damaged in crawl space. Section disconnected causing loss of airflow to master bedroom.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '14:00',
                    'time_end'    => '15:30',
                    'tech_index'  => 0, // James
                ],
                // TODAY - Maria
                [
                    'title'       => 'AC Not Cooling - Johnson Residence',
                    'customer'    => 'Robert Johnson',
                    'address'     => '456 Comfort Lane, Springfield, IL 62701',
                    'phone'       => '(555) 111-3001',
                    'description' => 'Central AC running but blowing warm air. Outdoor unit making unusual noise. System is Carrier 3-ton, 8 years old.',
                    'type'        => 'repair',
                    'priority'    => 'high',
                    'status'      => 'completed',
                    'scheduled'   => $today,
                    'time_start'  => '08:00',
                    'time_end'    => '10:00',
                    'tech_index'  => 1, // Maria
                ],
                [
                    'title'       => 'AC Service Call - Low Refrigerant',
                    'customer'    => 'Patricia White',
                    'address'     => '892 Cool Breeze Rd, Springfield, IL 62702',
                    'phone'       => '(555) 111-3004',
                    'description' => 'AC not cooling well. Previous company said low refrigerant. Check for leak and recharge if needed.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '10:30',
                    'time_end'    => '12:30',
                    'tech_index'  => 1, // Maria
                ],
                [
                    'title'       => 'Thermostat Not Responding - Smart Home',
                    'customer'    => 'Tech Startup Inc',
                    'address'     => '750 Innovation Way, Springfield, IL 62704',
                    'phone'       => '(555) 111-3005',
                    'description' => 'Ecobee thermostat shows offline. WiFi working fine. System not responding to app or manual control.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '14:00',
                    'time_end'    => '15:30',
                    'tech_index'  => 1, // Maria
                ],
                // TODAY - Chris
                [
                    'title'       => 'Emergency - Furnace Not Igniting',
                    'customer'    => 'Linda Peterson',
                    'address'     => '321 Thermal Drive, Springfield, IL 62701',
                    'phone'       => '(555) 333-5003',
                    'description' => 'URGENT: Gas furnace not igniting. House temp dropping. Elderly resident. System clicks but no ignition.',
                    'type'        => 'emergency',
                    'priority'    => 'urgent',
                    'status'      => 'en_route',
                    'scheduled'   => $today,
                    'time_start'  => '09:00',
                    'time_end'    => '11:00',
                    'tech_index'  => 2, // Chris
                ],
                [
                    'title'       => 'Boiler Service - Annual Maintenance',
                    'customer'    => 'Historic Inn B&B',
                    'address'     => '1800 Heritage Square, Springfield, IL 62701',
                    'phone'       => '(555) 111-3007',
                    'description' => 'Annual service on cast iron boiler. Flush system, check expansion tank, test safety controls.',
                    'type'        => 'maintenance',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '13:00',
                    'time_end'    => '15:30',
                    'tech_index'  => 2, // Chris
                ],
                // TODAY - Emily (Install Tech)
                [
                    'title'       => 'Mini-Split Installation - Sunroom',
                    'customer'    => 'Barbara Lewis',
                    'address'     => '567 Sunshine Way, Springfield, IL 62703',
                    'phone'       => '(555) 111-3008',
                    'description' => 'Install single-zone Mitsubishi mini-split in new sunroom addition. 12,000 BTU wall mount unit.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '08:00',
                    'time_end'    => '13:00',
                    'tech_index'  => 3, // Emily
                ],
                [
                    'title'       => 'Thermostat Upgrade - Chen Residence',
                    'customer'    => 'Wei Chen',
                    'address'     => '890 Smart Home Way, Springfield, IL 62701',
                    'phone'       => '(555) 777-9007',
                    'description' => 'Replace old mercury thermostat with Ecobee Smart Thermostat Premium. Include room sensors.',
                    'type'        => 'installation',
                    'priority'    => 'low',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '14:30',
                    'time_end'    => '16:00',
                    'tech_index'  => 3, // Emily
                ],
                // TODAY - Tyler (Apprentice)
                [
                    'title'       => 'Filter Change - Service Contract',
                    'customer'    => 'Springfield Dental Group',
                    'address'     => '200 Health Center Blvd, Springfield, IL 62702',
                    'phone'       => '(555) 111-3010',
                    'description' => 'Quarterly filter change and visual inspection. 4 systems total. Service contract customer.',
                    'type'        => 'maintenance',
                    'priority'    => 'normal',
                    'status'      => 'completed',
                    'scheduled'   => $today,
                    'time_start'  => '08:30',
                    'time_end'    => '10:00',
                    'tech_index'  => 4, // Tyler
                ],
                [
                    'title'       => 'Condensate Drain Cleaning',
                    'customer'    => 'Michael Brown',
                    'address'     => '567 Fresh Air Blvd, Springfield, IL 62702',
                    'phone'       => '(555) 666-8006',
                    'description' => 'AC condensate drain clogged. Water backing up in emergency pan. Clean drain line and treat with tablets.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'in_progress',
                    'scheduled'   => $today,
                    'time_start'  => '10:30',
                    'time_end'    => '11:30',
                    'tech_index'  => 4, // Tyler
                ],
                [
                    'title'       => 'UV Light Installation - Air Quality',
                    'customer'    => 'Allergy Clinic',
                    'address'     => '150 Medical Plaza, Springfield, IL 62701',
                    'phone'       => '(555) 111-3012',
                    'description' => 'Install REME HALO air purification system in main air handler. Customer concerned about indoor air quality.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $today,
                    'time_start'  => '13:00',
                    'time_end'    => '15:00',
                    'tech_index'  => 4, // Tyler
                ],
                // Tomorrow and future
                [
                    'title'       => 'Full System Replacement - Martinez Home',
                    'customer'    => 'Carlos & Elena Martinez',
                    'address'     => '789 Climate Court, Springfield, IL 62702',
                    'phone'       => '(555) 222-4002',
                    'description' => 'Replace 20-year-old Trane system. Install new Lennox XC21 4-ton AC and SL280 furnace.',
                    'type'        => 'installation',
                    'priority'    => 'high',
                    'status'      => 'scheduled',
                    'scheduled'   => $tomorrow,
                    'time_start'  => '07:00',
                    'time_end'    => '17:00',
                    'tech_index'  => 3, // Emily
                ],
                [
                    'title'       => 'Walk-in Cooler Repair - Fresh Market',
                    'customer'    => 'Fresh Foods Market',
                    'address'     => '200 Market Street, Springfield, IL 62702',
                    'phone'       => '(555) 888-0008',
                    'description' => 'Walk-in cooler temperature fluctuating. Running constantly but temp rising to 45F. Check refrigerant and compressor.',
                    'type'        => 'repair',
                    'priority'    => 'urgent',
                    'status'      => 'scheduled',
                    'scheduled'   => $tomorrow,
                    'time_start'  => '06:00',
                    'time_end'    => '09:00',
                    'tech_index'  => 5, // Rachel (off duty today but scheduled tomorrow)
                ],
                [
                    'title'       => 'Annual Maintenance - Commercial',
                    'customer'    => 'Williams & Associates',
                    'address'     => '500 Business Park Drive, Springfield, IL 62704',
                    'phone'       => '(555) 444-6004',
                    'description' => 'Bi-annual maintenance on remaining 2 commercial rooftop units. Clean coils, replace filters, test controls.',
                    'type'        => 'maintenance',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $in_2_days,
                    'time_start'  => '06:00',
                    'time_end'    => '10:00',
                    'tech_index'  => 0, // James
                ],
                [
                    'title'       => 'Heat Pump Installation - Green Home',
                    'customer'    => 'Sarah Green',
                    'address'     => '234 Eco Way, Springfield, IL 62703',
                    'phone'       => '(555) 555-7005',
                    'description' => 'Install new Mitsubishi hyper-heat heat pump system. 3-zone mini-split for whole house.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'scheduled',
                    'scheduled'   => $in_3_days,
                    'time_start'  => '08:00',
                    'time_end'    => '16:00',
                    'tech_index'  => 3, // Emily
                ],
                [
                    'title'       => 'Zoning System Install',
                    'customer'    => 'Anderson Family',
                    'address'     => '123 Zone Lane, Springfield, IL 62703',
                    'phone'       => '(555) 999-1009',
                    'description' => 'Install Honeywell TrueZONE 3-zone system. Customer has hot/cold spots between floors.',
                    'type'        => 'installation',
                    'priority'    => 'normal',
                    'status'      => 'pending',
                    'scheduled'   => date('Y-m-d', strtotime('+5 days')),
                    'time_start'  => '08:00',
                    'time_end'    => '15:00',
                    'tech_index'  => null, // Unassigned
                ],
                // Yesterday - completed
                [
                    'title'       => 'Completed - Capacitor Replacement',
                    'customer'    => 'Tom Richards',
                    'address'     => '456 Fixed Ave, Springfield, IL 62701',
                    'phone'       => '(555) 345-6789',
                    'description' => 'Replaced failed run capacitor on outdoor AC unit. Tested amp draw and system operation.',
                    'type'        => 'repair',
                    'priority'    => 'normal',
                    'status'      => 'completed',
                    'scheduled'   => $yesterday,
                    'time_start'  => '15:00',
                    'time_end'    => '16:00',
                    'tech_index'  => 1, // Maria
                ],
            ],
        ];

        return $work_orders[$template] ?? $work_orders['plumbing'];
    }

    /**
     * Create demo inventory for organization.
     * Inventory is tracked per-warehouse and per-vehicle (truck stock).
     *
     * @param int    $org_id Organization ID.
     * @param array  $vehicles Available vehicle IDs.
     * @param string $template Template type.
     * @return array Created inventory item IDs.
     */
    public static function create_demo_inventory($org_id, $vehicles, $template = 'plumbing') {
        if (!class_exists('FSM_Inventory_DB')) {
            return [];
        }

        $items = [];
        global $wpdb;
        $table = FSM_Inventory::get_items_table();

        // Create main warehouse inventory
        $warehouse_inventory = self::get_warehouse_inventory_data($template);
        foreach ($warehouse_inventory as $data) {
            $data['organization_id'] = $org_id;
            $data['location_type'] = 'warehouse';
            $data['location_id'] = 0; // Main warehouse
            $wpdb->insert($table, $data);
            if ($wpdb->insert_id) {
                $items[] = $wpdb->insert_id;
            }
        }

        // Create truck/vehicle stock - each vehicle gets its own inventory
        if (!empty($vehicles)) {
            $truck_stock = self::get_vehicle_stock_data($template);

            foreach ($vehicles as $index => $vehicle_id) {
                // Only stock active service vehicles (not maintenance vehicles)
                $vehicle_status = get_post_meta($vehicle_id, '_fsm_status', true);
                if ($vehicle_status !== 'active') {
                    continue;
                }

                $vehicle_title = get_the_title($vehicle_id);

                foreach ($truck_stock as $item) {
                    // Vary quantities slightly per vehicle for realism
                    $qty_variance = rand(-1, 2);
                    $quantity = max(1, $item['quantity'] + $qty_variance);

                    $wpdb->insert($table, [
                        'organization_id' => $org_id,
                        'sku'             => $item['sku'] . '-V' . $vehicle_id,
                        'name'            => $item['name'],
                        'description'     => 'Truck stock for ' . $vehicle_title,
                        'category'        => $item['category'],
                        'unit'            => $item['unit'],
                        'unit_cost'       => $item['unit_cost'],
                        'quantity'        => $quantity,
                        'reorder_point'   => $item['reorder_point'],
                        'location_type'   => 'vehicle',
                        'location_id'     => $vehicle_id,
                    ]);

                    if ($wpdb->insert_id) {
                        $items[] = $wpdb->insert_id;
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Create simulated GPS locations for technicians.
     * Places technicians at realistic locations based on their current status.
     *
     * @param int    $org_id Organization ID.
     * @param array  $technicians Available technicians.
     * @param string $template Template type.
     */
    public static function create_demo_gps_locations($org_id, $technicians, $template = 'plumbing') {
        if (!class_exists('FSM_GPS_DB')) {
            return;
        }

        // Springfield, IL area base coordinates
        $base_lat = 39.7817;
        $base_lng = -89.6501;

        // Simulated locations around Springfield for different scenarios
        $locations = [
            // Customer locations (for techs on_job or in_progress)
            'customer_sites' => [
                ['lat' => 39.7856, 'lng' => -89.6432, 'address' => '456 Oak Street, Springfield, IL 62701'],
                ['lat' => 39.7923, 'lng' => -89.6589, 'address' => '1523 Riverside Dr, Springfield, IL 62702'],
                ['lat' => 39.7745, 'lng' => -89.6678, 'address' => '892 Lincoln Ave, Springfield, IL 62701'],
                ['lat' => 39.8012, 'lng' => -89.6345, 'address' => '734 Meadow Lane, Springfield, IL 62703'],
                ['lat' => 39.7689, 'lng' => -89.6801, 'address' => '1156 Cherry Blossom Way, Springfield, IL 62701'],
                ['lat' => 39.7934, 'lng' => -89.6723, 'address' => '2847 Summit Rd, Springfield, IL 62702'],
            ],
            // En route locations (on the road)
            'en_route' => [
                ['lat' => 39.7878, 'lng' => -89.6556, 'address' => 'Near I-55 & Sangamon Ave'],
                ['lat' => 39.7801, 'lng' => -89.6612, 'address' => 'Near S 6th St & Capitol Ave'],
                ['lat' => 39.7756, 'lng' => -89.6478, 'address' => 'Near MacArthur Blvd & Iles Ave'],
            ],
            // Company HQ/Warehouse
            'hq' => [
                ['lat' => 39.7817, 'lng' => -89.6501, 'address' => '1847 Pipe Drive, Springfield, IL 62701'],
            ],
        ];

        foreach ($technicians as $index => $tech) {
            $status = $tech['status'];
            $tech_id = $tech['post_id'];

            // Skip off-duty technicians
            if ($status === 'off_duty') {
                continue;
            }

            // Find the technician's current active work order (in_progress or en_route)
            $active_wo_id = null;
            $active_wo_query = new WP_Query([
                'post_type'      => 'fsm_work_order',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'   => '_fsm_organization_id',
                        'value' => $org_id,
                    ],
                    [
                        'key'     => '_fsm_wo_status',
                        'value'   => ['in_progress', 'en_route'],
                        'compare' => 'IN',
                    ],
                    [
                        'key'     => '_fsm_wo_primary_technician_id',
                        'value'   => $tech_id,
                    ],
                ],
                'orderby' => 'meta_value',
                'meta_key' => '_fsm_wo_scheduled_time_start',
                'order'   => 'ASC',
            ]);
            if ($active_wo_query->have_posts()) {
                $active_wo_id = $active_wo_query->posts[0]->ID;
            }
            wp_reset_postdata();

            // Determine location based on status
            $location = null;
            $speed = 0;
            $heading = rand(0, 359);

            switch ($status) {
                case 'on_job':
                case 'available': // Available techs might just be finishing up
                    // At a customer site
                    $site_index = $index % count($locations['customer_sites']);
                    $location = $locations['customer_sites'][$site_index];
                    $speed = 0; // Stationary
                    break;

                case 'en_route':
                    // On the road
                    $route_index = $index % count($locations['en_route']);
                    $location = $locations['en_route'][$route_index];
                    $speed = rand(25, 45); // Moving
                    break;

                default:
                    // At HQ or nearby
                    $location = $locations['hq'][0];
                    // Add small random offset
                    $location['lat'] += (rand(-50, 50) / 10000);
                    $location['lng'] += (rand(-50, 50) / 10000);
                    $speed = 0;
            }

            if ($location) {
                // Add slight randomness to coordinates for realism
                $lat = $location['lat'] + (rand(-20, 20) / 100000);
                $lng = $location['lng'] + (rand(-20, 20) / 100000);

                // Record the GPS location with active work order linked
                FSM_GPS_DB::record([
                    'organization_id' => $org_id,
                    'technician_id'   => $tech_id,
                    'latitude'        => $lat,
                    'longitude'       => $lng,
                    'accuracy'        => rand(5, 15),
                    'heading'         => $heading,
                    'speed'           => $speed,
                    'work_order_id'   => $active_wo_id,
                    'source'          => 'device',
                    'address'         => $location['address'],
                    'recorded_at'     => current_time('mysql'),
                ]);

                // Also add a few historical points for trail display
                for ($i = 1; $i <= 3; $i++) {
                    $hist_lat = $lat - ($i * 0.001 * cos(deg2rad($heading)));
                    $hist_lng = $lng - ($i * 0.001 * sin(deg2rad($heading)));
                    $hist_time = date('Y-m-d H:i:s', strtotime("-{$i}0 minutes"));

                    FSM_GPS_DB::record([
                        'organization_id' => $org_id,
                        'technician_id'   => $tech_id,
                        'latitude'        => $hist_lat,
                        'longitude'       => $hist_lng,
                        'accuracy'        => rand(5, 20),
                        'heading'         => $heading,
                        'speed'           => $status === 'en_route' ? rand(20, 50) : 0,
                        'source'          => 'device',
                        'recorded_at'     => $hist_time,
                    ]);
                }
            }
        }
    }

    /**
     * Get warehouse inventory data for template.
     * This is the main warehouse stock - bulk items kept at the shop.
     *
     * @param string $template Template type.
     * @return array Warehouse inventory data.
     */
    private static function get_warehouse_inventory_data($template) {
        $inventory = [
            'plumbing' => [
                // Pipe Stock
                ['sku' => 'PVC-2IN-10FT', 'name' => 'PVC Pipe 2" x 10ft Schedule 40', 'category' => 'Pipe', 'unit' => 'each', 'unit_cost' => 12.50, 'quantity' => 50, 'reorder_point' => 15],
                ['sku' => 'PVC-1.5IN-10FT', 'name' => 'PVC Pipe 1.5" x 10ft Schedule 40', 'category' => 'Pipe', 'unit' => 'each', 'unit_cost' => 9.75, 'quantity' => 60, 'reorder_point' => 20],
                ['sku' => 'PVC-4IN-10FT', 'name' => 'PVC Pipe 4" x 10ft Schedule 40', 'category' => 'Pipe', 'unit' => 'each', 'unit_cost' => 28.00, 'quantity' => 25, 'reorder_point' => 8],
                ['sku' => 'COPPER-0.75-10FT', 'name' => 'Copper Pipe 3/4" x 10ft Type M', 'category' => 'Pipe', 'unit' => 'each', 'unit_cost' => 32.00, 'quantity' => 30, 'reorder_point' => 10],
                ['sku' => 'COPPER-0.5-10FT', 'name' => 'Copper Pipe 1/2" x 10ft Type M', 'category' => 'Pipe', 'unit' => 'each', 'unit_cost' => 24.00, 'quantity' => 40, 'reorder_point' => 12],
                ['sku' => 'PEX-0.75-100FT-R', 'name' => 'PEX Tubing 3/4" x 100ft Red', 'category' => 'Pipe', 'unit' => 'roll', 'unit_cost' => 89.00, 'quantity' => 8, 'reorder_point' => 3],
                ['sku' => 'PEX-0.75-100FT-B', 'name' => 'PEX Tubing 3/4" x 100ft Blue', 'category' => 'Pipe', 'unit' => 'roll', 'unit_cost' => 89.00, 'quantity' => 8, 'reorder_point' => 3],
                ['sku' => 'PEX-0.5-100FT-R', 'name' => 'PEX Tubing 1/2" x 100ft Red', 'category' => 'Pipe', 'unit' => 'roll', 'unit_cost' => 65.00, 'quantity' => 10, 'reorder_point' => 4],
                // Water Heaters
                ['sku' => 'WH-RHEEM-50G', 'name' => 'Rheem 50 Gal Gas Water Heater', 'category' => 'Water Heaters', 'unit' => 'each', 'unit_cost' => 685.00, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'WH-RHEEM-40G', 'name' => 'Rheem 40 Gal Gas Water Heater', 'category' => 'Water Heaters', 'unit' => 'each', 'unit_cost' => 595.00, 'quantity' => 3, 'reorder_point' => 2],
                ['sku' => 'WH-NAVIEN-NPE240', 'name' => 'Navien NPE-240A Tankless', 'category' => 'Water Heaters', 'unit' => 'each', 'unit_cost' => 1650.00, 'quantity' => 2, 'reorder_point' => 1],
                ['sku' => 'WH-RINNAI-RU199', 'name' => 'Rinnai RU199iN Tankless', 'category' => 'Water Heaters', 'unit' => 'each', 'unit_cost' => 1850.00, 'quantity' => 2, 'reorder_point' => 1],
                // Fixtures
                ['sku' => 'TOILET-TOTO-DRAKE', 'name' => 'TOTO Drake II Toilet', 'category' => 'Fixtures', 'unit' => 'each', 'unit_cost' => 295.00, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'TOILET-KOHLER-CIMARRON', 'name' => 'Kohler Cimarron Toilet', 'category' => 'Fixtures', 'unit' => 'each', 'unit_cost' => 275.00, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'FAUCET-DELTA-LELAND', 'name' => 'Delta Leland Kitchen Faucet', 'category' => 'Fixtures', 'unit' => 'each', 'unit_cost' => 189.00, 'quantity' => 8, 'reorder_point' => 3],
                ['sku' => 'FAUCET-MOEN-ARBOR', 'name' => 'Moen Arbor Kitchen Faucet', 'category' => 'Fixtures', 'unit' => 'each', 'unit_cost' => 215.00, 'quantity' => 5, 'reorder_point' => 2],
                // Appliances
                ['sku' => 'DISPOSAL-ISE-EXCEL', 'name' => 'InSinkErator Evolution Excel', 'category' => 'Appliances', 'unit' => 'each', 'unit_cost' => 385.00, 'quantity' => 5, 'reorder_point' => 2],
                ['sku' => 'DISPOSAL-ISE-BADGER5', 'name' => 'InSinkErator Badger 5', 'category' => 'Appliances', 'unit' => 'each', 'unit_cost' => 95.00, 'quantity' => 8, 'reorder_point' => 3],
                // Fittings (bulk warehouse stock)
                ['sku' => 'VALVE-GATE-0.75', 'name' => 'Gate Valve 3/4" Brass', 'category' => 'Fittings', 'unit' => 'each', 'unit_cost' => 18.50, 'quantity' => 50, 'reorder_point' => 15],
                ['sku' => 'VALVE-GATE-0.5', 'name' => 'Gate Valve 1/2" Brass', 'category' => 'Fittings', 'unit' => 'each', 'unit_cost' => 14.50, 'quantity' => 50, 'reorder_point' => 15],
                ['sku' => 'VALVE-BALL-0.75', 'name' => 'Ball Valve 3/4" Full Port', 'category' => 'Fittings', 'unit' => 'each', 'unit_cost' => 22.00, 'quantity' => 40, 'reorder_point' => 12],
                ['sku' => 'PEX-FITTING-KIT', 'name' => 'PEX Crimp Fitting Assortment', 'category' => 'Fittings', 'unit' => 'kit', 'unit_cost' => 89.00, 'quantity' => 10, 'reorder_point' => 3],
                // Repair Parts (warehouse bulk)
                ['sku' => 'FLAPPER-KORKY-UNI', 'name' => 'Korky Universal Flapper', 'category' => 'Repair Parts', 'unit' => 'each', 'unit_cost' => 8.50, 'quantity' => 100, 'reorder_point' => 30],
                ['sku' => 'FILLVALVE-FLUIDMASTER', 'name' => 'Fluidmaster 400A Fill Valve', 'category' => 'Repair Parts', 'unit' => 'each', 'unit_cost' => 12.00, 'quantity' => 60, 'reorder_point' => 20],
                ['sku' => 'WAXRING-JOHNI', 'name' => 'Johni-Ring Wax Ring with Horn', 'category' => 'Repair Parts', 'unit' => 'each', 'unit_cost' => 6.50, 'quantity' => 50, 'reorder_point' => 15],
                ['sku' => 'SUPPLYLINE-BRAIDED', 'name' => 'Braided Supply Line 3/8" x 12"', 'category' => 'Repair Parts', 'unit' => 'each', 'unit_cost' => 9.00, 'quantity' => 80, 'reorder_point' => 25],
            ],
            'electrical' => [
                // Wire (warehouse bulk)
                ['sku' => 'WIRE-12-2-250', 'name' => 'Romex 12/2 NM-B 250ft', 'category' => 'Wire', 'unit' => 'roll', 'unit_cost' => 145.00, 'quantity' => 12, 'reorder_point' => 4],
                ['sku' => 'WIRE-14-2-250', 'name' => 'Romex 14/2 NM-B 250ft', 'category' => 'Wire', 'unit' => 'roll', 'unit_cost' => 98.00, 'quantity' => 15, 'reorder_point' => 5],
                ['sku' => 'WIRE-10-3-100', 'name' => 'Romex 10/3 NM-B 100ft', 'category' => 'Wire', 'unit' => 'roll', 'unit_cost' => 125.00, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'WIRE-6-3-50', 'name' => 'Romex 6/3 NM-B 50ft', 'category' => 'Wire', 'unit' => 'roll', 'unit_cost' => 165.00, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'THHN-12-500-BLK', 'name' => 'THHN 12 AWG 500ft Black', 'category' => 'Wire', 'unit' => 'roll', 'unit_cost' => 85.00, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'THHN-12-500-WHT', 'name' => 'THHN 12 AWG 500ft White', 'category' => 'Wire', 'unit' => 'roll', 'unit_cost' => 85.00, 'quantity' => 6, 'reorder_point' => 2],
                // Panels
                ['sku' => 'PANEL-200A-MAIN', 'name' => 'Square D 200A Main Panel', 'category' => 'Panels', 'unit' => 'each', 'unit_cost' => 325.00, 'quantity' => 3, 'reorder_point' => 1],
                ['sku' => 'PANEL-100A-MAIN', 'name' => 'Square D 100A Main Panel', 'category' => 'Panels', 'unit' => 'each', 'unit_cost' => 185.00, 'quantity' => 2, 'reorder_point' => 1],
                ['sku' => 'PANEL-SUBPANEL-60A', 'name' => 'Square D 60A Sub Panel', 'category' => 'Panels', 'unit' => 'each', 'unit_cost' => 125.00, 'quantity' => 4, 'reorder_point' => 2],
                // Breakers (warehouse bulk)
                ['sku' => 'BREAKER-15A-SP', 'name' => 'Square D 15A Single Pole', 'category' => 'Breakers', 'unit' => 'each', 'unit_cost' => 7.50, 'quantity' => 60, 'reorder_point' => 20],
                ['sku' => 'BREAKER-20A-SP', 'name' => 'Square D 20A Single Pole', 'category' => 'Breakers', 'unit' => 'each', 'unit_cost' => 8.50, 'quantity' => 80, 'reorder_point' => 25],
                ['sku' => 'BREAKER-30A-DP', 'name' => 'Square D 30A Double Pole', 'category' => 'Breakers', 'unit' => 'each', 'unit_cost' => 18.00, 'quantity' => 20, 'reorder_point' => 6],
                ['sku' => 'BREAKER-50A-DP', 'name' => 'Square D 50A Double Pole', 'category' => 'Breakers', 'unit' => 'each', 'unit_cost' => 24.00, 'quantity' => 15, 'reorder_point' => 5],
                ['sku' => 'BREAKER-GFCI-20A', 'name' => 'Square D 20A GFCI Breaker', 'category' => 'Breakers', 'unit' => 'each', 'unit_cost' => 45.00, 'quantity' => 12, 'reorder_point' => 4],
                ['sku' => 'BREAKER-AFCI-20A', 'name' => 'Square D 20A AFCI Breaker', 'category' => 'Breakers', 'unit' => 'each', 'unit_cost' => 48.00, 'quantity' => 15, 'reorder_point' => 5],
                // Devices (warehouse bulk)
                ['sku' => 'OUTLET-DECORA-WHT', 'name' => 'Leviton Decora Outlet 15A White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 2.50, 'quantity' => 200, 'reorder_point' => 50],
                ['sku' => 'OUTLET-DECORA-IVY', 'name' => 'Leviton Decora Outlet 15A Ivory', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 2.50, 'quantity' => 100, 'reorder_point' => 30],
                ['sku' => 'GFCI-20A-WHITE', 'name' => 'Leviton GFCI 20A White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 18.00, 'quantity' => 50, 'reorder_point' => 15],
                ['sku' => 'SWITCH-DECORA-WHT', 'name' => 'Leviton Decora Switch 15A White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 2.25, 'quantity' => 150, 'reorder_point' => 40],
                ['sku' => 'SWITCH-3WAY-WHT', 'name' => 'Leviton 3-Way Switch 15A White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 4.50, 'quantity' => 50, 'reorder_point' => 15],
                // Smart Devices
                ['sku' => 'DIMMER-LUTRON-CASETA', 'name' => 'Lutron Caseta Smart Dimmer', 'category' => 'Smart Devices', 'unit' => 'each', 'unit_cost' => 65.00, 'quantity' => 12, 'reorder_point' => 4],
                ['sku' => 'SWITCH-LUTRON-CASETA', 'name' => 'Lutron Caseta Smart Switch', 'category' => 'Smart Devices', 'unit' => 'each', 'unit_cost' => 55.00, 'quantity' => 15, 'reorder_point' => 5],
                // EV & Generators
                ['sku' => 'EV-CHARGER-CHARGEPOINT', 'name' => 'ChargePoint Home Flex 50A', 'category' => 'EV Equipment', 'unit' => 'each', 'unit_cost' => 649.00, 'quantity' => 3, 'reorder_point' => 1],
                ['sku' => 'EV-CHARGER-TESLA-WALL', 'name' => 'Tesla Wall Connector', 'category' => 'EV Equipment', 'unit' => 'each', 'unit_cost' => 425.00, 'quantity' => 2, 'reorder_point' => 1],
                ['sku' => 'GEN-GENERAC-22KW', 'name' => 'Generac Guardian 22kW', 'category' => 'Generators', 'unit' => 'each', 'unit_cost' => 5800.00, 'quantity' => 1, 'reorder_point' => 1],
                ['sku' => 'GEN-TRANSFER-200A', 'name' => 'Generac 200A Transfer Switch', 'category' => 'Generators', 'unit' => 'each', 'unit_cost' => 850.00, 'quantity' => 2, 'reorder_point' => 1],
            ],
            'hvac' => [
                // Filters (warehouse bulk)
                ['sku' => 'FILTER-20X25X1', 'name' => 'Air Filter 20x25x1 MERV 11', 'category' => 'Filters', 'unit' => 'each', 'unit_cost' => 12.50, 'quantity' => 100, 'reorder_point' => 30],
                ['sku' => 'FILTER-16X25X1', 'name' => 'Air Filter 16x25x1 MERV 11', 'category' => 'Filters', 'unit' => 'each', 'unit_cost' => 11.00, 'quantity' => 80, 'reorder_point' => 25],
                ['sku' => 'FILTER-20X20X1', 'name' => 'Air Filter 20x20x1 MERV 11', 'category' => 'Filters', 'unit' => 'each', 'unit_cost' => 10.50, 'quantity' => 60, 'reorder_point' => 20],
                ['sku' => 'FILTER-16X20X1', 'name' => 'Air Filter 16x20x1 MERV 11', 'category' => 'Filters', 'unit' => 'each', 'unit_cost' => 9.50, 'quantity' => 50, 'reorder_point' => 15],
                // Refrigerants
                ['sku' => 'REFRIG-R410A-25LB', 'name' => 'Refrigerant R-410A 25lb', 'category' => 'Refrigerants', 'unit' => 'cylinder', 'unit_cost' => 185.00, 'quantity' => 12, 'reorder_point' => 4],
                ['sku' => 'REFRIG-R22-30LB', 'name' => 'Refrigerant R-22 30lb (Reclaimed)', 'category' => 'Refrigerants', 'unit' => 'cylinder', 'unit_cost' => 425.00, 'quantity' => 3, 'reorder_point' => 2],
                ['sku' => 'REFRIG-R134A-30LB', 'name' => 'Refrigerant R-134a 30lb', 'category' => 'Refrigerants', 'unit' => 'cylinder', 'unit_cost' => 165.00, 'quantity' => 4, 'reorder_point' => 2],
                // Electrical Components (warehouse bulk)
                ['sku' => 'CAP-45-5-MFD', 'name' => 'Run Capacitor 45/5 MFD 440V', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 28.00, 'quantity' => 40, 'reorder_point' => 12],
                ['sku' => 'CAP-35-5-MFD', 'name' => 'Run Capacitor 35/5 MFD 440V', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 25.00, 'quantity' => 35, 'reorder_point' => 10],
                ['sku' => 'CAP-40-5-MFD', 'name' => 'Run Capacitor 40/5 MFD 440V', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 26.00, 'quantity' => 30, 'reorder_point' => 10],
                ['sku' => 'CAP-55-5-MFD', 'name' => 'Run Capacitor 55/5 MFD 440V', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 32.00, 'quantity' => 20, 'reorder_point' => 6],
                ['sku' => 'CONTACTOR-2P-30A', 'name' => 'Contactor 2-Pole 30A', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 35.00, 'quantity' => 25, 'reorder_point' => 8],
                ['sku' => 'CONTACTOR-2P-40A', 'name' => 'Contactor 2-Pole 40A', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 42.00, 'quantity' => 15, 'reorder_point' => 5],
                // Thermostats
                ['sku' => 'THERMO-ECOBEE-PREM', 'name' => 'Ecobee Smart Thermostat Premium', 'category' => 'Thermostats', 'unit' => 'each', 'unit_cost' => 229.00, 'quantity' => 8, 'reorder_point' => 3],
                ['sku' => 'THERMO-HONEYWELL-T6', 'name' => 'Honeywell T6 Pro Programmable', 'category' => 'Thermostats', 'unit' => 'each', 'unit_cost' => 89.00, 'quantity' => 15, 'reorder_point' => 5],
                ['sku' => 'THERMO-NEST-LEARN', 'name' => 'Nest Learning Thermostat', 'category' => 'Thermostats', 'unit' => 'each', 'unit_cost' => 249.00, 'quantity' => 6, 'reorder_point' => 2],
                // Supplies
                ['sku' => 'LINESET-3-8-25FT', 'name' => 'Lineset 3/8" x 3/4" x 25ft', 'category' => 'Supplies', 'unit' => 'set', 'unit_cost' => 75.00, 'quantity' => 10, 'reorder_point' => 3],
                ['sku' => 'LINESET-3-8-50FT', 'name' => 'Lineset 3/8" x 3/4" x 50ft', 'category' => 'Supplies', 'unit' => 'set', 'unit_cost' => 125.00, 'quantity' => 6, 'reorder_point' => 2],
                // Motors & Ignition
                ['sku' => 'BLOWER-MOTOR-1-2HP', 'name' => 'Blower Motor 1/2 HP Variable', 'category' => 'Motors', 'unit' => 'each', 'unit_cost' => 285.00, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'BLOWER-MOTOR-3-4HP', 'name' => 'Blower Motor 3/4 HP Variable', 'category' => 'Motors', 'unit' => 'each', 'unit_cost' => 345.00, 'quantity' => 3, 'reorder_point' => 1],
                ['sku' => 'CONDENSER-FAN-MOTOR', 'name' => 'Condenser Fan Motor 1/4 HP', 'category' => 'Motors', 'unit' => 'each', 'unit_cost' => 125.00, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'IGNITOR-HOT-SURFACE', 'name' => 'Hot Surface Ignitor Universal', 'category' => 'Ignition', 'unit' => 'each', 'unit_cost' => 45.00, 'quantity' => 20, 'reorder_point' => 6],
                ['sku' => 'FLAME-SENSOR-UNI', 'name' => 'Flame Sensor Universal', 'category' => 'Ignition', 'unit' => 'each', 'unit_cost' => 18.00, 'quantity' => 15, 'reorder_point' => 5],
            ],
        ];

        return $inventory[$template] ?? $inventory['plumbing'];
    }

    /**
     * Get vehicle/truck stock data for template.
     * These are common parts that each service vehicle should carry.
     *
     * @param string $template Template type.
     * @return array Vehicle stock items.
     */
    private static function get_vehicle_stock_data($template) {
        $vehicle_stock = [
            'plumbing' => [
                // Essential repair parts every plumbing truck should carry
                ['sku' => 'FLAPPER-KORKY', 'name' => 'Korky Universal Flapper', 'category' => 'Repair Parts', 'unit' => 'each', 'unit_cost' => 8.50, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'FILLVALVE-FLUID', 'name' => 'Fluidmaster 400A Fill Valve', 'category' => 'Repair Parts', 'unit' => 'each', 'unit_cost' => 12.00, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'WAXRING-JOHNI', 'name' => 'Johni-Ring Wax Ring', 'category' => 'Repair Parts', 'unit' => 'each', 'unit_cost' => 6.50, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'SUPPLYLINE-BRAID', 'name' => 'Braided Supply Line Set', 'category' => 'Repair Parts', 'unit' => 'each', 'unit_cost' => 9.00, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'VALVE-GATE-0.75', 'name' => 'Gate Valve 3/4" Brass', 'category' => 'Fittings', 'unit' => 'each', 'unit_cost' => 18.50, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'VALVE-GATE-0.5', 'name' => 'Gate Valve 1/2" Brass', 'category' => 'Fittings', 'unit' => 'each', 'unit_cost' => 14.50, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'VALVE-BALL-0.75', 'name' => 'Ball Valve 3/4" Full Port', 'category' => 'Fittings', 'unit' => 'each', 'unit_cost' => 22.00, 'quantity' => 3, 'reorder_point' => 1],
                ['sku' => 'PEX-FITTING-KIT', 'name' => 'PEX Crimp Fitting Kit', 'category' => 'Fittings', 'unit' => 'kit', 'unit_cost' => 45.00, 'quantity' => 1, 'reorder_point' => 1],
                ['sku' => 'PTAPE-ROLL', 'name' => 'Teflon Tape Roll', 'category' => 'Supplies', 'unit' => 'each', 'unit_cost' => 2.50, 'quantity' => 10, 'reorder_point' => 4],
                ['sku' => 'PIPE-DOPE', 'name' => 'Pipe Thread Sealant', 'category' => 'Supplies', 'unit' => 'each', 'unit_cost' => 8.00, 'quantity' => 2, 'reorder_point' => 1],
            ],
            'electrical' => [
                // Essential electrical parts for service vans
                ['sku' => 'OUTLET-DECORA-WHT', 'name' => 'Decora Outlet 15A White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 2.50, 'quantity' => 15, 'reorder_point' => 5],
                ['sku' => 'OUTLET-DECORA-IVY', 'name' => 'Decora Outlet 15A Ivory', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 2.50, 'quantity' => 10, 'reorder_point' => 4],
                ['sku' => 'SWITCH-DECORA-WHT', 'name' => 'Decora Switch 15A White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 2.25, 'quantity' => 12, 'reorder_point' => 4],
                ['sku' => 'SWITCH-3WAY-WHT', 'name' => '3-Way Switch 15A White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 4.50, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'GFCI-20A-WHT', 'name' => 'GFCI 20A White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 18.00, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'BREAKER-15A-SP', 'name' => 'Square D 15A Single Pole', 'category' => 'Breakers', 'unit' => 'each', 'unit_cost' => 7.50, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'BREAKER-20A-SP', 'name' => 'Square D 20A Single Pole', 'category' => 'Breakers', 'unit' => 'each', 'unit_cost' => 8.50, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'WIRENUT-ASSORT', 'name' => 'Wire Nut Assortment', 'category' => 'Supplies', 'unit' => 'box', 'unit_cost' => 15.00, 'quantity' => 2, 'reorder_point' => 1],
                ['sku' => 'ETAPE-ROLL', 'name' => 'Electrical Tape Roll', 'category' => 'Supplies', 'unit' => 'each', 'unit_cost' => 3.50, 'quantity' => 6, 'reorder_point' => 2],
                ['sku' => 'COVER-PLATE-WHT', 'name' => 'Wall Plate Single White', 'category' => 'Devices', 'unit' => 'each', 'unit_cost' => 1.25, 'quantity' => 20, 'reorder_point' => 8],
            ],
            'hvac' => [
                // Essential HVAC parts for service vans
                ['sku' => 'CAP-45-5-MFD', 'name' => 'Run Capacitor 45/5 MFD 440V', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 28.00, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'CAP-35-5-MFD', 'name' => 'Run Capacitor 35/5 MFD 440V', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 25.00, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'CAP-40-5-MFD', 'name' => 'Run Capacitor 40/5 MFD 440V', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 26.00, 'quantity' => 3, 'reorder_point' => 1],
                ['sku' => 'CONTACTOR-2P-30A', 'name' => 'Contactor 2-Pole 30A', 'category' => 'Electrical', 'unit' => 'each', 'unit_cost' => 35.00, 'quantity' => 3, 'reorder_point' => 1],
                ['sku' => 'IGNITOR-HSI', 'name' => 'Hot Surface Ignitor', 'category' => 'Ignition', 'unit' => 'each', 'unit_cost' => 45.00, 'quantity' => 2, 'reorder_point' => 1],
                ['sku' => 'FLAME-SENSOR', 'name' => 'Flame Sensor Universal', 'category' => 'Ignition', 'unit' => 'each', 'unit_cost' => 18.00, 'quantity' => 3, 'reorder_point' => 1],
                ['sku' => 'FILTER-20X25X1', 'name' => 'Air Filter 20x25x1', 'category' => 'Filters', 'unit' => 'each', 'unit_cost' => 12.50, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'FILTER-16X25X1', 'name' => 'Air Filter 16x25x1', 'category' => 'Filters', 'unit' => 'each', 'unit_cost' => 11.00, 'quantity' => 4, 'reorder_point' => 2],
                ['sku' => 'CONDTABS-PAN', 'name' => 'Condensate Pan Tablets', 'category' => 'Supplies', 'unit' => 'pack', 'unit_cost' => 8.00, 'quantity' => 3, 'reorder_point' => 1],
                ['sku' => 'HVAC-TAPE', 'name' => 'HVAC Foil Tape Roll', 'category' => 'Supplies', 'unit' => 'each', 'unit_cost' => 12.00, 'quantity' => 2, 'reorder_point' => 1],
            ],
        ];

        return $vehicle_stock[$template] ?? $vehicle_stock['plumbing'];
    }

    /**
     * Parse a demo address string into components.
     *
     * @param string $address Full address string like "123 Main St, Springfield, IL 62701".
     * @return array Address components.
     */
    private static function parse_demo_address($address) {
        // Default values for Springfield, IL
        $result = [
            'street' => $address,
            'city'   => 'Springfield',
            'state'  => 'IL',
            'zip'    => '62701',
        ];

        // Try to parse: "123 Street Name, City, ST 12345"
        if (preg_match('/^(.+?),\s*([^,]+),\s*([A-Z]{2})\s+(\d{5})/', $address, $matches)) {
            $result['street'] = trim($matches[1]);
            $result['city']   = trim($matches[2]);
            $result['state']  = trim($matches[3]);
            $result['zip']    = trim($matches[4]);
        }
        // Try simpler format: "123 Street Name, City, ST ZIP" variations
        elseif (preg_match('/^(.+?),\s*Springfield,?\s*IL\s*(\d{5})?/', $address, $matches)) {
            $result['street'] = trim($matches[1]);
            if (!empty($matches[2])) {
                $result['zip'] = trim($matches[2]);
            }
        }

        return $result;
    }

    /**
     * Generate simulated GPS coordinates for a demo address.
     * Uses Springfield, IL as the base location with realistic variations.
     *
     * @param string $street Street address for deterministic variation.
     * @return array Latitude and longitude.
     */
    private static function generate_demo_coordinates($street) {
        // Springfield, IL center coordinates
        $base_lat = 39.7817;
        $base_lng = -89.6501;

        // Use street hash for deterministic but varied coordinates
        $hash = crc32($street);

        // Generate offset within ~5 mile radius (0.07 degrees ≈ 5 miles)
        $lat_offset = (($hash % 1400) - 700) / 10000; // -0.07 to +0.07
        $lng_offset = ((($hash >> 8) % 1400) - 700) / 10000;

        return [
            'lat' => round($base_lat + $lat_offset, 6),
            'lng' => round($base_lng + $lng_offset, 6),
        ];
    }

    /**
     * Delete all demo data for an organization.
     *
     * @param int $org_id Organization ID.
     * @return bool Success.
     */
    public static function delete_demo_organization($org_id) {
        // Load user admin functions if not already loaded
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        // Delete work orders
        $work_orders = get_posts([
            'post_type'      => 'fsm_work_order',
            'posts_per_page' => -1,
            'meta_key'       => '_fsm_organization_id',
            'meta_value'     => $org_id,
            'fields'         => 'ids',
        ]);
        foreach ($work_orders as $id) {
            wp_delete_post($id, true);
        }

        // Delete technicians and their users
        $technicians = get_posts([
            'post_type'      => 'fsm_technician',
            'posts_per_page' => -1,
            'meta_key'       => '_fsm_organization_id',
            'meta_value'     => $org_id,
        ]);
        foreach ($technicians as $tech) {
            $user_id = get_post_meta($tech->ID, '_fsm_user_id', true);
            if ($user_id) {
                wp_delete_user($user_id);
            }
            wp_delete_post($tech->ID, true);
        }

        // Delete vehicles
        $vehicles = get_posts([
            'post_type'      => 'fsm_vehicle',
            'posts_per_page' => -1,
            'meta_key'       => '_fsm_organization_id',
            'meta_value'     => $org_id,
            'fields'         => 'ids',
        ]);
        foreach ($vehicles as $id) {
            wp_delete_post($id, true);
        }

        // Delete org users
        $users = FSM_Organizations::get_organization_users($org_id);
        foreach ($users as $user) {
            wp_delete_user($user->ID);
        }

        // Delete inventory
        if (class_exists('FSM_Inventory')) {
            global $wpdb;
            $table = FSM_Inventory::get_items_table();
            $wpdb->delete($table, ['organization_id' => $org_id]);

            $log_table = FSM_Inventory::get_log_table();
            $wpdb->delete($log_table, ['organization_id' => $org_id]);
        }

        // Delete GPS data
        global $wpdb;
        $gps_table = $wpdb->prefix . 'fsm_gps_locations';
        $wpdb->delete($gps_table, ['organization_id' => $org_id]);

        // Delete PTT channels
        if (class_exists('FSM_PTT')) {
            $ptt_table = FSM_PTT::get_channels_table();
            $wpdb->delete($ptt_table, ['organization_id' => $org_id]);
        }

        // Delete organization
        wp_delete_post($org_id, true);

        return true;
    }

    /**
     * Reset a demo organization to its template state.
     *
     * @param int $org_id Organization ID.
     * @return bool Success.
     */
    public static function reset_demo_organization($org_id) {
        $template = get_post_meta($org_id, '_fsm_demo_template', true);
        if (!$template) {
            $template = 'plumbing';
        }

        $org_name = get_the_title($org_id);

        // Delete the org and recreate
        self::delete_demo_organization($org_id);

        // Recreate with same template
        return self::create_demo_organization($template, $org_name);
    }

    /**
     * Get available demo templates.
     *
     * @return array Template info.
     */
    public static function get_templates() {
        return [
            'plumbing' => [
                'id'          => 'plumbing',
                'name'        => 'Plumbing Company',
                'description' => 'Demo plumbing company with drain cleaning, water heater, and pipe repair work orders.',
                'company'     => 'Reliable Plumbing Co.',
            ],
            'electrical' => [
                'id'          => 'electrical',
                'name'        => 'Electrical Contractor',
                'description' => 'Demo electrical contractor with panel upgrades, EV chargers, and smart home installations.',
                'company'     => 'PowerPro Electric',
            ],
            'hvac' => [
                'id'          => 'hvac',
                'name'        => 'HVAC Company',
                'description' => 'Demo HVAC company with AC/heating repairs, system installations, and maintenance.',
                'company'     => 'ComfortZone HVAC Services',
            ],
        ];
    }
}
