/* TFP Admin Dashboard interactions */
document.addEventListener('DOMContentLoaded', function () {
    var app = document.querySelector('.tfp-admin-app');
    if (!app) return;

    // Desktop sidebar collapse and mobile off-canvas navigation.
    document.querySelectorAll('[data-tfp-admin-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 760px)').matches) {
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
});
