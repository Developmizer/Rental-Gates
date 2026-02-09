<?php
/**
 * Public Building Page
 * 
 * Public-facing building page accessed via QR code or direct link.
 * Includes lead capture functionality.
 */
if (!defined('ABSPATH')) exit;

// Try query var first, then parse URL directly
$building_slug = get_query_var('rental_gates_building_slug');

// Fallback: parse from URL for /b/ or /building/ routes
if (!$building_slug && isset($_SERVER['REQUEST_URI'])) {
    $uri = urldecode($_SERVER['REQUEST_URI']);
    if (preg_match('#/rental-gates/(?:b|building)/([a-zA-Z0-9-]+)#', $uri, $matches)) {
        $building_slug = $matches[1];
    }
}

if (!$building_slug) {
    wp_redirect(home_url('/rental-gates/map'));
    exit;
}

$building = Rental_Gates_Building::get_by_slug($building_slug);
if (!$building) {
    wp_redirect(home_url('/rental-gates/map'));
    exit;
}

$organization = Rental_Gates_Organization::get($building['organization_id']);
$units = Rental_Gates_Unit::get_for_building($building['id']);
$available_units = array_filter($units, function($unit) {
    return in_array($unit['availability'], array('available', 'coming_soon'));
});

$gallery = !empty($building['gallery']) ? $building['gallery'] : array();
$amenities = !empty($building['amenities']) ? $building['amenities'] : array();

// Track QR scan
$qr_code = isset($_GET['qr']) ? sanitize_text_field($_GET['qr']) : '';
if ($qr_code && class_exists('Rental_Gates_QR')) {
    Rental_Gates_QR::track_scan($qr_code, 'building', $building['id']);
}

// Helper to extract URL from gallery item
function rg_public_get_gallery_url($item) {
    if (empty($item)) return null;
    if (is_string($item)) return $item;
    if (is_array($item)) return $item['url'] ?? $item['thumbnail'] ?? null;
    return null;
}

$hero_img = !empty($gallery[0]) ? rg_public_get_gallery_url($gallery[0]) : null;

// Get rent range
$rents = array_map(function($u) { return floatval($u['rent_amount']); }, $available_units);
$min_rent = !empty($rents) ? min($rents) : 0;
$max_rent = !empty($rents) ? max($rents) : 0;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($building['name']); ?> - <?php echo esc_html($organization['name']); ?></title>
    <meta name="description" content="<?php echo esc_attr(wp_trim_words($building['description'] ?? '', 30)); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
    <style>
        :root { 
            --primary: #6366f1; 
            --primary-dark: #4f46e5;
            --success: #10b981;
            --gray-50: #f9fafb; 
            --gray-100: #f3f4f6; 
            --gray-200: #e5e7eb; 
            --gray-300: #d1d5db;
            --gray-500: #6b7280; 
            --gray-700: #374151; 
            --gray-900: #111827; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--gray-50); color: var(--gray-900); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        /* Header */
        .header { background: #fff; border-bottom: 1px solid var(--gray-200); padding: 16px 0; position: sticky; top: 0; z-index: 100; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .org-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--gray-900); }
        .org-logo { width: 44px; height: 44px; background: linear-gradient(135deg, var(--primary), #8b5cf6); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px; }
        .org-name { font-weight: 600; font-size: 18px; }
        .header-cta { display: flex; gap: 12px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-outline { background: transparent; border: 1px solid var(--gray-300); color: var(--gray-700); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn svg { width: 18px; height: 18px; }
        
        /* Hero Gallery */
        .hero-gallery { position: relative; height: 480px; background: var(--gray-200); overflow: hidden; }
        .hero-main-image { width: 100%; height: 100%; }
        .hero-main-image img { width: 100%; height: 100%; object-fit: cover; }
        .hero-gallery-grid { position: absolute; top: 0; right: 0; bottom: 0; width: 35%; display: grid; grid-template-rows: 1fr 1fr; gap: 4px; background: #000; }
        .hero-gallery-grid img { width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: opacity 0.2s; }
        .hero-gallery-grid img:hover { opacity: 0.85; }
        .gallery-count { position: absolute; bottom: 16px; right: 16px; background: rgba(0,0,0,0.7); color: #fff; padding: 8px 16px; border-radius: 8px; font-size: 14px; cursor: pointer; }
        .hero-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.8)); padding: 60px 0 24px; color: #fff; }
        .hero-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 12px; backdrop-filter: blur(4px); }
        .hero-title { font-size: 36px; font-weight: 700; margin-bottom: 8px; }
        .hero-address { display: flex; align-items: center; gap: 8px; opacity: 0.9; font-size: 16px; }
        .hero-stats { display: flex; gap: 24px; margin-top: 16px; }
        .hero-stat { display: flex; align-items: center; gap: 8px; }
        .hero-stat-value { font-weight: 700; font-size: 18px; }
        .hero-stat-label { opacity: 0.8; font-size: 14px; }
        
        /* Content */
        .content { padding: 40px 0; }
        .content-grid { display: grid; grid-template-columns: 1fr 380px; gap: 32px; }
        
        .card { background: #fff; border-radius: 16px; border: 1px solid var(--gray-200); margin-bottom: 24px; overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid var(--gray-100); font-weight: 600; font-size: 18px; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 24px; }
        
        /* Amenities */
        .amenities-grid { display: flex; flex-wrap: wrap; gap: 10px; }
        .amenity-tag { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; }
        .amenity-tag svg { width: 18px; height: 18px; color: var(--primary); }
        
        /* Units List */
        .units-grid { display: grid; gap: 16px; }
        .unit-card { display: grid; grid-template-columns: 160px 1fr auto; gap: 20px; padding: 20px; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; text-decoration: none; color: inherit; transition: all 0.2s; }
        .unit-card:hover { border-color: var(--primary); box-shadow: 0 8px 24px rgba(99,102,241,0.12); transform: translateY(-2px); }
        .unit-thumb { width: 160px; height: 120px; background: var(--gray-100); border-radius: 10px; overflow: hidden; }
        .unit-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .unit-info { display: flex; flex-direction: column; justify-content: center; }
        .unit-info h3 { font-size: 18px; font-weight: 600; margin-bottom: 6px; }
        .unit-details { display: flex; gap: 16px; font-size: 14px; color: var(--gray-500); margin-bottom: 8px; }
        .unit-details span { display: flex; align-items: center; gap: 4px; }
        .unit-features { display: flex; gap: 8px; flex-wrap: wrap; }
        .unit-feature { font-size: 12px; padding: 4px 8px; background: var(--gray-100); border-radius: 4px; color: var(--gray-600); }
        .unit-pricing { text-align: right; display: flex; flex-direction: column; justify-content: center; }
        .unit-rent { font-size: 24px; font-weight: 700; color: var(--gray-900); }
        .unit-rent span { font-size: 14px; font-weight: 400; color: var(--gray-500); }
        .unit-availability { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 8px; }
        .unit-availability.available { background: #d1fae5; color: #065f46; }
        .unit-availability.coming_soon { background: #fef3c7; color: #92400e; }
        
        /* Sidebar */
        .sidebar-sticky { position: sticky; top: 100px; }
        
        /* Contact Card */
        .contact-card { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: #fff; border: none; }
        .contact-card .card-header { border-bottom-color: rgba(255,255,255,0.2); color: #fff; }
        .contact-card .card-body { padding: 24px; }
        .contact-highlight { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .contact-subtext { opacity: 0.9; margin-bottom: 20px; }
        .contact-btn { display: block; width: 100%; padding: 14px; background: #fff; color: var(--primary); border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .contact-btn:hover { transform: scale(1.02); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .contact-divider { display: flex; align-items: center; gap: 16px; margin: 20px 0; opacity: 0.7; font-size: 14px; }
        .contact-divider::before, .contact-divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.3); }
        .contact-links { display: flex; flex-direction: column; gap: 12px; }
        .contact-link { display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; font-size: 15px; }
        .contact-link:hover { opacity: 0.8; }
        .contact-link svg { width: 20px; height: 20px; opacity: 0.8; }
        
        /* Map */
        .map-container { height: 200px; border-radius: 12px; overflow: hidden; margin-bottom: 12px; }
        .map-container iframe { width: 100%; height: 100%; border: 0; }
        .directions-link { display: flex; align-items: center; gap: 6px; color: var(--primary); font-weight: 500; text-decoration: none; }
        .directions-link:hover { text-decoration: underline; }
        
        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        .modal-overlay.open { display: flex; }
        .modal { background: #fff; border-radius: 20px; max-width: 480px; width: 100%; max-height: 90vh; overflow-y: auto; animation: modalIn 0.3s ease; }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-header { padding: 24px 24px 0; text-align: center; }
        .modal-icon { width: 64px; height: 64px; background: linear-gradient(135deg, var(--primary), #8b5cf6); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .modal-icon svg { width: 32px; height: 32px; color: #fff; }
        .modal-header h3 { font-size: 24px; margin-bottom: 8px; }
        .modal-header p { color: var(--gray-500); font-size: 15px; }
        .modal-close { position: absolute; top: 16px; right: 16px; background: var(--gray-100); border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .modal-close:hover { background: var(--gray-200); }
        .modal-body { padding: 24px; position: relative; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 8px; color: var(--gray-700); }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px 16px; border: 1px solid var(--gray-300); border-radius: 10px; font-size: 15px; font-family: inherit; transition: all 0.2s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }
        .form-group input::placeholder, .form-group textarea::placeholder { color: var(--gray-400); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-submit { width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .form-submit:hover { background: var(--primary-dark); }
        .form-submit:disabled { background: var(--gray-300); cursor: not-allowed; }
        .form-note { text-align: center; font-size: 13px; color: var(--gray-500); margin-top: 16px; }
        
        /* Success State */
        .success-state { text-align: center; padding: 40px 24px; }
        .success-icon { width: 80px; height: 80px; background: #d1fae5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .success-icon svg { width: 40px; height: 40px; color: var(--success); }
        .success-state h3 { font-size: 24px; margin-bottom: 12px; }
        .success-state p { color: var(--gray-500); margin-bottom: 24px; }
        
        /* Gallery Modal */
        .gallery-modal { max-width: 900px; }
        .gallery-modal .modal-body { padding: 0; }
        .gallery-viewer { position: relative; background: #000; }
        .gallery-viewer img { width: 100%; max-height: 70vh; object-fit: contain; }
        .gallery-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.9); border: none; width: 48px; height: 48px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .gallery-nav:hover { background: #fff; }
        .gallery-nav.prev { left: 16px; }
        .gallery-nav.next { right: 16px; }
        .gallery-thumbs { display: flex; gap: 8px; padding: 16px; overflow-x: auto; background: var(--gray-50); }
        .gallery-thumb { width: 80px; height: 60px; border-radius: 6px; overflow: hidden; cursor: pointer; opacity: 0.6; transition: all 0.2s; flex-shrink: 0; }
        .gallery-thumb.active, .gallery-thumb:hover { opacity: 1; }
        .gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }
        
        /* Footer */
        .footer { background: var(--gray-900); color: #fff; padding: 32px 0; margin-top: 60px; }
        .footer-content { display: flex; justify-content: space-between; align-items: center; }
        .footer a { color: var(--gray-400); text-decoration: none; }
        .footer a:hover { color: #fff; }
        
        .empty-state { text-align: center; padding: 48px 24px; }
        .empty-state svg { width: 64px; height: 64px; color: var(--gray-300); margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: var(--gray-500); }
        
        @media (max-width: 1024px) {
            .content-grid { grid-template-columns: 1fr; }
            .sidebar-sticky { position: static; }
            .hero-gallery-grid { display: none; }
        }
        @media (max-width: 768px) {
            .unit-card { grid-template-columns: 1fr; }
            .unit-thumb { width: 100%; height: 180px; }
            .unit-pricing { text-align: left; margin-top: 12px; }
            .hero-gallery { height: 320px; }
            .hero-title { font-size: 28px; }
            .hero-stats { flex-wrap: wrap; gap: 16px; }
            .form-row { grid-template-columns: 1fr; }
            .header-cta .btn span { display: none; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="<?php echo home_url('/rental-gates/map'); ?>" class="org-brand">
                    <div class="org-logo"><?php echo strtoupper(substr($organization['name'], 0, 1)); ?></div>
                    <span class="org-name"><?php echo esc_html($organization['name']); ?></span>
                </a>
                <div class="header-cta">
                    <a href="tel:<?php echo esc_attr($organization['contact_phone'] ?? ''); ?>" class="btn btn-outline">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <span><?php _e('Call', 'rental-gates'); ?></span>
                    </a>
                    <button class="btn btn-primary" onclick="openContactModal()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span><?php _e('Inquire', 'rental-gates'); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </header>
    
    <section class="hero-gallery">
        <div class="hero-main-image">
            <?php if ($hero_img): ?>
                <img src="<?php echo esc_url($hero_img); ?>" alt="<?php echo esc_attr($building['name']); ?>" id="heroMainImg">
            <?php else: ?>
                <div style="width: 100%; height: 100%; background: linear-gradient(135deg, var(--gray-200), var(--gray-300)); display: flex; align-items: center; justify-content: center;">
                    <svg width="80" height="80" fill="none" stroke="var(--gray-400)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($gallery) > 1): ?>
        <div class="hero-gallery-grid">
            <?php for ($i = 1; $i <= min(2, count($gallery) - 1); $i++): 
                $img_url = rg_public_get_gallery_url($gallery[$i]);
                if ($img_url):
            ?>
                <img src="<?php echo esc_url($img_url); ?>" alt="" onclick="openGallery(<?php echo $i; ?>)">
            <?php endif; endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php if (count($gallery) > 3): ?>
        <button class="gallery-count" onclick="openGallery(0)">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline; vertical-align: middle; margin-right: 6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <?php printf(__('View all %d photos', 'rental-gates'), count($gallery)); ?>
        </button>
        <?php endif; ?>
        
        <div class="hero-overlay">
            <div class="container">
                <?php if (count($available_units) > 0): ?>
                <div class="hero-badge">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <?php printf(_n('%d Unit Available', '%d Units Available', count($available_units), 'rental-gates'), count($available_units)); ?>
                </div>
                <?php endif; ?>
                
                <h1 class="hero-title"><?php echo esc_html($building['name']); ?></h1>
                
                <div class="hero-address">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <?php echo esc_html($building['derived_address']); ?>
                </div>
                
                <?php if ($min_rent > 0): ?>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-value">$<?php echo number_format($min_rent); ?><?php if ($max_rent > $min_rent) echo ' - $' . number_format($max_rent); ?></span>
                        <span class="hero-stat-label">/month</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value"><?php echo count($units); ?></span>
                        <span class="hero-stat-label"><?php _e('Total Units', 'rental-gates'); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <section class="content">
        <div class="container">
            <div class="content-grid">
                <div class="content-main">
                    <?php if (!empty($building['description'])): ?>
                    <div class="card">
                        <div class="card-header"><?php _e('About This Property', 'rental-gates'); ?></div>
                        <div class="card-body">
                            <p style="font-size: 15px; color: var(--gray-700);"><?php echo nl2br(esc_html($building['description'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($amenities)): ?>
                    <div class="card">
                        <div class="card-header"><?php _e('Amenities', 'rental-gates'); ?></div>
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
                    
                    <div class="card">
                        <div class="card-header">
                            <?php _e('Available Units', 'rental-gates'); ?>
                            <span style="font-size: 14px; font-weight: 500; color: var(--gray-500);"><?php echo count($available_units); ?> <?php _e('available', 'rental-gates'); ?></span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($available_units)): ?>
                            <div class="units-grid">
                                <?php foreach ($available_units as $unit): 
                                    $unit_gallery = !empty($unit['gallery']) && is_array($unit['gallery']) ? $unit['gallery'] : array();
                                    $unit_thumb = !empty($unit_gallery[0]) ? rg_public_get_gallery_url($unit_gallery[0]) : null;
                                    $unit_amenities = !empty($unit['amenities']) && is_array($unit['amenities']) ? array_slice($unit['amenities'], 0, 3) : array();
                                ?>
                                <a href="<?php echo home_url('/rental-gates/listings/' . $building['slug'] . '/' . $unit['slug']); ?>" class="unit-card">
                                    <div class="unit-thumb">
                                        <?php if ($unit_thumb): ?>
                                            <img src="<?php echo esc_url($unit_thumb); ?>" alt="<?php echo esc_attr($unit['name']); ?>">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--gray-100);">
                                                <svg width="32" height="32" fill="none" stroke="var(--gray-300)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="unit-info">
                                        <h3><?php echo esc_html($unit['name']); ?></h3>
                                        <div class="unit-details">
                                            <span>
                                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                                <?php echo intval($unit['bedrooms']); ?> <?php _e('bed', 'rental-gates'); ?>
                                            </span>
                                            <span>
                                                <?php echo number_format($unit['bathrooms'], $unit['bathrooms'] == floor($unit['bathrooms']) ? 0 : 1); ?> <?php _e('bath', 'rental-gates'); ?>
                                            </span>
                                            <?php if ($unit['square_footage']): ?>
                                            <span><?php echo number_format($unit['square_footage']); ?> <?php _e('sq ft', 'rental-gates'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($unit_amenities)): ?>
                                        <div class="unit-features">
                                            <?php foreach ($unit_amenities as $amenity): ?>
                                            <span class="unit-feature"><?php echo esc_html($amenity); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="unit-pricing">
                                        <div class="unit-rent">$<?php echo number_format($unit['rent_amount']); ?><span>/mo</span></div>
                                        <span class="unit-availability <?php echo esc_attr($unit['availability']); ?>">
                                            <?php echo $unit['availability'] === 'available' ? __('Available Now', 'rental-gates') : __('Coming Soon', 'rental-gates'); ?>
                                        </span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                <h3><?php _e('No Units Currently Available', 'rental-gates'); ?></h3>
                                <p><?php _e('Check back soon or contact us to get on the waitlist!', 'rental-gates'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="content-sidebar">
                    <div class="sidebar-sticky">
                        <div class="card contact-card">
                            <div class="card-header"><?php _e('Interested?', 'rental-gates'); ?></div>
                            <div class="card-body">
                                <?php if ($min_rent > 0): ?>
                                <div class="contact-highlight">
                                    <?php _e('Starting at', 'rental-gates'); ?> $<?php echo number_format($min_rent); ?>/mo
                                </div>
                                <?php endif; ?>
                                <p class="contact-subtext"><?php _e('Schedule a tour or ask us anything about this property.', 'rental-gates'); ?></p>
                                
                                <button class="contact-btn" onclick="openContactModal()">
                                    <?php _e('Request Information', 'rental-gates'); ?>
                                </button>
                                
                                <div class="contact-divider"><?php _e('or contact us directly', 'rental-gates'); ?></div>
                                
                                <div class="contact-links">
                                    <?php if (!empty($organization['contact_phone'])): ?>
                                    <a href="tel:<?php echo esc_attr($organization['contact_phone']); ?>" class="contact-link">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        <?php echo esc_html($organization['contact_phone']); ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!empty($organization['contact_email'])): ?>
                                    <a href="mailto:<?php echo esc_attr($organization['contact_email']); ?>?subject=<?php echo urlencode('Inquiry about ' . $building['name']); ?>" class="contact-link">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        <?php echo esc_html($organization['contact_email']); ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header"><?php _e('Location', 'rental-gates'); ?></div>
                            <div class="card-body">
                                <div class="map-container">
                                    <iframe src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo ($building['longitude'] - 0.008); ?>%2C<?php echo ($building['latitude'] - 0.006); ?>%2C<?php echo ($building['longitude'] + 0.008); ?>%2C<?php echo ($building['latitude'] + 0.006); ?>&layer=mapnik&marker=<?php echo $building['latitude']; ?>%2C<?php echo $building['longitude']; ?>" loading="lazy"></iframe>
                                </div>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo $building['latitude']; ?>,<?php echo $building['longitude']; ?>" target="_blank" class="directions-link">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                    <?php _e('Get Directions', 'rental-gates'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div>&copy; <?php echo date('Y'); ?> <?php echo esc_html($organization['name']); ?></div>
                <div><?php _e('Powered by', 'rental-gates'); ?> <a href="https://rentalgates.com">Rental Gates</a></div>
            </div>
        </div>
    </footer>
    
    <!-- Contact Modal -->
    <div class="modal-overlay" id="contactModal">
        <div class="modal">
            <button class="modal-close" onclick="closeContactModal()">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            
            <div id="contactForm">
                <div class="modal-header">
                    <div class="modal-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <h3><?php _e('Request Information', 'rental-gates'); ?></h3>
                    <p><?php printf(__('About %s', 'rental-gates'), esc_html($building['name'])); ?></p>
                </div>
                <form class="modal-body" onsubmit="submitInquiry(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label><?php _e('First Name', 'rental-gates'); ?> *</label>
                            <input type="text" name="first_name" required placeholder="<?php esc_attr_e('John', 'rental-gates'); ?>">
                        </div>
                        <div class="form-group">
                            <label><?php _e('Last Name', 'rental-gates'); ?> *</label>
                            <input type="text" name="last_name" required placeholder="<?php esc_attr_e('Doe', 'rental-gates'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?php _e('Email', 'rental-gates'); ?> *</label>
                        <input type="email" name="email" required placeholder="<?php esc_attr_e('john@example.com', 'rental-gates'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php _e('Phone', 'rental-gates'); ?></label>
                        <input type="tel" name="phone" placeholder="<?php esc_attr_e('(555) 123-4567', 'rental-gates'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php _e('Message', 'rental-gates'); ?></label>
                        <textarea name="message" rows="3" placeholder="<?php esc_attr_e("I'm interested in learning more about this property...", 'rental-gates'); ?>"></textarea>
                    </div>
                    <button type="submit" class="form-submit" id="submitBtn">
                        <?php _e('Send Inquiry', 'rental-gates'); ?>
                    </button>
                    <p class="form-note"><?php _e('We typically respond within 24 hours.', 'rental-gates'); ?></p>
                </form>
            </div>
            
            <div id="contactSuccess" style="display: none;">
                <div class="success-state">
                    <div class="success-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3><?php _e('Thank You!', 'rental-gates'); ?></h3>
                    <p><?php _e('Your inquiry has been submitted. We\'ll get back to you as soon as possible.', 'rental-gates'); ?></p>
                    <button class="btn btn-primary" onclick="closeContactModal()"><?php _e('Close', 'rental-gates'); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gallery Modal -->
    <?php if (count($gallery) > 0): ?>
    <div class="modal-overlay" id="galleryModal">
        <div class="modal gallery-modal">
            <button class="modal-close" onclick="closeGallery()" style="z-index: 10;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <div class="modal-body">
                <div class="gallery-viewer">
                    <img id="galleryMainImg" src="<?php echo esc_url($hero_img); ?>" alt="">
                    <button class="gallery-nav prev" onclick="prevImage()">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button class="gallery-nav next" onclick="nextImage()">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
                <div class="gallery-thumbs">
                    <?php foreach ($gallery as $i => $img): 
                        $img_url = rg_public_get_gallery_url($img);
                        if ($img_url):
                    ?>
                    <div class="gallery-thumb <?php echo $i === 0 ? 'active' : ''; ?>" onclick="showImage(<?php echo $i; ?>)">
                        <img src="<?php echo esc_url($img_url); ?>" alt="">
                    </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    const buildingId = <?php echo $building['id']; ?>;
    const orgId = <?php echo $building['organization_id']; ?>;
    const qrCode = '<?php echo esc_js($qr_code); ?>';
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    // Gallery
    const galleryImages = <?php echo wp_json_encode(array_map('rg_public_get_gallery_url', $gallery)); ?>;
    let currentImageIndex = 0;
    
    function openGallery(index = 0) {
        currentImageIndex = index;
        showImage(index);
        document.getElementById('galleryModal').classList.add('open');
    }
    
    function closeGallery() {
        document.getElementById('galleryModal').classList.remove('open');
    }
    
    function showImage(index) {
        currentImageIndex = index;
        document.getElementById('galleryMainImg').src = galleryImages[index];
        document.querySelectorAll('.gallery-thumb').forEach((thumb, i) => {
            thumb.classList.toggle('active', i === index);
        });
    }
    
    function prevImage() {
        const newIndex = currentImageIndex > 0 ? currentImageIndex - 1 : galleryImages.length - 1;
        showImage(newIndex);
    }
    
    function nextImage() {
        const newIndex = currentImageIndex < galleryImages.length - 1 ? currentImageIndex + 1 : 0;
        showImage(newIndex);
    }
    
    // Contact Modal
    function openContactModal() {
        document.getElementById('contactModal').classList.add('open');
        document.getElementById('contactForm').style.display = 'block';
        document.getElementById('contactSuccess').style.display = 'none';
    }
    
    function closeContactModal() {
        document.getElementById('contactModal').classList.remove('open');
    }
    
    async function submitInquiry(e) {
        e.preventDefault();
        
        const form = e.target;
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = '<?php echo esc_js(__('Submitting...', 'rental-gates')); ?>';
        
        const formData = new FormData(form);
        formData.append('action', 'rental_gates_public_inquiry');
        formData.append('nonce', '<?php echo wp_create_nonce('rental_gates_public'); ?>');
        formData.append('building_id', buildingId);
        formData.append('organization_id', orgId);
        formData.append('source', qrCode ? 'qr_building' : 'profile');
        formData.append('source_id', buildingId);
        
        try {
            const response = await fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('contactForm').style.display = 'none';
                document.getElementById('contactSuccess').style.display = 'block';
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
    
    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('open');
            }
        });
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeContactModal();
            closeGallery();
        }
        if (document.getElementById('galleryModal').classList.contains('open')) {
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        }
    });
    </script>
    <?php wp_footer(); ?>
</body>
</html>
