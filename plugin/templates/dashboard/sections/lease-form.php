<?php
/**
 * Lease Form Section (Add/Edit)
 */
if (!defined('ABSPATH')) exit;

$org_id = Rental_Gates_Roles::get_organization_id();
if (!$org_id) {
    wp_redirect(home_url('/rental-gates/login'));
    exit;
}

// Determine if editing - PRIMARY method: parse REQUEST_URI directly
$lease_id = 0;
$lease = null;
$is_edit = false;

if (isset($_SERVER['REQUEST_URI'])) {
    $uri = urldecode($_SERVER['REQUEST_URI']);
    // Match /leases/123 or /leases/123/edit but NOT /leases/add
    if (preg_match('#/leases/(\d+)(?:/edit)?(?:/|$|\?)#', $uri, $matches)) {
        $lease_id = intval($matches[1]);
    }
}

// FALLBACK: try query var if URL parsing failed
if (!$lease_id) {
    $section = get_query_var('rental_gates_section');
    $parts = explode('/', $section);
    if (count($parts) >= 2 && $parts[1] !== 'add' && is_numeric($parts[1])) {
        $lease_id = intval($parts[1]);
    }
}

if ($lease_id) {
    $lease = Rental_Gates_Lease::get_with_details($lease_id);
    
    if (!$lease || $lease['organization_id'] !== $org_id) {
        wp_redirect(home_url('/rental-gates/dashboard/leases'));
        exit;
    }
    
    // Only allow editing draft leases
    if ($lease['status'] !== 'draft') {
        wp_redirect(home_url('/rental-gates/dashboard/leases/' . $lease_id));
        exit;
    }
    
    $is_edit = true;
}

// Get buildings and units for selection
$buildings_result = Rental_Gates_Building::get_for_organization($org_id, array('per_page' => 100));
$buildings = $buildings_result['items'] ?? array();
$units_by_building = array();
foreach ($buildings as $building) {
    $units = Rental_Gates_Unit::get_for_building($building['id']);
    // Use string key for JavaScript compatibility
    $units_by_building[strval($building['id'])] = $units;
}

// Get tenants for selection
$tenants = Rental_Gates_Tenant::get_for_organization($org_id);

// Pre-select unit if provided in URL
$preselect_unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$preselect_building_id = 0;
if ($preselect_unit_id) {
    $preselect_unit = Rental_Gates_Unit::get($preselect_unit_id);
    if ($preselect_unit) {
        $preselect_building_id = $preselect_unit['building_id'];
    }
}

$page_title = $is_edit ? __('Edit Lease', 'rental-gates') : __('New Lease', 'rental-gates');
?>

<div class="rg-form-container">
    <a href="<?php echo home_url('/rental-gates/dashboard/leases' . ($is_edit ? '/' . $lease_id : '')); ?>" class="rg-back-link">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        <?php echo $is_edit ? __('Back to Lease', 'rental-gates') : __('Back to Leases', 'rental-gates'); ?>
    </a>
    
    <div class="rg-form-header">
        <h1><?php echo $page_title; ?></h1>
    </div>
    
    <div id="form-alert"></div>
    
    <form id="lease-form" class="rg-form-card">
        <input type="hidden" name="lease_id" value="<?php echo $lease_id; ?>">
        
        <!-- Unit Selection -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Property', 'rental-gates'); ?></h3>
            
            <div class="rg-form-row">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="building_id"><?php _e('Building', 'rental-gates'); ?> <span class="required">*</span></label>
                    <select id="building_id" name="building_id" class="rg-form-select" required onchange="updateUnits()">
                        <option value=""><?php _e('Select building...', 'rental-gates'); ?></option>
                        <?php foreach ($buildings as $building): ?>
                        <option value="<?php echo $building['id']; ?>" <?php selected($is_edit ? ($lease['building_id'] ?? 0) : $preselect_building_id, $building['id']); ?>>
                            <?php echo esc_html($building['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="rg-form-group">
                    <label class="rg-form-label" for="unit_id"><?php _e('Unit', 'rental-gates'); ?> <span class="required">*</span></label>
                    <select id="unit_id" name="unit_id" class="rg-form-select" required onchange="updateUnitPreview()" <?php echo !$is_edit && !$preselect_building_id ? 'disabled' : ''; ?>>
                        <option value=""><?php _e('Select unit...', 'rental-gates'); ?></option>
                    </select>
                </div>
            </div>
            
            <div id="unit-preview" class="rg-unit-preview">
                <div class="rg-unit-preview-title"><?php _e('Unit Details', 'rental-gates'); ?></div>
                <div class="rg-unit-preview-info">
                    <div class="rg-unit-preview-item"><span><?php _e('Rent', 'rental-gates'); ?></span><strong id="preview-rent">—</strong></div>
                    <div class="rg-unit-preview-item"><span><?php _e('Bedrooms', 'rental-gates'); ?></span><strong id="preview-beds">—</strong></div>
                    <div class="rg-unit-preview-item"><span><?php _e('Bathrooms', 'rental-gates'); ?></span><strong id="preview-baths">—</strong></div>
                    <div class="rg-unit-preview-item"><span><?php _e('Sq Ft', 'rental-gates'); ?></span><strong id="preview-sqft">—</strong></div>
                    <div class="rg-unit-preview-item"><span><?php _e('Deposit', 'rental-gates'); ?></span><strong id="preview-deposit">—</strong></div>
                    <div class="rg-unit-preview-item"><span><?php _e('Status', 'rental-gates'); ?></span><strong id="preview-status">—</strong></div>
                </div>
            </div>
        </div>
        
        <!-- Lease Terms -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Lease Terms', 'rental-gates'); ?></h3>
            
            <div class="rg-form-row">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="start_date"><?php _e('Start Date', 'rental-gates'); ?> <span class="required">*</span></label>
                    <input type="date" id="start_date" name="start_date" class="rg-form-input" 
                           value="<?php echo esc_attr($lease['start_date'] ?? ''); ?>" required>
                </div>
                <div class="rg-form-group">
                    <label class="rg-form-label" for="end_date"><?php _e('End Date', 'rental-gates'); ?></label>
                    <input type="date" id="end_date" name="end_date" class="rg-form-input" 
                           value="<?php echo esc_attr($lease['end_date'] ?? ''); ?>">
                    <span class="rg-form-hint"><?php _e('Leave empty for month-to-month', 'rental-gates'); ?></span>
                </div>
            </div>
            
            <div class="rg-form-row">
                <div class="rg-form-group">
                    <label class="rg-checkbox-group">
                        <input type="checkbox" id="is_month_to_month" name="is_month_to_month" value="1" 
                               <?php checked($lease['is_month_to_month'] ?? false); ?>>
                        <span class="rg-form-label"><?php _e('Month-to-Month Lease', 'rental-gates'); ?></span>
                    </label>
                </div>
                <div class="rg-form-group">
                    <label class="rg-form-label" for="notice_period_days"><?php _e('Notice Period (days)', 'rental-gates'); ?></label>
                    <input type="number" id="notice_period_days" name="notice_period_days" class="rg-form-input" 
                           value="<?php echo esc_attr($lease['notice_period_days'] ?? 30); ?>" min="0">
                </div>
            </div>
        </div>
        
        <!-- Financial -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Financial', 'rental-gates'); ?></h3>
            
            <div class="rg-form-row three-col">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="rent_amount"><?php _e('Rent Amount', 'rental-gates'); ?> <span class="required">*</span></label>
                    <input type="number" id="rent_amount" name="rent_amount" class="rg-form-input" 
                           value="<?php echo esc_attr($lease['rent_amount'] ?? ''); ?>" step="0.01" min="0" required>
                </div>
                <div class="rg-form-group">
                    <label class="rg-form-label" for="deposit_amount"><?php _e('Security Deposit', 'rental-gates'); ?></label>
                    <input type="number" id="deposit_amount" name="deposit_amount" class="rg-form-input" 
                           value="<?php echo esc_attr($lease['deposit_amount'] ?? ''); ?>" step="0.01" min="0">
                </div>
                <div class="rg-form-group">
                    <label class="rg-form-label" for="billing_day"><?php _e('Billing Day', 'rental-gates'); ?></label>
                    <select id="billing_day" name="billing_day" class="rg-form-select">
                        <?php for ($i = 1; $i <= 28; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($lease['billing_day'] ?? 1, $i); ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div class="rg-form-row">
                <div class="rg-form-group">
                    <label class="rg-form-label" for="billing_frequency"><?php _e('Billing Frequency', 'rental-gates'); ?></label>
                    <select id="billing_frequency" name="billing_frequency" class="rg-form-select">
                        <option value="monthly" <?php selected($lease['billing_frequency'] ?? 'monthly', 'monthly'); ?>><?php _e('Monthly', 'rental-gates'); ?></option>
                        <option value="weekly" <?php selected($lease['billing_frequency'] ?? '', 'weekly'); ?>><?php _e('Weekly', 'rental-gates'); ?></option>
                        <option value="biweekly" <?php selected($lease['billing_frequency'] ?? '', 'biweekly'); ?>><?php _e('Bi-Weekly', 'rental-gates'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Tenants -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Tenants', 'rental-gates'); ?></h3>
            <p class="rg-form-desc">
                <?php _e('Add tenants who will be on this lease. At least one tenant is required to activate the lease.', 'rental-gates'); ?>
            </p>
            
            <div id="selected-tenants" class="rg-selected-tenants">
                <!-- Populated by JS -->
            </div>
            
            <div class="rg-add-tenant-row">
                <select id="tenant-select" class="rg-form-select">
                    <option value=""><?php _e('Select tenant to add...', 'rental-gates'); ?></option>
                    <?php foreach ($tenants as $tenant): ?>
                    <option value="<?php echo $tenant['id']; ?>" data-name="<?php echo esc_attr($tenant['full_name']); ?>" data-email="<?php echo esc_attr($tenant['email']); ?>">
                        <?php echo esc_html($tenant['full_name']); ?> (<?php echo esc_html($tenant['email']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <select id="tenant-role" class="rg-form-select" style="width: 140px;">
                    <option value="primary"><?php _e('Primary', 'rental-gates'); ?></option>
                    <option value="co_tenant"><?php _e('Co-Tenant', 'rental-gates'); ?></option>
                    <option value="occupant"><?php _e('Occupant', 'rental-gates'); ?></option>
                </select>
                <button type="button" class="rg-btn rg-btn-secondary" onclick="addTenantToList()"><?php _e('Add', 'rental-gates'); ?></button>
            </div>
            
            <input type="hidden" name="tenants_json" id="tenants-json" value="">
        </div>
        
        <!-- Notes -->
        <div class="rg-form-section">
            <h3 class="rg-form-section-title"><?php _e('Notes', 'rental-gates'); ?></h3>
            <div class="rg-form-row full">
                <div class="rg-form-group">
                    <textarea id="notes" name="notes" class="rg-form-textarea" placeholder="<?php _e('Internal notes about this lease...', 'rental-gates'); ?>"><?php echo esc_textarea($lease['notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <div class="rg-form-actions">
            <a href="<?php echo home_url('/rental-gates/dashboard/leases'); ?>" class="rg-btn rg-btn-secondary"><?php _e('Cancel', 'rental-gates'); ?></a>
            <button type="submit" class="rg-btn rg-btn-primary" id="submit-btn">
                <?php echo $is_edit ? __('Update Lease', 'rental-gates') : __('Create Lease', 'rental-gates'); ?>
            </button>
        </div>
    </form>
</div>

<script>
// Units data
const unitsByBuilding = <?php echo Rental_Gates_Security::json_for_script($units_by_building); ?>;
const existingTenants = <?php echo Rental_Gates_Security::json_for_script($is_edit && !empty($lease['tenants']) ? $lease['tenants'] : []); ?>;
let selectedTenants = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load existing tenants
    existingTenants.forEach(t => {
        selectedTenants.push({
            tenant_id: t.tenant_id,
            name: t.full_name,
            email: t.email,
            role: t.role
        });
    });
    renderSelectedTenants();
    
    // Initialize units if building is selected
    const buildingSelect = document.getElementById('building_id');
    if (buildingSelect.value) {
        updateUnits();
    }
    
    <?php if ($preselect_unit_id): ?>
    // Pre-select unit
    setTimeout(() => {
        document.getElementById('unit_id').value = '<?php echo $preselect_unit_id; ?>';
        updateUnitPreview();
    }, 100);
    <?php endif; ?>
});

function updateUnits() {
    const buildingId = document.getElementById('building_id').value;
    const unitSelect = document.getElementById('unit_id');
    
    unitSelect.innerHTML = '<option value=""><?php _e('Select unit...', 'rental-gates'); ?></option>';
    
    if (!buildingId) {
        unitSelect.disabled = true;
        document.getElementById('unit-preview').classList.remove('visible');
        return;
    }
    
    unitSelect.disabled = false;
    const units = unitsByBuilding[buildingId] || [];
    
    units.forEach(unit => {
        const option = document.createElement('option');
        option.value = unit.id;
        option.textContent = unit.name;
        option.dataset.rent = unit.rent_amount || 0;
        option.dataset.deposit = unit.deposit_amount || 0;
        option.dataset.beds = unit.bedrooms || 0;
        option.dataset.baths = unit.bathrooms || 0;
        option.dataset.sqft = unit.square_footage || 0;
        option.dataset.status = unit.availability || 'available';
        unitSelect.appendChild(option);
    });
    
    <?php if ($is_edit): ?>
    unitSelect.value = '<?php echo $lease['unit_id']; ?>';
    updateUnitPreview();
    <?php endif; ?>
}

function updateUnitPreview() {
    const unitSelect = document.getElementById('unit_id');
    const preview = document.getElementById('unit-preview');
    const option = unitSelect.options[unitSelect.selectedIndex];
    
    if (!unitSelect.value || !option.dataset.rent) {
        preview.classList.remove('visible');
        return;
    }
    
    document.getElementById('preview-rent').textContent = '$' + parseFloat(option.dataset.rent).toLocaleString();
    document.getElementById('preview-beds').textContent = option.dataset.beds;
    document.getElementById('preview-baths').textContent = option.dataset.baths;
    document.getElementById('preview-sqft').textContent = option.dataset.sqft ? option.dataset.sqft.toLocaleString() : '—';
    document.getElementById('preview-deposit').textContent = option.dataset.deposit ? '$' + parseFloat(option.dataset.deposit).toLocaleString() : '—';
    document.getElementById('preview-status').textContent = option.dataset.status.charAt(0).toUpperCase() + option.dataset.status.slice(1).replace('_', ' ');
    
    preview.classList.add('visible');
    
    // Auto-fill rent and deposit if empty
    const rentInput = document.getElementById('rent_amount');
    const depositInput = document.getElementById('deposit_amount');
    
    if (!rentInput.value && option.dataset.rent) {
        rentInput.value = option.dataset.rent;
    }
    if (!depositInput.value && option.dataset.deposit) {
        depositInput.value = option.dataset.deposit;
    }
}

function addTenantToList() {
    const select = document.getElementById('tenant-select');
    const roleSelect = document.getElementById('tenant-role');
    
    if (!select.value) return;
    
    const option = select.options[select.selectedIndex];
    const tenantId = parseInt(select.value);
    
    // Check if already added
    if (selectedTenants.find(t => t.tenant_id === tenantId)) {
        alert('<?php _e('This tenant is already added', 'rental-gates'); ?>');
        return;
    }
    
    selectedTenants.push({
        tenant_id: tenantId,
        name: option.dataset.name,
        email: option.dataset.email,
        role: roleSelect.value
    });
    
    select.value = '';
    renderSelectedTenants();
}

function removeTenantFromList(tenantId) {
    selectedTenants = selectedTenants.filter(t => t.tenant_id !== tenantId);
    renderSelectedTenants();
}

function renderSelectedTenants() {
    const container = document.getElementById('selected-tenants');
    const jsonInput = document.getElementById('tenants-json');
    
    if (selectedTenants.length === 0) {
        container.innerHTML = '<p style="color: var(--rg-gray-400); text-align: center; padding: 20px;"><?php _e('No tenants added yet', 'rental-gates'); ?></p>';
    } else {
        container.innerHTML = selectedTenants.map(t => {
            const initials = t.name.split(' ').map(n => n[0]).join('').toUpperCase();
            const roleLabels = { primary: '<?php _e('Primary', 'rental-gates'); ?>', co_tenant: '<?php _e('Co-Tenant', 'rental-gates'); ?>', occupant: '<?php _e('Occupant', 'rental-gates'); ?>' };
            return `
                <div class="rg-selected-tenant">
                    <div class="rg-selected-tenant-info">
                        <div class="rg-selected-tenant-avatar">${initials}</div>
                        <div>
                            <strong>${t.name}</strong>
                            <span style="font-size: 13px; color: var(--rg-gray-500); display: block;">${t.email}</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="padding: 2px 8px; background: var(--rg-gray-100); border-radius: 12px; font-size: 12px;">${roleLabels[t.role]}</span>
                        <button type="button" onclick="removeTenantFromList(${t.tenant_id})" style="background: none; border: none; cursor: pointer; color: var(--rg-gray-400);">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    jsonInput.value = JSON.stringify(selectedTenants);
}

// Form submission
document.getElementById('lease-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submit-btn');
    const alertContainer = document.getElementById('form-alert');
    
    submitBtn.disabled = true;
    submitBtn.textContent = '<?php _e('Saving...', 'rental-gates'); ?>';
    
    const formData = new FormData(this);
    formData.append('action', '<?php echo $is_edit ? 'rental_gates_update_lease' : 'rental_gates_create_lease'; ?>');
    formData.append('nonce', rentalGatesData.nonce);
    
    fetch(rentalGatesData.ajaxUrl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = '<?php echo home_url('/rental-gates/dashboard/leases/'); ?>' + data.data.id;
            } else {
                alertContainer.textContent = '';
                var alertDiv = document.createElement('div');
                alertDiv.className = 'rg-alert rg-alert-error';
                alertDiv.textContent = (data.data || '<?php _e('Error saving lease', 'rental-gates'); ?>');
                alertContainer.appendChild(alertDiv);
                alertContainer.scrollIntoView({ behavior: 'smooth' });
                submitBtn.disabled = false;
                submitBtn.textContent = '<?php echo $is_edit ? __('Update Lease', 'rental-gates') : __('Create Lease', 'rental-gates'); ?>';
            }
        })
        .catch(() => {
            alertContainer.innerHTML = '<div class="rg-alert rg-alert-error"><?php _e('Error saving lease', 'rental-gates'); ?></div>';
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo $is_edit ? __('Update Lease', 'rental-gates') : __('Create Lease', 'rental-gates'); ?>';
        });
});
</script>
