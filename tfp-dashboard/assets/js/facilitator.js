(function () {
    'use strict';

    function getSettings() {
        return window.tfpFacilitatorStudent || null;
    }

    function closeStudentReview() {
        var overlays = document.querySelectorAll('[data-student-overlay]');
        overlays.forEach(function (overlay) {
            overlay.remove();
        });
        document.body.classList.remove('tfp-student-overlay-open');
    }

    function openStudentReview(trigger) {
        var settings = getSettings();
        if (!settings || !settings.ajaxUrl || !settings.nonce) {
            return;
        }

        var panel = trigger.getAttribute('data-student-review');
        var studentId = parseInt(trigger.getAttribute('data-student-id'), 10) || 0;
        var lessonId = parseInt(trigger.getAttribute('data-lesson-id'), 10) || 0;

        if (!panel || !studentId || !lessonId) {
            return;
        }

        closeStudentReview();
        document.body.classList.add('tfp-student-overlay-open');

        var formData = new FormData();
        formData.append('action', 'tfp_facilitator_student_panel');
        formData.append('nonce', settings.nonce);
        formData.append('student_id', studentId);
        formData.append('lesson_id', lessonId);
        formData.append('panel', panel);

        fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (response) {
            if (!response || !response.success || !response.data || !response.data.html) {
                throw new Error(
                    response && response.data && response.data.message
                        ? response.data.message
                        : 'Unable to load this review.'
                );
            }

            var wrapper = document.createElement('div');
            wrapper.innerHTML = response.data.html.trim();
            var overlay = wrapper.firstElementChild;

            if (!overlay) {
                throw new Error('Unable to load this review.');
            }

            overlay.setAttribute('data-student-overlay', '');
            overlay.setAttribute('data-student-id', String(studentId));
            overlay.setAttribute('data-lesson-id', String(lessonId));
            overlay.setAttribute('data-panel', panel);

            document.body.appendChild(overlay);

            var closeButtons = overlay.querySelectorAll('[data-close-student-overlay]');
            closeButtons.forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    closeStudentReview();
                });
            });

            var questionButtons = overlay.querySelectorAll('[data-review-question]');
            questionButtons.forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    switchReviewQuestion(button);
                });
            });

            var completeButton = overlay.querySelector('[data-complete-student-review]');
            if (completeButton) {
                completeButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    completeStudentReview(completeButton);
                });
            }

            var firstQuestion = overlay.querySelector('[data-review-question]');
            if (firstQuestion) {
                switchReviewQuestion(firstQuestion);
            }
        })
        .catch(function (error) {
            closeStudentReview();
            window.alert(error && error.message ? error.message : 'Unable to load this review.');
        });
    }

    function switchReviewQuestion(button) {
        var layout = button.closest('.tfp-facilitator-review-layout');
        if (!layout) {
            return;
        }

        var index = String(button.getAttribute('data-review-question'));
        var buttons = layout.querySelectorAll('[data-review-question]');
        var items = layout.querySelectorAll('[data-review-panel]');

        buttons.forEach(function (item) {
            var active = String(item.getAttribute('data-review-question')) === index;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        items.forEach(function (item) {
            var active = String(item.getAttribute('data-review-panel')) === index;
            item.classList.toggle('is-active', active);
            item.hidden = !active;
            if (active) {
                item.removeAttribute('style');
            } else {
                item.style.display = 'none';
            }
        });
    }

    function completeStudentReview(button) {
        var settings = getSettings();
        var overlay = button.closest('[data-student-overlay]');

        if (!settings || !settings.ajaxUrl || !settings.nonce || !overlay) {
            return;
        }

        var studentId = parseInt(overlay.getAttribute('data-student-id'), 10) || 0;
        var lessonId = parseInt(overlay.getAttribute('data-lesson-id'), 10) || 0;
        var type = overlay.getAttribute('data-panel') || '';

        if (!studentId || !lessonId || !type) {
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        var formData = new FormData();
        formData.append('action', 'tfp_facilitator_student_complete_review');
        formData.append('nonce', settings.nonce);
        formData.append('student_id', studentId);
        formData.append('lesson_id', lessonId);
        formData.append('review_type', type);

        fetch(settings.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (response) {
            if (!response || !response.success) {
                throw new Error(
                    response && response.data && response.data.message
                        ? response.data.message
                        : 'Unable to complete this review.'
                );
            }

            document.querySelectorAll(
                '.tfp-student-week-card[data-student-review="' + type + '"][data-lesson-id="' + lessonId + '"] .tfp-student-status'
            ).forEach(function (badge) {
                badge.classList.remove('tfp-student-status--danger', 'tfp-student-status--neutral');
                badge.classList.add('tfp-student-status--success');
                badge.textContent = 'Completed';
            });

            document.querySelectorAll(
                '.tfp-student-week-card[data-student-review="' + type + '"][data-lesson-id="' + lessonId + '"] small'
            ).forEach(function (meta) {
                meta.textContent = 'Completed';
            });

            document.querySelectorAll(
                '.tfp-student-pill-link[data-student-review="' + type + '"][data-lesson-id="' + lessonId + '"] .tfp-student-status'
            ).forEach(function (badge) {
                badge.classList.remove('tfp-student-status--danger', 'tfp-student-status--neutral');
                badge.classList.add('tfp-student-status--success');
                badge.textContent = 'Completed';
            });

            closeStudentReview();
        })
        .catch(function (error) {
            window.alert(error && error.message ? error.message : 'Unable to complete this review.');
            button.disabled = false;
            button.removeAttribute('aria-busy');
        });
    }

    document.addEventListener('click', function (event) {
        var reviewTrigger = event.target.closest
            ? event.target.closest('[data-student-review]')
            : null;

        if (reviewTrigger && (reviewTrigger.matches('.tfp-student-week-card') || reviewTrigger.matches('.tfp-student-pill-link'))) {
            event.preventDefault();
            openStudentReview(reviewTrigger);
            return;
        }

        var closeButton = event.target.closest ? event.target.closest('[data-close-student-overlay]') : null;
        if (closeButton) {
            event.preventDefault();
            closeStudentReview();
            return;
        }

        var questionButton = event.target.closest ? event.target.closest('[data-review-question]') : null;
        if (questionButton) {
            event.preventDefault();
            switchReviewQuestion(questionButton);
            return;
        }

        var completeButton = event.target.closest ? event.target.closest('[data-complete-student-review]') : null;
        if (completeButton) {
            event.preventDefault();
            completeStudentReview(completeButton);
            return;
        }

        if (event.target && event.target.classList && event.target.classList.contains('tfp-student-overlay')) {
            closeStudentReview();
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.querySelector('[data-student-overlay]')) {
            closeStudentReview();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.selectWoo) {
            return;
        }

        window.jQuery('.tfp-facilitator-filters select').each(function () {
            var $select = window.jQuery(this);

            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.selectWoo({
                width: '100%',
                minimumResultsForSearch: Infinity,
                dropdownAutoWidth: false
            });
        });
    });
})();
