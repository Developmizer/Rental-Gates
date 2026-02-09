/**
 * Rental Gates - Modal Module
 *
 * Modal system + delete confirmation dialog.
 * Extends window.RentalGates namespace.
 *
 * @package RentalGates
 * @since 2.42.0
 */
(function($) {
    'use strict';

    var RG = window.RentalGates = window.RentalGates || {};

    /**
     * Create modal DOM container (once).
     */
    RG.initModal = function() {
        if (!$('#rg-modal-container').length) {
            $('body').append(
                '<div id="rg-modal-container" class="rg-modal-overlay" style="display:none;">' +
                    '<div class="rg-modal">' +
                        '<div class="rg-modal-header">' +
                            '<h3 class="rg-modal-title"></h3>' +
                            '<button type="button" class="rg-modal-close" data-dismiss="modal">&times;</button>' +
                        '</div>' +
                        '<div class="rg-modal-body"></div>' +
                        '<div class="rg-modal-footer"></div>' +
                    '</div>' +
                '</div>'
            );
        }
    };

    /**
     * Show a modal dialog.
     * @param {Object} options  title, body, footer, size, closable, onShow, onHide
     */
    RG.showModal = function(options) {
        var defaults = {
            title: '', body: '', footer: '',
            size: 'medium', closable: true,
            onShow: null, onHide: null
        };

        var settings  = $.extend({}, defaults, options);
        var $modal    = $('#rg-modal-container');
        var $modalBox = $modal.find('.rg-modal');

        $modal.find('.rg-modal-title').text(settings.title);
        $modal.find('.rg-modal-body').html(settings.body);
        $modal.find('.rg-modal-footer').html(settings.footer);

        $modalBox.removeClass('rg-modal-small rg-modal-medium rg-modal-large')
                 .addClass('rg-modal-' + settings.size);

        $modal.find('.rg-modal-close').toggle(settings.closable);
        $modal.data('onHide', settings.onHide);

        $modal.fadeIn(200);
        $('body').addClass('rg-modal-open');

        if (settings.onShow) { settings.onShow($modal); }
    };

    /**
     * Hide the active modal.
     */
    RG.hideModal = function() {
        var $modal = $('#rg-modal-container');
        var onHide = $modal.data('onHide');

        $modal.fadeOut(200);
        $('body').removeClass('rg-modal-open');

        if (onHide) { onHide(); }
    };

    /**
     * Show a delete-confirmation modal with optional AJAX delete.
     * @param {Object} options  title, message, itemName, onConfirm, ajaxAction, ajaxData, redirectUrl
     */
    RG.confirmDelete = function(options) {
        var defaults = {
            title: this.config.i18n.deleteConfirmTitle,
            message: this.config.i18n.deleteConfirmMessage,
            itemName: '', itemType: 'item',
            onConfirm: null, onCancel: null,
            ajaxAction: null, ajaxData: {},
            redirectUrl: null
        };

        var settings = $.extend({}, defaults, options);
        var self = this;

        var bodyHtml =
            '<div class="rg-confirm-delete">' +
                '<div class="rg-confirm-icon">' +
                    '<svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" ' +
                              'd="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>' +
                    '</svg>' +
                '</div>' +
                '<p class="rg-confirm-message">' + self.escapeHtml(settings.message) + '</p>';

        if (settings.itemName) {
            bodyHtml += '<p class="rg-confirm-item"><strong>' + self.escapeHtml(settings.itemName) + '</strong></p>';
        }
        bodyHtml += '</div>';

        var footerHtml =
            '<button type="button" class="rg-btn rg-btn-secondary" data-dismiss="modal">' +
                this.config.i18n.cancel +
            '</button>' +
            '<button type="button" class="rg-btn rg-btn-danger" id="rg-confirm-delete-btn">' +
                '<span class="rg-btn-text">' + this.config.i18n['delete'] + '</span>' +
                '<span class="rg-btn-loading" style="display:none;">' +
                    '<svg class="rg-spinner" width="16" height="16" viewBox="0 0 24 24">' +
                        '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="32" stroke-linecap="round"/>' +
                    '</svg>' +
                    this.config.i18n.deleting +
                '</span>' +
            '</button>';

        this.showModal({
            title: settings.title,
            body: bodyHtml,
            footer: footerHtml,
            size: 'small',
            onShow: function() {
                $('#rg-confirm-delete-btn').off('click').on('click', function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $btn.find('.rg-btn-text').hide();
                    $btn.find('.rg-btn-loading').show();

                    if (settings.ajaxAction) {
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
                if (settings.onCancel) { settings.onCancel(); }
            }
        });
    };

    /**
     * Perform an AJAX delete request.
     */
    RG.ajaxDelete = function(action, data, callbacks) {
        var self = this;
        $.ajax({
            url: this.config.ajaxUrl,
            type: 'POST',
            data: $.extend({ action: action, nonce: this.config.nonce }, data),
            success: function(response) {
                if (response.success) {
                    if (callbacks.onSuccess) { callbacks.onSuccess(response); }
                } else {
                    if (callbacks.onError) { callbacks.onError(response.data || 'An error occurred'); }
                }
            },
            error: function(xhr, status, error) {
                if (callbacks.onError) { callbacks.onError('Network error: ' + error); }
            }
        });
    };

})(jQuery);
