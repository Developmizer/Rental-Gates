<?php
/**
 * Maintenance Service
 *
 * Shared business logic for work order operations, called by both
 * AJAX and REST handlers. Eliminates behavioral drift between the two APIs.
 *
 * Key fixes applied:
 * - Notification hook now fires from both AJAX and REST
 * - Consistent sanitization across both APIs
 *
 * @package RentalGates
 * @since 2.42.0
 */

if (!defined('ABSPATH')) exit;

class Rental_Gates_Service_Maintenance {

    /**
     * Create a work order with unified business logic.
     *
     * @param array $data    Work order data
     * @param int   $org_id  Organization ID
     * @param int   $user_id Reporting user ID
     * @return array|WP_Error Created work order or error
     */
    public static function create($data, $org_id, $user_id) {
        $clean = array(
            'organization_id'     => $org_id,
            'building_id'         => intval($data['building_id'] ?? 0),
            'unit_id'             => intval($data['unit_id'] ?? 0),
            'tenant_id'           => intval($data['tenant_id'] ?? 0),
            'reported_by'         => $user_id,
            'title'               => sanitize_text_field($data['title'] ?? ''),
            'description'         => sanitize_textarea_field($data['description'] ?? ''),
            'category'            => sanitize_text_field($data['category'] ?? 'other'),
            'priority'            => sanitize_text_field($data['priority'] ?? 'medium'),
            'permission_to_enter' => !empty($data['permission_to_enter']) ? 1 : 0,
            'access_instructions' => sanitize_textarea_field($data['access_instructions'] ?? ''),
        );

        if (!empty($data['scheduled_date'])) {
            $clean['scheduled_date'] = sanitize_text_field($data['scheduled_date']);
        }

        if (!empty($data['photos']) && is_array($data['photos'])) {
            $clean['photos'] = array_map('esc_url_raw', $data['photos']);
        }

        $result = Rental_Gates_Maintenance::create($clean);

        if (!is_wp_error($result)) {
            // Notification hook - now fires from BOTH AJAX and REST
            do_action('rental_gates_maintenance_created', $result, $org_id);
        }

        return $result;
    }

    /**
     * Update a work order with unified business logic.
     *
     * @param int   $id     Work order ID
     * @param array $data   Updated fields
     * @param int   $org_id Organization ID
     * @return array|WP_Error
     */
    public static function update($id, $data, $org_id) {
        $old_data = Rental_Gates_Maintenance::get($id);

        $result = Rental_Gates_Maintenance::update($id, $data);

        if (!is_wp_error($result)) {
            // Notification hook fires from both APIs
            do_action('rental_gates_maintenance_updated', $result, $old_data, $org_id);

            // Status-specific hooks
            if (isset($data['status'])) {
                if ($data['status'] === 'completed') {
                    do_action('rental_gates_maintenance_completed', $result, $org_id);
                }
            }
        }

        return $result;
    }

    /**
     * Assign a vendor to a work order.
     *
     * @param int $work_order_id Work order ID
     * @param int $vendor_id     Vendor ID
     * @param int $org_id        Organization ID
     * @return array|WP_Error
     */
    public static function assign_vendor($work_order_id, $vendor_id, $org_id) {
        $result = Rental_Gates_Maintenance::assign_vendor($work_order_id, $vendor_id);

        if (!is_wp_error($result)) {
            do_action('rental_gates_vendor_assigned', $work_order_id, $vendor_id, $org_id);
        }

        return $result;
    }
}
