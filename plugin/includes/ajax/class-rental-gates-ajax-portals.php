<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Tenant & Vendor Portals + Staff Management
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: tenant_create_maintenance, tenant_delete_maintenance,
 *          tenant_maintenance_feedback, tenant_add_note,
 *          tenant_update_profile, vendor_update_assignment,
 *          vendor_add_note, invite_vendor, save_staff, remove_staff
 */
class Rental_Gates_Ajax_Portals {

    public function __construct() {
        // Tenant portal
        add_action('wp_ajax_rental_gates_tenant_create_maintenance', array($this, 'handle_tenant_create_maintenance'));
        add_action('wp_ajax_rental_gates_tenant_delete_maintenance', array($this, 'handle_tenant_delete_maintenance'));
        add_action('wp_ajax_rental_gates_tenant_maintenance_feedback', array($this, 'handle_tenant_maintenance_feedback'));
        add_action('wp_ajax_rental_gates_tenant_add_note', array($this, 'handle_tenant_add_note'));
        add_action('wp_ajax_rental_gates_tenant_update_profile', array($this, 'handle_tenant_update_profile'));
        // Vendor portal
        add_action('wp_ajax_rental_gates_vendor_update_assignment', array($this, 'handle_vendor_update_assignment'));
        add_action('wp_ajax_rental_gates_vendor_add_note', array($this, 'handle_vendor_add_note'));
        add_action('wp_ajax_rental_gates_invite_vendor', array($this, 'handle_invite_vendor'));
        // Staff management
        add_action('wp_ajax_rental_gates_save_staff', array($this, 'handle_save_staff'));
        add_action('wp_ajax_rental_gates_remove_staff', array($this, 'handle_remove_staff'));
    }

    public function handle_tenant_create_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        $org_id = intval($_POST['organization_id'] ?? 0);

        if (!$tenant_id || !$org_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        $data = array(
            'organization_id' => $org_id,
            'tenant_id' => $tenant_id,
            'building_id' => intval($_POST['building_id'] ?? 0) ?: null,
            'unit_id' => intval($_POST['unit_id'] ?? 0) ?: null,
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? 'other'),
            'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
            'status' => 'open',
            'source' => 'tenant_portal',
            'permission_to_enter' => !empty($_POST['permission_to_enter']),
        );

        if (!empty($_FILES['photos']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $uploaded_photos = array();
            $files = $_FILES['photos'];

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    );
                    $_FILES['upload_file'] = $file;
                    $attachment_id = media_handle_upload('upload_file', 0);
                    if (!is_wp_error($attachment_id)) {
                        $uploaded_photos[] = wp_get_attachment_url($attachment_id);
                    }
                }
            }

            if (!empty($uploaded_photos)) {
                $data['photos'] = $uploaded_photos;
            }
        }

        $result = Rental_Gates_Maintenance::create($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('work_order_id' => $result));
    }

    public function handle_tenant_delete_maintenance()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);

        if (!$request_id || !$tenant_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        $work_order = Rental_Gates_Maintenance::get($request_id);
        if (!$work_order || $work_order['tenant_id'] != $tenant_id) {
            wp_send_json_error(array('message' => __('Request not found', 'rental-gates')));
        }

        if (!in_array($work_order['status'], array('open', 'assigned'))) {
            wp_send_json_error(array('message' => __('Cannot delete requests that are already in progress', 'rental-gates')));
        }

        $result = Rental_Gates_Maintenance::delete($request_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Request deleted successfully', 'rental-gates')));
    }

    public function handle_tenant_maintenance_feedback()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        $feedback = sanitize_text_field($_POST['feedback'] ?? '');

        if (!$request_id || !$tenant_id || !$feedback) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        $work_order = Rental_Gates_Maintenance::get($request_id);
        if (!$work_order || $work_order['tenant_id'] != $tenant_id) {
            wp_send_json_error(array('message' => __('Request not found', 'rental-gates')));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $meta = json_decode($work_order['meta_data'] ?? '{}', true);
        $meta['tenant_feedback'] = $feedback;
        $meta['tenant_feedback_at'] = current_time('mysql');

        $wpdb->update(
            $tables['work_orders'],
            array('meta_data' => wp_json_encode($meta)),
            array('id' => $request_id),
            array('%s'),
            array('%d')
        );

        if ($feedback === 'not_satisfied') {
            $wpdb->update(
                $tables['work_orders'],
                array('status' => 'open'),
                array('id' => $request_id),
                array('%s'),
                array('%d')
            );

            Rental_Gates_Maintenance::add_note(
                $request_id,
                $tenant['user_id'],
                'tenant',
                __('Tenant reported issue is not fully resolved. Request reopened.', 'rental-gates'),
                false
            );
        } else {
            Rental_Gates_Maintenance::add_note(
                $request_id,
                $tenant['user_id'],
                'tenant',
                __('Tenant confirmed issue has been resolved.', 'rental-gates'),
                false
            );
        }

        wp_send_json_success(array('message' => __('Feedback submitted', 'rental-gates')));
    }

    public function handle_tenant_add_note()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$request_id || !$tenant_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        $work_order = Rental_Gates_Maintenance::get($request_id);
        if (!$work_order || $work_order['tenant_id'] != $tenant_id) {
            wp_send_json_error(array('message' => __('Request not found', 'rental-gates')));
        }

        $attachments = array();
        if (!empty($_FILES['attachments']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $files = $_FILES['attachments'];

            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = array(
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    );
                    $_FILES['upload_file'] = $file;
                    $attachment_id = media_handle_upload('upload_file', 0);
                    if (!is_wp_error($attachment_id)) {
                        $attachments[] = wp_get_attachment_url($attachment_id);
                    }
                }
            }
        }

        if (empty($note) && empty($attachments)) {
            wp_send_json_error(array('message' => __('Please provide a comment or attachment', 'rental-gates')));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $wpdb->insert(
            $tables['work_order_notes'],
            array(
                'work_order_id' => $request_id,
                'user_id' => $tenant['user_id'],
                'user_type' => 'tenant',
                'note' => $note ?: __('Added photos', 'rental-gates'),
                'is_internal' => 0,
                'attachments' => !empty($attachments) ? wp_json_encode($attachments) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s', '%s')
        );

        wp_send_json_success(array('message' => __('Comment added', 'rental-gates')));
    }

    public function handle_tenant_update_profile()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        if (!$tenant_id) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        $tenant = Rental_Gates_Tenant::get($tenant_id);
        if (!$tenant || $tenant['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        $meta_data = $tenant['meta_data'] ?? array();

        $meta_data['emergency_contact'] = array(
            'name' => sanitize_text_field($_POST['emergency_name'] ?? ''),
            'relationship' => sanitize_text_field($_POST['emergency_relationship'] ?? ''),
            'phone' => sanitize_text_field($_POST['emergency_phone'] ?? ''),
            'email' => sanitize_email($_POST['emergency_email'] ?? ''),
        );

        $meta_data['vehicle'] = array(
            'make' => sanitize_text_field($_POST['vehicle_make'] ?? ''),
            'model' => sanitize_text_field($_POST['vehicle_model'] ?? ''),
            'color' => sanitize_text_field($_POST['vehicle_color'] ?? ''),
            'plate' => sanitize_text_field($_POST['vehicle_plate'] ?? ''),
        );

        $meta_data['communication_prefs'] = array(
            'email_reminders' => !empty($_POST['pref_email_reminders']),
            'email_maintenance' => !empty($_POST['pref_email_maintenance']),
            'email_announcements' => !empty($_POST['pref_email_announcements']),
        );

        $update_data = array(
            'phone' => sanitize_text_field($_POST['phone'] ?? $tenant['phone']),
            'meta_data' => $meta_data,
        );

        $result = Rental_Gates_Tenant::update($tenant_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }

    public function handle_vendor_update_assignment()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $cost = floatval($_POST['cost'] ?? 0);
        $work_order_id = intval($_POST['work_order_id'] ?? 0);

        if (!$assignment_id || !in_array($new_status, array('accepted', 'declined', 'completed'))) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT wov.*, v.user_id FROM {$tables['work_order_vendors']} wov
             JOIN {$tables['vendors']} v ON wov.vendor_id = v.id
             WHERE wov.id = %d",
            $assignment_id
        ), ARRAY_A);

        if (!$assignment || $assignment['user_id'] != get_current_user_id()) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        $update_data = array('status' => $new_status);
        if ($notes) {
            $update_data['notes'] = $notes;
        }
        if ($cost > 0) {
            $update_data['actual_cost'] = $cost;
        }

        $wpdb->update(
            $tables['work_order_vendors'],
            $update_data,
            array('id' => $assignment_id)
        );

        if ($new_status === 'completed' && $work_order_id) {
            $wpdb->update(
                $tables['work_orders'],
                array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                    'actual_cost' => $cost > 0 ? $cost : null,
                ),
                array('id' => $work_order_id)
            );
        }

        if ($new_status === 'accepted' && $work_order_id) {
            $wpdb->update(
                $tables['work_orders'],
                array('status' => 'in_progress'),
                array('id' => $assignment['work_order_id'])
            );
        }

        wp_send_json_success();
    }

    public function handle_vendor_add_note()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $work_order_id = intval($_POST['work_order_id'] ?? 0);
        $content = sanitize_textarea_field($_POST['content'] ?? '');

        if (!$work_order_id || !$content) {
            wp_send_json_error(array('message' => __('Invalid request', 'rental-gates')));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        $has_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['work_order_vendors']} wov
             JOIN {$tables['vendors']} v ON wov.vendor_id = v.id
             WHERE wov.work_order_id = %d AND v.user_id = %d",
            $work_order_id,
            get_current_user_id()
        ));

        if (!$has_access) {
            wp_send_json_error(array('message' => __('Access denied', 'rental-gates')));
        }

        $result = $wpdb->insert(
            $tables['work_order_notes'],
            array(
                'work_order_id' => $work_order_id,
                'user_id' => get_current_user_id(),
                'content' => $content,
                'is_internal' => 0,
                'created_at' => current_time('mysql'),
            )
        );

        if ($result === false) {
            wp_send_json_error(array('message' => __('Error adding note', 'rental-gates')));
        }

        wp_send_json_success();
    }

    public function handle_invite_vendor()
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'rental_gates_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'rental-gates')));
        }

        if (!current_user_can('rg_manage_vendors')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        if (!$vendor_id) {
            wp_send_json_error(array('message' => __('Invalid vendor ID', 'rental-gates')));
        }

        $org_id = Rental_Gates_Roles::get_organization_id();
        $vendor = Rental_Gates_Vendor::get($vendor_id);

        if (!$vendor || $vendor['organization_id'] !== $org_id) {
            wp_send_json_error(array('message' => __('Vendor not found', 'rental-gates')));
        }

        $result = Rental_Gates_Vendor::invite_to_portal($vendor_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('vendor' => $result));
    }

    public function handle_save_staff()
    {
        if (!wp_verify_nonce($_POST['staff_nonce'] ?? '', 'rental_gates_staff_form')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_staff')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        $org_id = Rental_Gates_Roles::get_organization_id();

        if (!$org_id) {
            wp_send_json_error(__('Organization not found. Please contact support.', 'rental-gates'));
        }

        $permissions = array();
        if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $module => $level) {
                $module = sanitize_key($module);
                $level = sanitize_key($level);
                if (in_array($level, array('none', 'view', 'edit', 'full'))) {
                    $permissions[$module] = $level;
                }
            }
        }
        $permissions_json = wp_json_encode($permissions);

        $staff_id = intval($_POST['staff_id'] ?? 0);

        if ($staff_id) {
            $staff = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$tables['organization_members']} WHERE id = %d AND organization_id = %d AND role = 'staff'",
                $staff_id,
                $org_id
            ), ARRAY_A);

            if (!$staff) {
                wp_send_json_error(__('Staff member not found', 'rental-gates'));
            }

            $status = sanitize_text_field($_POST['status'] ?? 'active');
            if (!in_array($status, array('active', 'inactive', 'pending'))) {
                $status = 'active';
            }

            $wpdb->update(
                $tables['organization_members'],
                array(
                    'permissions' => $permissions_json,
                    'status' => $status,
                    'updated_at' => current_time('mysql'),
                ),
                array('id' => $staff_id),
                array('%s', '%s', '%s'),
                array('%d')
            );

            wp_send_json_success(array('message' => __('Staff updated successfully', 'rental-gates')));
        } else {
            $email = sanitize_email($_POST['email'] ?? '');
            $display_name = sanitize_text_field($_POST['display_name'] ?? '');
            $phone = sanitize_text_field($_POST['phone'] ?? '');
            $job_title = sanitize_text_field($_POST['job_title'] ?? '');

            if (empty($email) || !is_email($email)) {
                wp_send_json_error(__('Please enter a valid email address', 'rental-gates'));
            }

            if (empty($display_name)) {
                wp_send_json_error(__('Please enter the staff member\'s name', 'rental-gates'));
            }

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT om.id FROM {$tables['organization_members']} om
                 JOIN {$wpdb->users} u ON om.user_id = u.ID
                 WHERE om.organization_id = %d AND u.user_email = %s",
                $org_id,
                $email
            ));

            if ($existing) {
                wp_send_json_error(__('This email is already associated with a member of your organization', 'rental-gates'));
            }

            $user = get_user_by('email', $email);

            if (!$user) {
                $username = sanitize_user(current(explode('@', $email)), true);
                $counter = 1;
                $original_username = $username;
                while (username_exists($username)) {
                    $username = $original_username . $counter;
                    $counter++;
                }

                $password = wp_generate_password(12, true);
                $user_id = wp_create_user($username, $password, $email);

                if (is_wp_error($user_id)) {
                    wp_send_json_error($user_id->get_error_message());
                }

                wp_update_user(array(
                    'ID' => $user_id,
                    'display_name' => $display_name,
                    'first_name' => explode(' ', $display_name)[0],
                    'last_name' => implode(' ', array_slice(explode(' ', $display_name), 1)),
                ));

                if ($phone) {
                    update_user_meta($user_id, 'phone', $phone);
                }
                if ($job_title) {
                    update_user_meta($user_id, 'job_title', $job_title);
                }

                $user = new WP_User($user_id);
                $user->set_role('rental_gates_staff');
            } else {
                $user_id = $user->ID;
                if (!in_array('rental_gates_staff', $user->roles)) {
                    $user->add_role('rental_gates_staff');
                }
            }

            $insert_result = $wpdb->insert(
                $tables['organization_members'],
                array(
                    'organization_id' => $org_id,
                    'user_id' => $user_id,
                    'role' => 'staff',
                    'permissions' => $permissions_json,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );

            if ($insert_result === false) {
                wp_send_json_error(__('Failed to add staff member. Please try again.', 'rental-gates'));
            }

            $org = Rental_Gates_Organization::get($org_id);
            $org_name = $org['name'] ?? __('Your Property Management Company', 'rental-gates');

            $subject = sprintf(__('You\'ve been invited to join %s', 'rental-gates'), $org_name);

            $login_url = home_url('/rental-gates/login');
            $message = sprintf(
                __("Hello %s,\n\nYou've been invited to join %s as a staff member.\n\nYou can access the staff portal by logging in at:\n%s\n\nIf this is your first time, use your email (%s) and click 'Forgot Password' to set up your account.\n\nBest regards,\n%s", 'rental-gates'),
                $display_name,
                $org_name,
                $login_url,
                $email,
                $org_name
            );

            wp_mail($email, $subject, $message);

            wp_send_json_success(array('message' => __('Invitation sent successfully', 'rental-gates')));
        }
    }

    public function handle_remove_staff()
    {
        if (!wp_verify_nonce($_POST['staff_nonce'] ?? '', 'rental_gates_staff_form')) {
            wp_send_json_error(__('Security check failed', 'rental-gates'));
        }

        if (!current_user_can('rg_manage_staff')) {
            wp_send_json_error(__('Permission denied', 'rental-gates'));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();
        $org_id = Rental_Gates_Roles::get_organization_id();

        $staff_id = intval($_POST['staff_id'] ?? 0);

        if (!$staff_id) {
            wp_send_json_error(__('Invalid staff ID', 'rental-gates'));
        }

        $staff = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tables['organization_members']} WHERE id = %d AND organization_id = %d AND role = 'staff'",
            $staff_id,
            $org_id
        ), ARRAY_A);

        if (!$staff) {
            wp_send_json_error(__('Staff member not found', 'rental-gates'));
        }

        $wpdb->delete(
            $tables['organization_members'],
            array('id' => $staff_id),
            array('%d')
        );

        $other_orgs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tables['organization_members']} WHERE user_id = %d AND role = 'staff'",
            $staff['user_id']
        ));

        if ($other_orgs == 0) {
            $user = new WP_User($staff['user_id']);
            $user->remove_role('rental_gates_staff');
        }

        wp_send_json_success(array('message' => __('Staff member removed successfully', 'rental-gates')));
    }
}
