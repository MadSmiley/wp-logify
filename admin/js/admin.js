/**
 * WP Logify Admin Scripts
 *
 * @package WP_Logify
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        console.log('WP Logify Admin JS Loaded');
        console.log('Dropdown toggles found:', $('.wp-logify-dropdown-toggle').length);

        /**
         * Handle meta modal display
         */
        $('.wp-logify-view-meta').on('click', function(e) {
            e.preventDefault();
            var targetId = $(this).data('target');
            $('#' + targetId).fadeIn(200);
        });

        /**
         * Close modal when clicking on X
         */
        $('.wp-logify-meta-close').on('click', function() {
            $(this).closest('.wp-logify-meta-modal').fadeOut(200);
        });

        /**
         * Close modal when clicking outside content
         */
        $('.wp-logify-meta-modal').on('click', function(e) {
            if ($(e.target).hasClass('wp-logify-meta-modal')) {
                $(this).fadeOut(200);
            }
        });

        /**
         * Close modal with ESC key
         */
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                $('.wp-logify-meta-modal:visible').fadeOut(200);
            }
        });

        /**
         * Handle dropdown filters
         */
        $(document).on('click', '.wp-logify-dropdown-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $button = $(this);
            var $dropdown = $button.siblings('.wp-logify-dropdown-content');
            var isVisible = $dropdown.hasClass('show');

            console.log('Dropdown clicked', $dropdown.length, isVisible);

            // Close all other dropdowns
            $('.wp-logify-dropdown-content').removeClass('show');

            // Toggle current dropdown
            if (!isVisible) {
                $dropdown.addClass('show');
                console.log('Opening dropdown');
            }
        });

        /**
         * Prevent dropdown from closing when clicking inside
         */
        $('.wp-logify-dropdown-content').on('click', function(e) {
            e.stopPropagation();
        });

        /**
         * Close dropdowns when clicking outside
         */
        $(document).on('click', function() {
            $('.wp-logify-dropdown-content').removeClass('show');
        });

        /**
         * Update dropdown button text when checkboxes change
         */
        $('.wp-logify-dropdown-content input[type="checkbox"]').on('change', function() {
            var $dropdown = $(this).closest('.wp-logify-dropdown');
            var $button = $dropdown.find('.wp-logify-dropdown-toggle');
            var $checkboxes = $dropdown.find('input[type="checkbox"]:checked');
            var count = $checkboxes.length;
            var label = $button.data('label') || '';

            if (count > 0) {
                var text = count + ' selected';
                $button.html('<strong>' + label + ':</strong> ' + text + ' <span class="dashicons dashicons-arrow-down-alt2"></span>');
            } else {
                $button.html('<strong>' + label + ':</strong> All <span class="dashicons dashicons-arrow-down-alt2"></span>');
            }
        });

    });

})(jQuery);
