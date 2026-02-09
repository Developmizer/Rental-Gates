<?php
/**
 * Maintenance Form Section
 */
if (!defined('ABSPATH')) exit;

$org_id = Rental_Gates_Roles::get_organization_id();
if (!$org_id) {
    wp_redirect(home_url('/rental-gates/login'));
    exit;
}

// Check if editing - PRIMARY method: parse REQUEST_URI directly
$work_order_id = 0;
$wo = null;
$is_edit = false;

if (isset($_SERVER['REQUEST_URI'])) {
    $uri = urldecode($_SERVER['REQUEST_URI']);
    if (preg_match('#/maintenance/(\d+)(?:/edit)?(?:/|$|\?)#', $uri, $matches)) {
        $work_order_id = intval($matches[1]);
    }
}

// FALLBACK: try query var if URL parsing failed
if (!$work_order_id) {
    $section = get_query_var('rental_gates_section');
    $parts = explode('/', $section);
    if (isset($parts[1]) && is_numeric($parts[1])) {
        $work_order_id = intval($parts[1]);
    }
}

if ($work_order_id) {
    $wo = Rental_Gates_Maintenance::get_with_details($work_order_id);
    if ($wo && $wo['organization_id'] === $org_id) {
        $is_edit = true;
    }
}

// Get buildings for selection
$buildings_result = Rental_Gates_Building::get_for_organization($org_id);
$buildings = $buildings_result['items'] ?? array();

// Get tenants for selection
$tenants_result = Rental_Gates_Tenant::get_for_organization($org_id, array('status' => 'active'));
$tenants = $tenants_result['tenants'] ?? array();

// Pre-select building/unit if provided
$preselect_building = isset($_GET['building_id']) ? intval($_GET['building_id']) : ($wo['building_id'] ?? 0);
$preselect_unit = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : ($wo['unit_id'] ?? 0);

// Build units by building for JS
$units_by_building = array();
foreach ($buildings as $building) {
    $units = Rental_Gates_Unit::get_for_building($building['id']);
    $units_by_building[strval($building['id'])] = $units;
}

$page_title = $is_edit ? __('Edit Work Order', 'rental-gates') : __('New Work Order', 'rental-gates');
?>

<a href="<?php echo home_url('/rental-gates/dashboard/maintenance'); ?>" class="rg-back-link">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    <?php _e('Back to Maintenance', 'rental-gates'); ?>
</a>

<div class="rg-form-container">
    <div class="rg-form-header">
        <h1><?php echo $page_title; ?></h1>
    </div>
    
    <div id="form-message"></div>
    
    <form id="maintenance-form" class="rg-form-card">
        <input type="hidden" name="work_order_id" value="<?php echo $work_order_id; ?>">
        
        <!-- Location -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Location', 'rental-gates'); ?></h3>
            
            <div class="rg-form-row">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="building_id"><?php _e('Building', 'rental-gates'); ?> <span class="required">*</span></label>
                    <select id="building_id" name="building_id" class="rg-form-select" required onchange="updateUnits()">
                        <option value=""><?php _e('Select building...', 'rental-gates'); ?></option>
                        <?php foreach ($buildings as $building): ?>
                        <option value="<?php echo $building['id']; ?>" <?php selected($preselect_building, $building['id']); ?>>
                            <?php echo esc_html($building['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="rg-form-group">
                    <label class="rg-form-label" for="unit_id"><?php _e('Unit', 'rental-gates'); ?></label>
                    <select id="unit_id" name="unit_id" class="rg-form-select">
                        <option value=""><?php _e('Common area / No unit', 'rental-gates'); ?></option>
                    </select>
                    <span class="rg-form-hint"><?php _e('Leave empty for common area issues', 'rental-gates'); ?></span>
                </div>
            </div>
            
            <div class="rg-form-row full">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="tenant_id"><?php _e('Reported By (Tenant)', 'rental-gates'); ?></label>
                    <select id="tenant_id" name="tenant_id" class="rg-form-select">
                        <option value=""><?php _e('Staff reported / No tenant', 'rental-gates'); ?></option>
                        <?php foreach ($tenants as $tenant): ?>
                        <option value="<?php echo $tenant['id']; ?>" <?php selected($wo['tenant_id'] ?? 0, $tenant['id']); ?>>
                            <?php echo esc_html($tenant['full_name'] . ' (' . $tenant['email'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Issue Details -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Issue Details', 'rental-gates'); ?></h3>
            
            <div class="rg-form-row full">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="title"><?php _e('Title', 'rental-gates'); ?> <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="rg-form-input" required
                           value="<?php echo esc_attr($wo['title'] ?? ''); ?>"
                           placeholder="<?php _e('e.g., Leaky faucet in bathroom', 'rental-gates'); ?>">
                </div>
            </div>
            
            <div class="rg-form-row full">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="description"><?php _e('Description', 'rental-gates'); ?> <span class="required">*</span></label>
                    <textarea id="description" name="description" class="rg-form-textarea" required
                              placeholder="<?php _e('Describe the issue in detail...', 'rental-gates'); ?>"><?php echo esc_textarea($wo['description'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="rg-form-row">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="category"><?php _e('Category', 'rental-gates'); ?></label>
                    <select id="category" name="category" class="rg-form-select">
                        <option value="plumbing" <?php selected($wo['category'] ?? '', 'plumbing'); ?>><?php _e('Plumbing', 'rental-gates'); ?></option>
                        <option value="electrical" <?php selected($wo['category'] ?? '', 'electrical'); ?>><?php _e('Electrical', 'rental-gates'); ?></option>
                        <option value="hvac" <?php selected($wo['category'] ?? '', 'hvac'); ?>><?php _e('HVAC', 'rental-gates'); ?></option>
                        <option value="appliance" <?php selected($wo['category'] ?? '', 'appliance'); ?>><?php _e('Appliance', 'rental-gates'); ?></option>
                        <option value="structural" <?php selected($wo['category'] ?? '', 'structural'); ?>><?php _e('Structural', 'rental-gates'); ?></option>
                        <option value="pest" <?php selected($wo['category'] ?? '', 'pest'); ?>><?php _e('Pest Control', 'rental-gates'); ?></option>
                        <option value="cleaning" <?php selected($wo['category'] ?? '', 'cleaning'); ?>><?php _e('Cleaning', 'rental-gates'); ?></option>
                        <option value="general" <?php selected($wo['category'] ?? 'general', 'general'); ?>><?php _e('General', 'rental-gates'); ?></option>
                        <option value="other" <?php selected($wo['category'] ?? '', 'other'); ?>><?php _e('Other', 'rental-gates'); ?></option>
                    </select>
                </div>
                
                <div class="rg-form-group">
                    <label class="rg-form-label" for="scheduled_date"><?php _e('Scheduled Date', 'rental-gates'); ?></label>
                    <input type="datetime-local" id="scheduled_date" name="scheduled_date" class="rg-form-input"
                           value="<?php echo !empty($wo['scheduled_date']) ? date('Y-m-d\TH:i', strtotime($wo['scheduled_date'])) : ''; ?>">
                </div>
            </div>
        </div>
        
        <!-- Priority -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Priority', 'rental-gates'); ?></h3>
            
            <div class="rg-priority-options">
                <div class="rg-priority-option">
                    <input type="radio" id="priority-low" name="priority" value="low" <?php checked($wo['priority'] ?? '', 'low'); ?>>
                    <label for="priority-low"><span style="color: #6b7280;">Low</span><small>Can wait</small></label>
                </div>
                <div class="rg-priority-option">
                    <input type="radio" id="priority-medium" name="priority" value="medium" <?php checked($wo['priority'] ?? 'medium', 'medium'); ?>>
                    <label for="priority-medium"><span style="color: #3b82f6;">Medium</span><small>Within a week</small></label>
                </div>
                <div class="rg-priority-option high">
                    <input type="radio" id="priority-high" name="priority" value="high" <?php checked($wo['priority'] ?? '', 'high'); ?>>
                    <label for="priority-high"><span style="color: #f59e0b;">High</span><small>Within 48 hours</small></label>
                </div>
                <div class="rg-priority-option emergency">
                    <input type="radio" id="priority-emergency" name="priority" value="emergency" <?php checked($wo['priority'] ?? '', 'emergency'); ?>>
                    <label for="priority-emergency"><span style="color: #dc2626;">Emergency</span><small>Immediate</small></label>
                </div>
            </div>
        </div>
        
        <!-- Access -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Access', 'rental-gates'); ?></h3>
            
            <div class="rg-form-row full">
                <div class="rg-form-group">
                    <label class="rg-checkbox-label">
                        <input type="checkbox" id="permission_to_enter" name="permission_to_enter" value="1" <?php checked($wo['permission_to_enter'] ?? false); ?>>
                        <span><?php _e('Permission to enter without tenant present', 'rental-gates'); ?></span>
                    </label>
                </div>
            </div>
            
            <div class="rg-form-row full">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="access_instructions"><?php _e('Access Instructions', 'rental-gates'); ?></label>
                    <textarea id="access_instructions" name="access_instructions" class="rg-form-textarea" style="min-height: 60px;"
                              placeholder="<?php _e('e.g., Key under mat, call ahead, etc.', 'rental-gates'); ?>"><?php echo esc_textarea($wo['access_instructions'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Cost -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Cost', 'rental-gates'); ?></h3>
            
            <div class="rg-form-row">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="cost_estimate"><?php _e('Estimated Cost', 'rental-gates'); ?></label>
                    <input type="number" id="cost_estimate" name="cost_estimate" class="rg-form-input" step="0.01" min="0"
                           value="<?php echo esc_attr($wo['cost_estimate'] ?? ''); ?>" placeholder="0.00">
                </div>
            </div>
        </div>
        
        <!-- Internal Notes -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Internal Notes', 'rental-gates'); ?></h3>
            
            <div class="rg-form-row full">
                <div class="rg-form-group">
                    <textarea id="internal_notes" name="internal_notes" class="rg-form-textarea"
                              placeholder="<?php _e('Notes for staff only...', 'rental-gates'); ?>"><?php echo esc_textarea($wo['internal_notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <div class="rg-form-actions">
            <a href="<?php echo home_url('/rental-gates/dashboard/maintenance'); ?>" class="rg-btn rg-btn-secondary"><?php _e('Cancel', 'rental-gates'); ?></a>
            <button type="submit" class="rg-btn rg-btn-primary"><?php echo $is_edit ? __('Update Work Order', 'rental-gates') : __('Create Work Order', 'rental-gates'); ?></button>
        </div>
    </form>
</div>

<script>
const unitsByBuilding = <?php echo Rental_Gates_Security::json_for_script($units_by_building); ?>;
const preselectUnit = <?php echo $preselect_unit; ?>;

document.addEventListener('DOMContentLoaded', function() {
    updateUnits();
});

function updateUnits() {
    const buildingId = document.getElementById('building_id').value;
    const unitSelect = document.getElementById('unit_id');
    
    unitSelect.innerHTML = '<option value=""><?php _e('Common area / No unit', 'rental-gates'); ?></option>';
    
    if (buildingId && unitsByBuilding[buildingId]) {
        unitsByBuilding[buildingId].forEach(function(unit) {
            const option = document.createElement('option');
            option.value = unit.id;
            option.textContent = unit.name;
            if (unit.id == preselectUnit) option.selected = true;
            unitSelect.appendChild(option);
        });
    }
}

document.getElementById('maintenance-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const workOrderId = parseInt(formData.get('work_order_id')) || 0;
    formData.append('action', workOrderId > 0 ? 'rental_gates_update_maintenance' : 'rental_gates_create_maintenance');
    formData.append('nonce', rentalGatesData.nonce);
    
    const messageDiv = document.getElementById('form-message');
    messageDiv.innerHTML = '';
    
    fetch(rentalGatesData.ajaxUrl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = '<?php echo home_url('/rental-gates/dashboard/maintenance/'); ?>' + data.data.work_order.id;
            } else {
                messageDiv.innerHTML = '<div class="rg-alert rg-alert-error">' + (data.data || '<?php _e('Error saving work order', 'rental-gates'); ?>') + '</div>';
                window.scrollTo(0, 0);
            }
        });
});
</script>
