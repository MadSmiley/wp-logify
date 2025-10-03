/**
 * WP Logify Admin Scripts
 *
 * @package WP_Logify
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {

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

    });

})(jQuery);
