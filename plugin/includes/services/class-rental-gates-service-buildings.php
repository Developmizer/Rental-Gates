<?php
/**
 * Building Service
 *
 * Shared business logic for building operations, called by both
 * AJAX and REST handlers. Eliminates behavioral drift between the two APIs.
 *
 * Key fixes applied:
 * - Feature gate check (was only in AJAX, missing from REST)
 * - Notification hooks (now fire from both APIs)
 * - Org ownership verification on delete (was only in AJAX)
 *
 * @package RentalGates
 * @since 2.42.0
 */

if (!defined('ABSPATH')) exit;

class Rental_Gates_Service_Buildings {

    /**
     * Create a building with feature gate enforcement and proper sanitization.
     *
     * @param array $data     Building data
     * @param int   $org_id   Organization ID
     * @param int   $user_id  Acting user ID
     * @return array|WP_Error Created building or error
     */
    public static function create($data, $org_id, $user_id = null) {
        // Feature gate check (was only in AJAX, missing from REST)
        if (class_exists('Rental_Gates_Feature_Gate')) {
            $gate = Rental_Gates_Feature_Gate::get_instance();
            $check = $gate->can_create('buildings', 1, $org_id);
            if (is_array($check) && !$check['allowed']) {
                return new WP_Error(
                    'limit_reached',
                    isset($check['message']) ? $check['message'] : __('Building limit reached for your plan.', 'rental-gates'),
                    array('upgrade' => true)
                );
            }
        }

        // Sanitize input
        $clean = array(
            'organization_id' => $org_id,
            'name'            => sanitize_text_field($data['name'] ?? ''),
            'address'         => sanitize_text_field($data['address'] ?? ''),
            'city'            => sanitize_text_field($data['city'] ?? ''),
            'state'           => sanitize_text_field($data['state'] ?? ''),
            'zip'             => sanitize_text_field($data['zip'] ?? ''),
            'country'         => sanitize_text_field($data['country'] ?? 'US'),
            'description'     => sanitize_textarea_field($data['description'] ?? ''),
            'building_type'   => sanitize_text_field($data['building_type'] ?? 'residential'),
            'featured_image'  => esc_url_raw($data['featured_image'] ?? ''),
            'gallery'         => $data['gallery'] ?? array(),
        );

        // Geocode if coordinates provided
        if (!empty($data['latitude']) && !empty($data['longitude'])) {
            $clean['latitude'] = floatval($data['latitude']);
            $clean['longitude'] = floatval($data['longitude']);
        }

        $result = Rental_Gates_Building::create($clean);

        if (!is_wp_error($result)) {
            // Fire action hook for notifications (was only in REST)
            do_action('rental_gates_building_created', $result, $org_id, $user_id);
        }

        return $result;
    }

    /**
     * Update a building.
     *
     * @param int   $building_id Building ID
     * @param array $data        Updated fields
     * @param int   $org_id      Organization ID (for ownership check)
     * @return array|WP_Error
     */
    public static function update($building_id, $data, $org_id) {
        // Verify ownership
        $building = Rental_Gates_Building::get($building_id);
        if (!$building) {
            return new WP_Error('not_found', __('Building not found', 'rental-gates'));
        }
        if ((int) $building['organization_id'] !== (int) $org_id) {
            return new WP_Error('forbidden', __('Not found', 'rental-gates'));
        }

        $result = Rental_Gates_Building::update($building_id, $data);

        if (!is_wp_error($result)) {
            do_action('rental_gates_building_updated', $result, $org_id);
        }

        return $result;
    }

    /**
     * Delete a building with org ownership verification.
     *
     * @param int $building_id Building ID
     * @param int $org_id      Organization ID
     * @return true|WP_Error
     */
    public static function delete($building_id, $org_id) {
        // Verify ownership (was only in AJAX)
        $building = Rental_Gates_Building::get($building_id);
        if (!$building) {
            return new WP_Error('not_found', __('Building not found', 'rental-gates'));
        }
        if ((int) $building['organization_id'] !== (int) $org_id) {
            return new WP_Error('forbidden', __('Not found', 'rental-gates'));
        }

        return Rental_Gates_Building::delete($building_id);
    }
}
