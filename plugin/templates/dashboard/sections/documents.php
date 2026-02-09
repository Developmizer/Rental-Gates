<?php
/**
 * Documents List Section
 */
if (!defined('ABSPATH')) exit;

$org_id = Rental_Gates_Roles::get_organization_id();
if (!$org_id) {
    wp_redirect(home_url('/rental-gates/login'));
    exit;
}

// Get filter parameters
$entity_type_filter = isset($_GET['entity_type']) ? sanitize_text_field($_GET['entity_type']) : '';
$doc_type_filter = isset($_GET['doc_type']) ? sanitize_text_field($_GET['doc_type']) : '';
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Get documents
$args = array(
    'entity_type' => $entity_type_filter ?: null,
    'document_type' => $doc_type_filter ?: null,
    'search' => $search ?: null,
    'orderby' => 'created_at',
    'order' => 'DESC',
    'limit' => 100,
);

$documents = Rental_Gates_Document::get_for_organization($org_id, $args);
if (!is_array($documents)) {
    $documents = array();
}

$stats = Rental_Gates_Document::get_stats($org_id);
$document_types = Rental_Gates_Document::get_document_types();
$entity_types = Rental_Gates_Document::get_entity_types();

// Get entity options for upload form
$buildings_result = Rental_Gates_Building::get_for_organization($org_id);
$buildings = is_array($buildings_result) && isset($buildings_result['items']) ? $buildings_result['items'] : array();

$tenants = Rental_Gates_Tenant::get_for_organization($org_id, array('status' => 'active'));
if (!is_array($tenants)) $tenants = array();

$leases_result = Rental_Gates_Lease::get_for_organization($org_id, array('status' => 'active'));
$leases = is_array($leases_result) && isset($leases_result['items']) ? $leases_result['items'] : array();

$vendors_result = Rental_Gates_Vendor::get_for_organization($org_id, array('status' => 'active', 'per_page' => 100));
$vendors = is_array($vendors_result) && isset($vendors_result['items']) ? $vendors_result['items'] : array();

// Get applications and work orders
$applications_result = Rental_Gates_Application::get_for_organization($org_id, array('status' => 'submitted'));
$applications = is_array($applications_result) && isset($applications_result['items']) ? $applications_result['items'] : array();

$work_orders = Rental_Gates_Maintenance::get_for_organization($org_id, array('status' => 'open', 'per_page' => 100));
if (!is_array($work_orders)) $work_orders = array();
?>

<style>
    .rg-documents-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
    .rg-documents-header h1 { font-size: 24px; font-weight: 700; color: var(--gray-900); margin: 0; }
    
    .rg-stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .rg-stat-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 16px 20px; }
    .rg-stat-card.highlight { border-color: var(--primary); background: linear-gradient(135deg, #eff6ff 0%, #fff 100%); }
    .rg-stat-value { font-size: 28px; font-weight: 700; color: var(--gray-900); }
    .rg-stat-label { font-size: 13px; color: var(--gray-500); margin-top: 4px; }
    
    .rg-filters-row { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .rg-search-box { flex: 1; min-width: 200px; position: relative; }
    .rg-search-box input { width: 100%; padding: 10px 14px 10px 40px; border: 1px solid var(--gray-300); border-radius: 8px; font-size: 14px; }
    .rg-search-box svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--gray-400); }
    .rg-filter-select { padding: 10px 14px; border: 1px solid var(--gray-300); border-radius: 8px; font-size: 14px; min-width: 140px; }
    
    .rg-documents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
    
    .rg-doc-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; transition: all 0.2s; }
    .rg-doc-card:hover { border-color: var(--gray-300); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    
    .rg-doc-preview { height: 140px; background: var(--gray-100); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
    .rg-doc-preview img { width: 100%; height: 100%; object-fit: cover; }
    .rg-doc-preview svg { width: 48px; height: 48px; color: var(--gray-400); }
    .rg-doc-preview.pdf { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); }
    .rg-doc-preview.pdf svg { color: #dc2626; }
    .rg-doc-preview.doc { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
    .rg-doc-preview.doc svg { color: #2563eb; }
    .rg-doc-preview.xls { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); }
    .rg-doc-preview.xls svg { color: #059669; }
    
    .rg-doc-ext { position: absolute; top: 12px; right: 12px; padding: 4px 8px; background: rgba(255,255,255,0.9); border-radius: 4px; font-size: 11px; font-weight: 600; color: var(--gray-600); }
    
    .rg-doc-info { padding: 16px; }
    .rg-doc-title { font-weight: 600; color: var(--gray-900); font-size: 14px; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rg-doc-meta { font-size: 12px; color: var(--gray-500); margin-bottom: 8px; }
    .rg-doc-meta span { display: inline-flex; align-items: center; gap: 4px; }
    .rg-doc-meta span + span::before { content: '•'; margin: 0 6px; }
    
    .rg-doc-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
    .rg-doc-tag { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
    .rg-doc-tag.type { background: #ede9fe; color: #7c3aed; }
    .rg-doc-tag.entity { background: #dbeafe; color: #2563eb; }
    
    .rg-doc-actions { display: flex; gap: 8px; padding-top: 12px; border-top: 1px solid var(--gray-100); }
    .rg-doc-action { flex: 1; padding: 8px; border: 1px solid var(--gray-200); border-radius: 6px; background: #fff; color: var(--gray-600); font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; transition: all 0.2s; }
    .rg-doc-action:hover { background: var(--gray-50); border-color: var(--gray-300); }
    .rg-doc-action.danger:hover { background: #fee2e2; border-color: #fca5a5; color: #dc2626; }
    .rg-doc-action svg { width: 14px; height: 14px; }
    
    .rg-empty-state { text-align: center; padding: 60px 20px; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; }
    .rg-empty-state svg { color: var(--gray-300); margin-bottom: 16px; }
    .rg-empty-state h3 { font-size: 18px; color: var(--gray-700); margin-bottom: 8px; }
    .rg-empty-state p { color: var(--gray-500); margin-bottom: 20px; }
    
    /* Upload Modal */
    .rg-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
    .rg-modal-overlay.active { display: flex; }
    .rg-modal { background: #fff; border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; }
    .rg-modal-header { padding: 20px 24px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
    .rg-modal-title { font-size: 18px; font-weight: 600; color: var(--gray-900); }
    .rg-modal-close { background: none; border: none; padding: 8px; cursor: pointer; color: var(--gray-400); border-radius: 8px; }
    .rg-modal-close:hover { background: var(--gray-100); color: var(--gray-600); }
    .rg-modal-body { padding: 24px; }
    .rg-modal-footer { padding: 16px 24px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 12px; }
    
    .rg-upload-zone { border: 2px dashed var(--gray-300); border-radius: 12px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.2s; margin-bottom: 20px; }
    .rg-upload-zone:hover, .rg-upload-zone.dragover { border-color: var(--primary); background: #eff6ff; }
    .rg-upload-zone svg { width: 48px; height: 48px; color: var(--gray-400); margin-bottom: 12px; }
    .rg-upload-zone h4 { font-size: 15px; color: var(--gray-700); margin-bottom: 4px; }
    .rg-upload-zone p { font-size: 13px; color: var(--gray-500); }
    .rg-upload-zone input { display: none; }
    
    .rg-file-preview { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--gray-50); border-radius: 8px; margin-bottom: 16px; }
    .rg-file-preview-icon { width: 40px; height: 40px; border-radius: 8px; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; }
    .rg-file-preview-info { flex: 1; }
    .rg-file-preview-name { font-size: 14px; font-weight: 500; color: var(--gray-900); }
    .rg-file-preview-size { font-size: 12px; color: var(--gray-500); }
    .rg-file-preview-remove { background: none; border: none; padding: 4px; cursor: pointer; color: var(--gray-400); }
    .rg-file-preview-remove:hover { color: #dc2626; }
    
    .rg-form-row { margin-bottom: 16px; }
    .rg-form-row label { display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 6px; }
    .rg-form-row input, .rg-form-row select, .rg-form-row textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--gray-300); border-radius: 8px; font-size: 14px; }
    .rg-form-row textarea { resize: vertical; min-height: 80px; }
    .rg-form-row-half { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    
    .rg-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: all 0.2s; text-decoration: none; }
    .rg-btn-primary { background: var(--primary); color: #fff; }
    .rg-btn-primary:hover { background: var(--primary-dark); }
    .rg-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
    .rg-btn-secondary { background: #fff; color: var(--gray-700); border: 1px solid var(--gray-300); }
    .rg-btn-secondary:hover { background: var(--gray-50); }
    .rg-btn svg { width: 16px; height: 16px; }
    
    @media (max-width: 1200px) { .rg-stats-row { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) { 
        .rg-documents-grid { grid-template-columns: 1fr; }
        .rg-form-row-half { grid-template-columns: 1fr; }
    }
</style>

<!-- Header -->
<div class="rg-documents-header">
    <h1><?php _e('Documents', 'rental-gates'); ?></h1>
    <button onclick="openUploadModal()" class="rg-btn rg-btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <?php _e('Upload Document', 'rental-gates'); ?>
    </button>
</div>

<!-- Stats -->
<div class="rg-stats-row">
    <div class="rg-stat-card highlight">
        <div class="rg-stat-value"><?php echo intval($stats['total']); ?></div>
        <div class="rg-stat-label"><?php _e('Total Documents', 'rental-gates'); ?></div>
    </div>
    <div class="rg-stat-card">
        <div class="rg-stat-value"><?php echo intval($stats['recent_count']); ?></div>
        <div class="rg-stat-label"><?php _e('Added This Month', 'rental-gates'); ?></div>
    </div>
    <div class="rg-stat-card">
        <div class="rg-stat-value"><?php echo Rental_Gates_Document::format_file_size($stats['total_size']); ?></div>
        <div class="rg-stat-label"><?php _e('Total Storage', 'rental-gates'); ?></div>
    </div>
    <div class="rg-stat-card">
        <div class="rg-stat-value"><?php echo count($stats['by_type']); ?></div>
        <div class="rg-stat-label"><?php _e('Document Types', 'rental-gates'); ?></div>
    </div>
</div>

<!-- Filters -->
<form method="get" action="<?php echo esc_url(home_url('/rental-gates/dashboard/documents')); ?>" class="rg-filters-row">
    <div class="rg-search-box">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="search" placeholder="<?php _e('Search documents...', 'rental-gates'); ?>" value="<?php echo esc_attr($search); ?>">
    </div>
    
    <select name="entity_type" class="rg-filter-select" onchange="this.form.submit()">
        <option value=""><?php _e('All Entities', 'rental-gates'); ?></option>
        <?php foreach ($entity_types as $key => $label): ?>
            <option value="<?php echo esc_attr($key); ?>" <?php selected($entity_type_filter, $key); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>
    
    <select name="doc_type" class="rg-filter-select" onchange="this.form.submit()">
        <option value=""><?php _e('All Types', 'rental-gates'); ?></option>
        <?php foreach ($document_types as $key => $label): ?>
            <option value="<?php echo esc_attr($key); ?>" <?php selected($doc_type_filter, $key); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>
    
    <button type="submit" class="rg-btn rg-btn-secondary"><?php _e('Filter', 'rental-gates'); ?></button>
</form>

<!-- Documents Grid -->
<?php if (empty($documents)): ?>
<div class="rg-empty-state">
    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <h3><?php _e('No documents found', 'rental-gates'); ?></h3>
    <p><?php _e('Upload your first document to start organizing your files.', 'rental-gates'); ?></p>
    <button onclick="openUploadModal()" class="rg-btn rg-btn-primary"><?php _e('Upload Document', 'rental-gates'); ?></button>
</div>
<?php else: ?>
<div class="rg-documents-grid">
    <?php foreach ($documents as $doc): 
        $preview_class = '';
        if (strpos($doc['mime_type'], 'pdf') !== false) $preview_class = 'pdf';
        elseif (strpos($doc['mime_type'], 'word') !== false || strpos($doc['mime_type'], 'document') !== false) $preview_class = 'doc';
        elseif (strpos($doc['mime_type'], 'excel') !== false || strpos($doc['mime_type'], 'sheet') !== false) $preview_class = 'xls';
    ?>
    <div class="rg-doc-card" data-id="<?php echo $doc['id']; ?>">
        <div class="rg-doc-preview <?php echo $preview_class; ?>">
            <?php if ($doc['is_image']): ?>
                <img src="<?php echo esc_url($doc['file_url']); ?>" alt="<?php echo esc_attr($doc['title']); ?>">
            <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <?php if ($preview_class === 'pdf'): ?>
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    <?php elseif ($preview_class === 'xls'): ?>
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><line x1="10" y1="9" x2="10" y2="9"/>
                    <?php else: ?>
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    <?php endif; ?>
                </svg>
            <?php endif; ?>
            <span class="rg-doc-ext"><?php echo esc_html($doc['file_extension']); ?></span>
        </div>
        <div class="rg-doc-info">
            <div class="rg-doc-title" title="<?php echo esc_attr($doc['title']); ?>"><?php echo esc_html($doc['title']); ?></div>
            <div class="rg-doc-meta">
                <span><?php echo esc_html($doc['file_size_formatted']); ?></span>
                <span><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></span>
            </div>
            <div class="rg-doc-tags">
                <span class="rg-doc-tag type"><?php echo esc_html($doc['document_type_label']); ?></span>
                <span class="rg-doc-tag entity"><?php echo esc_html($doc['entity_type_label']); ?></span>
            </div>
            <div class="rg-doc-actions">
                <a href="<?php echo esc_url($doc['file_url']); ?>" target="_blank" class="rg-doc-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <?php _e('View', 'rental-gates'); ?>
                </a>
                <a href="<?php echo esc_url($doc['file_url']); ?>" download class="rg-doc-action">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?php _e('Download', 'rental-gates'); ?>
                </a>
                <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" class="rg-doc-action danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upload Modal -->
<div class="rg-modal-overlay" id="uploadModal">
    <div class="rg-modal">
        <div class="rg-modal-header">
            <h3 class="rg-modal-title"><?php _e('Upload Document', 'rental-gates'); ?></h3>
            <button class="rg-modal-close" onclick="closeUploadModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="rg-modal-body">
                <div class="rg-upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <h4><?php _e('Click to upload or drag and drop', 'rental-gates'); ?></h4>
                    <p><?php _e('PDF, Word, Excel, Images (Max 50MB)', 'rental-gates'); ?></p>
                    <input type="file" id="fileInput" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv">
                </div>
                
                <div class="rg-file-preview" id="filePreview" style="display: none;">
                    <div class="rg-file-preview-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="rg-file-preview-info">
                        <div class="rg-file-preview-name" id="fileName"></div>
                        <div class="rg-file-preview-size" id="fileSize"></div>
                    </div>
                    <button type="button" class="rg-file-preview-remove" onclick="clearFile()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                
                <div class="rg-form-row">
                    <label><?php _e('Document Title', 'rental-gates'); ?></label>
                    <input type="text" name="title" id="docTitle" placeholder="<?php _e('Enter document title...', 'rental-gates'); ?>">
                </div>
                
                <div class="rg-form-row-half">
                    <div class="rg-form-row">
                        <label><?php _e('Document Type', 'rental-gates'); ?> *</label>
                        <select name="document_type" required>
                            <?php foreach ($document_types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rg-form-row">
                        <label><?php _e('Associate With', 'rental-gates'); ?> *</label>
                        <select name="entity_type" id="entityType" required onchange="updateEntityOptions()">
                            <option value=""><?php _e('Select type...', 'rental-gates'); ?></option>
                            <?php foreach ($entity_types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="rg-form-row">
                    <label><?php _e('Select Item', 'rental-gates'); ?> *</label>
                    <select name="entity_id" id="entityId" required>
                        <option value=""><?php _e('First select a type above...', 'rental-gates'); ?></option>
                    </select>
                </div>
                
                <div class="rg-form-row">
                    <label><?php _e('Description (Optional)', 'rental-gates'); ?></label>
                    <textarea name="description" rows="3" placeholder="<?php _e('Add any notes about this document...', 'rental-gates'); ?>"></textarea>
                </div>
            </div>
            <div class="rg-modal-footer">
                <button type="button" class="rg-btn rg-btn-secondary" onclick="closeUploadModal()"><?php _e('Cancel', 'rental-gates'); ?></button>
                <button type="submit" class="rg-btn rg-btn-primary" id="uploadBtn" disabled>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <?php _e('Upload', 'rental-gates'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Entity data for dynamic selects
const entityData = {
    building: <?php echo Rental_Gates_Security::json_for_script(array_map(function($b) {
        return array('id' => $b['id'], 'name' => $b['name']);
    }, $buildings)); ?>,
    unit: <?php 
        $units = array();
        foreach ($buildings as $b) {
            $b_units = Rental_Gates_Unit::get_for_building($b['id']);
            if (is_array($b_units)) {
                foreach ($b_units as $u) {
                    $units[] = array('id' => $u['id'], 'name' => $b['name'] . ' - ' . $u['name']);
                }
            }
        }
        echo Rental_Gates_Security::json_for_script($units);
    ?>,
    tenant: <?php echo Rental_Gates_Security::json_for_script(array_map(function($t) {
        return array('id' => $t['id'], 'name' => $t['first_name'] . ' ' . $t['last_name']);
    }, $tenants)); ?>,
    lease: <?php echo Rental_Gates_Security::json_for_script(array_map(function($l) {
        return array('id' => $l['id'], 'name' => 'Lease #' . $l['id'] . ' - ' . ($l['tenant_name'] ?? $l['unit_name'] ?? 'Unknown'));
    }, $leases)); ?>,
    vendor: <?php echo Rental_Gates_Security::json_for_script(array_map(function($v) {
        return array('id' => $v['id'], 'name' => $v['company_name']);
    }, $vendors)); ?>,
    application: <?php echo Rental_Gates_Security::json_for_script(array_map(function($a) {
        return array('id' => $a['id'], 'name' => 'App #' . $a['id'] . ' - ' . ($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
    }, $applications)); ?>,
    work_order: <?php echo Rental_Gates_Security::json_for_script(array_map(function($w) {
        return array('id' => $w['id'], 'name' => 'WO #' . $w['id'] . ' - ' . ($w['title'] ?? 'Untitled'));
    }, $work_orders)); ?>
};

function openUploadModal() {
    document.getElementById('uploadModal').classList.add('active');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
    document.getElementById('uploadForm').reset();
    clearFile();
}

function updateEntityOptions() {
    const type = document.getElementById('entityType').value;
    const select = document.getElementById('entityId');
    
    select.innerHTML = '<option value=""><?php echo esc_js(__('Select...', 'rental-gates')); ?></option>';
    
    if (type && entityData[type]) {
        entityData[type].forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            select.appendChild(option);
        });
    }
}

// File handling
const fileInput = document.getElementById('fileInput');
const uploadZone = document.getElementById('uploadZone');

fileInput.addEventListener('change', handleFileSelect);

uploadZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', () => {
    uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        handleFileSelect();
    }
});

function handleFileSelect() {
    const file = fileInput.files[0];
    if (file) {
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = formatFileSize(file.size);
        document.getElementById('filePreview').style.display = 'flex';
        document.getElementById('uploadZone').style.display = 'none';
        document.getElementById('uploadBtn').disabled = false;
        
        // Auto-fill title from filename
        if (!document.getElementById('docTitle').value) {
            document.getElementById('docTitle').value = file.name.replace(/\.[^/.]+$/, '');
        }
    }
}

function clearFile() {
    fileInput.value = '';
    document.getElementById('filePreview').style.display = 'none';
    document.getElementById('uploadZone').style.display = 'block';
    document.getElementById('uploadBtn').disabled = true;
}

function formatFileSize(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' bytes';
}

// Form submission
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('uploadBtn');
    btn.disabled = true;
    btn.innerHTML = '<?php echo esc_js(__('Uploading...', 'rental-gates')); ?>';
    
    const formData = new FormData(this);
    formData.append('action', 'rental_gates_upload_document');
    formData.append('nonce', '<?php echo wp_create_nonce('rental_gates_nonce'); ?>');
    
    try {
        const response = await fetch(ajaxurl, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.data?.message || '<?php echo esc_js(__('Upload failed', 'rental-gates')); ?>');
            btn.disabled = false;
            btn.innerHTML = '<?php echo esc_js(__('Upload', 'rental-gates')); ?>';
        }
    } catch (error) {
        alert('<?php echo esc_js(__('An error occurred', 'rental-gates')); ?>');
        btn.disabled = false;
        btn.innerHTML = '<?php echo esc_js(__('Upload', 'rental-gates')); ?>';
    }
});

function deleteDocument(id, docName) {
    RentalGates.confirmDelete({
        title: '<?php echo esc_js(__('Delete Document', 'rental-gates')); ?>',
        message: '<?php echo esc_js(__('Are you sure you want to delete this document?', 'rental-gates')); ?>',
        itemName: docName || '',
        ajaxAction: 'rental_gates_delete_document',
        ajaxData: { document_id: id },
        onConfirm: function() {
            const card = document.querySelector(`.rg-doc-card[data-id="${id}"]`);
            if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => card.remove(), 300);
            }
        }
    });
}

// Close modal on outside click
document.getElementById('uploadModal').addEventListener('click', function(e) {
    if (e.target === this) closeUploadModal();
});
</script>
