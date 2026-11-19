(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.querySelector('[data-tfp-cohort-modal]');
        const openButton = document.querySelector('[data-tfp-open-cohort-modal]');
        const form = document.querySelector('[data-tfp-cohort-create-form]');
        if (!modal || !openButton || !form) return;

        const message = modal.querySelector('[data-tfp-cohort-message]');
        const submit = form.querySelector('.tfp-cohort-submit');
        const course = form.querySelector('[name="course_id"]');
        const date = form.querySelector('[name="start_date"]');
        const day = form.querySelector('[name="meeting_day"]');
        const name = form.querySelector('#tfp-cohort-auto-name');
        const closeButtons = modal.querySelectorAll('[data-tfp-close-cohort-modal]');

        function semesterFor(month) {
            if (month >= 3 && month <= 5) return 'Spring';
            if (month >= 6 && month <= 8) return 'Summer';
            if (month >= 9 && month <= 11) return 'Fall';
            return 'Winter';
        }

        function updateName() {
            const selected = course.options[course.selectedIndex];
            if (!course.value || !date.value || !day.value) {
                name.value = '';
                return;
            }
            const parts = date.value.split('-').map(Number);
            name.value = selected.text + ' — ' + semesterFor(parts[1]) + ' ' + parts[0] + ' — ' + day.value + ' — Group 1';
        }

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('tfp-cohort-modal-open');
            const first = course;
            window.setTimeout(function () { first.focus(); }, 20);
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('tfp-cohort-modal-open');
            openButton.focus();
        }

        openButton.addEventListener('click', openModal);
        closeButtons.forEach(function (button) { button.addEventListener('click', closeModal); });
        [course, date, day].forEach(function (field) { field.addEventListener('change', updateName); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            message.textContent = '';
            submit.disabled = true;
            const data = new FormData(form);
            data.append('action', 'tfp_admin_create_cohort');
            data.append('nonce', (window.tfpAdminCohorts && window.tfpAdminCohorts.nonce) || '');

            fetch((window.tfpAdminCohorts && window.tfpAdminCohorts.ajaxUrl) || '', {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            }).then(function (response) {
                return response.json();
            }).then(function (response) {
                if (!response || !response.success) {
                    const error = response && response.data && response.data.message;
                    throw new Error(error || 'Unable to create cohort. Please check the form.');
                }
                window.location.href = response.data.redirect || window.location.href;
            }).catch(function (error) {
                message.textContent = error.message || 'Unable to create cohort.';
                submit.disabled = false;
            });
        });
    });
})();
