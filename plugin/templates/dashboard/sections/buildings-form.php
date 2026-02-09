<?php
/**
 * Dashboard Section: Add/Edit Building
 * 
 * Map-based building creation per Rule 3: No Manual Address Entry.
 * Location is set via map pin, address is derived via reverse geocoding.
 */
if (!defined('ABSPATH')) exit;

// Get organization ID
$org_id = null;
if (class_exists('Rental_Gates_Roles')) {
    $org_id = Rental_Gates_Roles::get_organization_id();
}

// Check if editing
$building_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$building = null;
$is_edit = false;

if ($building_id && class_exists('Rental_Gates_Building')) {
    $building = Rental_Gates_Building::get($building_id);
    if ($building && $building['organization_id'] == $org_id) {
        $is_edit = true;
    } else {
        $building = null;
    }
}

// Map provider settings
$map_provider = get_option('rental_gates_map_provider', 'openstreetmap');
$google_api_key = get_option('rental_gates_google_maps_api_key', '');

// Default coordinates (can be set from organization settings)
$default_lat = $building ? $building['latitude'] : 40.7128;
$default_lng = $building ? $building['longitude'] : -74.0060;
$default_zoom = $building ? 16 : 12;
?>

<style>
    .rg-form-container {
        max-width: 900px;
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
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
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
    
    .rg-form-input.readonly {
        background: var(--gray-50);
        color: var(--gray-600);
        cursor: not-allowed;
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
    
    /* Map Picker */
    .rg-map-container {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--gray-300);
    }
    
    .rg-map-search {
        position: absolute;
        top: 12px;
        left: 12px;
        right: 12px;
        z-index: 1000;
        display: flex;
        gap: 8px;
    }
    
    .rg-map-search-input {
        flex: 1;
        padding: 12px 16px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    
    .rg-map-search-input:focus {
        outline: none;
        box-shadow: 0 2px 12px rgba(37, 99, 235, 0.3);
    }
    
    .rg-map-search-btn {
        padding: 12px 20px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    
    .rg-map-search-btn:hover {
        background: var(--primary-dark);
    }
    
    #building-map {
        height: 400px;
        width: 100%;
    }
    
    .rg-map-instructions {
        position: absolute;
        bottom: 12px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0,0,0,0.75);
        color: #fff;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        z-index: 1000;
        pointer-events: none;
    }
    
    .rg-derived-address {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
        background: var(--gray-50);
        border-radius: 8px;
        margin-top: 16px;
    }
    
    .rg-derived-address-icon {
        width: 40px;
        height: 40px;
        background: var(--success);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex-shrink: 0;
    }
    
    .rg-derived-address-icon.pending {
        background: var(--gray-300);
    }
    
    .rg-derived-address-text {
        flex: 1;
    }
    
    .rg-derived-address-label {
        font-size: 12px;
        color: var(--gray-500);
        margin-bottom: 2px;
    }
    
    .rg-derived-address-value {
        font-size: 14px;
        color: var(--gray-900);
        font-weight: 500;
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
    
    /* Loading */
    .rg-loading {
        display: none;
        align-items: center;
        gap: 8px;
    }
    
    .rg-loading.active {
        display: flex;
    }
    
    .rg-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid var(--gray-200);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
        .rg-form-row {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .rg-form-container {
            max-width: 100%;
        }
        
        .rg-form-section {
            padding: 20px;
        }
        
        .rg-form-title {
            font-size: 20px;
        }
        
        #building-map {
            height: 300px;
        }
        
        .rg-map-search {
            flex-direction: column;
            gap: 8px;
        }
        
        .rg-map-search-input {
            width: 100%;
        }
        
        .rg-map-search-btn {
            width: 100%;
            min-height: 44px;
        }
        
        .rg-form-group input,
        .rg-form-group select,
        .rg-form-group textarea {
            min-height: 44px;
            font-size: 16px;
        }
        
        .rg-btn {
            min-height: 48px;
            font-size: 16px;
        }
    }
    
    @media (max-width: 375px) {
        .rg-form-section {
            padding: 16px;
        }
        
        .rg-form-header {
            margin-bottom: 16px;
        }
        
        #building-map {
            height: 250px;
        }
    }
</style>

<div class="rg-form-container">
    <div class="rg-form-header">
        <h1 class="rg-form-title">
            <?php echo $is_edit ? __('Edit Building', 'rental-gates') : __('Add Building', 'rental-gates'); ?>
        </h1>
        <p class="rg-form-subtitle">
            <?php _e('Place a pin on the map to set the building location. The address will be automatically derived.', 'rental-gates'); ?>
        </p>
    </div>
    
    <form id="building-form" method="post">
        <input type="hidden" name="action" value="rental_gates_save_building">
        <input type="hidden" name="building_id" value="<?php echo esc_attr($building_id); ?>">
        <input type="hidden" name="organization_id" value="<?php echo esc_attr($org_id); ?>">
        <?php wp_nonce_field('rental_gates_building', 'building_nonce'); ?>
        
        <!-- Hidden fields for map data -->
        <input type="hidden" name="latitude" id="building-lat" value="<?php echo esc_attr($building ? $building['latitude'] : ''); ?>">
        <input type="hidden" name="longitude" id="building-lng" value="<?php echo esc_attr($building ? $building['longitude'] : ''); ?>">
        <input type="hidden" name="derived_address" id="building-address" value="<?php echo esc_attr($building ? $building['derived_address'] : ''); ?>">
        
        <div class="rg-form-card">
            <!-- Location Section -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Location', 'rental-gates'); ?> <span class="required">*</span></h2>
                <p class="rg-section-description">
                    <?php _e('Search for an address or click on the map to place the building pin. Drag the pin to adjust.', 'rental-gates'); ?>
                </p>
                
                <div class="rg-map-container">
                    <div class="rg-map-search">
                        <input type="text" id="map-search" class="rg-map-search-input" placeholder="<?php _e('Search for an address...', 'rental-gates'); ?>">
                        <button type="button" id="map-search-btn" class="rg-map-search-btn"><?php _e('Search', 'rental-gates'); ?></button>
                    </div>
                    <div id="building-map"></div>
                    <div class="rg-map-instructions" id="map-instructions">
                        <?php _e('Click on the map to place a pin', 'rental-gates'); ?>
                    </div>
                </div>
                
                <div class="rg-derived-address" id="derived-address-box">
                    <div class="rg-derived-address-icon <?php echo empty($building['derived_address']) ? 'pending' : ''; ?>" id="address-icon">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="rg-derived-address-text">
                        <div class="rg-derived-address-label"><?php _e('Derived Address (read-only)', 'rental-gates'); ?></div>
                        <div class="rg-derived-address-value" id="address-display">
                            <?php echo $building ? esc_html($building['derived_address']) : __('Place a pin to derive address', 'rental-gates'); ?>
                        </div>
                    </div>
                    <div class="rg-loading" id="geocoding-loading">
                        <div class="rg-spinner"></div>
                        <span><?php _e('Getting address...', 'rental-gates'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Basic Info Section -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Building Details', 'rental-gates'); ?></h2>
                <p class="rg-section-description">
                    <?php _e('Basic information about this building.', 'rental-gates'); ?>
                </p>
                
                <div class="rg-form-row">
                    <div class="rg-form-group">
                        <label class="rg-form-label" for="building-name">
                            <?php _e('Building Name', 'rental-gates'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="building-name" 
                            name="name" 
                            class="rg-form-input" 
                            value="<?php echo esc_attr($building ? $building['name'] : ''); ?>"
                            required
                            placeholder="<?php _e('e.g., Sunset Apartments', 'rental-gates'); ?>"
                        >
                    </div>
                    
                    <div class="rg-form-group">
                        <label class="rg-form-label" for="building-status">
                            <?php _e('Status', 'rental-gates'); ?> <span class="required">*</span>
                        </label>
                        <select id="building-status" name="status" class="rg-form-select" required>
                            <option value="active" <?php selected($building ? $building['status'] : 'active', 'active'); ?>>
                                <?php _e('Active', 'rental-gates'); ?>
                            </option>
                            <option value="inactive" <?php selected($building ? $building['status'] : '', 'inactive'); ?>>
                                <?php _e('Inactive', 'rental-gates'); ?>
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="rg-form-row full">
                    <div class="rg-form-group">
                        <label class="rg-form-label" for="building-description">
                            <?php _e('Description', 'rental-gates'); ?>
                        </label>
                        <textarea 
                            id="building-description" 
                            name="description" 
                            class="rg-form-textarea"
                            placeholder="<?php _e('Describe this building for prospective renters...', 'rental-gates'); ?>"
                        ><?php echo esc_textarea($building ? $building['description'] : ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Amenities Section -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Building Amenities', 'rental-gates'); ?></h2>
                <p class="rg-section-description">
                    <?php _e('Select the amenities available in this building.', 'rental-gates'); ?>
                </p>
                
                <?php 
                $building_amenities_json = get_option('rental_gates_building_amenities', '[]');
                $available_amenities = json_decode($building_amenities_json, true);
                if (!is_array($available_amenities)) $available_amenities = array();
                
                // Building amenities are already decoded by format_building()
                $selected_amenities = $building && !empty($building['amenities']) ? $building['amenities'] : array();
                if (!is_array($selected_amenities)) $selected_amenities = array();
                ?>
                
                <div class="rg-amenities-grid">
                    <?php foreach ($available_amenities as $amenity): ?>
                    <label class="rg-amenity-item <?php echo in_array($amenity, $selected_amenities) ? 'selected' : ''; ?>">
                        <input type="checkbox" name="amenities[]" value="<?php echo esc_attr($amenity); ?>" <?php checked(in_array($amenity, $selected_amenities)); ?>>
                        <span class="rg-amenity-check">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        <span class="rg-amenity-label"><?php echo esc_html($amenity); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Gallery Section -->
            <div class="rg-form-section">
                <h2 class="rg-section-title"><?php _e('Photos', 'rental-gates'); ?></h2>
                <p class="rg-section-description">
                    <?php _e('Add photos of the building exterior, common areas, and amenities.', 'rental-gates'); ?>
                </p>
                
                <div class="rg-gallery-grid" id="gallery-grid">
                    <?php 
                    // Gallery is already decoded by format_building()
                    $gallery = $building && !empty($building['gallery']) ? $building['gallery'] : array();
                    if (!is_array($gallery)) $gallery = array();
                    
                    // Normalize gallery to URLs only for display and form data
                    $normalized_gallery = array();
                    foreach ($gallery as $index => $img): 
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
            
            <!-- Form Actions -->
            <div class="rg-form-actions">
                <a href="<?php echo home_url('/rental-gates/dashboard/buildings'); ?>" class="rg-btn rg-btn-secondary">
                    <?php _e('Cancel', 'rental-gates'); ?>
                </a>
                <button type="submit" class="rg-btn rg-btn-primary" id="submit-btn">
                    <?php echo $is_edit ? __('Update Building', 'rental-gates') : __('Create Building', 'rental-gates'); ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?php if ($map_provider === 'google' && $google_api_key): ?>
<!-- Google Maps -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr($google_api_key); ?>&libraries=places"></script>
<script>
(function() {
    let map, marker, geocoder;
    const defaultLat = <?php echo floatval($default_lat); ?>;
    const defaultLng = <?php echo floatval($default_lng); ?>;
    const defaultZoom = <?php echo intval($default_zoom); ?>;
    
    function initMap() {
        const center = { lat: defaultLat, lng: defaultLng };
        
        map = new google.maps.Map(document.getElementById('building-map'), {
            center: center,
            zoom: defaultZoom,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false
        });
        
        geocoder = new google.maps.Geocoder();
        
        // Initialize marker if editing
        const existingLat = document.getElementById('building-lat').value;
        const existingLng = document.getElementById('building-lng').value;
        
        if (existingLat && existingLng) {
            const position = { lat: parseFloat(existingLat), lng: parseFloat(existingLng) };
            placeMarker(position, false);
            map.setCenter(position);
        }
        
        // Click to place marker
        map.addListener('click', function(e) {
            placeMarker(e.latLng, true);
        });
        
        // Search functionality
        const searchInput = document.getElementById('map-search');
        const searchBtn = document.getElementById('map-search-btn');
        
        const autocomplete = new google.maps.places.Autocomplete(searchInput);
        autocomplete.bindTo('bounds', map);
        
        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            if (place.geometry) {
                map.setCenter(place.geometry.location);
                map.setZoom(17);
                placeMarker(place.geometry.location, true);
            }
        });
        
        searchBtn.addEventListener('click', function() {
            searchAddress(searchInput.value);
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchAddress(this.value);
            }
        });
    }
    
    function placeMarker(position, doGeocode) {
        if (marker) {
            marker.setMap(null);
        }
        
        marker = new google.maps.Marker({
            position: position,
            map: map,
            draggable: true,
            animation: google.maps.Animation.DROP
        });
        
        // Update hidden fields
        document.getElementById('building-lat').value = position.lat();
        document.getElementById('building-lng').value = position.lng();
        
        // Hide instructions
        document.getElementById('map-instructions').style.display = 'none';
        
        // Reverse geocode
        if (doGeocode) {
            reverseGeocode(position);
        }
        
        // Handle drag
        marker.addListener('dragend', function() {
            const newPos = marker.getPosition();
            document.getElementById('building-lat').value = newPos.lat();
            document.getElementById('building-lng').value = newPos.lng();
            reverseGeocode(newPos);
        });
    }
    
    function reverseGeocode(position) {
        const loading = document.getElementById('geocoding-loading');
        loading.classList.add('active');
        
        geocoder.geocode({ location: position }, function(results, status) {
            loading.classList.remove('active');
            
            if (status === 'OK' && results[0]) {
                const address = results[0].formatted_address;
                document.getElementById('building-address').value = address;
                document.getElementById('address-display').textContent = address;
                document.getElementById('address-icon').classList.remove('pending');
            } else {
                document.getElementById('address-display').textContent = '<?php _e('Could not determine address', 'rental-gates'); ?>';
            }
        });
    }
    
    function searchAddress(query) {
        if (!query) return;
        
        geocoder.geocode({ address: query }, function(results, status) {
            if (status === 'OK' && results[0]) {
                const location = results[0].geometry.location;
                map.setCenter(location);
                map.setZoom(17);
                placeMarker(location, true);
            }
        });
    }
    
    document.addEventListener('DOMContentLoaded', initMap);
})();
</script>

<?php else: ?>
<!-- OpenStreetMap (Leaflet) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function() {
    let map, marker;
    const defaultLat = <?php echo floatval($default_lat); ?>;
    const defaultLng = <?php echo floatval($default_lng); ?>;
    const defaultZoom = <?php echo intval($default_zoom); ?>;
    
    function initMap() {
        map = L.map('building-map').setView([defaultLat, defaultLng], defaultZoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Initialize marker if editing
        const existingLat = document.getElementById('building-lat').value;
        const existingLng = document.getElementById('building-lng').value;
        
        if (existingLat && existingLng) {
            const position = [parseFloat(existingLat), parseFloat(existingLng)];
            placeMarker(position, false);
            map.setView(position, 16);
        }
        
        // Click to place marker
        map.on('click', function(e) {
            placeMarker([e.latlng.lat, e.latlng.lng], true);
        });
        
        // Search functionality
        const searchInput = document.getElementById('map-search');
        const searchBtn = document.getElementById('map-search-btn');
        
        searchBtn.addEventListener('click', function() {
            searchAddress(searchInput.value);
        });
        
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchAddress(this.value);
            }
        });
    }
    
    function placeMarker(position, doGeocode) {
        if (marker) {
            map.removeLayer(marker);
        }
        
        marker = L.marker(position, { draggable: true }).addTo(map);
        
        // Update hidden fields
        document.getElementById('building-lat').value = position[0];
        document.getElementById('building-lng').value = position[1];
        
        // Hide instructions
        document.getElementById('map-instructions').style.display = 'none';
        
        // Reverse geocode
        if (doGeocode) {
            reverseGeocode(position);
        }
        
        // Handle drag
        marker.on('dragend', function() {
            const newPos = marker.getLatLng();
            document.getElementById('building-lat').value = newPos.lat;
            document.getElementById('building-lng').value = newPos.lng;
            reverseGeocode([newPos.lat, newPos.lng]);
        });
    }
    
    function reverseGeocode(position) {
        const loading = document.getElementById('geocoding-loading');
        loading.classList.add('active');
        
        // Use Nominatim for reverse geocoding
        fetch(`https://nominatim.openstreetmap.org/reverse?lat=${position[0]}&lon=${position[1]}&format=json`)
            .then(response => response.json())
            .then(data => {
                loading.classList.remove('active');
                
                if (data && data.display_name) {
                    const address = data.display_name;
                    document.getElementById('building-address').value = address;
                    document.getElementById('address-display').textContent = address;
                    document.getElementById('address-icon').classList.remove('pending');
                } else {
                    document.getElementById('address-display').textContent = '<?php _e('Could not determine address', 'rental-gates'); ?>';
                }
            })
            .catch(error => {
                loading.classList.remove('active');
                console.error('Geocoding error:', error);
            });
    }
    
    function searchAddress(query) {
        if (!query) return;
        
        // Use Nominatim for forward geocoding
        fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1`)
            .then(response => response.json())
            .then(data => {
                if (data && data.length > 0) {
                    const result = data[0];
                    const position = [parseFloat(result.lat), parseFloat(result.lon)];
                    map.setView(position, 17);
                    placeMarker(position, true);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    }
    
    document.addEventListener('DOMContentLoaded', initMap);
})();
</script>
<?php endif; ?>

<script>
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
let galleryData = <?php echo Rental_Gates_Security::json_for_script($normalized_gallery); ?>;

// Initialize Media Library gallery button
document.getElementById('add-gallery-images').addEventListener('click', function() {
    RentalGates.selectImages(function(attachments) {
        attachments.forEach(function(att) {
            // Store either the medium size or full URL
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
    
    // Remove existing items
    grid.querySelectorAll('.rg-gallery-item').forEach(item => item.remove());
    
    // Add items
    galleryData.forEach((url, index) => {
        const item = document.createElement('div');
        item.className = 'rg-gallery-item';
        item.setAttribute('data-index', index);
        var img = document.createElement('img');
        img.setAttribute('src', url);
        img.setAttribute('alt', '');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rg-gallery-item-remove';
        btn.setAttribute('onclick', 'removeGalleryImage(' + parseInt(index, 10) + ')');
        btn.innerHTML = '<svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
        item.appendChild(img);
        item.appendChild(btn);
        grid.insertBefore(item, addButton);
    });
    
    // Update hidden field
    document.getElementById('gallery-data').value = JSON.stringify(galleryData);
}

// Form submission
document.getElementById('building-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const lat = document.getElementById('building-lat').value;
    const lng = document.getElementById('building-lng').value;
    const address = document.getElementById('building-address').value;
    const name = document.getElementById('building-name').value;
    
    // Validation
    if (!lat || !lng) {
        alert('<?php _e('Please place a pin on the map to set the building location.', 'rental-gates'); ?>');
        return;
    }
    
    if (!address) {
        alert('<?php _e('Please wait for the address to be derived from the map.', 'rental-gates'); ?>');
        return;
    }
    
    if (!name.trim()) {
        alert('<?php _e('Please enter a building name.', 'rental-gates'); ?>');
        return;
    }
    
    // Submit via AJAX
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
            window.location.href = '<?php echo home_url('/rental-gates/dashboard/buildings'); ?>/' + data.data.id;
        } else {
            alert(data.data || '<?php _e('Error saving building', 'rental-gates'); ?>');
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo $is_edit ? __('Update Building', 'rental-gates') : __('Create Building', 'rental-gates'); ?>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('<?php _e('Error saving building', 'rental-gates'); ?>');
        submitBtn.disabled = false;
        submitBtn.textContent = '<?php echo $is_edit ? __('Update Building', 'rental-gates') : __('Create Building', 'rental-gates'); ?>';
    });
});
</script>
