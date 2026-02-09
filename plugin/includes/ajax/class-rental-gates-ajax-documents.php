<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Documents
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: upload_document, delete_document
 */
class Rental_Gates_Ajax_Documents {

    public function __construct() {
        add_action('wp_ajax_rental_gates_upload_document', array($this, 'handle_upload_document'));
        add_action('wp_ajax_rental_gates_delete_document', array($this, 'handle_delete_document'));
    }

    public function handle_upload_document()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_buildings') && !current_user_can('rg_manage_tenants') && !Rental_Gates_Roles::is_owner_or_manager()) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        if (empty($_FILES['document'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $entity_type = sanitize_key($_POST['entity_type'] ?? '');
        $entity_id = intval($_POST['entity_id'] ?? 0);

        if (!$entity_type || !$entity_id) {
            wp_send_json_error(array('message' => __('Please select an entity to associate the document with', 'rental-gates')));
        }

        $valid = false;
        switch ($entity_type) {
            case 'building':
                $entity = Rental_Gates_Building::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'unit':
                $entity = Rental_Gates_Unit::get($entity_id);
                if ($entity) {
                    $building = Rental_Gates_Building::get($entity['building_id']);
                    $valid = $building && $building['organization_id'] === $org_id;
                }
                break;
            case 'tenant':
                $entity = Rental_Gates_Tenant::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'lease':
                $entity = Rental_Gates_Lease::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'vendor':
                $entity = Rental_Gates_Vendor::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'application':
                $entity = Rental_Gates_Application::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
            case 'work_order':
                $entity = Rental_Gates_Maintenance::get($entity_id);
                $valid = $entity && $entity['organization_id'] === $org_id;
                break;
        }

        if (!$valid) {
            wp_send_json_error(array('message' => __('Invalid entity', 'rental-gates')));
        }

        $doc_data = array(
            'organization_id' => $org_id,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'document_type' => sanitize_key($_POST['document_type'] ?? 'other'),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'is_private' => 1,
        );

        $result = Rental_Gates_Document::upload($_FILES['document'], $doc_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('document' => $result));
    }

    public function handle_delete_document()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_buildings') && !current_user_can('rg_manage_tenants') && !Rental_Gates_Roles::is_owner_or_manager()) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $document_id = intval($_POST['document_id'] ?? 0);
        if (!$document_id) {
            wp_send_json_error(array('message' => __('Invalid document ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $document = Rental_Gates_Document::get($document_id);

        if (!$document || $document['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Document not found', 'rental-gates')));
        }

        $result = Rental_Gates_Document::delete($document_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete document', 'rental-gates')));
        }

        wp_send_json_success();
    }
}
