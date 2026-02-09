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

<!-- Header -->
<div class="rg-documents-header">
    <h1><?php _e('Documents', 'rental-gates'); ?></h1>
    <button onclick="openUploadModal()" class="rg-btn rg-btn-primary">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
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
        <svg aria-hidden="true" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
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
    <svg aria-hidden="true" width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
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
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
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
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <?php _e('View', 'rental-gates'); ?>
                </a>
                <a href="<?php echo esc_url($doc['file_url']); ?>" download class="rg-doc-action">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <?php _e('Download', 'rental-gates'); ?>
                </a>
                <button onclick="deleteDocument(<?php echo $doc['id']; ?>, '<?php echo esc_js($doc['title']); ?>')" class="rg-doc-action danger" aria-label="<?php printf(esc_attr__('Delete %s', 'rental-gates'), esc_attr($doc['title'])); ?>">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Upload Modal -->
<div class="rg-modal-overlay" id="uploadModal" role="dialog" aria-modal="true" aria-labelledby="upload-modal-title">
    <div class="rg-modal">
        <div class="rg-modal-header">
            <h3 class="rg-modal-title" id="upload-modal-title"><?php _e('Upload Document', 'rental-gates'); ?></h3>
            <button class="rg-modal-close" onclick="closeUploadModal()" aria-label="<?php esc_attr_e('Close', 'rental-gates'); ?>">
                <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="rg-modal-body">
                <div class="rg-upload-zone" id="uploadZone" role="button" tabindex="0" aria-label="<?php esc_attr_e('Click to upload or drag and drop files', 'rental-gates'); ?>" onclick="document.getElementById('fileInput').click()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();document.getElementById('fileInput').click();}"
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <h4><?php _e('Click to upload or drag and drop', 'rental-gates'); ?></h4>
                    <p><?php _e('PDF, Word, Excel, Images (Max 50MB)', 'rental-gates'); ?></p>
                    <input type="file" id="fileInput" name="document" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv">
                </div>
                
                <div class="rg-file-preview" id="filePreview" style="display: none;">
                    <div class="rg-file-preview-icon">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="rg-file-preview-info">
                        <div class="rg-file-preview-name" id="fileName"></div>
                        <div class="rg-file-preview-size" id="fileSize"></div>
                    </div>
                    <button type="button" class="rg-file-preview-remove" onclick="clearFile()">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                
                <div class="rg-form-row">
                    <label for="docTitle"><?php _e('Document Title', 'rental-gates'); ?></label>
                    <input type="text" name="title" id="docTitle" placeholder="<?php _e('Enter document title...', 'rental-gates'); ?>">
                </div>

                <div class="rg-form-row-half">
                    <div class="rg-form-row">
                        <label for="docType"><?php _e('Document Type', 'rental-gates'); ?> <span aria-hidden="true">*</span></label>
                        <select name="document_type" id="docType" required aria-required="true">
                            <?php foreach ($document_types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="rg-form-row">
                        <label for="entityType"><?php _e('Associate With', 'rental-gates'); ?> <span aria-hidden="true">*</span></label>
                        <select name="entity_type" id="entityType" required aria-required="true" onchange="updateEntityOptions()">
                            <option value=""><?php _e('Select type...', 'rental-gates'); ?></option>
                            <?php foreach ($entity_types as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="rg-form-row">
                    <label for="entityId"><?php _e('Select Item', 'rental-gates'); ?> <span aria-hidden="true">*</span></label>
                    <select name="entity_id" id="entityId" required aria-required="true">
                        <option value=""><?php _e('First select a type above...', 'rental-gates'); ?></option>
                    </select>
                </div>
                
                <div class="rg-form-row">
                    <label for="docDescription"><?php _e('Description (Optional)', 'rental-gates'); ?></label>
                    <textarea name="description" id="docDescription" rows="3" placeholder="<?php _e('Add any notes about this document...', 'rental-gates'); ?>"></textarea>
                </div>
            </div>
            <div class="rg-modal-footer">
                <button type="button" class="rg-btn rg-btn-secondary" onclick="closeUploadModal()"><?php _e('Cancel', 'rental-gates'); ?></button>
                <button type="submit" class="rg-btn rg-btn-primary" id="uploadBtn" disabled>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
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
    const modal = document.getElementById('uploadModal');
    modal.classList.add('active');
    modal.querySelector('input, select, button').focus();
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
    document.getElementById('uploadForm').reset();
    clearFile();
}

// ESC key closes modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('uploadModal');
        if (modal.classList.contains('active')) closeUploadModal();
    }
});

// Focus trap inside modal
document.getElementById('uploadModal').addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    const focusable = this.querySelectorAll('button, [href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])');
    if (!focusable.length) return;
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
});

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
            if (typeof RentalGates !== 'undefined' && RentalGates.toast) {
                RentalGates.toast(data.data?.message || '<?php echo esc_js(__('Upload failed', 'rental-gates')); ?>', 'error');
            }
            btn.disabled = false;
            btn.innerHTML = '<?php echo esc_js(__('Upload', 'rental-gates')); ?>';
        }
    } catch (error) {
        if (typeof RentalGates !== 'undefined' && RentalGates.toast) {
            RentalGates.toast('<?php echo esc_js(__('An error occurred', 'rental-gates')); ?>', 'error');
        }
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
