/**
 * 🛠️ ADMIN: TAXONOMY SELECT2 INITIALIZATION
 *
 * Initializes Select2 AJAX-backed dropdowns on the WooCommerce product editor screen.
 * Reads nonce and AJAX URL from window.swacAdminTax (injected by wp_localize_script).
 *
 * @package StatelessWaCommerce
 */
(function ($) {
    'use strict';

    var config = window.swacAdminTax || {};

    $('.swac-taxonomy-select2').each(function () {
        var $select = $(this);
        var taxonomy = $select.data('taxonomy');

        if ($select.data('select2')) {
            return;
        }

        $select.select2({
            allowClear: true,
            placeholder: $select.data('placeholder') || 'Select...',
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: config.ajax_url || config.ajaxUrl || ajaxurl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        action: 'swac_search_taxonomy_terms',
                        taxonomy: taxonomy,
                        q: params.term,
                        security: config.nonce || '',
                    };
                },
                processResults: function (data) {
                    return {
                        results: (data && data.results) ? data.results : [],
                    };
                },
                cache: true,
            },
        });
    });
}(jQuery));
