(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof tfpWeekSettings === 'undefined') return;

        // Submit weekly reflection (student)
        var submitBtn = document.getElementById('tfp-submit-weekly-notes');
        if (submitBtn) {
            submitBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var btn = this;
                var lessonId = btn.getAttribute('data-lesson-id');
                var textarea = document.getElementById('tfp-week-reflection');
                if (!lessonId || !textarea) return;
                var text = textarea.value || '';

                btn.disabled = true;
                btn.textContent = tfpWeekSettings.submittingText || 'Submitting...';
                var status = document.getElementById('tfp-weekly-notes-status');

                var body = new URLSearchParams({
                    action: 'tfp_week_submit_reflection',
                    tfp_week_nonce: tfpWeekSettings.nonce,
                    lesson_id: lessonId,
                    text: text
                });

                fetch(tfpWeekSettings.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        if (status) status.textContent = res.message || 'Saved';
                    } else {
                        if (status) status.textContent = (res && res.message) ? res.message : (tfpWeekSettings.submitError || 'Error');
                    }
                })
                .catch(function () {
                    var status = document.getElementById('tfp-weekly-notes-status');
                    if (status) status.textContent = tfpWeekSettings.networkError || 'Network error';
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Submit Weekly Notes';
                });
            });
        }

        // Add facilitator note (editors)
        var addNoteBtn = document.getElementById('tfp-add-facilitator-note');
        if (addNoteBtn) {
            addNoteBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var btn = this;
                var lessonId = btn.getAttribute('data-lesson-id');
                var textarea = document.getElementById('tfp-facilitator-note-text');
                if (!lessonId || !textarea) return;
                var text = textarea.value || '';
                if (!text.trim()) return;

                btn.disabled = true;
                var status = document.getElementById('tfp-add-facilitator-note-status');
                if (status) status.textContent = tfpWeekSettings.submittingText || 'Submitting...';

                var body = new URLSearchParams({
                    action: 'tfp_week_add_facilitator_note',
                    tfp_week_nonce: tfpWeekSettings.nonce,
                    lesson_id: lessonId,
                    text: text
                });

                fetch(tfpWeekSettings.ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (res && res.success) {
                        var wrap = document.getElementById('tfp-facilitator-notes-wrap');
                        if (wrap && res.html) {
                            // insert at top
                            var div = document.createElement('div');
                            div.innerHTML = res.html;
                            wrap.insertBefore(div.firstElementChild, wrap.firstChild);
                        }
                        textarea.value = '';
                        if (status) status.textContent = res.message || 'Posted';
                    } else {
                        if (status) status.textContent = (res && res.message) ? res.message : (tfpWeekSettings.submitError || 'Error');
                    }
                })
                .catch(function () {
                    if (status) status.textContent = tfpWeekSettings.networkError || 'Network error';
                })
                .finally(function () {
                    btn.disabled = false;
                });
            });
        }
    });
})();
