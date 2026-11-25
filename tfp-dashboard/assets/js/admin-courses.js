/* Admin Courses filter interactions */
document.addEventListener('DOMContentLoaded', function () {
    var filters = document.querySelector('.tfp-admin-courses-filters');
    if (!filters) {
        return;
    }
    filters.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', function () {
            filters.submit();
        });
    });
});
