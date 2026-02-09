/**
 * Rental Gates - QR Code Module
 *
 * QR code generation via AJAX and display modal.
 * Extends window.RentalGates namespace.
 *
 * @package RentalGates
 * @since 2.42.0
 */
(function($) {
    'use strict';

    var RG = window.RentalGates = window.RentalGates || {};

    /**
     * Generate a QR code via AJAX.
     * @param {string}   type     Entity type (building, unit, organization)
     * @param {number}   id       Entity ID
     * @param {function} callback Receives QR data object
     */
    RG.generateQR = function(type, id, callback) {
        var self = this;

        $.ajax({
            url:  this.config.ajaxUrl,
            type: 'POST',
            data: {
                action:    'rental_gates_generate_qr',
                nonce:     this.config.nonce,
                type:      type,
                entity_id: id
            },
            success: function(response) {
                if (response.success) {
                    if (callback) { callback(response.data); }
                } else {
                    self.showToast(response.data || 'Failed to generate QR code', 'error');
                }
            },
            error: function() {
                self.showToast('Network error', 'error');
            }
        });
    };

    /**
     * Display QR code in a modal.
     * All dynamic values are escaped to prevent XSS.
     * @param {Object} qrData  qr_image, url, scan_count, last_scanned_at
     */
    RG.showQRModal = function(qrData) {
        var esc       = this.escapeHtml.bind(this);
        var scanCount = parseInt(qrData.scan_count, 10) || 0;

        var bodyHtml =
            '<div class="rg-qr-display">' +
                '<img src="' + esc(qrData.qr_image) + '" alt="QR Code" class="rg-qr-image">' +
                '<p class="rg-qr-url">' + esc(qrData.url) + '</p>' +
                '<div class="rg-qr-stats">' +
                    '<span><strong>Scans:</strong> ' + scanCount + '</span>' +
                    (qrData.last_scanned_at ? '<span><strong>Last Scan:</strong> ' + esc(qrData.last_scanned_at) + '</span>' : '') +
                '</div>' +
            '</div>';

        var footerHtml =
            '<button type="button" class="rg-btn rg-btn-secondary" data-dismiss="modal">Close</button>' +
            '<a href="' + esc(qrData.qr_image) + '&format=png" download="qr-code.png" class="rg-btn rg-btn-primary">' +
                '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>' +
                '</svg>' +
                'Download PNG' +
            '</a>';

        this.showModal({
            title: 'QR Code',
            body: bodyHtml,
            footer: footerHtml,
            size: 'small'
        });
    };

})(jQuery);
