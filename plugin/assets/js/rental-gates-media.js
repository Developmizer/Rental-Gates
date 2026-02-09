/**
 * Rental Gates - Media Module
 *
 * WordPress Media Library integration, gallery manager,
 * and featured-image selector.
 * Extends window.RentalGates namespace.
 *
 * @package RentalGates
 * @since 2.42.0
 */
(function($) {
    'use strict';

    var RG = window.RentalGates = window.RentalGates || {};

    RG.mediaFrame    = null;
    RG.mediaCallback = null;

    RG.initMediaLibrary = function() { /* initialized on demand */ };

    /**
     * Open the WordPress Media Library picker.
     * @param {Object} options  title, button, multiple, type, onSelect
     */
    RG.openMediaLibrary = function(options) {
        var defaults = {
            title: this.config.i18n.selectImage,
            button: this.config.i18n.useImage,
            multiple: false,
            type: 'image',
            onSelect: null
        };

        var settings = $.extend({}, defaults, options);
        var self = this;

        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            console.error('WordPress media library not available');
            this.showToast('Media library not available', 'error');
            return;
        }

        if (this.mediaFrame) { this.mediaFrame.off('select'); }

        this.mediaFrame = wp.media({
            title:    settings.title,
            button:   { text: settings.button },
            multiple: settings.multiple,
            library:  { type: settings.type }
        });

        this.mediaFrame.on('select', function() {
            if (settings.multiple) {
                var attachments = self.mediaFrame.state().get('selection').toJSON();
                if (settings.onSelect) { settings.onSelect(attachments); }
            } else {
                var attachment = self.mediaFrame.state().get('selection').first().toJSON();
                if (settings.onSelect) { settings.onSelect(attachment); }
            }
        });

        this.mediaFrame.open();
    };

    /** Select a single image. */
    RG.selectImage = function(callback) {
        this.openMediaLibrary({
            title: this.config.i18n.selectImage,
            button: this.config.i18n.useImage,
            multiple: false,
            onSelect: callback
        });
    };

    /** Select multiple images. */
    RG.selectImages = function(callback) {
        this.openMediaLibrary({
            title: this.config.i18n.selectImages,
            button: this.config.i18n.useImages,
            multiple: true,
            onSelect: callback
        });
    };

    // --------------------------------------------------
    // Gallery manager
    // --------------------------------------------------

    /**
     * Initialise a gallery widget.
     * @param {string} containerId  Wrapper element ID
     * @param {string} inputId      Hidden input ID for JSON storage
     */
    RG.initGallery = function(containerId, inputId) {
        var self       = this;
        var $container = $('#' + containerId);
        var $input     = $('#' + inputId);

        var gallery = [];
        try { gallery = JSON.parse($input.val() || '[]'); } catch (e) { gallery = []; }

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

        this.renderGallery($container, gallery);

        $container.data('gallery', gallery);
        $container.data('input', $input);
    };

    /**
     * Render gallery items using safe DOM construction (no innerHTML).
     */
    RG.renderGallery = function($container, gallery) {
        var self   = this;
        var $grid  = $container.find('.rg-gallery-grid');
        var $addBtn = $container.find('.rg-gallery-add');

        $grid.find('.rg-gallery-item').remove();

        var removeSvg =
            '<svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">' +
            '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>' +
            '</svg>';

        gallery.forEach(function(img, index) {
            var url = typeof img === 'string' ? img : (img.thumbnail || img.url);
            var $item = $('<div>', { 'class': 'rg-gallery-item', 'data-index': index });
            $item.append(
                $('<img>', { src: url, alt: '', loading: 'lazy' }),
                $('<button>', { type: 'button', 'class': 'rg-gallery-item-remove', title: self.config.i18n.removeImage }).html(removeSvg)
            );

            $item.find('.rg-gallery-item-remove').on('click', function() {
                gallery.splice(index, 1);
                self.renderGallery($container, gallery);
                $container.data('input').val(JSON.stringify(gallery));
            });

            $addBtn.before($item);
        });

        $container.data('gallery', gallery);
    };

    // --------------------------------------------------
    // Featured-image selector
    // --------------------------------------------------

    /**
     * Bind a featured-image picker.
     * @param {string} buttonId   Trigger button ID
     * @param {string} previewId  Preview container ID
     * @param {string} inputId    Hidden input ID
     */
    RG.initFeaturedImage = function(buttonId, previewId, inputId) {
        var self     = this;
        var $button  = $('#' + buttonId);
        var $preview = $('#' + previewId);
        var $input   = $('#' + inputId);

        $button.off('click').on('click', function() {
            self.selectImage(function(attachment) {
                $input.val(attachment.id);
                var imgUrl = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
                $preview.empty().append(
                    $('<img>', { src: imgUrl, alt: '' }),
                    $('<button>', { type: 'button', 'class': 'rg-featured-remove', title: self.config.i18n.removeImage }).html(
                        '<svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">' +
                        '<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>' +
                        '</svg>'
                    )
                );
                $preview.addClass('has-image');

                $preview.find('.rg-featured-remove').on('click', function(e) {
                    e.stopPropagation();
                    $input.val('');
                    $preview.html('').removeClass('has-image');
                });
            });
        });
    };

})(jQuery);
