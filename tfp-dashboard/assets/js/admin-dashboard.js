/* TFP Admin Dashboard interactions */
document.addEventListener('DOMContentLoaded', function () {
    var app = document.querySelector('.tfp-admin-app');
    if (app) {
        var main = app.querySelector('.tfp-admin-main');
        var mobileBar = main ? main.querySelector('.tfp-admin-mobilebar') : null;

        // Add the shared mobile menu bar on Admin subpages that do not render one.
        if (main && !mobileBar) {
            mobileBar = document.createElement('div');
            mobileBar.className = 'tfp-admin-mobilebar';
            main.insertBefore(mobileBar, main.firstChild);
        }

        if (mobileBar) {
            var mobileToggle = mobileBar.querySelector('[data-tfp-admin-toggle]');
            if (!mobileToggle) {
                mobileToggle = document.createElement('button');
                mobileToggle.type = 'button';
                mobileToggle.setAttribute('data-tfp-admin-toggle', '');
                mobileBar.insertBefore(mobileToggle, mobileBar.firstChild);
            }

            // Normalize the icon and accessibility attributes on every Admin subpage.
            mobileToggle.className = 'tfp-admin-mobilebar-toggle';
            mobileToggle.setAttribute('aria-controls', 'tfp-admin-sidebar');
            mobileToggle.setAttribute('aria-expanded', 'false');
            mobileToggle.setAttribute('aria-label', 'Open admin menu');
            mobileToggle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M6 17V1M6 17H13.8C15.5 17 17 2.5 17 4.2V13.8C17 15.5 15.5 17 13.8 17H6M6 17H4.2C2.5 17 1 15.5 1 13.8V4.2C1 2.5 2.5 1 4.2 1H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }

        // Desktop sidebar collapse and mobile off-canvas navigation.
        app.querySelectorAll('[data-tfp-admin-toggle]').forEach(function (button) {
            if (button.dataset.tfpToggleBound === '1') return;
            button.dataset.tfpToggleBound = '1';
            button.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 991px)').matches) {
                    var isOpen = app.classList.toggle('is-menu-open');
                    app.querySelectorAll('.tfp-admin-mobilebar-toggle').forEach(function (toggle) {
                        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        toggle.setAttribute('aria-label', isOpen ? 'Close admin menu' : 'Open admin menu');
                    });
                } else {
                    document.body.classList.toggle('tfp-admin-sidebar-is-collapsed');
                }
            });
        });

        // Close the mobile navigation when the backdrop is selected.
        app.addEventListener('click', function (event) {
            if (app.classList.contains('is-menu-open') && event.target === app) {
                app.classList.remove('is-menu-open');
                app.querySelectorAll('.tfp-admin-mobilebar-toggle').forEach(function (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Open admin menu');
                });
            }
        });

        // Close the off-canvas navigation after selecting a destination.
        app.querySelectorAll('.tfp-admin-nav-link').forEach(function (navLink) {
            navLink.addEventListener('click', function () {
                app.classList.remove('is-menu-open');
                app.querySelectorAll('.tfp-admin-mobilebar-toggle').forEach(function (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('aria-label', 'Open admin menu');
                });
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
