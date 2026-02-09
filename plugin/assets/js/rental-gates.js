/**
 * Rental Gates Main JavaScript
 * Version: 2.0.0
 * 
 * Features:
 * - Delete confirmation modals
 * - WordPress Media Library integration
 * - Toast notifications
 * - QR code management
 * - Common utilities
 */

(function($) {
    'use strict';
    
    // Support both old and new variable names
    var rgConfig = typeof rentalGatesData !== 'undefined' ? rentalGatesData : (typeof rgData !== 'undefined' ? rgData : {});
    
    window.RentalGates = {

        /**
         * Escape HTML special characters to prevent XSS.
         * @param {string} str - Raw string to escape
         * @returns {string} HTML-safe string
         */
        escapeHtml: function(str) {
            if (typeof str !== 'string') return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        // Configuration
        config: {
            ajaxUrl: rgConfig.ajaxUrl || '/wp-admin/admin-ajax.php',
            nonce: rgConfig.nonce || '',
            i18n: rgConfig.i18n || {
                confirm: 'Confirm',
                cancel: 'Cancel',
                delete: 'Delete',
                deleting: 'Deleting...',
                success: 'Success',
                error: 'Error',
                deleteConfirmTitle: 'Confirm Delete',
                deleteConfirmMessage: 'Are you sure you want to delete this item? This action cannot be undone.',
                selectImage: 'Select Image',
                selectImages: 'Select Images',
                useImage: 'Use this image',
                useImages: 'Use these images',
                removeImage: 'Remove image'
            }
        },
        
        // Initialize
        init: function() {
            this.initModal();
            this.initToast();
            this.initMediaLibrary();
            this.bindEvents();
        },
        
        // ==========================================
        // MODAL SYSTEM
        // ==========================================
        
        initModal: function() {
            // Create modal container if not exists
            if (!$('#rg-modal-container').length) {
                $('body').append(`
                    <div id="rg-modal-container" class="rg-modal-overlay" style="display:none;">
                        <div class="rg-modal">
                            <div class="rg-modal-header">
                                <h3 class="rg-modal-title"></h3>
                                <button type="button" class="rg-modal-close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="rg-modal-body"></div>
                            <div class="rg-modal-footer"></div>
                        </div>
                    </div>
                `);
            }
        },
        
        /**
         * Show a modal dialog
         * @param {Object} options - Modal options
         */
        showModal: function(options) {
            const defaults = {
                title: '',
                body: '',
                footer: '',
                size: 'medium', // small, medium, large
                closable: true,
                onShow: null,
                onHide: null
            };
            
            const settings = $.extend({}, defaults, options);
            const $modal = $('#rg-modal-container');
            const $modalBox = $modal.find('.rg-modal');
            
            // Set content - title uses .text() to prevent XSS
            $modal.find('.rg-modal-title').text(settings.title);
            $modal.find('.rg-modal-body').html(settings.body);
            $modal.find('.rg-modal-footer').html(settings.footer);
            
            // Set size
            $modalBox.removeClass('rg-modal-small rg-modal-medium rg-modal-large')
                     .addClass('rg-modal-' + settings.size);
            
            // Toggle close button
            $modal.find('.rg-modal-close').toggle(settings.closable);
            
            // Store callbacks
            $modal.data('onHide', settings.onHide);
            
            // Show modal
            $modal.fadeIn(200);
            $('body').addClass('rg-modal-open');
            
            if (settings.onShow) {
                settings.onShow($modal);
            }
        },
        
        /**
         * Hide modal
         */
        hideModal: function() {
            const $modal = $('#rg-modal-container');
            const onHide = $modal.data('onHide');
            
            $modal.fadeOut(200);
            $('body').removeClass('rg-modal-open');
            
            if (onHide) {
                onHide();
            }
        },
        
        /**
         * Show delete confirmation modal
         * @param {Object} options - Confirmation options
         */
        confirmDelete: function(options) {
            const defaults = {
                title: this.config.i18n.deleteConfirmTitle,
                message: this.config.i18n.deleteConfirmMessage,
                itemName: '',
                itemType: 'item',
                onConfirm: null,
                onCancel: null,
                ajaxAction: null,
                ajaxData: {},
                redirectUrl: null
            };
            
            const settings = $.extend({}, defaults, options);
            const self = this;
            
            // Build message with item name if provided
            let bodyHtml = `
                <div class="rg-confirm-delete">
                    <div class="rg-confirm-icon">
                        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <p class="rg-confirm-message">${settings.message}</p>
            `;
            
            if (settings.itemName) {
                bodyHtml += `<p class="rg-confirm-item"><strong>${settings.itemName}</strong></p>`;
            }
            
            bodyHtml += '</div>';
            
            const footerHtml = `
                <button type="button" class="rg-btn rg-btn-secondary" data-dismiss="modal">
                    ${this.config.i18n.cancel}
                </button>
                <button type="button" class="rg-btn rg-btn-danger" id="rg-confirm-delete-btn">
                    <span class="rg-btn-text">${this.config.i18n.delete}</span>
                    <span class="rg-btn-loading" style="display:none;">
                        <svg class="rg-spinner" width="16" height="16" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="32" stroke-linecap="round"/>
                        </svg>
                        ${this.config.i18n.deleting}
                    </span>
                </button>
            `;
            
            this.showModal({
                title: settings.title,
                body: bodyHtml,
                footer: footerHtml,
                size: 'small',
                onShow: function($modal) {
                    // Bind confirm button
                    $('#rg-confirm-delete-btn').off('click').on('click', function() {
                        const $btn = $(this);
                        $btn.prop('disabled', true);
                        $btn.find('.rg-btn-text').hide();
                        $btn.find('.rg-btn-loading').show();
                        
                        if (settings.ajaxAction) {
                            // Perform AJAX delete
                            self.ajaxDelete(settings.ajaxAction, settings.ajaxData, {
                                onSuccess: function(response) {
                                    self.hideModal();
                                    self.showToast(self.config.i18n.success, 'success');
                                    
                                    if (settings.redirectUrl) {
                                        window.location.href = settings.redirectUrl;
                                    } else if (settings.onConfirm) {
                                        settings.onConfirm(response);
                                    }
                                },
                                onError: function(error) {
                                    $btn.prop('disabled', false);
                                    $btn.find('.rg-btn-text').show();
                                    $btn.find('.rg-btn-loading').hide();
                                    self.showToast(error, 'error');
                                }
                            });
                        } else if (settings.onConfirm) {
                            settings.onConfirm();
                        }
                    });
                },
                onHide: function() {
                    if (settings.onCancel) {
                        settings.onCancel();
                    }
                }
            });
        },
        
        /**
         * Perform AJAX delete request
         */
        ajaxDelete: function(action, data, callbacks) {
            const self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: $.extend({
                    action: action,
                    nonce: this.config.nonce
                }, data),
                success: function(response) {
                    if (response.success) {
                        if (callbacks.onSuccess) {
                            callbacks.onSuccess(response);
                        }
                    } else {
                        if (callbacks.onError) {
                            callbacks.onError(response.data || 'An error occurred');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (callbacks.onError) {
                        callbacks.onError('Network error: ' + error);
                    }
                }
            });
        },
        
        // ==========================================
        // TOAST NOTIFICATIONS
        // ==========================================
        
        initToast: function() {
            if (!$('#rg-toast-container').length) {
                $('body').append('<div id="rg-toast-container" class="rg-toast-container"></div>');
            }
        },
        
        /**
         * Show toast notification
         * @param {string} message - Toast message
         * @param {string} type - Toast type (success, error, warning, info)
         * @param {number} duration - Duration in ms (default 4000)
         */
        showToast: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 4000;
            
            const icons = {
                success: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
                error: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
                warning: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
                info: '<svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
            };
            
            const $toast = $('<div>', {
                class: 'rg-toast rg-toast-' + RentalGates.escapeHtml(type)
            }).append(
                $('<span>', { class: 'rg-toast-icon' }).html(icons[type] || icons['info']),
                $('<span>', { class: 'rg-toast-message' }).text(message),
                $('<button>', { type: 'button', class: 'rg-toast-close' }).html('&times;')
            );
            
            $('#rg-toast-container').append($toast);
            
            // Animate in
            setTimeout(function() {
                $toast.addClass('rg-toast-show');
            }, 10);
            
            // Auto dismiss
            const timer = setTimeout(function() {
                $toast.removeClass('rg-toast-show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, duration);
            
            // Manual dismiss
            $toast.find('.rg-toast-close').on('click', function() {
                clearTimeout(timer);
                $toast.removeClass('rg-toast-show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            });
        },
        
        // ==========================================
        // WORDPRESS MEDIA LIBRARY
        // ==========================================
        
        mediaFrame: null,
        mediaCallback: null,
        
        initMediaLibrary: function() {
            // Will be initialized on demand
        },
        
        /**
         * Open WordPress Media Library
         * @param {Object} options - Media library options
         */
        openMediaLibrary: function(options) {
            const defaults = {
                title: this.config.i18n.selectImage,
                button: this.config.i18n.useImage,
                multiple: false,
                type: 'image',
                onSelect: null
            };
            
            const settings = $.extend({}, defaults, options);
            const self = this;
            
            // Check if wp.media is available
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                console.error('WordPress media library not available');
                this.showToast('Media library not available', 'error');
                return;
            }
            
            // Create frame if needed or reset
            if (this.mediaFrame) {
                this.mediaFrame.off('select');
            }
            
            this.mediaFrame = wp.media({
                title: settings.title,
                button: {
                    text: settings.button
                },
                multiple: settings.multiple,
                library: {
                    type: settings.type
                }
            });
            
            // Handle selection
            this.mediaFrame.on('select', function() {
                if (settings.multiple) {
                    const attachments = self.mediaFrame.state().get('selection').toJSON();
                    if (settings.onSelect) {
                        settings.onSelect(attachments);
                    }
                } else {
                    const attachment = self.mediaFrame.state().get('selection').first().toJSON();
                    if (settings.onSelect) {
                        settings.onSelect(attachment);
                    }
                }
            });
            
            this.mediaFrame.open();
        },
        
        /**
         * Open media library for single image selection
         * @param {function} callback - Callback with attachment object
         */
        selectImage: function(callback) {
            this.openMediaLibrary({
                title: this.config.i18n.selectImage,
                button: this.config.i18n.useImage,
                multiple: false,
                onSelect: callback
            });
        },
        
        /**
         * Open media library for multiple image selection
         * @param {function} callback - Callback with array of attachments
         */
        selectImages: function(callback) {
            this.openMediaLibrary({
                title: this.config.i18n.selectImages,
                button: this.config.i18n.useImages,
                multiple: true,
                onSelect: callback
            });
        },
        
        // ==========================================
        // GALLERY MANAGER
        // ==========================================
        
        /**
         * Initialize gallery manager for a container
         * @param {string} containerId - Container element ID
         * @param {string} inputId - Hidden input ID for storing data
         */
        initGallery: function(containerId, inputId) {
            const self = this;
            const $container = $('#' + containerId);
            const $input = $('#' + inputId);
            
            // Get existing gallery data
            let gallery = [];
            try {
                gallery = JSON.parse($input.val() || '[]');
            } catch (e) {
                gallery = [];
            }
            
            // Add button handler
            $container.find('.rg-gallery-add').off('click').on('click', function() {
                self.selectImages(function(attachments) {
                    attachments.forEach(function(att) {
                        gallery.push({
                            id: att.id,
                            url: att.url,
                            thumbnail: att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url
                        });
                    });
                    self.renderGallery($container, gallery);
                    $input.val(JSON.stringify(gallery));
                });
            });
            
            // Render initial gallery
            this.renderGallery($container, gallery);
            
            // Store reference
            $container.data('gallery', gallery);
            $container.data('input', $input);
        },
        
        /**
         * Render gallery items
         */
        renderGallery: function($container, gallery) {
            const self = this;
            const $grid = $container.find('.rg-gallery-grid');
            const $addBtn = $container.find('.rg-gallery-add');
            
            // Remove existing items (except add button)
            $grid.find('.rg-gallery-item').remove();
            
            // Add items
            gallery.forEach(function(img, index) {
                const url = typeof img === 'string' ? img : (img.thumbnail || img.url);
                const $item = $(`
                    <div class="rg-gallery-item" data-index="${index}">
                        <img src="${url}" alt="">
                        <button type="button" class="rg-gallery-item-remove" title="${self.config.i18n.removeImage}">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                `);
                
                // Remove handler
                $item.find('.rg-gallery-item-remove').on('click', function() {
                    gallery.splice(index, 1);
                    self.renderGallery($container, gallery);
                    $container.data('input').val(JSON.stringify(gallery));
                });
                
                $addBtn.before($item);
            });
            
            $container.data('gallery', gallery);
        },
        
        /**
         * Initialize featured image selector
         * @param {string} buttonId - Button element ID
         * @param {string} previewId - Preview image element ID
         * @param {string} inputId - Hidden input ID
         */
        initFeaturedImage: function(buttonId, previewId, inputId) {
            const self = this;
            const $button = $('#' + buttonId);
            const $preview = $('#' + previewId);
            const $input = $('#' + inputId);
            
            $button.off('click').on('click', function() {
                self.selectImage(function(attachment) {
                    $input.val(attachment.id);
                    var imgUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
                    $preview.empty().append(
                        $('<img>', { src: imgUrl, alt: '' }),
                        $('<button>', { type: 'button', class: 'rg-featured-remove', title: self.config.i18n.removeImage }).html(
                            '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">' +
                            '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>' +
                            '</svg>'
                        )
                    );
                    $preview.addClass('has-image');
                    
                    // Remove handler
                    $preview.find('.rg-featured-remove').on('click', function(e) {
                        e.stopPropagation();
                        $input.val('');
                        $preview.html('').removeClass('has-image');
                    });
                });
            });
        },
        
        // ==========================================
        // QR CODE MANAGEMENT
        // ==========================================
        
        /**
         * Generate QR code for entity
         * @param {string} type - Entity type (building, unit, organization)
         * @param {number} id - Entity ID
         * @param {function} callback - Callback with QR data
         */
        generateQR: function(type, id, callback) {
            const self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rental_gates_generate_qr',
                    nonce: this.config.nonce,
                    type: type,
                    entity_id: id
                },
                success: function(response) {
                    if (response.success) {
                        if (callback) {
                            callback(response.data);
                        }
                    } else {
                        self.showToast(response.data || 'Failed to generate QR code', 'error');
                    }
                },
                error: function() {
                    self.showToast('Network error', 'error');
                }
            });
        },
        
        /**
         * Show QR code modal
         * @param {Object} qrData - QR code data
         */
        showQRModal: function(qrData) {
            const bodyHtml = `
                <div class="rg-qr-display">
                    <img src="${qrData.qr_image}" alt="QR Code" class="rg-qr-image">
                    <p class="rg-qr-url">${qrData.url}</p>
                    <div class="rg-qr-stats">
                        <span><strong>Scans:</strong> ${qrData.scan_count || 0}</span>
                        ${qrData.last_scanned_at ? `<span><strong>Last Scan:</strong> ${qrData.last_scanned_at}</span>` : ''}
                    </div>
                </div>
            `;
            
            const footerHtml = `
                <button type="button" class="rg-btn rg-btn-secondary" data-dismiss="modal">Close</button>
                <a href="${qrData.qr_image}&format=png" download="qr-code.png" class="rg-btn rg-btn-primary">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download PNG
                </a>
            `;
            
            this.showModal({
                title: 'QR Code',
                body: bodyHtml,
                footer: footerHtml,
                size: 'small'
            });
        },
        
        // ==========================================
        // EVENT BINDINGS
        // ==========================================
        
        bindEvents: function() {
            const self = this;
            
            // Modal dismiss buttons
            $(document).on('click', '[data-dismiss="modal"]', function() {
                self.hideModal();
            });
            
            // Modal overlay click
            $(document).on('click', '#rg-modal-container', function(e) {
                if ($(e.target).is('#rg-modal-container')) {
                    self.hideModal();
                }
            });
            
            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#rg-modal-container').is(':visible')) {
                    self.hideModal();
                }
            });
            
            // Generic delete buttons with data attributes
            $(document).on('click', '[data-rg-delete]', function(e) {
                e.preventDefault();
                const $btn = $(this);
                
                self.confirmDelete({
                    title: $btn.data('rg-delete-title') || self.config.i18n.deleteConfirmTitle,
                    message: $btn.data('rg-delete-message') || self.config.i18n.deleteConfirmMessage,
                    itemName: $btn.data('rg-delete-item') || '',
                    ajaxAction: $btn.data('rg-delete'),
                    ajaxData: {
                        [$btn.data('rg-delete-id-field') || 'id']: $btn.data('rg-delete-id')
                    },
                    redirectUrl: $btn.data('rg-delete-redirect') || null,
                    onConfirm: function() {
                        // If no redirect, remove the element's parent row/card
                        const $target = $btn.closest($btn.data('rg-delete-target') || '.rg-item-row');
                        if ($target.length) {
                            $target.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    }
                });
            });
            
            // QR code generation buttons
            $(document).on('click', '[data-rg-qr]', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const type = $btn.data('rg-qr');
                const id = $btn.data('rg-qr-id');
                
                $btn.prop('disabled', true);
                
                self.generateQR(type, id, function(data) {
                    $btn.prop('disabled', false);
                    self.showQRModal(data);
                });
            });
        },
        
        // ==========================================
        // UTILITY FUNCTIONS
        // ==========================================
        
        /**
         * Format currency
         */
        formatCurrency: function(amount, currency) {
            currency = currency || 'USD';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency
            }).format(amount);
        },
        
        /**
         * Format date
         */
        formatDate: function(date, format) {
            const d = new Date(date);
            return d.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },
        
        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        RentalGates.init();
    });
    
})(jQuery);
