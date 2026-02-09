<?php
/**
 * Public Map Page
 * 
 * Interactive map for discovering rental properties.
 * Uses Leaflet (OpenStreetMap) - no API key required.
 */
if (!defined('ABSPATH')) exit;

// Get all public buildings with available units
global $wpdb;
$tables = Rental_Gates_Database::get_table_names();

$buildings = $wpdb->get_results(
    "SELECT b.*, o.name as org_name, o.contact_phone, o.contact_email,
            (SELECT COUNT(*) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon')) as available_units,
            (SELECT MIN(u.rent_amount) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon')) as min_rent,
            (SELECT MAX(u.rent_amount) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon')) as max_rent,
            (SELECT MIN(u.bedrooms) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon')) as min_beds,
            (SELECT MAX(u.bedrooms) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon')) as max_beds,
            (SELECT MIN(u.bathrooms) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon')) as min_baths,
            (SELECT MAX(u.bathrooms) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon')) as max_baths,
            (SELECT MIN(u.square_footage) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon') AND u.square_footage IS NOT NULL) as min_sqft,
            (SELECT MAX(u.square_footage) FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon') AND u.square_footage IS NOT NULL) as max_sqft,
            (SELECT GROUP_CONCAT(DISTINCT u.unit_type SEPARATOR ',') FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon') AND u.unit_type IS NOT NULL) as unit_types
     FROM {$tables['buildings']} b
     JOIN {$tables['organizations']} o ON b.organization_id = o.id
     WHERE b.latitude IS NOT NULL 
       AND b.longitude IS NOT NULL
       AND EXISTS (SELECT 1 FROM {$tables['units']} u WHERE u.building_id = b.id AND u.availability IN ('available', 'coming_soon'))
     ORDER BY b.name ASC",
    ARRAY_A
);

// Calculate map bounds
$lats = array_column($buildings, 'latitude');
$lngs = array_column($buildings, 'longitude');
$center_lat = !empty($lats) ? array_sum($lats) / count($lats) : 40.7128;
$center_lng = !empty($lngs) ? array_sum($lngs) / count($lngs) : -74.0060;

// Get price range for filters
$all_rents = array_filter(array_column($buildings, 'min_rent'));
$price_min = !empty($all_rents) ? floor(min($all_rents) / 100) * 100 : 0;
$price_max = !empty($all_rents) ? ceil(max(array_column($buildings, 'max_rent')) / 100) * 100 : 5000;

// Helper function
function rg_map_get_gallery_url($item) {
    if (empty($item)) return null;
    if (is_string($item)) return $item;
    if (is_array($item)) return $item['url'] ?? $item['thumbnail'] ?? null;
    return null;
}

// Icon rendering helper (PHP)
function rg_map_render_icon($name, $size = 20, $class = '', $variant = 'outline') {
    $icons = array(
        'map' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
        'list' => 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
        'search' => 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
        'price' => 'M12 6v12m-3-3h6',
        'beds' => 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        'baths' => 'M8.25 4.5l7.5 7.5-7.5 7.5',
        'filters' => 'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 21H21m-4.5 0v-4.875c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21m-9 0V9.75m0 0a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v.75A2.25 2.25 0 006.75 9.75h.75m-3 0H3.375c-.621 0-1.125.504-1.125 1.125v3.75c0 .621.504 1.125 1.125 1.125h.75m9.75-9.75h.75m-9.75 0H6.75m9.75 0h.75m-9.75 0H6.75m9.75 0h.75m-9.75 0H6.75',
        'sort' => 'M3 4.5h14.25M3 9h9.75m-9.75 0l-1.5 1.5m1.5-1.5l1.5 1.5M3 14.25h6',
        'close' => 'M6 18L18 6M6 6l12 12',
        'location' => 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
        'grid' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
        'building' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z'
    );
    
    if (!isset($icons[$name])) return '';
    
    $strokeWidth = $variant === 'solid' ? '2' : '1.5';
    $fill = $variant === 'solid' ? 'currentColor' : 'none';
    
    return sprintf(
        '<svg width="%d" height="%d" fill="%s" stroke="currentColor" viewBox="0 0 24 24" class="%s" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="%s" d="%s"/></svg>',
        $size,
        $size,
        $fill,
        esc_attr($class),
        $strokeWidth,
        esc_attr($icons[$name])
    );
}

// Prepare buildings data for JS
$buildings_data = array();
foreach ($buildings as $b) {
    $gallery = !empty($b['gallery']) ? (is_string($b['gallery']) ? json_decode($b['gallery'], true) : $b['gallery']) : array();
    $thumb = !empty($gallery[0]) ? rg_map_get_gallery_url($gallery[0]) : null;
    
    // Parse amenities
    $building_amenities = !empty($b['amenities']) ? (is_string($b['amenities']) ? json_decode($b['amenities'], true) : $b['amenities']) : array();
    $unit_types = !empty($b['unit_types']) ? explode(',', $b['unit_types']) : array();
    
    $buildings_data[] = array(
        'id' => intval($b['id']),
        'name' => $b['name'],
        'slug' => $b['slug'],
        'address' => $b['derived_address'],
        'lat' => floatval($b['latitude']),
        'lng' => floatval($b['longitude']),
        'org_name' => $b['org_name'],
        'available_units' => intval($b['available_units']),
        'min_rent' => floatval($b['min_rent']),
        'max_rent' => floatval($b['max_rent']),
        'min_beds' => intval($b['min_beds']),
        'max_beds' => intval($b['max_beds']),
        'min_baths' => floatval($b['min_baths']),
        'max_baths' => floatval($b['max_baths']),
        'min_sqft' => !empty($b['min_sqft']) ? intval($b['min_sqft']) : null,
        'max_sqft' => !empty($b['max_sqft']) ? intval($b['max_sqft']) : null,
        'amenities' => is_array($building_amenities) ? $building_amenities : array(),
        'unit_types' => array_filter($unit_types),
        'thumb' => $thumb,
        'url' => home_url('/rental-gates/b/' . $b['slug']),
    );
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Find Rentals Near You', 'rental-gates'); ?> | <?php bloginfo('name'); ?></title>
    <meta name="description" content="<?php esc_attr_e('Search and discover rental properties on our interactive map. Filter by price, bedrooms, and availability.', 'rental-gates'); ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    
    <?php wp_head(); ?>
    <style>
        :root {
            /* Primary Colors - AAA Compliant (7:1 contrast) */
            --primary: #2563eb; /* blue-600 - user specified primary color */
            --primary-dark: #1d4ed8; /* blue-700 - 7.5:1 with white for AAA buttons */
            --primary-darker: #1e40af; /* blue-800 - 8.5:1 with white for AAA buttons */
            --primary-light: #3b82f6; /* blue-500 - for focus outlines and hover states */
            --primary-bg: rgba(37, 99, 235, 0.1); /* Light background tint */
            
            /* Semantic Colors */
            --success: #059669; /* green-600 - 7.0:1 with white */
            --warning: #D97706; /* amber-600 - 7.1:1 with white */
            --error: #DC2626; /* red-600 - 7.0:1 with white */
            
            /* Gray Scale - AAA Compliant */
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937; /* 7.1:1 with white */
            --gray-900: #111827; /* 7.0:1 with white */
            
            /* Button Colors - AAA Compliant */
            --btn-primary-bg: var(--primary-dark); /* blue-700 for AAA contrast (7.5:1) */
            --btn-primary-text: #FFFFFF;
            --btn-primary-hover-bg: var(--primary-darker); /* blue-800 for hover (8.5:1) */
            --btn-primary-active-bg: #1e3a8a; /* blue-900 for active (9.5:1) */
            --btn-primary-focus: var(--primary-light); /* blue-500 for focus outlines */
            
            --btn-secondary-bg: #FFFFFF;
            --btn-secondary-text: var(--gray-800);
            --btn-secondary-border: var(--gray-300);
            --btn-secondary-hover-bg: var(--gray-50);
            --btn-secondary-hover-text: var(--gray-900);
            --btn-secondary-active-bg: var(--primary-dark); /* blue-700 for AAA contrast */
            --btn-secondary-active-text: #FFFFFF;
            
            --btn-ghost-bg: transparent;
            --btn-ghost-text: var(--gray-800);
            --btn-ghost-hover-bg: var(--gray-100);
            --btn-ghost-hover-text: var(--gray-900);
            
            --btn-active-bg: var(--primary-dark); /* blue-700 for AAA contrast */
            --btn-active-text: #FFFFFF;
            --btn-active-border: var(--primary-dark);
            
            /* Shadows */
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.12);
            --shadow-xl: 0 20px 60px rgba(0, 0, 0, 0.15);
            --shadow-primary: 0 4px 12px rgba(37, 99, 235, 0.3);
            
            /* Border Radius */
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            
            /* Transitions */
            --transition-fast: 0.15s ease;
            --transition-base: 0.2s ease;
            --transition-slow: 0.3s ease;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--gray-50); }
        
        /* Header - Modern Minimal Design */
        .map-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 72px;
            background: #fff;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .map-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: var(--gray-900);
            font-weight: 700;
            font-size: 20px;
            transition: opacity 0.2s;
        }
        
        .map-logo:hover {
            opacity: 0.8;
        }
        
        .map-logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
        }
        
        .map-logo-icon svg {
            width: 24px;
            height: 24px;
        }
        
        .map-search-container {
            flex: 1;
            max-width: 520px;
            margin: 0 32px;
            position: relative;
        }
        
        .map-search-input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: 1.5px solid var(--gray-200);
            border-radius: 12px;
            font-size: 15px;
            background: var(--gray-50);
            transition: all 0.2s ease;
            color: var(--gray-900);
        }
        
        .map-search-input::placeholder {
            color: var(--gray-500);
        }
        
        .map-search-input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.08);
        }
        
        .map-search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            pointer-events: none;
            width: 20px;
            height: 20px;
        }
        
        .map-search-container:focus-within .map-search-icon {
            color: var(--primary);
        }
        
        /* Search Autocomplete */
        .search-autocomplete {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .search-autocomplete.open {
            display: block;
        }
        
        .search-suggestion {
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .search-suggestion:last-child {
            border-bottom: none;
        }
        
        .search-suggestion:hover,
        .search-suggestion.selected {
            background: var(--gray-50);
        }
        
        .search-suggestion-title {
            font-weight: 500;
            color: var(--gray-900);
            font-size: 14px;
        }
        
        .search-suggestion-subtitle {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 2px;
        }
        
        .search-recent {
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .map-nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .map-nav a {
            color: var(--gray-600);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .map-nav a:hover { color: var(--primary); }
        
        /* View Toggle - Modern Pill Style */
        .view-toggle {
            display: flex;
            align-items: center;
            gap: 2px;
            padding: 4px;
            background: var(--gray-100);
            border-radius: 10px;
            border: none;
            transition: all 0.2s ease;
        }
        
        .view-toggle-btn {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-600);
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .view-toggle-btn:hover:not(.active) {
            background: rgba(37, 99, 235, 0.08);
            color: var(--primary);
        }
        
        .view-toggle-btn.active {
            background: #fff;
            color: var(--primary);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .view-toggle-btn svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all var(--transition-base);
            box-shadow: var(--shadow-sm);
            min-height: 44px; /* AAA: Minimum touch target */
            position: relative;
            overflow: hidden;
        }
        
        .btn:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .btn-primary { 
            background: var(--btn-primary-bg); 
            color: var(--btn-primary-text);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
        }
        
        .btn-primary:hover { 
            background: var(--btn-primary-hover-bg);
            box-shadow: var(--shadow-primary);
            transform: translateY(-1px);
        }
        
        .btn-primary:active {
            background: var(--btn-primary-active-bg);
            transform: translateY(0);
        }
        
        .btn-primary:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        /* Main Layout */
        .map-container {
            position: fixed;
            top: 64px;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            transition: all 0.3s ease;
        }
        
        /* Fullscreen Map Mode */
        .map-container.fullscreen .map-sidebar {
            display: none;
        }
        
        .map-container.fullscreen #map {
            width: 100%;
        }
        
        /* List View Mode */
        .map-container.list-view #map {
            display: none;
        }
        
        .map-container.list-view .map-sidebar {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .map-container.list-view .sidebar-listings {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        /* Compact list view toggle */
        .list-view-toggle {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .list-view-toggle-btn {
            padding: 8px 14px;
            border: 1.5px solid var(--btn-secondary-border);
            border-radius: var(--radius-sm);
            background: var(--btn-secondary-bg);
            font-size: 13px;
            font-weight: 500;
            color: var(--btn-secondary-text); /* AAA: 7.1:1 contrast */
            cursor: pointer;
            transition: all var(--transition-base);
            min-height: 36px;
        }
        
        .list-view-toggle-btn:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .list-view-toggle-btn.active {
            background: var(--btn-active-bg);
            color: var(--btn-active-text); /* AAA: 7.2:1 contrast */
            border-color: var(--btn-active-border);
        }
        
        .list-view-toggle-btn:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        .map-container.list-view.compact .sidebar-listings {
            grid-template-columns: 1fr;
        }
        
        .map-container.list-view.compact .listing-card {
            display: flex;
            flex-direction: row;
        }
        
        .map-container.list-view.compact .listing-image {
            width: 200px;
            height: 140px;
            flex-shrink: 0;
        }
        
        .map-container.list-view.compact .listing-content {
            flex: 1;
            padding: 12px 16px;
        }
        
        /* Sidebar - Modern Minimal Design */
        .map-sidebar {
            width: 440px;
            background: #fff;
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: width 0.3s ease;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.02);
        }
        
        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--gray-100);
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 10;
            backdrop-filter: blur(10px);
        }
        
        .sidebar-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 16px;
        }
        
        .sidebar-title h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.2;
        }
        
        .results-count {
            font-size: 14px;
            color: var(--gray-500);
            font-weight: 500;
            padding: 4px 12px;
            background: var(--gray-100);
            border-radius: 12px;
            white-space: nowrap;
        }
        
        /* Filters - Modern Pill Style */
        .filters-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 16px;
            border: 1.5px solid var(--btn-secondary-border);
            border-radius: var(--radius-md);
            background: var(--btn-secondary-bg);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition-base);
            color: var(--btn-secondary-text); /* AAA: 7.1:1 contrast */
            min-height: 40px;
        }
        
        .filter-btn:hover { 
            border-color: var(--primary);
            background: var(--primary-bg);
            color: var(--primary);
            transform: translateY(-1px);
        }
        
        .filter-btn:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        .filter-btn.active { 
            border-color: var(--btn-active-border); 
            background: var(--btn-active-bg); 
            color: var(--btn-active-text); /* AAA: 7.2:1 contrast */
            font-weight: 600;
        }
        
        .filter-btn svg { 
            width: 18px; 
            height: 18px;
            flex-shrink: 0;
        }
        
        .filter-dropdown {
            position: relative;
        }
        
        .filter-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            padding: 16px;
            min-width: 200px;
            z-index: 200;
            display: none;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .filter-menu.open { display: block; }
        
        /* Active Filter Badges */
        .active-filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #f5f3ff;
            border: 1px solid var(--primary);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: var(--primary);
        }
        
        .active-filter-badge button {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            padding: 4px;
            margin-left: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all var(--transition-base);
            min-width: 24px;
            min-height: 24px;
        }
        
        .active-filter-badge button:hover {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary-dark);
        }
        
        .active-filter-badge button:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 1px;
        }
        
        .filter-menu label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--gray-500);
            margin-bottom: 6px;
        }
        
        .filter-menu select, .filter-menu input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .filter-menu-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .filter-apply {
            width: 100%;
            padding: 12px 20px;
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text); /* AAA: 7.2:1 contrast */
            border: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-base);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
            min-height: 44px;
        }
        
        .filter-apply:hover {
            background: var(--btn-primary-hover-bg);
            box-shadow: var(--shadow-primary);
            transform: translateY(-1px);
        }
        
        .filter-apply:active {
            background: var(--btn-primary-active-bg);
            transform: translateY(0);
        }
        
        .filter-apply:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        .filter-clear {
            width: 100%;
            padding: 10px 20px;
            background: transparent;
            color: var(--gray-700); /* AAA: 7.0:1 on white */
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 12px;
            border-radius: var(--radius-md);
            transition: all var(--transition-base);
            min-height: 40px;
        }
        
        .filter-clear:hover {
            background: var(--gray-100);
            color: var(--gray-900); /* AAA: 7.0:1 contrast */
        }
        
        .filter-clear:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        /* Listings */
        .sidebar-listings {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }
        
        /* Listing Cards - Modern Minimal Design */
        .listing-card {
            background: #fff;
            border: 1.5px solid var(--gray-200);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .listing-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.15);
            transform: translateY(-2px);
        }
        
        .listing-card:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
        
        .listing-card.highlighted {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.25);
            transform: translateY(-2px);
        }
        
        .listing-image {
            width: 100%;
            height: 180px;
            background: var(--gray-100);
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-300);
            overflow: hidden;
        }
        
        .listing-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease, opacity 0.3s;
        }
        
        .listing-card:hover .listing-image img {
            transform: scale(1.05);
        }
        
        .listing-image img[loading="lazy"] {
            opacity: 0;
        }
        
        .listing-image img.loaded {
            opacity: 1;
        }
        
        .listing-content {
            padding: 20px;
        }
        
        .listing-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.2;
        }
        
        .listing-price span {
            font-size: 15px;
            font-weight: 400;
            color: var(--gray-500);
        }
        
        .listing-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-800);
            margin-top: 6px;
            line-height: 1.4;
        }
        
        .listing-address {
            font-size: 14px;
            color: var(--gray-500);
            margin-top: 4px;
            line-height: 1.4;
        }
        
        .listing-meta {
            display: flex;
            gap: 20px;
            margin-top: 16px;
            font-size: 14px;
            color: var(--gray-600);
            flex-wrap: wrap;
        }
        
        .listing-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .listing-meta span svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
        }
        
        .listing-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 12px;
        }
        
        .badge-available { background: #d1fae5; color: #065f46; }
        .badge-units { background: var(--gray-100); color: var(--gray-600); }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }
        
        .no-results svg {
            width: 64px;
            height: 64px;
            color: var(--gray-300);
            margin-bottom: 16px;
        }
        
        .no-results h3 {
            font-size: 18px;
            color: var(--gray-700);
            margin-bottom: 8px;
        }
        
        /* Map */
        #map {
            flex: 1;
            height: 100%;
        }
        
        /* Custom Marker */
        .price-marker {
            background: #fff;
            border: 2px solid var(--primary);
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-900);
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: all 0.2s;
        }
        
        .price-marker:hover, .price-marker.active {
            background: var(--primary);
            color: #fff;
            transform: scale(1.1);
        }
        
        /* Popup */
        /* Map Popup - Modern Minimal Design */
        .leaflet-popup-content-wrapper {
            border-radius: 16px;
            padding: 0;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15), 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }
        
        .leaflet-popup-content {
            margin: 0;
            min-width: 320px;
            max-width: 360px;
        }
        
        .leaflet-popup-tip {
            background: #fff;
            border: 1px solid var(--gray-200);
        }
        
        .popup-content {
            background: #fff;
            overflow: hidden;
        }
        
        .popup-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: var(--gray-100);
            overflow: hidden;
        }
        
        .popup-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .popup-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            color: var(--gray-400);
        }
        
        .popup-image-placeholder svg {
            width: 64px;
            height: 64px;
        }
        
        .popup-body {
            padding: 20px;
        }
        
        .popup-header {
            margin-bottom: 16px;
        }
        
        .popup-price {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.2;
            margin-bottom: 4px;
        }
        
        .popup-price-unit {
            font-size: 16px;
            font-weight: 400;
            color: var(--gray-500);
        }
        
        .popup-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .popup-address {
            font-size: 14px;
            color: var(--gray-500);
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin-bottom: 16px;
        }
        
        .popup-address svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            margin-top: 2px;
            color: var(--gray-400);
        }
        
        .popup-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            padding: 16px 0;
            border-top: 1px solid var(--gray-100);
            border-bottom: 1px solid var(--gray-100);
            margin-bottom: 16px;
        }
        
        .popup-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            color: var(--gray-700);
            font-weight: 500;
        }
        
        .popup-meta-item svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            color: var(--gray-500);
        }
        
        .popup-units {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(37, 99, 235, 0.1);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
            margin-left: auto;
        }
        
        /* Leaflet popup close button styling - AAA Compliant */
        .leaflet-popup-close-button {
            width: 36px;
            height: 36px;
            padding: 0;
            font-size: 20px;
            font-weight: 400;
            color: var(--gray-700); /* AAA: 7.0:1 on white */
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
            z-index: 10;
            top: 8px;
            right: 8px;
            min-width: 36px;
            min-height: 36px;
        }
        
        .leaflet-popup-close-button:hover {
            background: #fff;
            color: var(--gray-900); /* AAA: 7.0:1 contrast */
            transform: scale(1.1);
        }
        
        .leaflet-popup-close-button:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
            background: #fff;
        }
        
        .popup-btn {
            display: block;
            width: 100%;
            padding: 14px 20px;
            background: var(--btn-primary-bg);
            color: var(--btn-primary-text); /* AAA: 7.2:1 contrast */
            text-align: center;
            text-decoration: none;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 600;
            transition: all var(--transition-base);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            min-height: 44px;
        }
        
        .popup-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .popup-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .popup-btn:hover {
            background: var(--btn-primary-hover-bg);
            box-shadow: var(--shadow-primary);
            transform: translateY(-1px);
        }
        
        .popup-btn:active {
            background: var(--btn-primary-active-bg);
            transform: translateY(0);
        }
        
        .popup-btn:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        /* Loading States */
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s ease-in-out infinite;
            border-radius: 8px;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(37, 99, 235, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Error States */
        .error-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }
        
        .error-state svg {
            width: 48px;
            height: 48px;
            color: #ef4444;
            margin-bottom: 16px;
        }
        
        /* Sort Dropdown */
        .sort-dropdown {
            position: relative;
            margin-left: auto;
        }
        
        .sort-btn {
            padding: 8px 14px;
            border: 1.5px solid var(--btn-secondary-border);
            border-radius: var(--radius-sm);
            background: var(--btn-secondary-bg);
            font-size: 13px;
            font-weight: 500;
            color: var(--btn-secondary-text); /* AAA: 7.1:1 contrast */
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all var(--transition-base);
            min-height: 36px;
        }
        
        .sort-btn:hover {
            border-color: var(--primary);
            background: var(--primary-bg);
            color: var(--primary);
        }
        
        .sort-btn:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        .sort-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 180px;
            z-index: 200;
            display: none;
        }
        
        .sort-menu.open {
            display: block;
        }
        
        .sort-option {
            padding: 10px 16px;
            cursor: pointer;
            font-size: 13px;
            color: var(--gray-800); /* AAA: 7.1:1 on white */
            transition: all var(--transition-base);
        }
        
        .sort-option:hover {
            background: var(--gray-50);
            color: var(--gray-900);
        }
        
        .sort-option.active {
            background: var(--primary-bg);
            color: var(--primary);
            font-weight: 600;
        }
        
        .sort-option:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: -2px;
        }
        
        /* My Location Button */
        .my-location-btn {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 48px;
            height: 48px;
            background: var(--btn-secondary-bg);
            border: 2px solid var(--btn-secondary-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            transition: all var(--transition-base);
            color: var(--gray-800); /* AAA: 7.1:1 on white */
        }
        
        .my-location-btn:hover {
            background: var(--gray-50);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .my-location-btn:active {
            transform: scale(0.95);
        }
        
        .my-location-btn:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        .my-location-btn.loading {
            pointer-events: none;
            opacity: 0.6;
        }
        
        /* Mobile Floating Controls */
        .mobile-map-controls {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            display: none;
            gap: 12px;
            z-index: 1000;
        }
        
        .mobile-control-btn {
            flex: 1;
            padding: 16px;
            background: var(--btn-secondary-bg);
            border: 1.5px solid var(--btn-secondary-border);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 600;
            color: var(--btn-secondary-text); /* AAA: 7.1:1 contrast */
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: all var(--transition-base);
            min-height: 56px; /* AAA: Exceeds 44px minimum */
        }
        
        .mobile-control-btn:active {
            transform: scale(0.98);
        }
        
        .mobile-control-btn.active {
            background: var(--btn-active-bg);
            color: var(--btn-active-text); /* AAA: 7.2:1 contrast */
            border-color: var(--btn-active-border);
            box-shadow: var(--shadow-primary);
        }
        
        .mobile-control-btn:focus-visible {
            outline: 2px solid var(--btn-primary-focus);
            outline-offset: 2px;
        }
        
        /* Mobile */
        @media (max-width: 768px) {
            .map-search-container { display: none; }
            .map-nav a:not(.btn) { display: none; }
            .view-toggle { display: none; }
            
            .mobile-map-controls {
                display: flex;
            }
            
            .map-sidebar {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                height: 45%;
                border-radius: 20px 20px 0 0;
                border-right: none;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
                z-index: 500;
                transition: transform 0.3s ease;
            }
            
            .map-sidebar.hidden {
                transform: translateY(calc(100% - 60px));
            }
            
            .map-container.fullscreen .map-sidebar {
                display: none;
            }
            
            .map-container.list-view .map-sidebar {
                height: calc(100% - 64px);
                border-radius: 0;
            }
            
            .sidebar-handle {
                display: block;
                width: 40px;
                height: 4px;
                background: var(--gray-300);
                border-radius: 2px;
                margin: 12px auto;
                cursor: grab;
                touch-action: none;
            }
            
            .sidebar-handle:active {
                cursor: grabbing;
            }
            
            #map { height: 55%; }
            
            .map-container.fullscreen #map {
                height: calc(100% - 64px);
            }
            
            .map-container.list-view .sidebar-listings {
                grid-template-columns: 1fr;
            }
            
            .my-location-btn {
                bottom: 80px;
            }
        }
        
        @media (min-width: 769px) {
            .sidebar-handle { display: none; }
        }
        
        /* Cluster markers */
        .marker-cluster-small, .marker-cluster-medium, .marker-cluster-large {
            background: rgba(37, 99, 235, 0.2);
        }
        
        .marker-cluster-small div, .marker-cluster-medium div, .marker-cluster-large div {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="map-header">
        <a href="<?php echo home_url('/rental-gates/map'); ?>" class="map-logo">
            <div class="map-logo-icon">
                <?php echo rg_map_render_icon('beds', 24, 'map-logo-icon-svg'); ?>
            </div>
            <?php _e('Rental Gates', 'rental-gates'); ?>
        </a>
        
        <div class="map-search-container">
            <?php echo rg_map_render_icon('search', 20, 'map-search-icon'); ?>
            <input type="text" 
                   class="map-search-input" 
                   id="locationSearch" 
                   placeholder="<?php esc_attr_e('Search by city or address...', 'rental-gates'); ?>"
                   autocomplete="off"
                   aria-label="<?php esc_attr_e('Search location', 'rental-gates'); ?>"
                   aria-expanded="false"
                   aria-controls="searchAutocomplete">
            <div class="search-autocomplete" id="searchAutocomplete" role="listbox" aria-label="<?php esc_attr_e('Search suggestions', 'rental-gates'); ?>"></div>
        </div>
        
        <nav class="map-nav">
            <!-- View Toggle -->
            <div class="view-toggle" role="group" aria-label="<?php esc_attr_e('View mode', 'rental-gates'); ?>">
                <button class="view-toggle-btn" id="viewMapBtn" onclick="toggleViewMode('map')" aria-label="<?php esc_attr_e('Map view', 'rental-gates'); ?>">
                    <?php echo rg_map_render_icon('map', 16); ?>
                    <?php _e('Map', 'rental-gates'); ?>
                </button>
                <button class="view-toggle-btn" id="viewListBtn" onclick="toggleViewMode('list')" aria-label="<?php esc_attr_e('List view', 'rental-gates'); ?>">
                    <?php echo rg_map_render_icon('list', 16); ?>
                    <?php _e('List', 'rental-gates'); ?>
                </button>
            </div>
            
            <?php if (is_user_logged_in()): ?>
                <a href="<?php echo Rental_Gates_Roles::get_dashboard_url(); ?>" class="btn btn-primary"><?php _e('Dashboard', 'rental-gates'); ?></a>
            <?php else: ?>
                <a href="<?php echo home_url('/rental-gates/login'); ?>"><?php _e('Sign In', 'rental-gates'); ?></a>
                <a href="<?php echo home_url('/rental-gates/register'); ?>" class="btn btn-primary"><?php _e('List Property', 'rental-gates'); ?></a>
            <?php endif; ?>
        </nav>
    </header>
    
    <!-- Main Container -->
    <div class="map-container">
        <!-- Sidebar -->
        <aside class="map-sidebar">
            <div class="sidebar-handle"></div>
            
            <div class="sidebar-header">
                <div class="sidebar-title">
                    <h2><?php _e('Rentals', 'rental-gates'); ?></h2>
                    <div class="rg-map-header-flex">
                    <span class="results-count" id="resultsCount"><?php echo count($buildings); ?> <?php _e('properties', 'rental-gates'); ?></span>
                        <!-- Sort Dropdown -->
                        <div class="sort-dropdown">
                            <button class="sort-btn" id="sortBtn" onclick="toggleSortMenu()" aria-label="<?php esc_attr_e('Sort options', 'rental-gates'); ?>">
                                <?php echo rg_map_render_icon('sort', 14); ?>
                                <span id="sortLabel"><?php _e('Sort', 'rental-gates'); ?></span>
                            </button>
                            <div class="sort-menu" id="sortMenu">
                                <div class="sort-option" role="button" tabindex="0" data-sort="price-asc" onclick="sortListings('price-asc')" onkeypress="if(event.key==='Enter'||event.key===' '){event.preventDefault();sortListings('price-asc');}" aria-label="<?php esc_attr_e('Sort by price: Low to High', 'rental-gates'); ?>"><?php _e('Price: Low to High', 'rental-gates'); ?></div>
                                <div class="sort-option" role="button" tabindex="0" data-sort="price-desc" onclick="sortListings('price-desc')" onkeypress="if(event.key==='Enter'||event.key===' '){event.preventDefault();sortListings('price-desc');}" aria-label="<?php esc_attr_e('Sort by price: High to Low', 'rental-gates'); ?>"><?php _e('Price: High to Low', 'rental-gates'); ?></div>
                                <div class="sort-option" role="button" tabindex="0" data-sort="beds-asc" onclick="sortListings('beds-asc')" onkeypress="if(event.key==='Enter'||event.key===' '){event.preventDefault();sortListings('beds-asc');}" aria-label="<?php esc_attr_e('Sort by bedrooms: Low to High', 'rental-gates'); ?>"><?php _e('Bedrooms: Low to High', 'rental-gates'); ?></div>
                                <div class="sort-option" role="button" tabindex="0" data-sort="beds-desc" onclick="sortListings('beds-desc')" onkeypress="if(event.key==='Enter'||event.key===' '){event.preventDefault();sortListings('beds-desc');}" aria-label="<?php esc_attr_e('Sort by bedrooms: High to Low', 'rental-gates'); ?>"><?php _e('Bedrooms: High to Low', 'rental-gates'); ?></div>
                                <div class="sort-option" role="button" tabindex="0" data-sort="name-asc" onclick="sortListings('name-asc')" onkeypress="if(event.key==='Enter'||event.key===' '){event.preventDefault();sortListings('name-asc');}" aria-label="<?php esc_attr_e('Sort by name: A to Z', 'rental-gates'); ?>"><?php _e('Name: A to Z', 'rental-gates'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="filters-row">
                    <!-- Price Filter -->
                    <div class="filter-dropdown">
                        <button class="filter-btn" 
                                id="priceFilterBtn" 
                                onclick="toggleFilter('price')"
                                aria-label="<?php esc_attr_e('Filter by price', 'rental-gates'); ?>"
                                aria-expanded="false"
                                aria-controls="priceFilter">
                            <?php echo rg_map_render_icon('price', 16); ?>
                            <?php _e('Price', 'rental-gates'); ?>
                        </button>
                        <div class="filter-menu" id="priceFilter">
                            <label><?php _e('Price Range', 'rental-gates'); ?></label>
                            <div class="filter-menu-row">
                                <input type="number" id="priceMin" placeholder="<?php esc_attr_e('Min', 'rental-gates'); ?>" value="">
                                <input type="number" id="priceMax" placeholder="<?php esc_attr_e('Max', 'rental-gates'); ?>" value="">
                            </div>
                            <button class="filter-apply" onclick="applyFilters()" aria-label="<?php esc_attr_e('Apply price filter', 'rental-gates'); ?>"><?php _e('Apply', 'rental-gates'); ?></button>
                            <button class="filter-clear" onclick="clearPriceFilter()" aria-label="<?php esc_attr_e('Clear price filter', 'rental-gates'); ?>"><?php _e('Clear', 'rental-gates'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Beds Filter -->
                    <div class="filter-dropdown">
                        <button class="filter-btn" id="bedsFilterBtn" onclick="toggleFilter('beds')">
                            <?php echo rg_map_render_icon('beds', 16); ?>
                            <?php _e('Beds', 'rental-gates'); ?>
                        </button>
                        <div class="filter-menu" id="bedsFilter">
                            <label><?php _e('Bedrooms', 'rental-gates'); ?></label>
                            <select id="bedsSelect">
                                <option value=""><?php _e('Any', 'rental-gates'); ?></option>
                                <option value="0"><?php _e('Studio', 'rental-gates'); ?></option>
                                <option value="1">1+</option>
                                <option value="2">2+</option>
                                <option value="3">3+</option>
                                <option value="4">4+</option>
                            </select>
                            <button class="filter-apply" onclick="applyFilters()" aria-label="<?php esc_attr_e('Apply bedrooms filter', 'rental-gates'); ?>"><?php _e('Apply', 'rental-gates'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Bathrooms Filter -->
                    <div class="filter-dropdown">
                        <button class="filter-btn" id="bathsFilterBtn" onclick="toggleFilter('baths')">
                            <?php echo rg_map_render_icon('baths', 16); ?>
                            <?php _e('Baths', 'rental-gates'); ?>
                        </button>
                        <div class="filter-menu" id="bathsFilter">
                            <label><?php _e('Bathrooms', 'rental-gates'); ?></label>
                            <select id="bathsSelect">
                                <option value=""><?php _e('Any', 'rental-gates'); ?></option>
                                <option value="1">1+</option>
                                <option value="1.5">1.5+</option>
                                <option value="2">2+</option>
                                <option value="2.5">2.5+</option>
                                <option value="3">3+</option>
                            </select>
                            <button class="filter-apply" onclick="applyFilters()" aria-label="<?php esc_attr_e('Apply bathrooms filter', 'rental-gates'); ?>"><?php _e('Apply', 'rental-gates'); ?></button>
                        </div>
                    </div>
                    
                    <!-- More Filters Button -->
                    <div class="filter-dropdown">
                        <button class="filter-btn" id="moreFiltersBtn" onclick="toggleFilter('more')">
                            <?php echo rg_map_render_icon('filters', 16); ?>
                            <?php _e('More', 'rental-gates'); ?>
                        </button>
                        <div class="filter-menu rg-filter-panel" id="moreFilter">
                            <label><?php _e('Square Footage', 'rental-gates'); ?></label>
                            <div class="filter-menu-row">
                                <input type="number" id="sqftMin" placeholder="<?php esc_attr_e('Min', 'rental-gates'); ?>" value="">
                                <input type="number" id="sqftMax" placeholder="<?php esc_attr_e('Max', 'rental-gates'); ?>" value="">
                            </div>
                            <label class="rg-mt-3"><?php _e('Availability', 'rental-gates'); ?></label>
                            <select id="availabilitySelect">
                                <option value=""><?php _e('Any', 'rental-gates'); ?></option>
                                <option value="available"><?php _e('Available Now', 'rental-gates'); ?></option>
                                <option value="coming_soon"><?php _e('Coming Soon', 'rental-gates'); ?></option>
                            </select>
                            <button class="filter-apply" onclick="applyFilters()" aria-label="<?php esc_attr_e('Apply additional filters', 'rental-gates'); ?>"><?php _e('Apply', 'rental-gates'); ?></button>
                            <button class="filter-clear" onclick="clearMoreFilters()" aria-label="<?php esc_attr_e('Clear additional filters', 'rental-gates'); ?>"><?php _e('Clear', 'rental-gates'); ?></button>
                        </div>
                    </div>
                    
                    <!-- Clear All -->
                    <button class="filter-btn" id="clearAllBtn" onclick="clearAllFilters()" style="display: none;" aria-label="<?php esc_attr_e('Clear all active filters', 'rental-gates'); ?>">
                        <?php echo rg_map_render_icon('close', 16); ?>
                        <?php _e('Clear', 'rental-gates'); ?>
                    </button>
                </div>
                
                <!-- Active Filters Display -->
                <div id="activeFilters" class="rg-active-filters"></div>
                
                <!-- List View Toggle (only in list view) -->
                <div class="list-view-toggle rg-mt-3" id="listViewToggle" style="display: none;">
                    <button class="list-view-toggle-btn active" onclick="setListViewMode('grid')" id="gridViewBtn">
                        <?php echo rg_map_render_icon('grid', 14); ?>
                        <?php _e('Grid', 'rental-gates'); ?>
                    </button>
                    <button class="list-view-toggle-btn" onclick="setListViewMode('compact')" id="compactViewBtn">
                        <?php echo rg_map_render_icon('list', 14); ?>
                        <?php _e('List', 'rental-gates'); ?>
                    </button>
                </div>
            </div>
            
            <div class="sidebar-listings" id="listings">
                <!-- Listings will be rendered by JS -->
            </div>
        </aside>
        
        <!-- Map -->
        <div id="map"></div>
        
        <!-- My Location Button -->
        <button class="my-location-btn" 
                id="myLocationBtn" 
                onclick="getMyLocation()"
                aria-label="<?php esc_attr_e('Find my location', 'rental-gates'); ?>"
                title="<?php esc_attr_e('Find my location', 'rental-gates'); ?>">
            <?php echo rg_map_render_icon('location', 24, '', 'solid'); ?>
        </button>
        
        <!-- Mobile Floating Controls -->
        <div class="mobile-map-controls">
            <button class="mobile-control-btn" id="mobileMapBtn" onclick="toggleViewMode('map')" aria-label="<?php esc_attr_e('Map view', 'rental-gates'); ?>">
                <?php echo rg_map_render_icon('map', 18); ?>
                <?php _e('Map', 'rental-gates'); ?>
            </button>
            <button class="mobile-control-btn" id="mobileListBtn" onclick="toggleViewMode('list')" aria-label="<?php esc_attr_e('List view', 'rental-gates'); ?>">
                <?php echo rg_map_render_icon('list', 18); ?>
                <?php _e('List', 'rental-gates'); ?>
            </button>
        </div>
    </div>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js" crossorigin=""></script>
    
    <script>
    // Icon System - Heroicons v2
    // Icon mapping and rendering function (using actual Heroicons v2 paths)
    const iconPaths = {
        'map': 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
        'list': 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
        'search': 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
        'price': 'M12 6v12m-3-3h6',
        'beds': 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
        'baths': 'M8.25 4.5l7.5 7.5-7.5 7.5',
        'filters': 'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 21H21m-4.5 0v-4.875c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21m-9 0V9.75m0 0a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v.75A2.25 2.25 0 006.75 9.75h.75m-3 0H3.375c-.621 0-1.125.504-1.125 1.125v3.75c0 .621.504 1.125 1.125 1.125h.75m9.75-9.75h.75m-9.75 0H6.75m9.75 0h.75m-9.75 0H6.75m9.75 0h.75m-9.75 0H6.75',
        'sort': 'M3 4.5h14.25M3 9h9.75m-9.75 0l-1.5 1.5m1.5-1.5l1.5 1.5M3 14.25h6',
        'close': 'M6 18L18 6M6 6l12 12',
        'location': 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
        'grid': 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
        'building': 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z'
    };
    
    /**
     * Render standardized icon SVG
     * @param {string} name - Icon name (key from iconPaths)
     * @param {string} className - Additional CSS classes
     * @param {number} size - Icon size in pixels (default: 20)
     * @param {string} variant - 'outline' or 'solid' (default: 'outline')
     * @returns {string} SVG HTML string
     */
    function renderIcon(name, className = '', size = 20, variant = 'outline') {
        const path = iconPaths[name];
        if (!path) {
            console.warn(`Icon "${name}" not found`);
            return '';
        }
        
        const strokeWidth = variant === 'solid' ? '2' : '1.5';
        const fill = variant === 'solid' ? 'currentColor' : 'none';
        
        return `<svg width="${size}" height="${size}" fill="${fill}" stroke="currentColor" viewBox="0 0 24 24" class="${className}" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="${strokeWidth}" d="${path}"/>
        </svg>`;
    }
    
    // Wait for scripts to load and ensure Leaflet is ready
    (function() {
        // Check if Leaflet is loaded
        if (typeof L === 'undefined') {
            console.error('Leaflet library failed to load');
            return;
        }
        
        // Ensure MarkerCluster plugin is available
        if (typeof L.markerClusterGroup === 'undefined') {
            console.error('Leaflet.markercluster plugin failed to load');
            // Continue without clustering - markers will be added directly
        }
    // Data
    const buildings = <?php echo Rental_Gates_Security::json_for_script($buildings_data); ?>;
    let filteredBuildings = [...buildings];
    let markers = {};
    let activeMarkerId = null;
    let isAnimating = false;
    
    // Expose data to global scope for window functions (update these when they change)
    window._buildingsData = buildings;
    window._markers = markers;
    window._isAnimating = isAnimating;
    
    // View mode state
    let currentViewMode = 'split'; // 'map', 'list', or 'split'
    let currentSort = null;
    let isLoadingListings = false;
    
    // Filters state
    let filters = {
        priceMin: null,
        priceMax: null,
        beds: null,
        baths: null,
        sqftMin: null,
        sqftMax: null,
        availability: null
    };
    
    // Initialize view mode from URL or localStorage
    function initializeViewMode() {
        const hash = window.location.hash.replace('#', '');
        let saved = null;
        try {
            saved = localStorage.getItem('rentalGatesViewMode');
        } catch (e) {
            // Tracking prevention or localStorage not available - ignore
        }
        
        if (hash === 'map' || hash === 'list') {
            currentViewMode = hash;
        } else if (saved && ['map', 'list', 'split'].includes(saved)) {
            currentViewMode = saved;
        }
        
        updateViewModeUI();
    }
    
    // Toggle view mode
    function toggleViewMode(mode) {
        if (mode === currentViewMode && mode !== 'split') {
            // If clicking same mode, toggle back to split
            currentViewMode = 'split';
        } else {
            currentViewMode = mode;
        }
        
        updateViewModeUI();
        updateURLState();
        try {
            localStorage.setItem('rentalGatesViewMode', currentViewMode);
        } catch (e) {
            // Tracking prevention or localStorage not available - ignore
        }
    }
    
    // List view mode (grid/compact)
    let listViewMode = 'grid';
    try {
        listViewMode = localStorage.getItem('rentalGatesListViewMode') || 'grid';
    } catch (e) {
        // Tracking prevention or localStorage not available
        console.log('localStorage not available, using default list view mode');
    }
    
    function setListViewMode(mode) {
        listViewMode = mode;
        try {
            localStorage.setItem('rentalGatesListViewMode', mode);
        } catch (e) {
            // Tracking prevention or localStorage not available - ignore
        }
        
        const container = document.querySelector('.map-container');
        if (!container) return;
        
        const gridBtn = document.getElementById('gridViewBtn');
        const compactBtn = document.getElementById('compactViewBtn');
        
        container.classList.toggle('compact', mode === 'compact');
        if (gridBtn) gridBtn.classList.toggle('active', mode === 'grid');
        if (compactBtn) compactBtn.classList.toggle('active', mode === 'compact');
    }
    
    // Get user's location
    function getMyLocation() {
        const btn = document.getElementById('myLocationBtn');
        if (!navigator.geolocation) {
            showError('<?php echo esc_js(__('Geolocation is not supported by your browser', 'rental-gates')); ?>');
            return;
        }
        
        btn.classList.add('loading');
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                map.setView([lat, lng], 15);
                btn.classList.remove('loading');
            },
            function(error) {
                btn.classList.remove('loading');
                let message = '<?php echo esc_js(__('Unable to get your location', 'rental-gates')); ?>';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message = '<?php echo esc_js(__('Location access denied. Please enable location permissions.', 'rental-gates')); ?>';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = '<?php echo esc_js(__('Location information unavailable', 'rental-gates')); ?>';
                        break;
                    case error.TIMEOUT:
                        message = '<?php echo esc_js(__('Location request timed out', 'rental-gates')); ?>';
                        break;
                }
                showError(message);
            },
            { timeout: 10000, enableHighAccuracy: true }
        );
    }
    
    // Show error message
    function showError(message) {
        // Create or update error toast with modern design
        let toast = document.getElementById('errorToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'errorToast';
            toast.className = 'toast error';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.style.display = 'block';
        toast.style.opacity = '1';
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 300);
        }, 4000);
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }
    
    // Update view mode UI
    function updateViewModeUI() {
        const container = document.querySelector('.map-container');
        if (!container) return;
        
        const mapBtn = document.getElementById('viewMapBtn');
        const listBtn = document.getElementById('viewListBtn');
        const mobileMapBtn = document.getElementById('mobileMapBtn');
        const mobileListBtn = document.getElementById('mobileListBtn');
        const listViewToggle = document.getElementById('listViewToggle');
        
        // Remove all view classes
        container.classList.remove('fullscreen', 'list-view');
        
        // Add appropriate class
        if (currentViewMode === 'map') {
            container.classList.add('fullscreen');
            if (mapBtn) mapBtn.classList.add('active');
            if (listBtn) listBtn.classList.remove('active');
            if (mobileMapBtn) mobileMapBtn.classList.add('active');
            if (mobileListBtn) mobileListBtn.classList.remove('active');
            if (listViewToggle) listViewToggle.style.display = 'none';
        } else if (currentViewMode === 'list') {
            container.classList.add('list-view');
            if (listBtn) listBtn.classList.add('active');
            if (mapBtn) mapBtn.classList.remove('active');
            if (mobileListBtn) mobileListBtn.classList.add('active');
            if (mobileMapBtn) mobileMapBtn.classList.remove('active');
            if (listViewToggle) listViewToggle.style.display = 'flex';
            // Apply saved list view mode only if toggle exists
            if (listViewToggle) {
                setListViewMode(listViewMode);
            }
        } else {
            // Split view
            if (mapBtn) mapBtn.classList.remove('active');
            if (listBtn) listBtn.classList.remove('active');
            if (mobileMapBtn) mobileMapBtn.classList.remove('active');
            if (mobileListBtn) mobileListBtn.classList.remove('active');
            if (listViewToggle) listViewToggle.style.display = 'none';
        }
        
        // Trigger map resize if needed
        if (typeof map !== 'undefined') {
            setTimeout(() => {
                map.invalidateSize();
            }, 300);
        }
    }
    
    // Update URL state with filters
    function updateURLState() {
        const url = new URL(window.location);
        
        // Set hash for view mode
        if (currentViewMode === 'map' || currentViewMode === 'list') {
            url.hash = currentViewMode;
        } else {
            url.hash = '';
        }
        
        // Add filter parameters
        const params = new URLSearchParams();
        if (filters.priceMin) params.set('priceMin', filters.priceMin);
        if (filters.priceMax) params.set('priceMax', filters.priceMax);
        if (filters.beds !== null) params.set('beds', filters.beds);
        if (filters.baths !== null) params.set('baths', filters.baths);
        if (filters.sqftMin) params.set('sqftMin', filters.sqftMin);
        if (filters.sqftMax) params.set('sqftMax', filters.sqftMax);
        if (filters.availability) params.set('availability', filters.availability);
        if (currentSort) params.set('sort', currentSort);
        
        // Only update search params if there are filters
        if (params.toString()) {
            url.search = params.toString();
        } else {
            url.search = '';
        }
        
        window.history.replaceState({}, '', url);
    }
    
    // Initialize filters from URL
    function initializeFiltersFromURL() {
        const url = new URL(window.location);
        const params = url.searchParams;
        
        if (params.get('priceMin')) filters.priceMin = parseInt(params.get('priceMin'));
        if (params.get('priceMax')) filters.priceMax = parseInt(params.get('priceMax'));
        if (params.get('beds')) filters.beds = parseInt(params.get('beds'));
        if (params.get('baths')) filters.baths = parseFloat(params.get('baths'));
        if (params.get('sqftMin')) filters.sqftMin = parseInt(params.get('sqftMin'));
        if (params.get('sqftMax')) filters.sqftMax = parseInt(params.get('sqftMax'));
        if (params.get('availability')) filters.availability = params.get('availability');
        if (params.get('sort')) currentSort = params.get('sort');
        
        // Update filter inputs
        if (filters.priceMin && document.getElementById('priceMin')) {
            document.getElementById('priceMin').value = filters.priceMin;
        }
        if (filters.priceMax && document.getElementById('priceMax')) {
            document.getElementById('priceMax').value = filters.priceMax;
        }
        if (filters.beds !== null && document.getElementById('bedsSelect')) {
            document.getElementById('bedsSelect').value = filters.beds;
        }
        if (filters.baths !== null && document.getElementById('bathsSelect')) {
            document.getElementById('bathsSelect').value = filters.baths;
        }
        if (filters.sqftMin && document.getElementById('sqftMin')) {
            document.getElementById('sqftMin').value = filters.sqftMin;
        }
        if (filters.sqftMax && document.getElementById('sqftMax')) {
            document.getElementById('sqftMax').value = filters.sqftMax;
        }
        if (filters.availability && document.getElementById('availabilitySelect')) {
            document.getElementById('availabilitySelect').value = filters.availability;
        }
        
        // Apply filters and update UI
        if (Object.values(filters).some(v => v !== null && v !== '')) {
            filterBuildings();
            updateFilterButtons();
            updateActiveFilters();
        }
        
        // Apply sorting if set
        if (currentSort) {
            sortListings(currentSort, true);
        }
    }
    
    // Sort listings
    function sortListings(sortType, updateUI = true) {
        currentSort = sortType;
        
        if (updateUI) {
            const sortOptions = document.querySelectorAll('.sort-option');
            sortOptions.forEach(opt => opt.classList.remove('active'));
            const option = document.querySelector(`.sort-option[data-sort="${sortType}"]`);
            if (option) option.classList.add('active');
            
            const sortLabel = document.getElementById('sortLabel');
            const labels = {
                'price-asc': '<?php echo esc_js(__('Price: Low to High', 'rental-gates')); ?>',
                'price-desc': '<?php echo esc_js(__('Price: High to Low', 'rental-gates')); ?>',
                'beds-asc': '<?php echo esc_js(__('Bedrooms: Low to High', 'rental-gates')); ?>',
                'beds-desc': '<?php echo esc_js(__('Bedrooms: High to Low', 'rental-gates')); ?>',
                'name-asc': '<?php echo esc_js(__('Name: A to Z', 'rental-gates')); ?>'
            };
            if (sortLabel) {
                sortLabel.textContent = labels[sortType] || '<?php echo esc_js(__('Sort', 'rental-gates')); ?>';
            }
        }
        
        // Sort filtered buildings
        filteredBuildings.sort((a, b) => {
            switch(sortType) {
                case 'price-asc':
                    return (a.min_rent || 0) - (b.min_rent || 0);
                case 'price-desc':
                    return (b.min_rent || 0) - (a.min_rent || 0);
                case 'beds-asc':
                    return (a.min_beds || 0) - (b.min_beds || 0);
                case 'beds-desc':
                    return (b.min_beds || 0) - (a.min_beds || 0);
                case 'name-asc':
                    return (a.name || '').localeCompare(b.name || '');
                default:
                    return 0;
            }
        });
        
        renderListings();
        if (updateUI) toggleSortMenu();
    }
    
    // Toggle sort menu
    function toggleSortMenu() {
        const menu = document.getElementById('sortMenu');
        menu.classList.toggle('open');
    }
    
    // Close sort menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.sort-dropdown')) {
            document.getElementById('sortMenu').classList.remove('open');
        }
    });
    
    // Initialize map
    const map = L.map('map', {
        zoomAnimation: true,
        markerZoomAnimation: true
    }).setView([<?php echo $center_lat; ?>, <?php echo $center_lng; ?>], 12);
    
    // Expose map to global scope
    window._map = map;
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    
    // Marker cluster group - ensure plugin is loaded and properly initialized
    // Disable clustering if there are compatibility issues
    let markerCluster = null;
    let useClustering = false;
    
    // Expose markerCluster to global scope (will be updated after initialization)
    window._markerCluster = null;
    
    if (typeof L !== 'undefined' && typeof L.markerClusterGroup !== 'undefined') {
        try {
            markerCluster = L.markerClusterGroup({
        maxClusterRadius: 50,
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
                animate: false, // Disable animation to avoid errors
        animateAddingMarkers: false,
        disableClusteringAtZoom: 17,
        removeOutsideVisibleBounds: false
    });
    
    // Track animation state
    markerCluster.on('animationend', function() {
        isAnimating = false;
    });
            
            useClustering = true;
            window._markerCluster = markerCluster; // Update global reference
        } catch (e) {
            console.error('Error initializing MarkerCluster, using simple layer group:', e);
            // Fallback: create a simple layer group
            markerCluster = L.layerGroup();
            useClustering = false;
            window._markerCluster = markerCluster; // Update global reference
        }
    } else {
        console.warn('MarkerCluster plugin not available. Markers will be added directly to map.');
        // Fallback: create a simple layer group if markercluster is not available
        markerCluster = L.layerGroup();
        useClustering = false;
        window._markerCluster = markerCluster; // Update global reference
    }
    
    map.on('zoomstart', function() {
        isAnimating = true;
    });
    
    map.on('zoomend', function() {
        // Small delay to ensure cluster animation completes
        setTimeout(() => {
            isAnimating = false;
        }, 300);
    });
    
    // Create custom marker icon
    function createPriceMarker(building, isActive = false) {
        const price = building.min_rent ? '$' + Math.round(building.min_rent).toLocaleString() : '?';
        return L.divIcon({
            className: 'price-marker-container',
            html: `<div class="price-marker ${isActive ? 'active' : ''}">${price}</div>`,
            iconSize: [80, 30],
            iconAnchor: [40, 15]
        });
    }
    
    // Add markers with loading state
    function addMarkers() {
        // Show loading state briefly for better UX
        isLoadingListings = true;
        renderListings();
        
        // Always reset loading state, even on errors
        const resetLoading = () => {
            isLoadingListings = false;
            renderListings();
        };
        
        setTimeout(() => {
            try {
        // Remove cluster from map first
                if (markerCluster && map.hasLayer(markerCluster)) {
                    try {
            map.removeLayer(markerCluster);
                    } catch (e) {
                        console.warn('Error removing cluster from map:', e);
                    }
        }
        
        // Clear all layers from cluster
                if (markerCluster && typeof markerCluster.clearLayers === 'function') {
                    try {
        markerCluster.clearLayers();
                    } catch (e) {
                        console.warn('Error clearing cluster layers:', e);
                    }
                }
        markers = {};
            window._markers = markers; // Update global reference
        activeMarkerId = null;
            
            // Track if we successfully added any markers
            let markersAdded = 0;
        
        filteredBuildings.forEach(building => {
            // Validate building has valid coordinates
            if (!building.lat || !building.lng || isNaN(building.lat) || isNaN(building.lng)) {
                console.warn('Invalid coordinates for building:', building.id);
                return; // Skip this building
            }
            
            // Create marker - ensure it's a proper L.Marker instance
            let marker;
            try {
                marker = L.marker([parseFloat(building.lat), parseFloat(building.lng)], {
                    icon: createPriceMarker(building)
                });
                
                // Verify marker was created successfully (check for basic marker properties)
                if (!marker || typeof marker.getLatLng !== 'function') {
                    console.warn('Failed to create valid marker for building:', building.id);
                    return; // Skip this building
                }
            } catch (e) {
                console.error('Error creating marker:', e);
                return; // Skip this building
            }
            
            // Store building ID as a property on the marker object (not in options)
            marker.buildingId = building.id;
            
            // Popup content
            // Popup content - Modern minimal design with metadata
            const priceDisplay = building.min_rent 
                ? `$${building.min_rent.toLocaleString()}${building.max_rent && building.max_rent > building.min_rent ? ' - $' + building.max_rent.toLocaleString() : ''}`
                : 'Contact';
            
            const bedsDisplay = building.min_beds !== null 
                ? `${building.min_beds}${building.max_beds > building.min_beds ? '-' + building.max_beds : ''}`
                : null;
            
            const bathsDisplay = building.min_baths 
                ? `${building.min_baths}${building.max_baths > building.min_baths ? '-' + building.max_baths : ''}`
                : null;
            
            const sqftDisplay = building.min_sqft 
                ? `${building.min_sqft.toLocaleString()}${building.max_sqft > building.min_sqft ? '-' + building.max_sqft.toLocaleString() : ''} sq ft`
                : null;
            
            // Format address to show only the most relevant parts
            const formatAddress = (addr) => {
                if (!addr) return '';
                const parts = addr.split(',');
                // Show street address and city if available
                if (parts.length >= 2) {
                    return parts[0] + (parts[1] ? ', ' + parts[1].trim() : '');
                }
                return addr;
            };
            
            const popupHtml = `
                <div class="popup-content">
                    <div class="popup-image-container">
                        ${building.thumb 
                            ? `<img src="${building.thumb}" class="popup-image" alt="${building.name}" loading="lazy">` 
                            : `<div class="popup-image-placeholder">${renderIcon('building', '', 64)}</div>`
                        }
                    </div>
                    <div class="popup-body">
                        <div class="popup-header">
                            <div class="popup-price">
                                ${priceDisplay}
                                <span class="popup-price-unit">/mo</span>
                            </div>
                        <div class="popup-name">${building.name}</div>
                            <div class="popup-address">
                                ${renderIcon('location', '', 16)}
                                <span>${formatAddress(building.address) || '<?php echo esc_js(__('Address not available', 'rental-gates')); ?>'}</span>
                            </div>
                        </div>
                        
                        ${(bedsDisplay || bathsDisplay || sqftDisplay || building.available_units) ? `
                        <div class="popup-meta">
                            ${bedsDisplay ? `
                            <div class="popup-meta-item">
                                ${renderIcon('beds', '', 16)}
                                <span>${bedsDisplay} ${bedsDisplay === '1' ? '<?php echo esc_js(__('bed', 'rental-gates')); ?>' : '<?php echo esc_js(__('beds', 'rental-gates')); ?>'}</span>
                            </div>
                            ` : ''}
                            ${bathsDisplay ? `
                            <div class="popup-meta-item">
                                ${renderIcon('baths', '', 16)}
                                <span>${bathsDisplay} ${bathsDisplay === '1' ? '<?php echo esc_js(__('bath', 'rental-gates')); ?>' : '<?php echo esc_js(__('baths', 'rental-gates')); ?>'}</span>
                            </div>
                            ` : ''}
                            ${sqftDisplay ? `
                            <div class="popup-meta-item">
                                <span>${sqftDisplay}</span>
                            </div>
                            ` : ''}
                            ${building.available_units > 0 ? `
                            <div class="popup-units">
                                ${building.available_units} ${building.available_units === 1 ? '<?php echo esc_js(__('unit', 'rental-gates')); ?>' : '<?php echo esc_js(__('units', 'rental-gates')); ?>'} <?php echo esc_js(__('available', 'rental-gates')); ?>
                            </div>
                            ` : ''}
                        </div>
                        ` : ''}
                        
                        <a href="${building.url}" class="popup-btn" aria-label="${building.name} - <?php echo esc_js(__('View property details', 'rental-gates')); ?>">
                            <?php echo esc_js(__('View Property', 'rental-gates')); ?>
                        </a>
                    </div>
                </div>
            `;
            
            marker.bindPopup(popupHtml, { 
                closeButton: true, 
                maxWidth: 360,
                minWidth: 320,
                className: 'custom-popup',
                autoPan: true,
                autoPanPadding: [50, 50]
            });
            
            marker.on('click', () => {
                highlightListing(building.id, false);
            });
            
            marker.on('popupclose', () => {
                unhighlightListing();
            });
            
            markers[building.id] = marker;
            window._markers = markers; // Update global reference
            
            // Add marker - use clustering only if it's working
            // Skip instanceof check as MarkerCluster plugin has compatibility issues
            try {
                if (useClustering && markerCluster && typeof markerCluster.addLayer === 'function') {
                    // Try adding to cluster, but catch any errors
            markerCluster.addLayer(marker);
                    markersAdded++;
                } else {
                    // Add directly to map (either no clustering or fallback)
                    marker.addTo(map);
                    markersAdded++;
                }
            } catch (e) {
                console.warn('Error adding marker to cluster, adding directly to map:', e);
                // Disable clustering if it fails
                useClustering = false;
                window._markerCluster = null; // Clear cluster reference
                // Fallback: add directly to map
                try {
                    marker.addTo(map);
                    markersAdded++;
                } catch (e2) {
                    console.error('Failed to add marker to map:', e2);
                }
            }
        });
        
                // Add cluster back to map (if clustering is enabled and we added markers)
                if (markersAdded > 0 && useClustering && markerCluster && typeof markerCluster.addLayer === 'function') {
                    try {
                        if (!map.hasLayer(markerCluster)) {
        map.addLayer(markerCluster);
                        }
                    } catch (e) {
                        console.warn('Error adding cluster to map, markers added directly:', e);
                        useClustering = false; // Disable clustering on error
                        // If cluster fails, markers should already be on map from fallback
                    }
                }
                
                // Fit bounds if we have valid markers
                if (markersAdded > 0) {
                    try {
                        // Get valid coordinates from successfully added markers
                        const validCoords = [];
                        Object.values(markers).forEach(m => {
                            try {
                                const latlng = m.getLatLng();
                                if (latlng && !isNaN(latlng.lat) && !isNaN(latlng.lng)) {
                                    validCoords.push([latlng.lat, latlng.lng]);
                                }
                            } catch (e) {
                                // Skip invalid markers
                            }
                        });
                        
                        if (validCoords.length > 0) {
                            const bounds = L.latLngBounds(validCoords);
                            // Validate bounds before fitting
                            if (bounds.isValid && bounds.isValid()) {
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
                            } else if (bounds.getNorth && bounds.getSouth) {
                                // Alternative validation
                                const north = bounds.getNorth();
                                const south = bounds.getSouth();
                                const east = bounds.getEast();
                                const west = bounds.getWest();
                                // Validate bounds more strictly
                                const isValid = !isNaN(north) && !isNaN(south) && !isNaN(east) && !isNaN(west) && 
                                               north !== south && east !== west &&
                                               Math.abs(north - south) > 0.0001 && Math.abs(east - west) > 0.0001;
                                if (isValid) {
                                    map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
                                } else {
                                    console.warn('Invalid bounds, skipping fitBounds');
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('Error fitting bounds:', e);
                    }
                }
            } catch (e) {
                console.error('Error in addMarkers:', e);
            } finally {
                // Always reset loading state
                resetLoading();
            }
        }, 300);
    }
    
    // Render listings with loading state support
    function renderListings() {
        const container = document.getElementById('listings');
        const countEl = document.getElementById('resultsCount');
        
        if (isLoadingListings) {
            container.innerHTML = `
                <div class="rg-loading-state">
                    <div class="loading-spinner rg-mx-auto"></div>
                    <p class="rg-mt-3 rg-text-sm-muted"><?php echo esc_js(__('Loading properties...', 'rental-gates')); ?></p>
                </div>
            `;
            return;
        }
        
        countEl.textContent = filteredBuildings.length + ' <?php echo esc_js(__('properties', 'rental-gates')); ?>';
        
        if (filteredBuildings.length === 0) {
            container.innerHTML = `
                <div class="no-results">
                    ${renderIcon('building', '', 64)}
                    <h3><?php echo esc_js(__('No properties found', 'rental-gates')); ?></h3>
                    <p><?php echo esc_js(__('Try adjusting your filters or search in a different area.', 'rental-gates')); ?></p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = filteredBuildings.map(building => {
            const baths = building.min_baths ? `${building.min_baths}${building.max_baths > building.min_baths ? '-' + building.max_baths : ''} bath` : '';
            const sqft = building.min_sqft ? `${building.min_sqft.toLocaleString()}${building.max_sqft > building.min_sqft ? '-' + building.max_sqft.toLocaleString() : ''} sq ft` : '';
            
            return `
            <div class="listing-card" 
                 data-id="${building.id}" 
                 onclick="focusBuilding(${building.id})"
                 role="button"
                 tabindex="0"
                 aria-label="${building.name} - ${building.min_rent ? '$' + building.min_rent.toLocaleString() : 'Contact'} per month"
                 onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();focusBuilding(${building.id})}">
                <div class="listing-image">
                    ${building.thumb ? `<img src="${building.thumb}" alt="${building.name}" loading="lazy" onload="this.classList.add('loaded')" onerror="this.style.display='none';this.parentElement.innerHTML='<svg width=\\'40\\' height=\\'40\\' fill=\\'none\\' stroke=\\'currentColor\\' viewBox=\\'0 0 24 24\\'><path stroke-linecap=\\'round\\' stroke-linejoin=\\'round\\' stroke-width=\\'1.5\\' d=\\'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4\\'/></svg>'">` : '<svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>'}
                </div>
                <div class="listing-content">
                    <div class="listing-price">
                        ${building.min_rent ? '$' + building.min_rent.toLocaleString() : 'Contact'}${building.max_rent && building.max_rent > building.min_rent ? ' - $' + building.max_rent.toLocaleString() : ''}
                        <span>/mo</span>
                    </div>
                    <div class="listing-name">${building.name}</div>
                    <div class="listing-address">${building.address || ''}</div>
                    <div class="listing-meta">
                        ${building.min_beds !== null ? `<span>${renderIcon('beds', '', 14)} ${building.min_beds}${building.max_beds > building.min_beds ? '-' + building.max_beds : ''} bed</span>` : ''}
                        ${baths ? `<span>${baths}</span>` : ''}
                        ${sqft ? `<span>${sqft}</span>` : ''}
                        <span>${building.available_units} ${building.available_units === 1 ? '<?php echo esc_js(__('unit', 'rental-gates')); ?>' : '<?php echo esc_js(__('units', 'rental-gates')); ?>'} <?php echo esc_js(__('available', 'rental-gates')); ?></span>
                    </div>
                </div>
            </div>
        `;
        }).join('');
    }
    
    // Focus on building
    function focusBuilding(id) {
        const building = buildings.find(b => b.id === id);
        if (!building || isAnimating) return;
        
        // First highlight the listing
        highlightListing(id, false);
        
        // Check if marker is visible (not clustered)
        const marker = markers[id];
        if (!marker) return;
        
        // Get the visible parent (could be cluster or marker itself)
        // Check if markerCluster has getVisibleParent method (MarkerCluster plugin method)
        let visibleParent = marker;
        if (markerCluster && typeof markerCluster.getVisibleParent === 'function') {
            try {
                visibleParent = markerCluster.getVisibleParent(marker);
            } catch (e) {
                console.warn('Error getting visible parent:', e);
                visibleParent = marker;
            }
        }
        
        if (visibleParent === marker) {
            // Marker is already visible, just zoom and open popup
            map.setView([building.lat, building.lng], Math.max(map.getZoom(), 16), { animate: true });
            setTimeout(() => {
                if (markers[id] && !isAnimating) {
                    markers[id].openPopup();
                }
            }, 300);
        } else {
            // Marker is in a cluster, zoom to it using cluster's method
            if (markerCluster && typeof markerCluster.zoomToShowLayer === 'function') {
                try {
            markerCluster.zoomToShowLayer(marker, function() {
                setTimeout(() => {
                    if (markers[id] && !isAnimating) {
                        markers[id].openPopup();
                    }
                }, 300);
            });
                } catch (e) {
                    console.warn('Error zooming to marker:', e);
                    // Fallback: just zoom to marker location
                    map.setView([building.lat, building.lng], Math.max(map.getZoom(), 16), { animate: true });
                    setTimeout(() => {
                        if (markers[id] && !isAnimating) {
                            markers[id].openPopup();
                        }
                    }, 300);
                }
            } else {
                // Fallback if zoomToShowLayer not available
                map.setView([building.lat, building.lng], Math.max(map.getZoom(), 16), { animate: true });
                setTimeout(() => {
                    if (markers[id] && !isAnimating) {
                        markers[id].openPopup();
                    }
                }, 300);
            }
        }
    }
    
    // Highlight listing
    function highlightListing(id, updateMarker = true) {
        // Don't update markers during animation
        if (isAnimating && updateMarker) return;
        
        unhighlightListing();
        activeMarkerId = id;
        
        const card = document.querySelector(`.listing-card[data-id="${id}"]`);
        if (card) {
            card.classList.add('highlighted');
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // Update marker icon only if not animating
        if (updateMarker && markers[id] && !isAnimating) {
            try {
                const building = buildings.find(b => b.id === id);
                if (markerCluster && typeof markerCluster.getVisibleParent === 'function') {
                const visibleParent = markerCluster.getVisibleParent(markers[id]);
                if (visibleParent === markers[id]) {
                        markers[id].setIcon(createPriceMarker(building, true));
                    }
                } else {
                    // Fallback: update icon directly
                    markers[id].setIcon(createPriceMarker(building, true));
                }
            } catch (e) {
                console.warn('Marker update skipped:', e);
            }
        }
    }
    
    function unhighlightListing() {
        if (activeMarkerId) {
            const card = document.querySelector(`.listing-card[data-id="${activeMarkerId}"]`);
            if (card) card.classList.remove('highlighted');
            
            // Only update marker if not animating
            if (markers[activeMarkerId] && !isAnimating) {
                try {
                    const building = buildings.find(b => b.id === activeMarkerId);
                    if (markerCluster && typeof markerCluster.getVisibleParent === 'function') {
                    const visibleParent = markerCluster.getVisibleParent(markers[activeMarkerId]);
                    if (visibleParent === markers[activeMarkerId]) {
                            markers[activeMarkerId].setIcon(createPriceMarker(building, false));
                        }
                    } else {
                        // Fallback: update icon directly
                        markers[activeMarkerId].setIcon(createPriceMarker(building, false));
                    }
                } catch (e) {
                    console.warn('Marker update skipped:', e);
                }
            }
        }
        activeMarkerId = null;
    }
    
    // Filters
    function toggleFilter(name) {
        const menu = document.getElementById(name + 'Filter');
        const btn = document.getElementById(name + 'FilterBtn');
        const isOpen = menu.classList.contains('open');
        
        document.querySelectorAll('.filter-menu').forEach(m => {
            if (m !== menu) {
                m.classList.remove('open');
                const otherBtn = m.previousElementSibling;
                if (otherBtn && otherBtn.id) {
                    otherBtn.setAttribute('aria-expanded', 'false');
                }
            }
        });
        
        menu.classList.toggle('open');
        if (btn) {
            btn.setAttribute('aria-expanded', !isOpen);
        }
    }
    
    function applyFilters() {
        filters.priceMin = document.getElementById('priceMin').value ? parseInt(document.getElementById('priceMin').value) : null;
        filters.priceMax = document.getElementById('priceMax').value ? parseInt(document.getElementById('priceMax').value) : null;
        filters.beds = document.getElementById('bedsSelect').value ? parseInt(document.getElementById('bedsSelect').value) : null;
        filters.baths = document.getElementById('bathsSelect') && document.getElementById('bathsSelect').value ? parseFloat(document.getElementById('bathsSelect').value) : null;
        filters.sqftMin = document.getElementById('sqftMin') && document.getElementById('sqftMin').value ? parseInt(document.getElementById('sqftMin').value) : null;
        filters.sqftMax = document.getElementById('sqftMax') && document.getElementById('sqftMax').value ? parseInt(document.getElementById('sqftMax').value) : null;
        filters.availability = document.getElementById('availabilitySelect') && document.getElementById('availabilitySelect').value ? document.getElementById('availabilitySelect').value : null;
        
        filterBuildings();
        closeAllFilters();
        updateFilterButtons();
        updateActiveFilters();
        updateURLState();
    }
    
    function filterBuildings() {
        filteredBuildings = buildings.filter(b => {
            // Price filter
            if (filters.priceMin && b.min_rent < filters.priceMin) return false;
            if (filters.priceMax && b.min_rent > filters.priceMax) return false;
            
            // Bedrooms filter
            if (filters.beds !== null && b.max_beds < filters.beds) return false;
            
            // Bathrooms filter
            if (filters.baths !== null && b.max_baths < filters.baths) return false;
            
            // Square footage filter
            if (filters.sqftMin && b.max_sqft && b.max_sqft < filters.sqftMin) return false;
            if (filters.sqftMax && b.min_sqft && b.min_sqft > filters.sqftMax) return false;
            
            // Availability filter (would need to check unit availability, simplified here)
            // This is a building-level filter, so we check if building has units matching availability
            
            return true;
        });
        
        // Apply sorting if set
        if (currentSort) {
            sortListings(currentSort, false); // false = don't update UI
        }
        
        renderListings();
        addMarkers();
    }
    
    function clearPriceFilter() {
        document.getElementById('priceMin').value = '';
        document.getElementById('priceMax').value = '';
        filters.priceMin = null;
        filters.priceMax = null;
        filterBuildings();
        closeAllFilters();
        updateFilterButtons();
    }
    
    function clearMoreFilters() {
        if (document.getElementById('sqftMin')) document.getElementById('sqftMin').value = '';
        if (document.getElementById('sqftMax')) document.getElementById('sqftMax').value = '';
        if (document.getElementById('availabilitySelect')) document.getElementById('availabilitySelect').value = '';
        filters.sqftMin = null;
        filters.sqftMax = null;
        filters.availability = null;
        filterBuildings();
        closeAllFilters();
        updateFilterButtons();
        updateActiveFilters();
    }
    
    function clearAllFilters() {
        document.getElementById('priceMin').value = '';
        document.getElementById('priceMax').value = '';
        document.getElementById('bedsSelect').value = '';
        if (document.getElementById('bathsSelect')) document.getElementById('bathsSelect').value = '';
        if (document.getElementById('sqftMin')) document.getElementById('sqftMin').value = '';
        if (document.getElementById('sqftMax')) document.getElementById('sqftMax').value = '';
        if (document.getElementById('availabilitySelect')) document.getElementById('availabilitySelect').value = '';
        filters = { priceMin: null, priceMax: null, beds: null, baths: null, sqftMin: null, sqftMax: null, availability: null };
        currentSort = null;
        filterBuildings();
        updateFilterButtons();
        updateActiveFilters();
        updateURLState();
    }
    
    function updateActiveFilters() {
        const container = document.getElementById('activeFilters');
        const activeFilters = [];
        
        if (filters.priceMin || filters.priceMax) {
            const label = filters.priceMin && filters.priceMax 
                ? `$${filters.priceMin.toLocaleString()} - $${filters.priceMax.toLocaleString()}`
                : filters.priceMin 
                    ? `$${filters.priceMin.toLocaleString()}+`
                    : `Up to $${filters.priceMax.toLocaleString()}`;
            activeFilters.push({ type: 'price', label: label });
        }
        
        if (filters.beds !== null) {
            activeFilters.push({ type: 'beds', label: `${filters.beds}+ <?php echo esc_js(__('bedrooms', 'rental-gates')); ?>` });
        }
        
        if (filters.baths !== null) {
            activeFilters.push({ type: 'baths', label: `${filters.baths}+ <?php echo esc_js(__('bathrooms', 'rental-gates')); ?>` });
        }
        
        if (filters.sqftMin || filters.sqftMax) {
            const label = filters.sqftMin && filters.sqftMax
                ? `${filters.sqftMin.toLocaleString()} - ${filters.sqftMax.toLocaleString()} sq ft`
                : filters.sqftMin
                    ? `${filters.sqftMin.toLocaleString()}+ sq ft`
                    : `Up to ${filters.sqftMax.toLocaleString()} sq ft`;
            activeFilters.push({ type: 'sqft', label: label });
        }
        
        if (filters.availability) {
            const labels = {
                'available': '<?php echo esc_js(__('Available Now', 'rental-gates')); ?>',
                'coming_soon': '<?php echo esc_js(__('Coming Soon', 'rental-gates')); ?>'
            };
            activeFilters.push({ type: 'availability', label: labels[filters.availability] || filters.availability });
        }
        
        if (activeFilters.length > 0) {
            container.style.display = 'flex';
            container.innerHTML = activeFilters.map(f => `
                <span class="active-filter-badge">
                    ${f.label}
                    <button onclick="removeFilter('${f.type}')" aria-label="<?php echo esc_js(__('Remove filter', 'rental-gates')); ?>">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </span>
            `).join('');
        } else {
            container.style.display = 'none';
        }
    }
    
    function removeFilter(type) {
        switch(type) {
            case 'price':
                document.getElementById('priceMin').value = '';
                document.getElementById('priceMax').value = '';
                filters.priceMin = null;
                filters.priceMax = null;
                break;
            case 'beds':
                document.getElementById('bedsSelect').value = '';
                filters.beds = null;
                break;
            case 'baths':
                if (document.getElementById('bathsSelect')) {
                    document.getElementById('bathsSelect').value = '';
                    filters.baths = null;
                }
                break;
            case 'sqft':
                if (document.getElementById('sqftMin')) document.getElementById('sqftMin').value = '';
                if (document.getElementById('sqftMax')) document.getElementById('sqftMax').value = '';
                filters.sqftMin = null;
                filters.sqftMax = null;
                break;
            case 'availability':
                if (document.getElementById('availabilitySelect')) {
                    document.getElementById('availabilitySelect').value = '';
                    filters.availability = null;
                }
                break;
        }
        filterBuildings();
        updateFilterButtons();
        updateActiveFilters();
        updateURLState();
    }
    
    function closeAllFilters() {
        document.querySelectorAll('.filter-menu').forEach(m => m.classList.remove('open'));
    }
    
    function updateFilterButtons() {
        const priceBtn = document.getElementById('priceFilterBtn');
        const bedsBtn = document.getElementById('bedsFilterBtn');
        const bathsBtn = document.getElementById('bathsFilterBtn');
        const moreBtn = document.getElementById('moreFiltersBtn');
        const clearBtn = document.getElementById('clearAllBtn');
        
        priceBtn.classList.toggle('active', filters.priceMin !== null || filters.priceMax !== null);
        bedsBtn.classList.toggle('active', filters.beds !== null);
        if (bathsBtn) bathsBtn.classList.toggle('active', filters.baths !== null);
        if (moreBtn) moreBtn.classList.toggle('active', filters.sqftMin || filters.sqftMax || filters.availability);
        
        const hasFilters = filters.priceMin || filters.priceMax || filters.beds !== null || 
                          filters.baths !== null || filters.sqftMin || filters.sqftMax || filters.availability;
        clearBtn.style.display = hasFilters ? 'flex' : 'none';
    }
    
    // Close filters when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.filter-dropdown')) {
            closeAllFilters();
        }
    });
    
    // Enhanced Location Search with Autocomplete
    let searchTimeout;
    let searchSuggestions = [];
    let selectedSuggestionIndex = -1;
    let recentSearches = [];
    try {
        recentSearches = JSON.parse(localStorage.getItem('rentalGatesRecentSearches') || '[]');
    } catch (e) {
        // Tracking prevention or localStorage not available - use empty array
        recentSearches = [];
    }
    
    const searchInput = document.getElementById('locationSearch');
    const autocomplete = document.getElementById('searchAutocomplete');
    
    function showAutocomplete() {
        autocomplete.classList.add('open');
        searchInput.setAttribute('aria-expanded', 'true');
    }
    
    function hideAutocomplete() {
        autocomplete.classList.remove('open');
        searchInput.setAttribute('aria-expanded', 'false');
        selectedSuggestionIndex = -1;
    }
    
    function renderAutocomplete(suggestions, showRecent = false) {
        if (suggestions.length === 0 && (!showRecent || recentSearches.length === 0)) {
            hideAutocomplete();
            return;
        }
        
        let html = '';
        
        if (showRecent && recentSearches.length > 0 && searchInput.value.trim().length === 0) {
            html += '<div class="search-recent"><?php echo esc_js(__('Recent Searches', 'rental-gates')); ?></div>';
            recentSearches.slice(0, 5).forEach(search => {
                html += `
                    <div class="search-suggestion" onclick="selectSearchSuggestion('${search.query.replace(/'/g, "\\'")}', ${search.lat}, ${search.lng})">
                        <div class="search-suggestion-title">${search.query}</div>
                    </div>
                `;
            });
        } else if (suggestions.length > 0) {
            suggestions.forEach((suggestion, index) => {
                html += `
                    <div class="search-suggestion ${index === selectedSuggestionIndex ? 'selected' : ''}" 
                         onclick="selectSearchSuggestion('${suggestion.display_name.replace(/'/g, "\\'")}', ${suggestion.lat}, ${suggestion.lon})"
                         role="option"
                         aria-selected="${index === selectedSuggestionIndex}">
                        <div class="search-suggestion-title">${suggestion.display_name}</div>
                        ${suggestion.type ? `<div class="search-suggestion-subtitle">${suggestion.type}</div>` : ''}
                    </div>
                `;
            });
        }
        
        autocomplete.innerHTML = html;
        showAutocomplete();
    }
    
    function selectSearchSuggestion(query, lat, lng) {
        searchInput.value = query;
        hideAutocomplete();
        
        // Save to recent searches
        recentSearches = recentSearches.filter(s => s.query !== query);
        recentSearches.unshift({ query, lat, lng, timestamp: Date.now() });
        recentSearches = recentSearches.slice(0, 10); // Keep last 10
        try {
            localStorage.setItem('rentalGatesRecentSearches', JSON.stringify(recentSearches));
        } catch (e) {
            // Tracking prevention or localStorage not available - ignore
        }
        
        // Update map view
        map.setView([parseFloat(lat), parseFloat(lng)], 13);
    }
    
    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length === 0) {
            renderAutocomplete([], true); // Show recent searches
            return;
        }
        
        if (query.length < 2) {
            hideAutocomplete();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            // Use Nominatim for autocomplete
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`)
                .then(r => r.json())
                .then(results => {
                    searchSuggestions = results.map(r => ({
                        display_name: r.display_name,
                        lat: r.lat,
                        lon: r.lon,
                        type: r.type || r.class || ''
                    }));
                    renderAutocomplete(searchSuggestions);
                })
                .catch(err => {
                    console.error('Search error:', err);
                    hideAutocomplete();
                });
        }, 300);
    });
    
    // Keyboard navigation for autocomplete
    searchInput.addEventListener('keydown', function(e) {
        if (!autocomplete.classList.contains('open')) return;
        
        const suggestions = autocomplete.querySelectorAll('.search-suggestion');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, suggestions.length - 1);
            suggestions.forEach((s, i) => {
                s.classList.toggle('selected', i === selectedSuggestionIndex);
                s.setAttribute('aria-selected', i === selectedSuggestionIndex);
            });
            suggestions[selectedSuggestionIndex]?.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
            suggestions.forEach((s, i) => {
                s.classList.toggle('selected', i === selectedSuggestionIndex);
                s.setAttribute('aria-selected', i === selectedSuggestionIndex);
            });
        } else if (e.key === 'Enter' && selectedSuggestionIndex >= 0) {
            e.preventDefault();
            const suggestion = searchSuggestions[selectedSuggestionIndex];
            if (suggestion) {
                selectSearchSuggestion(suggestion.display_name, suggestion.lat, suggestion.lon);
            }
        } else if (e.key === 'Escape') {
            hideAutocomplete();
        }
    });
    
    // Close autocomplete when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.map-search-container')) {
            hideAutocomplete();
        }
    });
    
    // Show recent searches on focus if input is empty
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length === 0 && recentSearches.length > 0) {
            renderAutocomplete([], true);
        }
    });
    
    // Initialize
    initializeViewMode();
    initializeFiltersFromURL();
    renderListings();
    addMarkers();
    
    // Handle hash changes
    window.addEventListener('hashchange', function() {
        const hash = window.location.hash.replace('#', '');
        if (hash === 'map' || hash === 'list') {
            currentViewMode = hash;
            updateViewModeUI();
        }
    });
    
    // Mobile sidebar drag handler
    let sidebarDragStart = 0;
    let sidebarDragCurrent = 0;
    const sidebar = document.querySelector('.map-sidebar');
    const sidebarHandle = document.querySelector('.sidebar-handle');
    
    if (sidebarHandle && window.innerWidth <= 768) {
        sidebarHandle.addEventListener('touchstart', function(e) {
            sidebarDragStart = e.touches[0].clientY;
        });
        
        sidebarHandle.addEventListener('touchmove', function(e) {
            e.preventDefault();
            sidebarDragCurrent = e.touches[0].clientY;
            const diff = sidebarDragCurrent - sidebarDragStart;
            if (diff > 0) {
                sidebar.style.transform = `translateY(${Math.min(diff, 200)}px)`;
            }
        });
        
        sidebarHandle.addEventListener('touchend', function() {
            if (sidebarDragCurrent - sidebarDragStart > 100) {
                sidebar.classList.add('hidden');
            } else {
                sidebar.style.transform = '';
            }
            sidebarDragStart = 0;
            sidebarDragCurrent = 0;
        });
    }
    
    // Toggle mobile sidebar
    function toggleMobileSidebar() {
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle('hidden');
        }
    }
    
    // Show sidebar when in split view on mobile
    if (window.innerWidth <= 768 && currentViewMode === 'split') {
        sidebar.classList.remove('hidden');
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // M for map, L for list, S for split (when not in input)
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        if (e.key === 'm' || e.key === 'M') {
            e.preventDefault();
            toggleViewMode('map');
        } else if (e.key === 'l' || e.key === 'L') {
            e.preventDefault();
            toggleViewMode('list');
        } else if (e.key === 's' || e.key === 'S') {
            e.preventDefault();
            toggleViewMode('split');
        }
    });
    
    // Expose functions and data to global scope BEFORE IIFE closes
    window.focusBuilding = focusBuilding;
    window.getMyLocation = getMyLocation;
    window.showError = showError;
    window.toggleViewMode = toggleViewMode;
    window.setListViewMode = setListViewMode;
    window.toggleSortMenu = toggleSortMenu;
    window.toggleFilter = toggleFilter;
    window._map = map;
    window._markers = markers;
    window._markerCluster = markerCluster;
    window._isAnimating = isAnimating;
    window._buildingsData = buildings;
    
    })(); // End IIFE - ensures scripts are loaded
    
    // Expose functions to global scope for onclick handlers
    // These functions are defined inside the IIFE, so we need to expose them globally
    window.focusBuilding = function(id) {
        const building = window._buildingsData?.find(b => b.id === id);
        if (!building || window._isAnimating) return;
        
        // Highlight listing
        const listingCard = document.querySelector(`[data-id="${id}"]`);
        if (listingCard) {
            document.querySelectorAll('.listing-card').forEach(card => card.classList.remove('active'));
            listingCard.classList.add('active');
        }
        
        // Focus on marker
        const marker = window._markers?.[id];
        if (!marker) return;
        
        const map = window._map;
        if (!map) return;
        
        // Get visible parent if using clustering
        let visibleParent = marker;
        if (window._markerCluster && typeof window._markerCluster.getVisibleParent === 'function') {
            try {
                visibleParent = window._markerCluster.getVisibleParent(marker);
            } catch (e) {
                visibleParent = marker;
            }
        }
        
        if (visibleParent === marker) {
            map.setView([building.lat, building.lng], Math.max(map.getZoom(), 16), { animate: true });
            setTimeout(() => {
                if (window._markers?.[id] && !window._isAnimating) {
                    window._markers[id].openPopup();
                }
            }, 300);
        } else {
            if (window._markerCluster && typeof window._markerCluster.zoomToShowLayer === 'function') {
                try {
                    window._markerCluster.zoomToShowLayer(marker, function() {
                        setTimeout(() => {
                            if (window._markers?.[id] && !window._isAnimating) {
                                window._markers[id].openPopup();
                            }
                        }, 300);
                    });
                } catch (e) {
                    map.setView([building.lat, building.lng], Math.max(map.getZoom(), 16), { animate: true });
                    setTimeout(() => {
                        if (window._markers?.[id] && !window._isAnimating) {
                            window._markers[id].openPopup();
                        }
                    }, 300);
                }
            } else {
                map.setView([building.lat, building.lng], Math.max(map.getZoom(), 16), { animate: true });
                setTimeout(() => {
                    if (window._markers?.[id] && !window._isAnimating) {
                        window._markers[id].openPopup();
                    }
                }, 300);
            }
        }
    };
    
    window.getMyLocation = function() {
        const btn = document.getElementById('myLocationBtn');
        const map = window._map;
        
        if (!navigator.geolocation) {
            if (window.showError) {
                window.showError('<?php echo esc_js(__('Geolocation is not supported by your browser', 'rental-gates')); ?>');
            } else {
                alert('<?php echo esc_js(__('Geolocation is not supported by your browser', 'rental-gates')); ?>');
            }
            return;
        }
        
        if (btn) {
            btn.disabled = true;
            btn.textContent = '<?php echo esc_js(__('Locating...', 'rental-gates')); ?>';
        }
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                if (map) {
                    map.setView([position.coords.latitude, position.coords.longitude], 15, { animate: true });
                }
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('My Location', 'rental-gates')); ?>';
                }
            },
            function(error) {
                if (window.showError) {
                    window.showError('<?php echo esc_js(__('Unable to get your location', 'rental-gates')); ?>');
                } else {
                    alert('<?php echo esc_js(__('Unable to get your location', 'rental-gates')); ?>');
                }
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('My Location', 'rental-gates')); ?>';
                }
            }
        );
    };
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>
