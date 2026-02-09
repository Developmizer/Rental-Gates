<?php
/**
 * Public Unit Page
 * 
 * Public-facing unit listing page accessed via QR code or direct link.
 */
if (!defined('ABSPATH')) exit;

$building_slug = get_query_var('rental_gates_building_slug');
$unit_slug = get_query_var('rental_gates_unit_slug');

if (!$building_slug || !$unit_slug) {
    wp_redirect(home_url('/rental-gates/map'));
    exit;
}

$unit = Rental_Gates_Unit::get_by_slug($unit_slug, $building_slug);
if (!$unit) {
    wp_redirect(home_url('/rental-gates/map'));
    exit;
}

$building = Rental_Gates_Building::get($unit['building_id']);
if (!$building) {
    wp_redirect(home_url('/rental-gates/map'));
    exit;
}

$organization = Rental_Gates_Organization::get($building['organization_id']);

// Helper to extract URL from gallery item
function rg_unit_get_gallery_url($item) {
    if (empty($item)) return null;
    if (is_string($item)) return $item;
    if (is_array($item)) return $item['url'] ?? $item['thumbnail'] ?? null;
    return null;
}

// Parse and normalize gallery (convert objects to URLs)
$raw_gallery = !empty($unit['gallery']) ? $unit['gallery'] : array();
$gallery = array();
foreach ($raw_gallery as $img) {
    $url = rg_unit_get_gallery_url($img);
    if ($url) $gallery[] = $url;
}

$amenities = !empty($unit['amenities']) ? $unit['amenities'] : array();
$building_amenities = !empty($building['amenities']) ? $building['amenities'] : array();

// Availability info
$availability_labels = array(
    'available' => __('Available Now', 'rental-gates'),
    'coming_soon' => __('Coming Soon', 'rental-gates'),
    'occupied' => __('Occupied', 'rental-gates'),
    'renewal_pending' => __('Renewal Pending', 'rental-gates'),
    'unlisted' => __('Unlisted', 'rental-gates'),
);

$availability_colors = array(
    'available' => '#10b981',
    'coming_soon' => '#f59e0b',
    'occupied' => '#ef4444',
    'renewal_pending' => '#8b5cf6',
    'unlisted' => '#6b7280',
);

// Track QR scan if applicable
if (isset($_GET['qr']) && class_exists('Rental_Gates_QR')) {
    $qr_code = sanitize_text_field(wp_unslash($_GET['qr']));
    Rental_Gates_QR::track_scan($qr_code, 'unit', $unit['id']);
}

// Check if unit is available for applications
$can_apply = in_array($unit['availability'], array('available', 'coming_soon'));
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($unit['name']); ?> - <?php echo esc_html($building['name']); ?> | <?php echo esc_html($organization['name']); ?></title>
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo esc_attr($unit['name']); ?> - $<?php echo number_format($unit['rent_amount']); ?>/mo">
    <meta property="og:description" content="<?php echo esc_attr($unit['bedrooms']); ?> bed, <?php echo esc_attr($unit['bathrooms']); ?> bath at <?php echo esc_attr($building['name']); ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($gallery)): ?>
    <meta property="og:image" content="<?php echo esc_url($gallery[0]); ?>">
    <?php endif; ?>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --success: #10b981;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--gray-50); color: var(--gray-900); line-height: 1.5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        /* Header */
        .header { background: #fff; border-bottom: 1px solid var(--gray-200); padding: 16px 0; position: sticky; top: 0; z-index: 100; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .org-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--gray-900); }
        .org-logo { width: 40px; height: 40px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; }
        .org-name { font-weight: 600; font-size: 18px; }
        .back-link { color: var(--gray-500); text-decoration: none; display: flex; align-items: center; gap: 6px; font-size: 14px; }
        .back-link:hover { color: var(--primary); }
        
        /* Gallery */
        .gallery { position: relative; height: 450px; background: var(--gray-200); }
        .gallery-main { width: 100%; height: 100%; }
        .gallery-main img { width: 100%; height: 100%; object-fit: cover; }
        .gallery-thumbs { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; }
        .gallery-thumb { width: 60px; height: 40px; border-radius: 6px; overflow: hidden; cursor: pointer; border: 2px solid transparent; opacity: 0.7; transition: all 0.2s; }
        .gallery-thumb:hover, .gallery-thumb.active { opacity: 1; border-color: #fff; }
        .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .gallery-count { position: absolute; bottom: 16px; right: 16px; background: rgba(0,0,0,0.6); color: #fff; padding: 6px 12px; border-radius: 20px; font-size: 13px; }
        
        /* Content */
        .content { padding: 32px 0 60px; }
        .content-grid { display: grid; grid-template-columns: 1fr 380px; gap: 32px; }
        
        /* Unit Info */
        .unit-header { margin-bottom: 24px; }
        .unit-title { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .unit-location { display: flex; align-items: center; gap: 6px; color: var(--gray-500); font-size: 15px; }
        .unit-location a { color: var(--primary); text-decoration: none; }
        .unit-location a:hover { text-decoration: underline; }
        
        /* Price & Availability */
        .price-row { display: flex; align-items: center; justify-content: space-between; padding: 20px 0; border-bottom: 1px solid var(--gray-200); margin-bottom: 24px; }
        .price { font-size: 32px; font-weight: 700; color: var(--gray-900); }
        .price span { font-size: 16px; font-weight: 400; color: var(--gray-500); }
        .availability-badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; }
        .availability-badge .dot { width: 8px; height: 8px; border-radius: 50%; }
        
        /* Features */
        .features-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
        .feature-item { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 16px; text-align: center; }
        .feature-value { font-size: 24px; font-weight: 700; color: var(--gray-900); }
        .feature-label { font-size: 13px; color: var(--gray-500); margin-top: 4px; }
        
        /* Cards */
        .card { background: #fff; border-radius: 12px; border: 1px solid var(--gray-200); margin-bottom: 24px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--gray-100); font-weight: 600; font-size: 16px; }
        .card-body { padding: 20px; }
        
        /* Description */
        .description { color: var(--gray-700); line-height: 1.7; }
        
        /* Amenities */
        .amenities-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .amenity-tag { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: var(--gray-100); border-radius: 8px; font-size: 14px; color: var(--gray-700); }
        .amenity-tag svg { width: 16px; height: 16px; color: var(--success); }
        
        /* Sidebar */
        .sidebar { position: sticky; top: 100px; }
        
        /* Apply Card */
        .apply-card { background: #fff; border-radius: 16px; border: 1px solid var(--gray-200); padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .apply-card-title { font-size: 18px; font-weight: 600; margin-bottom: 16px; }
        .apply-btn { display: block; width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; transition: background 0.2s; }
        .apply-btn:hover { background: var(--primary-dark); }
        .apply-btn:disabled { background: var(--gray-300); cursor: not-allowed; }
        .apply-note { font-size: 13px; color: var(--gray-500); text-align: center; margin-top: 12px; }
        
        /* Contact Info */
        .contact-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100); }
        .contact-item:last-child { border-bottom: none; }
        .contact-icon { width: 40px; height: 40px; background: var(--gray-100); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--gray-600); }
        .contact-label { font-size: 12px; color: var(--gray-500); }
        .contact-value { font-size: 14px; color: var(--gray-900); }
        .contact-value a { color: var(--gray-900); text-decoration: none; }
        .contact-value a:hover { color: var(--primary); }
        
        /* Map */
        .map-container { height: 180px; border-radius: 12px; overflow: hidden; margin-top: 16px; }
        .map-container iframe { width: 100%; height: 100%; border: 0; }
        
        /* Footer */
        .footer { background: var(--gray-800); color: #fff; padding: 24px 0; }
        .footer-content { display: flex; justify-content: space-between; font-size: 14px; }
        .footer a { color: var(--gray-400); }
        
        /* Move-in Details */
        .move-in-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--gray-100); }
        .move-in-row:last-child { border-bottom: none; }
        .move-in-label { color: var(--gray-500); font-size: 14px; }
        .move-in-value { font-weight: 500; font-size: 14px; }
        
        @media (max-width: 992px) {
            .content-grid { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .gallery { height: 300px; }
        }
        
        @media (max-width: 576px) {
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .unit-title { font-size: 22px; }
            .price { font-size: 26px; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="<?php echo home_url('/rental-gates/building/' . $building['slug']); ?>" class="back-link">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <?php _e('Back to', 'rental-gates'); ?> <?php echo esc_html($building['name']); ?>
                </a>
                <a href="<?php echo home_url('/rental-gates/building/' . $building['slug']); ?>" class="org-brand">
                    <div class="org-logo"><?php echo strtoupper(substr($organization['name'], 0, 1)); ?></div>
                    <span class="org-name"><?php echo esc_html($organization['name']); ?></span>
                </a>
            </div>
        </div>
    </header>
    
    <!-- Gallery -->
    <section class="gallery">
        <?php if (!empty($gallery)): ?>
            <div class="gallery-main">
                <img src="<?php echo esc_url($gallery[0]); ?>" alt="<?php echo esc_attr($unit['name']); ?>" id="main-image">
            </div>
            <?php if (count($gallery) > 1): ?>
            <div class="gallery-thumbs">
                <?php foreach (array_slice($gallery, 0, 5) as $i => $img): ?>
                <div class="gallery-thumb <?php echo $i === 0 ? 'active' : ''; ?>" onclick="showImage(<?php echo $i; ?>)">
                    <img src="<?php echo esc_url($img); ?>" alt="">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="gallery-count"><?php echo count($gallery); ?> <?php _e('photos', 'rental-gates'); ?></div>
            <?php endif; ?>
        <?php else: ?>
            <div style="height: 100%; display: flex; align-items: center; justify-content: center; color: var(--gray-400);">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        <?php endif; ?>
    </section>
    
    <!-- Content -->
    <section class="content">
        <div class="container">
            <div class="content-grid">
                <div class="content-main">
                    <!-- Unit Header -->
                    <div class="unit-header">
                        <h1 class="unit-title"><?php echo esc_html($unit['name']); ?></h1>
                        <div class="unit-location">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                            <a href="<?php echo home_url('/rental-gates/building/' . $building['slug']); ?>"><?php echo esc_html($building['name']); ?></a>
                            <span>•</span>
                            <span><?php echo esc_html($building['derived_address']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Price & Availability -->
                    <div class="price-row">
                        <div class="price">$<?php echo number_format($unit['rent_amount']); ?><span>/mo</span></div>
                        <div class="availability-badge" style="background: <?php echo $availability_colors[$unit['availability']] ?? '#6b7280'; ?>20; color: <?php echo $availability_colors[$unit['availability']] ?? '#6b7280'; ?>;">
                            <span class="dot" style="background: <?php echo $availability_colors[$unit['availability']] ?? '#6b7280'; ?>;"></span>
                            <?php echo $availability_labels[$unit['availability']] ?? ucfirst($unit['availability']); ?>
                            <?php if ($unit['availability'] === 'coming_soon' && $unit['available_from']): ?>
                                - <?php echo date('M j, Y', strtotime($unit['available_from'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Features -->
                    <div class="features-grid">
                        <div class="feature-item">
                            <div class="feature-value"><?php echo intval($unit['bedrooms']); ?></div>
                            <div class="feature-label"><?php _e('Bedrooms', 'rental-gates'); ?></div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-value"><?php echo number_format($unit['bathrooms'], $unit['bathrooms'] == floor($unit['bathrooms']) ? 0 : 1); ?></div>
                            <div class="feature-label"><?php _e('Bathrooms', 'rental-gates'); ?></div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-value"><?php echo $unit['square_footage'] ? number_format($unit['square_footage']) : '—'; ?></div>
                            <div class="feature-label"><?php _e('Sq Ft', 'rental-gates'); ?></div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-value"><?php echo intval($unit['parking_spaces']); ?></div>
                            <div class="feature-label"><?php _e('Parking', 'rental-gates'); ?></div>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <?php if (!empty($unit['description'])): ?>
                    <div class="card">
                        <div class="card-header"><?php _e('About This Unit', 'rental-gates'); ?></div>
                        <div class="card-body">
                            <p class="description"><?php echo nl2br(esc_html($unit['description'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Unit Amenities -->
                    <?php if (!empty($amenities)): ?>
                    <div class="card">
                        <div class="card-header"><?php _e('Unit Features', 'rental-gates'); ?></div>
                        <div class="card-body">
                            <div class="amenities-grid">
                                <?php foreach ($amenities as $amenity): ?>
                                <span class="amenity-tag">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <?php echo esc_html($amenity); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Building Amenities -->
                    <?php if (!empty($building_amenities)): ?>
                    <div class="card">
                        <div class="card-header"><?php _e('Building Amenities', 'rental-gates'); ?></div>
                        <div class="card-body">
                            <div class="amenities-grid">
                                <?php foreach ($building_amenities as $amenity): ?>
                                <span class="amenity-tag">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <?php echo esc_html($amenity); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Additional Details -->
                    <div class="card">
                        <div class="card-header"><?php _e('Move-in Details', 'rental-gates'); ?></div>
                        <div class="card-body">
                            <div class="move-in-row">
                                <span class="move-in-label"><?php _e('Security Deposit', 'rental-gates'); ?></span>
                                <span class="move-in-value">$<?php echo number_format($unit['deposit_amount'] ?: $unit['rent_amount']); ?></span>
                            </div>
                            <?php if ($unit['available_from']): ?>
                            <div class="move-in-row">
                                <span class="move-in-label"><?php _e('Available From', 'rental-gates'); ?></span>
                                <span class="move-in-value"><?php echo date('F j, Y', strtotime($unit['available_from'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($unit['minimum_lease_months']): ?>
                            <div class="move-in-row">
                                <span class="move-in-label"><?php _e('Minimum Lease', 'rental-gates'); ?></span>
                                <span class="move-in-value"><?php echo $unit['minimum_lease_months']; ?> <?php _e('months', 'rental-gates'); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="move-in-row">
                                <span class="move-in-label"><?php _e('Unit Type', 'rental-gates'); ?></span>
                                <span class="move-in-value"><?php echo esc_html(ucfirst($unit['unit_type'] ?: 'Apartment')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Apply Card -->
                    <div class="apply-card">
                        <h3 class="apply-card-title"><?php _e('Interested in this unit?', 'rental-gates'); ?></h3>
                        <?php if ($can_apply): ?>
                            <a href="<?php echo home_url('/rental-gates/apply/' . $building['slug'] . '/' . $unit['slug']); ?>" class="apply-btn">
                                <?php _e('Apply Now', 'rental-gates'); ?>
                            </a>
                            <button class="apply-btn" style="background: transparent; color: var(--primary); border: 2px solid var(--primary); margin-top: 12px;" onclick="openInquiryModal()">
                                <?php _e('Ask a Question', 'rental-gates'); ?>
                            </button>
                            <p class="apply-note"><?php _e('Free to apply • Usually responds within 24 hours', 'rental-gates'); ?></p>
                        <?php else: ?>
                            <button class="apply-btn" onclick="openInquiryModal()">
                                <?php _e('Request Info', 'rental-gates'); ?>
                            </button>
                            <p class="apply-note"><?php _e('Get notified when this unit becomes available', 'rental-gates'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Contact Card -->
                    <div class="card" style="margin-top: 20px;">
                        <div class="card-header"><?php _e('Contact', 'rental-gates'); ?></div>
                        <div class="card-body">
                            <?php if (!empty($organization['contact_phone'])): ?>
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="contact-label"><?php _e('Phone', 'rental-gates'); ?></div>
                                    <div class="contact-value">
                                        <a href="tel:<?php echo esc_attr($organization['contact_phone']); ?>"><?php echo esc_html($organization['contact_phone']); ?></a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($organization['contact_email'])): ?>
                            <div class="contact-item">
                                <div class="contact-icon">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="contact-label"><?php _e('Email', 'rental-gates'); ?></div>
                                    <div class="contact-value">
                                        <a href="mailto:<?php echo esc_attr($organization['contact_email']); ?>"><?php echo esc_html($organization['contact_email']); ?></a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Map -->
                            <div class="map-container">
                                <iframe src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo ($building['longitude'] - 0.005); ?>%2C<?php echo ($building['latitude'] - 0.005); ?>%2C<?php echo ($building['longitude'] + 0.005); ?>%2C<?php echo ($building['latitude'] + 0.005); ?>&layer=mapnik&marker=<?php echo $building['latitude']; ?>%2C<?php echo $building['longitude']; ?>" loading="lazy"></iframe>
                            </div>
                            <div style="padding-top: 12px;">
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $building['latitude']; ?>,<?php echo $building['longitude']; ?>" target="_blank" style="color: var(--primary); font-size: 14px; text-decoration: none;">
                                    <?php _e('Get Directions', 'rental-gates'); ?> →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div>&copy; <?php echo date('Y'); ?> <?php echo esc_html($organization['name']); ?></div>
                <div><?php _e('Powered by', 'rental-gates'); ?> <a href="https://rentalgates.com">Rental Gates</a></div>
            </div>
        </div>
    </footer>
    
    <?php if (count($gallery) > 1): ?>
    <script>
    const images = <?php echo wp_json_encode($gallery); ?>;
    let currentIndex = 0;
    
    function showImage(index) {
        currentIndex = index;
        document.getElementById('main-image').src = images[index];
        document.querySelectorAll('.gallery-thumb').forEach((thumb, i) => {
            thumb.classList.toggle('active', i === index);
        });
    }
    </script>
    <?php endif; ?>
    
    <!-- Inquiry Modal -->
    <style>
        .inquiry-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        .inquiry-overlay.open { display: flex; }
        .inquiry-modal { background: #fff; border-radius: 20px; max-width: 480px; width: 100%; max-height: 90vh; overflow-y: auto; animation: modalIn 0.3s ease; position: relative; }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .inquiry-close { position: absolute; top: 16px; right: 16px; background: var(--gray-100); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; }
        .inquiry-close:hover { background: var(--gray-200); }
        .inquiry-header { padding: 24px 24px 0; text-align: center; }
        .inquiry-icon { width: 64px; height: 64px; background: linear-gradient(135deg, var(--primary), #8b5cf6); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .inquiry-icon svg { width: 32px; height: 32px; color: #fff; }
        .inquiry-header h3 { font-size: 24px; margin-bottom: 8px; }
        .inquiry-header p { color: var(--gray-500); font-size: 15px; }
        .inquiry-body { padding: 24px; }
        .inquiry-group { margin-bottom: 20px; }
        .inquiry-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: var(--gray-700); }
        .inquiry-group input, .inquiry-group textarea { width: 100%; padding: 12px 16px; border: 1px solid var(--gray-300); border-radius: 10px; font-size: 15px; font-family: inherit; transition: all 0.2s; }
        .inquiry-group input:focus, .inquiry-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .inquiry-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .inquiry-submit { width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .inquiry-submit:hover { background: var(--primary-dark); }
        .inquiry-submit:disabled { background: var(--gray-300); cursor: not-allowed; }
        .inquiry-note { text-align: center; font-size: 13px; color: var(--gray-500); margin-top: 16px; }
        .inquiry-success { text-align: center; padding: 40px 24px; }
        .inquiry-success-icon { width: 80px; height: 80px; background: #d1fae5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .inquiry-success-icon svg { width: 40px; height: 40px; color: var(--success); }
        .inquiry-success h3 { font-size: 24px; margin-bottom: 12px; }
        .inquiry-success p { color: var(--gray-500); margin-bottom: 24px; }
        .inquiry-success .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; }
        @media (max-width: 768px) { .inquiry-row { grid-template-columns: 1fr; } }
    </style>
    
    <div class="inquiry-overlay" id="inquiryModal">
        <div class="inquiry-modal">
            <button class="inquiry-close" onclick="closeInquiryModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            
            <div id="inquiryForm">
                <div class="inquiry-header">
                    <div class="inquiry-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <h3><?php _e('Ask About This Unit', 'rental-gates'); ?></h3>
                    <p><?php echo esc_html($unit['name']); ?> - $<?php echo number_format($unit['rent_amount']); ?>/mo</p>
                </div>
                <form class="inquiry-body" onsubmit="submitUnitInquiry(event)">
                    <div class="inquiry-row">
                        <div class="inquiry-group">
                            <label><?php _e('First Name', 'rental-gates'); ?> *</label>
                            <input type="text" name="first_name" required placeholder="<?php esc_attr_e('John', 'rental-gates'); ?>">
                        </div>
                        <div class="inquiry-group">
                            <label><?php _e('Last Name', 'rental-gates'); ?> *</label>
                            <input type="text" name="last_name" required placeholder="<?php esc_attr_e('Doe', 'rental-gates'); ?>">
                        </div>
                    </div>
                    <div class="inquiry-group">
                        <label><?php _e('Email', 'rental-gates'); ?> *</label>
                        <input type="email" name="email" required placeholder="<?php esc_attr_e('john@example.com', 'rental-gates'); ?>">
                    </div>
                    <div class="inquiry-group">
                        <label><?php _e('Phone', 'rental-gates'); ?></label>
                        <input type="tel" name="phone" placeholder="<?php esc_attr_e('(555) 123-4567', 'rental-gates'); ?>">
                    </div>
                    <div class="inquiry-group">
                        <label><?php _e('Message', 'rental-gates'); ?></label>
                        <textarea name="message" rows="3" placeholder="<?php esc_attr_e("I'm interested in this unit and would like to schedule a tour...", 'rental-gates'); ?>"></textarea>
                    </div>
                    <button type="submit" class="inquiry-submit" id="inquirySubmitBtn">
                        <?php _e('Send Inquiry', 'rental-gates'); ?>
                    </button>
                    <p class="inquiry-note"><?php _e('We typically respond within 24 hours.', 'rental-gates'); ?></p>
                </form>
            </div>
            
            <div id="inquirySuccess" style="display: none;">
                <div class="inquiry-success">
                    <div class="inquiry-success-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3><?php _e('Thank You!', 'rental-gates'); ?></h3>
                    <p><?php _e('Your inquiry has been submitted. We\'ll get back to you as soon as possible.', 'rental-gates'); ?></p>
                    <button class="btn" onclick="closeInquiryModal()"><?php _e('Close', 'rental-gates'); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    const unitId = <?php echo $unit['id']; ?>;
    const buildingId = <?php echo $building['id']; ?>;
    const orgId = <?php echo $building['organization_id']; ?>;
    const qrCode = '<?php echo esc_js($_GET['qr'] ?? ''); ?>';
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    function openInquiryModal() {
        document.getElementById('inquiryModal').classList.add('open');
        document.getElementById('inquiryForm').style.display = 'block';
        document.getElementById('inquirySuccess').style.display = 'none';
    }
    
    function closeInquiryModal() {
        document.getElementById('inquiryModal').classList.remove('open');
    }
    
    async function submitUnitInquiry(e) {
        e.preventDefault();
        
        const form = e.target;
        const btn = document.getElementById('inquirySubmitBtn');
        btn.disabled = true;
        btn.textContent = '<?php echo esc_js(__('Submitting...', 'rental-gates')); ?>';
        
        const formData = new FormData(form);
        formData.append('action', 'rental_gates_public_inquiry');
        formData.append('nonce', '<?php echo wp_create_nonce('rental_gates_public'); ?>');
        formData.append('building_id', buildingId);
        formData.append('unit_id', unitId);
        formData.append('organization_id', orgId);
        formData.append('source', qrCode ? 'qr_unit' : 'profile');
        formData.append('source_id', unitId);
        
        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('inquiryForm').style.display = 'none';
                document.getElementById('inquirySuccess').style.display = 'block';
            } else {
                alert(result.data?.message || '<?php echo esc_js(__('Error submitting inquiry. Please try again.', 'rental-gates')); ?>');
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js(__('Send Inquiry', 'rental-gates')); ?>';
            }
        } catch (error) {
            alert('<?php echo esc_js(__('Error submitting inquiry. Please try again.', 'rental-gates')); ?>');
            btn.disabled = false;
            btn.textContent = '<?php echo esc_js(__('Send Inquiry', 'rental-gates')); ?>';
        }
    }
    
    // Close modal on overlay click
    document.getElementById('inquiryModal').addEventListener('click', function(e) {
        if (e.target === this) closeInquiryModal();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeInquiryModal();
    });
    </script>
</body>
</html>
