/* User Overview filter controls */
document.addEventListener('DOMContentLoaded', function () {
    var filters = document.querySelector('.tfp-admin-users-filters');
    if (!filters) return;

    // Apply role, status and system filters immediately after selection.
    filters.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', function () {
            filters.submit();
        });
    });
});
