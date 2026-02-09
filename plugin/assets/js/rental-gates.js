/**
 * Rental Gates - Main Entry Point
 *
 * Core namespace, configuration, utility functions, event bindings.
 * Module files (modal, toast, media, qr) extend this namespace
 * and must be enqueued BEFORE this file.
 *
 * @package RentalGates
 * @since 2.42.0
 */
(function($) {
    'use strict';

    var rgConfig = typeof rentalGatesData !== 'undefined' ? rentalGatesData
                 : (typeof rgData !== 'undefined' ? rgData : {});

    var RG = window.RentalGates = window.RentalGates || {};

    // ==========================================
    // CONFIGURATION
    // ==========================================

    RG.config = {
        ajaxUrl: rgConfig.ajaxUrl || '/wp-admin/admin-ajax.php',
        nonce:   rgConfig.nonce   || '',
        i18n:    rgConfig.i18n    || {
            confirm: 'Confirm', cancel: 'Cancel', 'delete': 'Delete',
            deleting: 'Deleting...', success: 'Success', error: 'Error',
            deleteConfirmTitle: 'Confirm Delete',
            deleteConfirmMessage: 'Are you sure you want to delete this item? This action cannot be undone.',
            selectImage: 'Select Image', selectImages: 'Select Images',
            useImage: 'Use this image', useImages: 'Use these images',
            removeImage: 'Remove image'
        }
    };

    // ==========================================
    // CORE UTILITIES
    // ==========================================

    /**
     * Escape HTML special characters to prevent XSS.
     * @param {string} str Raw string
     * @returns {string} HTML-safe string
     */
    RG.escapeHtml = function(str) {
        if (typeof str !== 'string') return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    };

    /** Format a number as currency. */
    RG.formatCurrency = function(amount, currency) {
        currency = currency || 'USD';
        return new Intl.NumberFormat('en-US', {
            style: 'currency', currency: currency
        }).format(amount);
    };

    /** Format a date string. */
    RG.formatDate = function(date) {
        var d = new Date(date);
        return d.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric'
        });
    };

    /** Debounce a function. */
    RG.debounce = function(func, wait) {
        var timeout;
        return function() {
            var args = arguments;
            var context = this;
            clearTimeout(timeout);
            timeout = setTimeout(function() { func.apply(context, args); }, wait);
        };
    };

    // ==========================================
    // INITIALISATION
    // ==========================================

    RG.init = function() {
        this.initModal();
        this.initToast();
        if (typeof this.initMediaLibrary === 'function') {
            this.initMediaLibrary();
        }
        this.bindEvents();
    };

    // ==========================================
    // EVENT BINDINGS
    // ==========================================

    RG.bindEvents = function() {
        var self = this;

        // Modal dismiss
        $(document).on('click', '[data-dismiss="modal"]', function() {
            self.hideModal();
        });

        // Overlay click
        $(document).on('click', '#rg-modal-container', function(e) {
            if ($(e.target).is('#rg-modal-container')) {
                self.hideModal();
            }
        });

        // Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#rg-modal-container').is(':visible')) {
                self.hideModal();
            }
        });

        // Generic data-attribute delete buttons
        $(document).on('click', '[data-rg-delete]', function(e) {
            e.preventDefault();
            var $btn = $(this);

            self.confirmDelete({
                title:   $btn.data('rg-delete-title')   || self.config.i18n.deleteConfirmTitle,
                message: $btn.data('rg-delete-message') || self.config.i18n.deleteConfirmMessage,
                itemName: $btn.data('rg-delete-item')   || '',
                ajaxAction: $btn.data('rg-delete'),
                ajaxData: {
                    [$btn.data('rg-delete-id-field') || 'id']: $btn.data('rg-delete-id')
                },
                redirectUrl: $btn.data('rg-delete-redirect') || null,
                onConfirm: function() {
                    var $target = $btn.closest($btn.data('rg-delete-target') || '.rg-item-row');
                    if ($target.length) {
                        $target.fadeOut(300, function() { $(this).remove(); });
                    }
                }
            });
        });

        // QR code generation buttons
        $(document).on('click', '[data-rg-qr]', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var type = $btn.data('rg-qr');
            var id   = $btn.data('rg-qr-id');

            $btn.prop('disabled', true);
            self.generateQR(type, id, function(data) {
                $btn.prop('disabled', false);
                self.showQRModal(data);
            });
        });
    };

    // ==========================================
    // DOM READY
    // ==========================================

    $(document).ready(function() {
        RG.init();
    });

})(jQuery);
