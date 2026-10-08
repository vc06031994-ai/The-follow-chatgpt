(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || !jQuery.fn.selectWoo) {
            return;
        }

        jQuery('.tfp-facilitator-filters select').each(function () {
            var $select = jQuery(this);

            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.selectWoo({
                width: '100%',
                minimumResultsForSearch: Infinity,
                dropdownAutoWidth: false,
            });
        });
    });
})();
