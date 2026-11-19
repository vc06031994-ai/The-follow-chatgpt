(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        // Keep the existing Admin sidebar collapse interaction available on this route.
        var toggle = document.querySelector('[data-tfp-admin-toggle]');
        var app = document.querySelector('.tfp-admin-app');
        if (toggle && app) {
            toggle.addEventListener('click', function () {
                app.classList.toggle('is-sidebar-collapsed');
            });
        }
    });
})();