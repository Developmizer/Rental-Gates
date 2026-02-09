<?php
/**
 * Dashboard Section: Add/Edit Unit
 * 
 * Always accessed from within a building context (Rule 2).
 * Includes availability state controls per the state machine.
 */
if (!defined('ABSPATH')) exit;

// Get organization ID
$org_id = null;
if (class_exists('Rental_Gates_Roles')) {
    $org_id = Rental_Gates_Roles::get_organization_id();
}

// Get building ID from URL (required)
$building_id = isset($_GET['building_id']) ? intval($_GET['building_id']) : 0;
$building = null;

if ($building_id && class_exists('Rental_Gates_Building')) {
    $building = Rental_Gates_Building::get($building_id);
    if (!$building || $building['organization_id'] != $org_id) {
        wp_redirect(home_url('/rental-gates/dashboard/buildings'));
        exit;
    }
}

if (!$building) {
    wp_redirect(home_url('/rental-gates/dashboard/buildings'));
    exit;
}

// Check if editing
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$unit = null;
$is_edit = false;

if ($unit_id && class_exists('Rental_Gates_Unit')) {
    $unit = Rental_Gates_Unit::get($unit_id);
    if ($unit && $unit['building_id'] == $building_id) {
        $is_edit = true;
    } else {
        $unit = null;
    }
}

// Get gallery and amenities - already decoded by format_unit()
$gallery = $unit && !empty($unit['gallery']) ? $unit['gallery'] : array();
if (!is_array($gallery)) $gallery = array();

$amenities = $unit && !empty($unit['amenities']) ? $unit['amenities'] : array();
if (!is_array($amenities)) $amenities = array();
?>

<style>
    .rg-form-container {
        max-width: 800px;
    }
    
    .rg-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        margin-bottom: 16px;
    }
    
    .rg-breadcrumb a {
        color: var(--gray-500);
        text-decoration: none;
    }
    
    .rg-breadcrumb a:hover {
        color: var(--primary);
    }
    
    .rg-breadcrumb-separator {
        color: var(--gray-400);
    }
    
    .rg-breadcrumb-current {
        color: var(--gray-900);
        font-weight: 500;
    }
    
    .rg-form-header {
        margin-bottom: 24px;
    }
    
    .rg-form-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--gray-900);
        margin: 0 0 8px 0;
    }
    
    .rg-form-subtitle {
        font-size: 14px;
        color: var(--gray-500);
    }
    
    .rg-form-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid var(--gray-200);
        margin-bottom: 24px;
    }
    
    .rg-form-section {
        padding: 24px;
    }
    
    .rg-form-section + .rg-form-section {
        border-top: 1px solid var(--gray-100);
    }
    
    .rg-section-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 4px;
    }
    
    .rg-section-description {
        font-size: 14px;
        color: var(--gray-500);
        margin-bottom: 20px;
    }
    
    .rg-form-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 16px;
    }
    
    .rg-form-row.two-col {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .rg-form-row.full {
        grid-template-columns: 1fr;
    }
    
    .rg-form-group {
        display: flex;
        flex-direction: column;
    }
    
    .rg-form-label {
        font-size: 14px;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 6px;
    }
    
    .rg-form-label .required {
        color: var(--danger);
    }
    
    .rg-form-input,
    .rg-form-select,
    .rg-form-textarea {
        padding: 10px 14px;
        border: 1px solid var(--gray-300);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .rg-form-input:focus,
    .rg-form-select:focus,
    .rg-form-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    
    .rg-form-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .rg-form-help {
        font-size: 12px;
        color: var(--gray-500);
        margin-top: 4px;
    }
    
    /* Availability Section */
    .rg-availability-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }
    
    .rg-availability-option {
        position: relative;
        border: 2px solid var(--gray-200);
        border-radius: 10px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .rg-availability-option:hover {
        border-color: var(--gray-300);
    }
    
    .rg-availability-option.selected {
        border-color: var(--primary);
        background: #eff6ff;
    }
    
    .rg-availability-option input {
        position: absolute;
        opacity: 0;
    }
    
    .rg-availability-name {
        font-weight: 600;
        color: var(--gray-900);
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .rg-availability-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }
    
    .rg-availability-dot.available { background: var(--success); }
    .rg-availability-dot.occupied { background: var(--primary); }
    .rg-availability-dot.coming_soon { background: var(--warning); }
    .rg-availability-dot.renewal_pending { background: #8b5cf6; }
    .rg-availability-dot.unlisted { background: var(--gray-400); }
    
    .rg-availability-desc {
        font-size: 13px;
        color: var(--gray-500);
    }
    
    .rg-date-field {
        margin-top: 16px;
        display: none;
    }
    
    .rg-date-field.visible {
        display: block;
    }
    
    /* Room Counts */
    .rg-room-counts {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
    }
    
    .rg-room-count {
        text-align: center;
    }
    
    .rg-room-count-label {
        font-size: 13px;
        color: var(--gray-500);
        margin-bottom: 8px;
    }
    
    .rg-room-count-input {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .rg-room-btn {
        width: 32px;
        height: 32px;
        border: 1px solid var(--gray-300);
        border-radius: 6px;
        background: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gray-600);
        font-size: 18px;
        transition: all 0.2s;
    }
    
    .rg-room-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .rg-room-value {
        width: 40px;
        text-align: center;
        font-size: 18px;
        font-weight: 600;
        color: var(--gray-900);
    }
    
    /* Gallery */
    .rg-gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 12px;
    }
    
    .rg-gallery-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        background: var(--gray-100);
    }
    
    .rg-gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .rg-gallery-item-remove {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 24px;
        height: 24px;
        background: rgba(0,0,0,0.6);
        border: none;
        border-radius: 50%;
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .rg-gallery-add {
        aspect-ratio: 1;
        border: 2px dashed var(--gray-300);
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--gray-400);
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .rg-gallery-add:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .rg-gallery-add input {
        display: none;
    }
    
    /* Amenities */
    .rg-amenities-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 8px;
    }
    
    .rg-amenity-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border: 1px solid var(--gray-200);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .rg-amenity-item:hover {
        border-color: var(--gray-300);
    }
    
    .rg-amenity-item.selected {
        border-color: var(--primary);
        background: #eff6ff;
    }
    
    .rg-amenity-item input {
        display: none;
    }
    
    .rg-amenity-check {
        width: 18px;
        height: 18px;
        border: 2px solid var(--gray-300);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex-shrink: 0;
    }
    
    .rg-amenity-item.selected .rg-amenity-check {
        background: var(--primary);
        border-color: var(--primary);
    }
    
    .rg-amenity-label {
        font-size: 14px;
        color: var(--gray-700);
    }
    
    /* Form Actions */
    .rg-form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        padding: 20px 24px;
        background: var(--gray-50);
        border-top: 1px solid var(--gray-200);
        border-radius: 0 0 12px 12px;
    }
    
    .rg-btn-secondary {
        background: #fff;
        border: 1px solid var(--gray-300);
        color: var(--gray-700);
    }
    
    .rg-btn-secondary:hover {
        border-color: var(--gray-400);
        background: var(--gray-50);
    }
    
    @media (max-width: 768px) {
        .rg-form-row {
            grid-template-columns: 1fr;
        }
        
        .rg-form-row.two-col {
            grid-template-columns: 1fr;
        }
        
        .rg-room-counts {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>

<div class="rg-form-container">
    <!-- Breadcrumb -->
    <nav class="rg-breadcrumb">
        <a href="<?php echo home_url('/rental-gates/dashboard/buildings'); ?>"><?php _e('Buildings', 'rental-gates'); ?></a>
        <span class="rg-breadcrumb-separator">/</span>
        <a href="<?php echo home_url('/rental-gates/dashboard/buildings/' . $building['id']); ?>"><?php echo esc_html($building['name']); ?></a>
        <span class="rg-breadcrumb-separator">/</span>
        <span class="rg-breadcrumb-current"><?php echo $is_edit ? __('Edit Unit', 'rental-gates') : __('Add Unit', 'rental-gates'); ?></span>
    </nav>
    
    <div class="rg-form-header">
        <h1 class="rg-form-title">
            <?php echo $is_edit ? __('Edit Unit', 'rental-gates') : __('Add Unit', 'rental-gates'); ?>
        </h1>
        <p class="rg-form-subtitle">
            <?php printf(__('Adding unit to %s', 'rental-gates'), esc_html($building['name'])); ?>
        </p>
    </div>
    
    <form id="unit-form" method="post">
        <input type="hidden" name="action" value="rental_gates_save_unit">
        <input type="hidden" name="unit_id" value="<?php echo esc_attr($unit_id); ?>">
        <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
        <input type="hidden" name="organization_id" value="<?php echo esc_attr($org_id); ?>">
        <?php wp_nonce_field('rental_gates_unit', 'unit_nonce'); ?>
        
        <div class="rg-form-card">
            <!-- Basic Info -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Basic Information', 'rental-gates'); ?></h2>
                <p class="rg-section-description"><?php _e('Unit identifier and type.', 'rental-gates'); ?></p>
                
                <div class="rg-form-row two-col">
                    <div class="rg-form-group">
                        <label class="rg-form-label" for="unit-name">
                            <?php _e('Unit Name/Number', 'rental-gates'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="unit-name" 
                            name="name" 
                            class="rg-form-input" 
                            value="<?php echo esc_attr($unit ? $unit['name'] : ''); ?>"
                            required
                            placeholder="<?php _e('e.g., 101, Suite A, Unit 2B', 'rental-gates'); ?>"
                        >
                    </div>
                    
                    <div class="rg-form-group">
                        <label class="rg-form-label" for="unit-type">
                            <?php _e('Unit Type', 'rental-gates'); ?>
                        </label>
                        <select id="unit-type" name="unit_type" class="rg-form-select">
                            <option value=""><?php _e('Select type...', 'rental-gates'); ?></option>
                            <option value="apartment" <?php selected($unit ? $unit['unit_type'] : '', 'apartment'); ?>><?php _e('Apartment', 'rental-gates'); ?></option>
                            <option value="studio" <?php selected($unit ? $unit['unit_type'] : '', 'studio'); ?>><?php _e('Studio', 'rental-gates'); ?></option>
                            <option value="house" <?php selected($unit ? $unit['unit_type'] : '', 'house'); ?>><?php _e('House', 'rental-gates'); ?></option>
                            <option value="townhouse" <?php selected($unit ? $unit['unit_type'] : '', 'townhouse'); ?>><?php _e('Townhouse', 'rental-gates'); ?></option>
                            <option value="condo" <?php selected($unit ? $unit['unit_type'] : '', 'condo'); ?>><?php _e('Condo', 'rental-gates'); ?></option>
                            <option value="suite" <?php selected($unit ? $unit['unit_type'] : '', 'suite'); ?>><?php _e('Suite', 'rental-gates'); ?></option>
                            <option value="room" <?php selected($unit ? $unit['unit_type'] : '', 'room'); ?>><?php _e('Room', 'rental-gates'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="rg-form-row two-col">
                    <div class="rg-form-group">
                        <label class="rg-form-label" for="rent-amount">
                            <?php _e('Monthly Rent', 'rental-gates'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="rent-amount" 
                            name="rent_amount" 
                            class="rg-form-input" 
                            value="<?php echo esc_attr($unit ? $unit['rent_amount'] : ''); ?>"
                            required
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        >
                    </div>
                    
                    <div class="rg-form-group">
                        <label class="rg-form-label" for="deposit-amount">
                            <?php _e('Security Deposit', 'rental-gates'); ?>
                        </label>
                        <input 
                            type="number" 
                            id="deposit-amount" 
                            name="deposit_amount" 
                            class="rg-form-input" 
                            value="<?php echo esc_attr($unit ? $unit['deposit_amount'] : ''); ?>"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        >
                    </div>
                </div>
                
                <div class="rg-form-row full">
                    <div class="rg-form-group">
                        <label class="rg-form-label" for="square-footage">
                            <?php _e('Square Footage', 'rental-gates'); ?>
                        </label>
                        <input 
                            type="number" 
                            id="square-footage" 
                            name="square_footage" 
                            class="rg-form-input" 
                            value="<?php echo esc_attr($unit ? $unit['square_footage'] : ''); ?>"
                            min="0"
                            placeholder="0"
                            style="max-width: 200px;"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Room Counts -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Room Counts', 'rental-gates'); ?></h2>
                <p class="rg-section-description"><?php _e('Number of rooms and spaces.', 'rental-gates'); ?></p>
                
                <div class="rg-room-counts">
                    <div class="rg-room-count">
                        <div class="rg-room-count-label"><?php _e('Bedrooms', 'rental-gates'); ?></div>
                        <div class="rg-room-count-input">
                            <button type="button" class="rg-room-btn" onclick="adjustCount('bedrooms', -1)">−</button>
                            <span class="rg-room-value" id="bedrooms-value"><?php echo intval($unit ? $unit['bedrooms'] : 0); ?></span>
                            <button type="button" class="rg-room-btn" onclick="adjustCount('bedrooms', 1)">+</button>
                        </div>
                        <input type="hidden" name="bedrooms" id="bedrooms" value="<?php echo intval($unit ? $unit['bedrooms'] : 0); ?>">
                    </div>
                    
                    <div class="rg-room-count">
                        <div class="rg-room-count-label"><?php _e('Bathrooms', 'rental-gates'); ?></div>
                        <div class="rg-room-count-input">
                            <button type="button" class="rg-room-btn" onclick="adjustCount('bathrooms', -1)">−</button>
                            <span class="rg-room-value" id="bathrooms-value"><?php echo intval($unit ? $unit['bathrooms'] : 0); ?></span>
                            <button type="button" class="rg-room-btn" onclick="adjustCount('bathrooms', 1)">+</button>
                        </div>
                        <input type="hidden" name="bathrooms" id="bathrooms" value="<?php echo intval($unit ? $unit['bathrooms'] : 0); ?>">
                    </div>
                    
                    <div class="rg-room-count">
                        <div class="rg-room-count-label"><?php _e('Living Rooms', 'rental-gates'); ?></div>
                        <div class="rg-room-count-input">
                            <button type="button" class="rg-room-btn" onclick="adjustCount('living_rooms', -1)">−</button>
                            <span class="rg-room-value" id="living_rooms-value"><?php echo intval($unit ? $unit['living_rooms'] : 0); ?></span>
                            <button type="button" class="rg-room-btn" onclick="adjustCount('living_rooms', 1)">+</button>
                        </div>
                        <input type="hidden" name="living_rooms" id="living_rooms" value="<?php echo intval($unit ? $unit['living_rooms'] : 0); ?>">
                    </div>
                    
                    <div class="rg-room-count">
                        <div class="rg-room-count-label"><?php _e('Kitchens', 'rental-gates'); ?></div>
                        <div class="rg-room-count-input">
                            <button type="button" class="rg-room-btn" onclick="adjustCount('kitchens', -1)">−</button>
                            <span class="rg-room-value" id="kitchens-value"><?php echo intval($unit ? $unit['kitchens'] : 0); ?></span>
                            <button type="button" class="rg-room-btn" onclick="adjustCount('kitchens', 1)">+</button>
                        </div>
                        <input type="hidden" name="kitchens" id="kitchens" value="<?php echo intval($unit ? $unit['kitchens'] : 0); ?>">
                    </div>
                    
                    <div class="rg-room-count">
                        <div class="rg-room-count-label"><?php _e('Parking', 'rental-gates'); ?></div>
                        <div class="rg-room-count-input">
                            <button type="button" class="rg-room-btn" onclick="adjustCount('parking_spots', -1)">−</button>
                            <span class="rg-room-value" id="parking_spots-value"><?php echo intval($unit ? $unit['parking_spots'] : 0); ?></span>
                            <button type="button" class="rg-room-btn" onclick="adjustCount('parking_spots', 1)">+</button>
                        </div>
                        <input type="hidden" name="parking_spots" id="parking_spots" value="<?php echo intval($unit ? $unit['parking_spots'] : 0); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Availability -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Availability Status', 'rental-gates'); ?></h2>
                <p class="rg-section-description"><?php _e('Set the current availability status of this unit.', 'rental-gates'); ?></p>
                
                <div class="rg-availability-options">
                    <label class="rg-availability-option <?php echo (!$unit || $unit['availability'] === 'available') ? 'selected' : ''; ?>" onclick="selectAvailability(this, 'available')">
                        <input type="radio" name="availability" value="available" <?php checked(!$unit || $unit['availability'] === 'available'); ?>>
                        <div class="rg-availability-name">
                            <span class="rg-availability-dot available"></span>
                            <?php _e('Available', 'rental-gates'); ?>
                        </div>
                        <div class="rg-availability-desc"><?php _e('Ready now, accepting applications', 'rental-gates'); ?></div>
                    </label>
                    
                    <label class="rg-availability-option <?php echo ($unit && $unit['availability'] === 'coming_soon') ? 'selected' : ''; ?>" onclick="selectAvailability(this, 'coming_soon')">
                        <input type="radio" name="availability" value="coming_soon" <?php checked($unit && $unit['availability'] === 'coming_soon'); ?>>
                        <div class="rg-availability-name">
                            <span class="rg-availability-dot coming_soon"></span>
                            <?php _e('Coming Soon', 'rental-gates'); ?>
                        </div>
                        <div class="rg-availability-desc"><?php _e('Available on a future date', 'rental-gates'); ?></div>
                    </label>
                    
                    <label class="rg-availability-option <?php echo ($unit && $unit['availability'] === 'occupied') ? 'selected' : ''; ?>" onclick="selectAvailability(this, 'occupied')">
                        <input type="radio" name="availability" value="occupied" <?php checked($unit && $unit['availability'] === 'occupied'); ?>>
                        <div class="rg-availability-name">
                            <span class="rg-availability-dot occupied"></span>
                            <?php _e('Occupied', 'rental-gates'); ?>
                        </div>
                        <div class="rg-availability-desc"><?php _e('Currently has an active lease', 'rental-gates'); ?></div>
                    </label>
                    
                    <label class="rg-availability-option <?php echo ($unit && $unit['availability'] === 'unlisted') ? 'selected' : ''; ?>" onclick="selectAvailability(this, 'unlisted')">
                        <input type="radio" name="availability" value="unlisted" <?php checked($unit && $unit['availability'] === 'unlisted'); ?>>
                        <div class="rg-availability-name">
                            <span class="rg-availability-dot unlisted"></span>
                            <?php _e('Unlisted', 'rental-gates'); ?>
                        </div>
                        <div class="rg-availability-desc"><?php _e('Hidden from public listings', 'rental-gates'); ?></div>
                    </label>
                </div>
                
                <div class="rg-date-field <?php echo ($unit && $unit['availability'] === 'coming_soon') ? 'visible' : ''; ?>" id="available-from-field">
                    <div class="rg-form-group" style="max-width: 250px;">
                        <label class="rg-form-label" for="available-from">
                            <?php _e('Available From', 'rental-gates'); ?>
                        </label>
                        <input 
                            type="date" 
                            id="available-from" 
                            name="available_from" 
                            class="rg-form-input" 
                            value="<?php echo esc_attr($unit && !empty($unit['available_from']) ? date('Y-m-d', strtotime($unit['available_from'])) : ''); ?>"
                        >
                    </div>
                </div>
            </div>
            
            <!-- Description -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Description', 'rental-gates'); ?></h2>
                <p class="rg-section-description"><?php _e('Describe this unit for prospective renters.', 'rental-gates'); ?></p>
                
                <div class="rg-form-row full">
                    <div class="rg-form-group">
                        <textarea 
                            id="unit-description" 
                            name="description" 
                            class="rg-form-textarea"
                            placeholder="<?php _e('Describe the unit features, views, recent upgrades, etc...', 'rental-gates'); ?>"
                        ><?php echo esc_textarea($unit ? $unit['description'] : ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Gallery -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Photos', 'rental-gates'); ?></h2>
                <p class="rg-section-description"><?php _e('Add photos of the unit interior and features.', 'rental-gates'); ?></p>
                
                <div class="rg-gallery-grid" id="gallery-grid">
                    <?php 
                    // Normalize gallery to URLs only
                    $normalized_gallery = array();
                    foreach ($gallery as $img):
                        $image_url = is_array($img) ? ($img['url'] ?? $img['thumbnail'] ?? '') : $img;
                        if (empty($image_url)) continue;
                        $normalized_gallery[] = $image_url;
                    ?>
                    <div class="rg-gallery-item" data-index="<?php echo count($normalized_gallery) - 1; ?>">
                        <img src="<?php echo esc_url($image_url); ?>" alt="">
                        <button type="button" class="rg-gallery-item-remove" onclick="removeGalleryImage(<?php echo count($normalized_gallery) - 1; ?>)">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    
                    <button type="button" class="rg-gallery-add" id="add-gallery-images">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span style="font-size: 12px; margin-top: 4px;"><?php _e('Add Photos', 'rental-gates'); ?></span>
                    </button>
                </div>
                <input type="hidden" name="gallery" id="gallery-data" value="<?php echo esc_attr(wp_json_encode($normalized_gallery)); ?>">
            </div>
            
            <!-- Amenities -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Amenities', 'rental-gates'); ?></h2>
                <p class="rg-section-description"><?php _e('Select the amenities included with this unit.', 'rental-gates'); ?></p>
                
                <div class="rg-amenities-grid" id="amenities-grid">
                    <?php 
                    $all_amenities = array(
                        'air_conditioning' => __('Air Conditioning', 'rental-gates'),
                        'heating' => __('Heating', 'rental-gates'),
                        'washer_dryer' => __('Washer/Dryer', 'rental-gates'),
                        'dishwasher' => __('Dishwasher', 'rental-gates'),
                        'balcony' => __('Balcony', 'rental-gates'),
                        'patio' => __('Patio', 'rental-gates'),
                        'hardwood_floors' => __('Hardwood Floors', 'rental-gates'),
                        'carpet' => __('Carpet', 'rental-gates'),
                        'walk_in_closet' => __('Walk-in Closet', 'rental-gates'),
                        'storage' => __('Storage', 'rental-gates'),
                        'fireplace' => __('Fireplace', 'rental-gates'),
                        'furnished' => __('Furnished', 'rental-gates'),
                        'pet_friendly' => __('Pet Friendly', 'rental-gates'),
                        'pool_access' => __('Pool Access', 'rental-gates'),
                        'gym_access' => __('Gym Access', 'rental-gates'),
                        'doorman' => __('Doorman', 'rental-gates'),
                        'elevator' => __('Elevator', 'rental-gates'),
                        'wheelchair_accessible' => __('Wheelchair Accessible', 'rental-gates'),
                    );
                    
                    foreach ($all_amenities as $key => $label):
                        $selected = in_array($key, $amenities);
                    ?>
                    <label class="rg-amenity-item <?php echo $selected ? 'selected' : ''; ?>">
                        <input type="checkbox" name="amenities[]" value="<?php echo esc_attr($key); ?>" <?php checked($selected); ?>>
                        <span class="rg-amenity-check">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <span class="rg-amenity-label"><?php echo esc_html($label); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="rg-form-actions">
                <a href="<?php echo home_url('/rental-gates/dashboard/buildings/' . $building_id); ?>" class="rg-btn rg-btn-secondary">
                    <?php _e('Cancel', 'rental-gates'); ?>
                </a>
                <button type="submit" class="rg-btn rg-btn-primary" id="submit-btn">
                    <?php echo $is_edit ? __('Update Unit', 'rental-gates') : __('Create Unit', 'rental-gates'); ?>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Room count adjustment
function adjustCount(field, delta) {
    const input = document.getElementById(field);
    const display = document.getElementById(field + '-value');
    let value = parseInt(input.value) + delta;
    if (value < 0) value = 0;
    input.value = value;
    display.textContent = value;
}

// Availability selection
function selectAvailability(element, value) {
    document.querySelectorAll('.rg-availability-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    element.classList.add('selected');
    
    const dateField = document.getElementById('available-from-field');
    if (value === 'coming_soon') {
        dateField.classList.add('visible');
    } else {
        dateField.classList.remove('visible');
    }
}

// Amenity toggle - use event listener to prevent label's natural toggle
document.querySelectorAll('.rg-amenity-item').forEach(function(item) {
    item.addEventListener('click', function(e) {
        e.preventDefault();
        this.classList.toggle('selected');
        const checkbox = this.querySelector('input[type="checkbox"]');
        checkbox.checked = this.classList.contains('selected');
    });
});

// Gallery handling - uses WordPress media library
let galleryData = <?php echo wp_json_encode($normalized_gallery); ?>;

// Initialize Media Library gallery button
document.getElementById('add-gallery-images').addEventListener('click', function() {
    RentalGates.selectImages(function(attachments) {
        attachments.forEach(function(att) {
            const imageUrl = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
            galleryData.push(imageUrl);
        });
        updateGalleryDisplay();
        RentalGates.showToast('<?php _e('Images added successfully', 'rental-gates'); ?>', 'success');
    });
});

function removeGalleryImage(index) {
    galleryData.splice(index, 1);
    updateGalleryDisplay();
}

function updateGalleryDisplay() {
    const grid = document.getElementById('gallery-grid');
    const addButton = document.getElementById('add-gallery-images');
    
    grid.querySelectorAll('.rg-gallery-item').forEach(item => item.remove());
    
    galleryData.forEach((url, index) => {
        const item = document.createElement('div');
        item.className = 'rg-gallery-item';
        item.setAttribute('data-index', index);
        item.innerHTML = `
            <img src="${url}" alt="">
            <button type="button" class="rg-gallery-item-remove" onclick="removeGalleryImage(${index})">
                <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        `;
        grid.insertBefore(item, addButton);
    });
    
    document.getElementById('gallery-data').value = JSON.stringify(galleryData);
}

// Form submission
document.getElementById('unit-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('unit-name').value;
    const rentAmount = document.getElementById('rent-amount').value;
    
    if (!name.trim()) {
        alert('<?php _e('Please enter a unit name/number.', 'rental-gates'); ?>');
        return;
    }
    
    if (!rentAmount || parseFloat(rentAmount) < 0) {
        alert('<?php _e('Please enter a valid rent amount.', 'rental-gates'); ?>');
        return;
    }
    
    const formData = new FormData(this);
    const submitBtn = document.getElementById('submit-btn');
    submitBtn.disabled = true;
    submitBtn.textContent = '<?php _e('Saving...', 'rental-gates'); ?>';
    
    fetch(rentalGatesData.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?php echo home_url('/rental-gates/dashboard/buildings/' . $building_id); ?>';
        } else {
            alert(data.data || '<?php _e('Error saving unit', 'rental-gates'); ?>');
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo $is_edit ? __('Update Unit', 'rental-gates') : __('Create Unit', 'rental-gates'); ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php _e('Error saving unit', 'rental-gates'); ?>');
        submitBtn.disabled = false;
        submitBtn.textContent = '<?php echo $is_edit ? __('Update Unit', 'rental-gates') : __('Create Unit', 'rental-gates'); ?>';
    });
});
</script>
