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

    function saveStudentAttendance(form) {
        var settings = getSettings();
        var overlay = form.closest('[data-student-overlay]');

        if (!settings || !settings.ajaxUrl || !settings.nonce || !overlay) {
            return;
        }

        var studentId = parseInt(overlay.getAttribute('data-student-id'), 10) || 0;
        var lessonId = parseInt(overlay.getAttribute('data-lesson-id'), 10) || 0;
        var button = form.querySelector('.tfp-student-attendance-save');

        if (!studentId || !lessonId || !button) {
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        var formData = new FormData(form);
        formData.set('action', 'tfp_facilitator_student_save_attendance');
        formData.set('nonce', settings.nonce);
        formData.set('student_id', String(studentId));
        formData.set('lesson_id', String(lessonId));

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
                        : 'Unable to save attendance.'
                );
            }

            var statusLabel = response.data.status_label || 'Present';
            var metaText = 'Confirmed · ' + (response.data.display_time || '') + ' – ' + (response.data.display_date || '');

            document.querySelectorAll(
                '.tfp-student-week-card[data-student-review="attendance"][data-lesson-id="' + lessonId + '"] .tfp-student-status'
            ).forEach(function (badge) {
                badge.classList.remove('tfp-student-status--danger', 'tfp-student-status--neutral');
                badge.classList.add('tfp-student-status--success');
                badge.textContent = 'Completed';
            });

            document.querySelectorAll(
                '.tfp-student-week-card[data-student-review="attendance"][data-lesson-id="' + lessonId + '"] small'
            ).forEach(function (meta) {
                meta.textContent = metaText;
            });

            closeStudentReview();
        })
        .catch(function (error) {
            window.alert(error && error.message ? error.message : 'Unable to save attendance.');
            button.disabled = false;
            button.removeAttribute('aria-busy');
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

        if (reviewTrigger && (reviewTrigger.matches('.tfp-student-week-card') || reviewTrigger.matches('.tfp-student-pill-link') || reviewTrigger.matches('.tfp-student-attendance-edit'))) {
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

        var attendanceForm = event.target.closest ? event.target.closest('.tfp-student-attendance-form') : null;
        if (attendanceForm && event.target.matches && event.target.matches('.tfp-student-attendance-save')) {
            event.preventDefault();
            saveStudentAttendance(attendanceForm);
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

    document.addEventListener('submit', function (event) {
        var attendanceForm = event.target.closest ? event.target.closest('.tfp-student-attendance-form') : null;
        if (attendanceForm) {
            event.preventDefault();
            event.stopPropagation();
            saveStudentAttendance(attendanceForm);
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && document.querySelector('[data-student-overlay]')) {
            closeStudentReview();
        }
    });


    function homeworkReviewPage() {
        return document.querySelector('[data-homework-review-page]');
    }

    function getHomeworkQuestion(page, index) {
        return page ? page.querySelector('[data-homework-question-panel="' + index + '"]') : null;
    }

    function switchHomeworkQuestion(page, index) {
        if (!page) {
            return;
        }

        var count = parseInt(page.getAttribute('data-question-count'), 10) || 0;
        if (!count) {
            return;
        }

        index = Math.max(0, Math.min(index, count - 1));
        page.setAttribute('data-question-index', String(index));

        page.querySelectorAll('[data-homework-question-panel]').forEach(function (panel) {
            var active = String(panel.getAttribute('data-homework-question-panel')) === String(index);
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });

        page.querySelectorAll('[data-homework-curriculum-panel]').forEach(function (panel) {
            var active = String(panel.getAttribute('data-homework-curriculum-panel')) === String(index);
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });

        page.querySelectorAll('[data-homework-question]').forEach(function (button) {
            var active = String(button.getAttribute('data-homework-question')) === String(index);
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-current', active ? 'step' : 'false');
        });

        var currentQuestion = page.querySelector('[data-homework-current-question]');
        if (currentQuestion) {
            currentQuestion.textContent = String(index + 1);
        }

        page.querySelectorAll('.tfp-homework-progress-bars span').forEach(function (bar, barIndex) {
            bar.classList.toggle('is-complete', barIndex <= index);
            bar.classList.toggle('is-current', barIndex === count - 1);
        });

        var previous = page.querySelector('[data-homework-prev-question]');
        if (previous) {
            previous.classList.toggle('is-disabled', index <= 0);
            previous.disabled = index <= 0;
        }
    }

    function homeworkReviewState(page) {
        var index = parseInt(page.getAttribute('data-question-index'), 10) || 0;
        var panel = getHomeworkQuestion(page, index);

        if (!panel) {
            return null;
        }

        var decisionInput = panel.querySelector('[data-homework-decision]:checked');
        var mastery = Array.prototype.map.call(
            panel.querySelectorAll('[data-homework-mastery]:checked'),
            function (input) {
                return input.value;
            }
        );

        return {
            index: index,
            panel: panel,
            questionId: panel.getAttribute('data-question-id') || '',
            decision: decisionInput ? decisionInput.value : '',
            mastery: mastery,
            feedback: (panel.querySelector('[data-homework-feedback]') || {}).value || ''
        };
    }

    function setHomeworkButtonBusy(button, busy) {
        if (!button) {
            return;
        }

        button.disabled = !!busy;
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function homeworkAjaxSave(page, finalize, onSuccess) {
        var settings = getSettings();
        var state = homeworkReviewState(page);

        if (!settings || !settings.ajaxUrl || !settings.nonce || !state || !state.questionId) {
            return;
        }

        if (finalize && !state.decision) {
            window.alert('Select Correct or Incorrect before submitting this question.');
            return;
        }

        var button = finalize
            ? page.querySelector('[data-homework-submit-next]')
            : page.querySelector('[data-homework-save-draft]');

        setHomeworkButtonBusy(button, true);

        var formData = new FormData();
        formData.append('action', 'tfp_facilitator_homework_save_review');
        formData.append('nonce', settings.nonce);
        formData.append('student_id', page.getAttribute('data-student-id') || '0');
        formData.append('lesson_id', page.getAttribute('data-lesson-id') || '0');
        formData.append('cohort_id', page.getAttribute('data-cohort-id') || '0');
        formData.append('question_id', state.questionId);
        formData.append('decision', state.decision);
        formData.append('feedback', state.feedback);
        formData.append('finalize', finalize ? '1' : '0');

        state.mastery.forEach(function (value) {
            formData.append('mastery[]', value);
        });

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
                        : 'Unable to save this review.'
                );
            }

            page.setAttribute('data-current-status', response.data.status || 'submitted');
            if (response.data.grade) {
                page.setAttribute('data-grade', response.data.grade);
            }

            page.querySelectorAll('[data-homework-question="' + state.index + '"]').forEach(function (questionButton) {
                questionButton.classList.toggle('is-reviewed', !!state.decision);
            });

            if (typeof onSuccess === 'function') {
                onSuccess(response.data || {});
            }
        })
        .catch(function (error) {
            window.alert(error && error.message ? error.message : 'Unable to save this review.');
        })
        .finally(function () {
            setHomeworkButtonBusy(button, false);
        });
    }

    function homeworkCompleteReview(page) {
        var settings = getSettings();
        if (!settings || !settings.ajaxUrl || !settings.nonce) {
            return;
        }

        var button = page.querySelector('[data-homework-submit-next]');
        setHomeworkButtonBusy(button, true);

        var formData = new FormData();
        formData.append('action', 'tfp_facilitator_homework_complete_review');
        formData.append('nonce', settings.nonce);
        formData.append('student_id', page.getAttribute('data-student-id') || '0');
        formData.append('lesson_id', page.getAttribute('data-lesson-id') || '0');
        formData.append('cohort_id', page.getAttribute('data-cohort-id') || '0');

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

            var redirect = response.data && response.data.redirect
                ? response.data.redirect
                : window.location.href;

            window.location.href = redirect;
        })
        .catch(function (error) {
            window.alert(error && error.message ? error.message : 'Unable to complete this review.');
            setHomeworkButtonBusy(button, false);
        });
    }

    function initHomeworkFilters() {
        var form = document.querySelector('.tfp-homework-filters');

        if (!form || form.getAttribute('data-homework-filters-bound') === '1') {
            return;
        }

        form.setAttribute('data-homework-filters-bound', '1');

        var pageInput = form.querySelector('input[name="tfp_homework_page"]');
        var searchInput = form.querySelector('input[name="tfp_homework_search"]');
        var searchTimer = null;

        function submitFilters() {
            if (pageInput) {
                pageInput.value = '1';
            }

            form.submit();
        }

        form.querySelectorAll('select[name="tfp_homework_course"], select[name="tfp_homework_cohort_filter"]').forEach(function (select) {
            select.addEventListener('change', submitFilters);
        });

        if (searchInput) {
            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitFilters();
                }
            });

            searchInput.addEventListener('search', submitFilters);

            searchInput.addEventListener('input', function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(function () {
                    submitFilters();
                }, 500);
            });
        }
    }

    function initHomeworkReview() {
        var page = homeworkReviewPage();

        if (!page || page.getAttribute('data-homework-review-bound') === '1') {
            return;
        }

        page.setAttribute('data-homework-review-bound', '1');

        switchHomeworkQuestion(
            page,
            parseInt(page.getAttribute('data-question-index'), 10) || 0
        );

        page.querySelectorAll('[data-homework-question]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                switchHomeworkQuestion(
                    page,
                    parseInt(button.getAttribute('data-homework-question'), 10) || 0
                );
            });
        });

        var previous = page.querySelector('[data-homework-prev-question]');
        if (previous) {
            previous.addEventListener('click', function (event) {
                event.preventDefault();

                var index = parseInt(page.getAttribute('data-question-index'), 10) || 0;
                if (index > 0) {
                    switchHomeworkQuestion(page, index - 1);
                }
            });
        }

        var saveDraft = page.querySelector('[data-homework-save-draft]');
        if (saveDraft) {
            saveDraft.addEventListener('click', function (event) {
                event.preventDefault();
                homeworkAjaxSave(page, false);
            });
        }

        var submitNext = page.querySelector('[data-homework-submit-next]');
        if (submitNext) {
            submitNext.addEventListener('click', function (event) {
                event.preventDefault();

                homeworkAjaxSave(page, true, function () {
                    var index = parseInt(page.getAttribute('data-question-index'), 10) || 0;
                    var count = parseInt(page.getAttribute('data-question-count'), 10) || 0;

                    if (index < count - 1) {
                        switchHomeworkQuestion(page, index + 1);
                        return;
                    }

                    homeworkCompleteReview(page);
                });
            });
        }

        page.querySelectorAll('[data-homework-decision]').forEach(function (input) {
            input.addEventListener('change', function () {
                var panel = input.closest('[data-homework-question-panel]');
                if (!panel) {
                    return;
                }

                var selected = panel.querySelector('[data-homework-decision]:checked');
                var incorrect = selected && selected.value === 'incorrect';
                var heading = panel.querySelector('.tfp-homework-answer-heading');

                if (heading) {
                    heading.classList.toggle('is-incorrect', !!incorrect);

                    var icon = heading.querySelector('span');
                    if (icon) {
                        icon.textContent = incorrect ? '!' : '✓';
                    }
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initHomeworkFilters();
        initHomeworkReview();

        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.selectWoo) {
            return;
        }

        window.jQuery('.tfp-facilitator-filters select, .tfp-attendance-course-filters select, .tfp-homework-context-filters select').each(function () {
            var $select = window.jQuery(this);

            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.selectWoo({
                width: '100%',
                minimumResultsForSearch: Infinity,
                dropdownAutoWidth: false,
                escapeMarkup: function (markup) {
                    return markup;
                }
            });
        });
    });
})();
