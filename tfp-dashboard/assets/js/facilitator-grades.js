(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('.tfp-fg-filters');

        forms.forEach(function (form) {
            var courseSelect = form.querySelector('[name="tfp_fg_course"]');
            var cohortSelect = form.querySelector('[name="tfp_fg_cohort"]');
            var weekSelect = form.querySelector('[name="tfp_fg_week"]');
            var search = form.querySelector('input[name="tfp_fg_search"]');
            var searchTimer = null;

            function submitFilters() {
                form.submit();
            }

            /*
             * Use the jQuery change event as well as the native select so this
             * works reliably with WooCommerce SelectWoo, which replaces the
             * visible control while keeping the original <select>.
             */
            if (window.jQuery) {
                window.jQuery(form).off('change.tfpGrades').on(
                    'change.tfpGrades',
                    'select',
                    function () {
                        if (this === courseSelect && cohortSelect) {
                            window.jQuery(cohortSelect).val('').trigger('change.select2');
                        }
                        if (this === courseSelect && weekSelect) {
                            window.jQuery(weekSelect).val('0').trigger('change.select2');
                        }
                        submitFilters();
                    }
                );
            } else {
                form.querySelectorAll('select').forEach(function (select) {
                    select.addEventListener('change', function () {
                        if (select === courseSelect && cohortSelect) {
                            cohortSelect.value = '';
                        }
                        if (select === courseSelect && weekSelect) {
                            weekSelect.value = '0';
                        }
                        submitFilters();
                    });
                });
            }

            if (search) {
                search.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submitFilters();
                    }
                });

                search.addEventListener('search', submitFilters);

                search.addEventListener('input', function () {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(submitFilters, 500);
                });
            }
        });
    });
})();
