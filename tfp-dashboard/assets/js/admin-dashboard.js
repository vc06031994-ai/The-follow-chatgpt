/* TFP Admin Dashboard interactions */
document.addEventListener('DOMContentLoaded', function () {
    var app = document.querySelector('.tfp-admin-app');
    if (app) {
        // Desktop sidebar collapse and mobile off-canvas navigation.
        document.querySelectorAll('[data-tfp-admin-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 991px)').matches) {
                    app.classList.toggle('is-menu-open');
                } else {
                    document.body.classList.toggle('tfp-admin-sidebar-is-collapsed');
                }
            });
        });

        // Close the mobile navigation when the backdrop is selected.
        app.addEventListener('click', function (event) {
            if (app.classList.contains('is-menu-open') && event.target === app) {
                app.classList.remove('is-menu-open');
            }
        });

        // Close the off-canvas navigation after selecting a destination.
        document.querySelectorAll('.tfp-admin-nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                app.classList.remove('is-menu-open');
            });
        });
    }

    // Apply role, status and system filters immediately after selection.
    var filters = document.querySelector('.tfp-admin-users-filters');
    if (filters) {
        filters.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () {
                filters.submit();
            });
        });
    }
});


/* Course Editor: submit the active reading form from the page-level Save button. */
document.addEventListener('click', function (event) {
    var saveTrigger = event.target.closest('[data-tfp-editor-save-trigger]');
    if (saveTrigger) {
        var readingForm = document.querySelector('.tfp-editor-reading-form');
        if (readingForm) {
            readingForm.requestSubmit();
        }
    }

    var moveTrigger = event.target.closest('[data-tfp-editor-move-toggle]');
    if (moveTrigger) {
        var moveRow = document.querySelector('.tfp-editor-move-row');
        if (moveRow) {
            moveRow.hidden = !moveRow.hidden;
        }
    }
});
