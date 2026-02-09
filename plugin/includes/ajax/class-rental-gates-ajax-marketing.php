<?php
if (!defined('ABSPATH')) exit;

/**
 * AJAX Handlers for Marketing (QR Codes, Flyers, Reports)
 *
 * Extracted from rental-gates.php during refactor.
 * Handles: generate_qr, get_qr, bulk_generate_qr, qr_analytics,
 *          delete_qr, create_flyer, regenerate_flyer,
 *          flyer_preview, calculate_lead_score, get_marketing_analytics,
 *          report_export
 */
class Rental_Gates_Ajax_Marketing {

    public function __construct() {
        add_action('wp_ajax_rental_gates_generate_qr', array($this, 'handle_qr_generation'));
        add_action('wp_ajax_rental_gates_get_qr', array($this, 'handle_get_qr'));
        add_action('wp_ajax_rental_gates_bulk_generate_qr', array($this, 'handle_bulk_generate_qr'));
        add_action('wp_ajax_rental_gates_qr_analytics', array($this, 'handle_qr_analytics'));
        add_action('wp_ajax_rental_gates_delete_qr', array($this, 'handle_delete_qr'));
        add_action('wp_ajax_rental_gates_create_flyer', array($this, 'handle_create_flyer'));
        add_action('wp_ajax_rental_gates_regenerate_flyer', array($this, 'handle_regenerate_flyer'));
        add_action('wp_ajax_rental_gates_flyer_preview', array($this, 'handle_flyer_preview'));
        add_action('wp_ajax_rental_gates_calculate_lead_score', array($this, 'handle_calculate_lead_score'));
        add_action('wp_ajax_rental_gates_get_marketing_analytics', array($this, 'handle_get_marketing_analytics'));

        // Report export (init hook for early processing)
        add_action('init', array($this, 'handle_report_export'));
    }

    private function get_org_id() {
        return Rental_Gates_Roles::get_organization_id();
    }

    public function handle_qr_generation()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $type = sanitize_text_field($_POST['type'] ?? 'building');
        $id = intval($_POST['entity_id'] ?? $_POST['id'] ?? 0);
        $size = sanitize_text_field($_POST['size'] ?? 'medium');

        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid ID', 'rental-gates')));
        }

        $qr = new Rental_Gates_QR();

        if ($type === 'unit') {
            $result = $qr->generate_for_unit($id, $size);
        } elseif ($type === 'organization') {
            $result = $qr->generate_for_organization($id, $size);
        } else {
            $result = $qr->generate_for_building($id, $size);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function handle_get_qr()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $qr_id = intval($_POST['qr_id'] ?? 0);
        $size = sanitize_text_field($_POST['size'] ?? 'medium');

        if (!$qr_id) {
            wp_send_json_error(array('message' => __('Invalid QR ID', 'rental-gates')));
        }

        $qr = Rental_Gates_QR::get($qr_id);

        if (!$qr) {
            wp_send_json_error(array('message' => __('QR code not found', 'rental-gates')));
        }

        $sizes = array('small' => 150, 'medium' => 300, 'large' => 500, 'print' => 1000);
        $size_px = $sizes[$size] ?? 300;
        $qr['qr_image'] = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size_px . 'x' . $size_px . '&data=' . urlencode($qr['url']);

        wp_send_json_success($qr);
    }

    public function handle_bulk_generate_qr()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!current_user_can('rg_manage_marketing') && !current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $include_buildings = !empty($_POST['include_buildings']);
        $include_units = !empty($_POST['include_units']);
        $size = sanitize_text_field($_POST['size'] ?? 'medium');

        $qr = new Rental_Gates_QR();
        $results = array();

        if ($include_buildings) {
            $building_results = $qr->generate_all_buildings($org_id, $size);
            $results = array_merge($results, $building_results);
        }

        if ($include_units) {
            $unit_results = $qr->generate_all_units($org_id, $size);
            $results = array_merge($results, $unit_results);
        }

        wp_send_json_success(array(
            'count' => count($results),
            'items' => $results
        ));
    }

    public function handle_qr_analytics()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $qr_id = intval($_POST['qr_id'] ?? 0);
        $days = intval($_POST['days'] ?? 30);

        if (!$qr_id) {
            wp_send_json_error(array('message' => __('Invalid QR ID', 'rental-gates')));
        }

        $analytics = Rental_Gates_QR::get_scan_analytics($qr_id, $days);

        wp_send_json_success($analytics);
    }

    public function handle_delete_qr()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!current_user_can('rg_manage_marketing') && !current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $qr_id = intval($_POST['qr_id'] ?? 0);

        if (!$qr_id) {
            wp_send_json_error(array('message' => __('Invalid QR ID', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        $qr = Rental_Gates_QR::get($qr_id);

        if (!$qr || $qr['organization_id'] != $org_id) {
            wp_send_json_error(array('message' => __('QR code not found', 'rental-gates')));
        }

        $result = Rental_Gates_QR::delete($qr_id);

        if ($result) {
            wp_send_json_success(array('message' => __('QR code deleted successfully', 'rental-gates')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete QR code', 'rental-gates')));
        }
    }

    public function handle_create_flyer()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        if (!current_user_can('rg_manage_marketing') && !current_user_can('rg_manage_buildings') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'rental-gates')));
        }

        $org_id = $this->get_org_id();
        if (!$org_id) {
            wp_send_json_error(array('message' => __('Organization not found', 'rental-gates')));
        }

        $unit_id = intval($_POST['unit_id'] ?? 0);
        $template = sanitize_text_field($_POST['template'] ?? 'modern');
        $include_qr = !empty($_POST['include_qr']);
        $title = sanitize_text_field($_POST['title'] ?? '');

        if (!$unit_id) {
            wp_send_json_error(array('message' => __('Please select a unit', 'rental-gates')));
        }

        $result = Rental_Gates_Flyer::create(array(
            'organization_id' => $org_id,
            'type' => 'unit',
            'entity_id' => $unit_id,
            'template' => $template,
            'include_qr' => $include_qr,
            'title' => $title
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    public function handle_regenerate_flyer()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');

        $flyer_id = intval($_POST['flyer_id'] ?? 0);

        if (!$flyer_id) {
            wp_send_json_error(array('message' => __('Invalid flyer ID', 'rental-gates')));
        }

        $result = Rental_Gates_Flyer::regenerate($flyer_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Handle flyer preview
     * Note: Registered in init_hooks() but no implementation existed in the original source.
     */
    public function handle_flyer_preview()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');
        wp_send_json_error(array('message' => __('Flyer preview not yet implemented', 'rental-gates')));
    }

    /**
     * Handle lead score calculation
     * Note: Registered in init_hooks() but no implementation existed in the original source.
     */
    public function handle_calculate_lead_score()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');
        wp_send_json_error(array('message' => __('Lead score calculation not yet implemented', 'rental-gates')));
    }

    /**
     * Handle marketing analytics retrieval
     * Note: Registered in init_hooks() but no implementation existed in the original source.
     */
    public function handle_get_marketing_analytics()
    {
        check_ajax_referer('rental_gates_nonce', 'nonce');
        wp_send_json_error(array('message' => __('Marketing analytics not yet implemented', 'rental-gates')));
    }

    /**
     * Handle report export (CSV/PDF)
     * Hooked to 'init' for early processing before template output
     */
    public function handle_report_export()
    {
        // Check if this is a report export request
        if (!isset($_GET['export']) || !in_array($_GET['export'], array('csv', 'pdf'))) {
            return;
        }

        $format = sanitize_text_field($_GET['export']);

        // Check if on reports page
        $page = get_query_var('rental_gates_page');
        $section = get_query_var('rental_gates_section');

        if ($page !== 'dashboard' || $section !== 'reports') {
            return;
        }

        // Verify user has access
        if (!is_user_logged_in() || !current_user_can('rg_view_reports')) {
            wp_die(__('Access denied', 'rental-gates'));
        }

        // Get organization
        $org_id = Rental_Gates_Roles::get_organization_id();
        if (!$org_id) {
            wp_die(__('Organization not found', 'rental-gates'));
        }

        global $wpdb;
        $tables = Rental_Gates_Database::get_table_names();

        // Get parameters
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'financial';
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
        $year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
        $month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));

        // Calculate date range
        switch ($period) {
            case 'year':
                $start_date = "$year-01-01";
                $end_date = "$year-12-31";
                $filename_period = $year;
                break;
            case 'quarter':
                $quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil($month / 3);
                $start_month = (($quarter - 1) * 3) + 1;
                $end_month = $quarter * 3;
                $start_date = "$year-" . str_pad($start_month, 2, '0', STR_PAD_LEFT) . "-01";
                $end_date = date('Y-m-t', strtotime("$year-" . str_pad($end_month, 2, '0', STR_PAD_LEFT) . "-01"));
                $filename_period = "Q{$quarter}-{$year}";
                break;
            default:
                $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
                $end_date = date('Y-m-t', strtotime($start_date));
                $filename_period = date('M-Y', strtotime($start_date));
                break;
        }

        // Generate data based on tab
        $report_data = array();
        $filename = "rental-gates-{$tab}-report-{$filename_period}";

        switch ($tab) {
            case 'financial':
                $report_data[] = array('Building', 'Billed', 'Collected', 'Collection Rate');

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.name as building_name,
                            COALESCE(SUM(p.amount), 0) as billed,
                            COALESCE(SUM(p.amount_paid), 0) as collected
                     FROM {$tables['buildings']} b
                     LEFT JOIN {$tables['units']} u ON b.id = u.building_id
                     LEFT JOIN {$tables['leases']} l ON u.id = l.unit_id
                     LEFT JOIN {$tables['payments']} p ON l.id = p.lease_id
                         AND p.due_date BETWEEN %s AND %s
                     WHERE b.organization_id = %d
                     GROUP BY b.id, b.name
                     ORDER BY b.name",
                    $start_date,
                    $end_date,
                    $org_id
                ), ARRAY_A);

                foreach ($results as $row) {
                    $rate = $row['billed'] > 0 ? round(($row['collected'] / $row['billed']) * 100, 1) . '%' : '0%';
                    $report_data[] = array(
                        $row['building_name'],
                        '$' . number_format($row['billed'], 2),
                        '$' . number_format($row['collected'], 2),
                        $rate
                    );
                }
                break;

            case 'occupancy':
                $report_data[] = array('Building', 'Total Units', 'Occupied', 'Vacant', 'Occupancy Rate');

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.name as building_name,
                            COUNT(DISTINCT u.id) as total_units,
                            COUNT(DISTINCT CASE WHEN l.id IS NOT NULL THEN u.id END) as occupied
                     FROM {$tables['buildings']} b
                     LEFT JOIN {$tables['units']} u ON b.id = u.building_id
                     LEFT JOIN {$tables['leases']} l ON u.id = l.unit_id AND l.status = 'active'
                     WHERE b.organization_id = %d
                     GROUP BY b.id, b.name
                     ORDER BY b.name",
                    $org_id
                ), ARRAY_A);

                foreach ($results as $row) {
                    $vacant = $row['total_units'] - $row['occupied'];
                    $rate = $row['total_units'] > 0 ? round(($row['occupied'] / $row['total_units']) * 100, 1) . '%' : '0%';
                    $report_data[] = array(
                        $row['building_name'],
                        $row['total_units'],
                        $row['occupied'],
                        $vacant,
                        $rate
                    );
                }
                break;

            case 'maintenance':
                $report_data[] = array('Building', 'Total Work Orders', 'Completed', 'Completion Rate');

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.name as building_name,
                            COUNT(wo.id) as total_orders,
                            SUM(CASE WHEN wo.status = 'completed' THEN 1 ELSE 0 END) as completed
                     FROM {$tables['buildings']} b
                     LEFT JOIN {$tables['work_orders']} wo ON b.id = wo.building_id
                         AND wo.created_at BETWEEN %s AND %s
                     WHERE b.organization_id = %d
                     GROUP BY b.id, b.name
                     ORDER BY b.name",
                    $start_date,
                    $end_date . ' 23:59:59',
                    $org_id
                ), ARRAY_A);

                foreach ($results as $row) {
                    $rate = $row['total_orders'] > 0 ? round(($row['completed'] / $row['total_orders']) * 100, 1) . '%' : 'N/A';
                    $report_data[] = array(
                        $row['building_name'],
                        $row['total_orders'],
                        $row['completed'],
                        $rate
                    );
                }
                break;
        }

        // Output based on format
        if ($format === 'csv') {
            // Output CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($report_data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } elseif ($format === 'pdf') {
            // Generate HTML for PDF
            $org = Rental_Gates_Organization::get($org_id);
            $tab_labels = array(
                'financial' => __('Financial Report', 'rental-gates'),
                'occupancy' => __('Occupancy Report', 'rental-gates'),
                'maintenance' => __('Maintenance Report', 'rental-gates'),
            );

            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            $html .= '<title>' . esc_html($tab_labels[$tab] ?? ucfirst($tab)) . ' - ' . esc_html($filename_period) . '</title>';
            $html .= '<style>
                body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
                h1 { color: #111827; margin-bottom: 10px; }
                .meta { color: #6b7280; margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #f3f4f6; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e5e7eb; }
                td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
                tr:hover { background: #f9fafb; }
                @media print { body { padding: 10px; } }
            </style></head><body>';
            $html .= '<h1>' . esc_html($tab_labels[$tab] ?? ucfirst($tab)) . '</h1>';
            $html .= '<div class="meta">';
            $html .= '<strong>' . __('Organization', 'rental-gates') . ':</strong> ' . esc_html($org['name'] ?? '') . '<br>';
            $html .= '<strong>' . __('Period', 'rental-gates') . ':</strong> ' . esc_html($filename_period) . '<br>';
            $html .= '<strong>' . __('Generated', 'rental-gates') . ':</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'));
            $html .= '</div>';
            $html .= '<table>';

            if (!empty($report_data)) {
                $header = array_shift($report_data);
                $html .= '<thead><tr>';
                foreach ($header as $col) {
                    $html .= '<th>' . esc_html($col) . '</th>';
                }
                $html .= '</tr></thead><tbody>';

                foreach ($report_data as $row) {
                    $html .= '<tr>';
                    foreach ($row as $col) {
                        $html .= '<td>' . esc_html($col) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</tbody>';
            }

            $html .= '</table>';
            $html .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px;">';
            $html .= __('Generated by Rental Gates', 'rental-gates');
            $html .= '</div>';
            $html .= '</body></html>';

            // Output HTML with print instructions for PDF
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            echo '<script>window.print();</script>';
            exit;
        }
    }
}
