(function ($) {
    'use strict';

    function openStudentReview(trigger) {
        var $trigger = $(trigger);
        var panel = $trigger.data('student-review');
        var studentId = parseInt($trigger.data('student-id'), 10) || 0;
        var lessonId = parseInt($trigger.data('lesson-id'), 10) || 0;

        if (!panel || !studentId || !lessonId || !window.tfpFacilitatorStudent) {
            return false;
        }

        var $existing = $('[data-student-overlay]');
        $existing.remove();

        $('body').addClass('tfp-student-overlay-open');

        $.ajax({
            url: tfpFacilitatorStudent.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tfp_facilitator_student_panel',
                nonce: tfpFacilitatorStudent.nonce,
                student_id: studentId,
                lesson_id: lessonId,
                panel: panel
            }
        }).done(function (response) {
            if (response && response.success && response.data && response.data.html) {
                $('body').append(response.data.html);
                var $overlay = $('[data-student-overlay]').last();
                $overlay.attr('data-student-id', studentId).attr('data-lesson-id', lessonId).attr('data-panel', panel);
            } else {
                window.alert(
                    response && response.data && response.data.message
                        ? response.data.message
                        : 'Unable to load this review.'
                );
                $('body').removeClass('tfp-student-overlay-open');
            }
        }).fail(function () {
            window.alert('Unable to load this review. Please try again.');
            $('body').removeClass('tfp-student-overlay-open');
        });

        return false;
    }

    function closeStudentReview() {
        $('[data-student-overlay]').remove();
        $('body').removeClass('tfp-student-overlay-open');
    }

    function completeStudentReview(button) {
        var $button = $(button);
        var $overlay = $button.closest('[data-student-overlay]');
        var studentId = parseInt($overlay.attr('data-student-id'), 10) || 0;
        var lessonId = parseInt($overlay.attr('data-lesson-id'), 10) || 0;
        var type = $overlay.attr('data-panel') || $overlay.find('[data-review-type]').data('review-type') || '';

        if (!studentId || !lessonId || !type || !window.tfpFacilitatorStudent) {
            return;
        }

        $button.prop('disabled', true).attr('aria-busy', 'true');

        $.ajax({
            url: tfpFacilitatorStudent.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'tfp_facilitator_student_complete_review',
                nonce: tfpFacilitatorStudent.nonce,
                student_id: studentId,
                lesson_id: lessonId,
                review_type: type
            }
        }).done(function (response) {
            if (!response || !response.success) {
                window.alert(
                    response && response.data && response.data.message
                        ? response.data.message
                        : 'Unable to complete this review.'
                );
                return;
            }

            $('.tfp-student-week-card[data-student-review="' + type + '"][data-lesson-id="' + lessonId + '"] .tfp-student-status')
                .removeClass('tfp-student-status--danger tfp-student-status--neutral')
                .addClass('tfp-student-status--success')
                .text('Completed');

            $('.tfp-student-week-card[data-student-review="' + type + '"][data-lesson-id="' + lessonId + '"] small')
                .text('Completed');

            $('.tfp-student-pill-link[data-student-review="' + type + '"][data-lesson-id="' + lessonId + '"] .tfp-student-status')
                .removeClass('tfp-student-status--danger tfp-student-status--neutral')
                .addClass('tfp-student-status--success')
                .text('Completed');

            $('.tfp-student-activity-row[data-student-review="' + type + '"][data-lesson-id="' + lessonId + '"] .tfp-student-status')
                .removeClass('tfp-student-status--danger tfp-student-status--neutral')
                .addClass('tfp-student-status--success')
                .text('Completed');

            closeStudentReview();
        }).fail(function (xhr) {
            var message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : 'Unable to complete this review. Please try again.';
            window.alert(message);
        }).always(function () {
            $button.prop('disabled', false).removeAttr('aria-busy');
        });
    }

    function switchReviewQuestion(button) {
        var $button = $(button);
        var $layout = $button.closest('.tfp-student-review-layout');
        var index = String($button.data('review-question'));
        var $items = $layout.find('[data-review-panel-question]');
        var $buttons = $layout.find('[data-review-question]');

        $buttons.removeClass('is-active').attr('aria-selected', 'false');
        $button.addClass('is-active').attr('aria-selected', 'true');

        $items.each(function () {
            var $item = $(this);
            var isActive = String($item.data('review-panel-question')) === index;
            $item.toggleClass('is-active', isActive);
            $item.prop('hidden', !isActive);
        });
    }

    $(document).on('click', '.tfp-student-week-card[data-student-review], .tfp-student-pill-link[data-student-review]', function (event) {
        var panel = $(this).data('student-review');

        if (!panel) {
            return;
        }

        event.preventDefault();
        openStudentReview(this);
    });

    $(document).on('click', '[data-close-student-overlay]', function (event) {
        event.preventDefault();
        closeStudentReview();
    });

    $(document).on('click', '[data-student-overlay]', function (event) {
        if (event.target === this) {
            closeStudentReview();
        }
    });

    $(document).on('click', '[data-review-question]', function (event) {
        event.preventDefault();
        switchReviewQuestion(this);
    });

    $(document).on('click', '[data-complete-student-review], .tfp-student-review-complete-form button', function (event) {
        event.preventDefault();
        completeStudentReview(this);
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $('[data-student-overlay]').length) {
            closeStudentReview();
        }
    });

    $(document).ready(function () {
        if (!window.jQuery || !jQuery.fn.selectWoo) {
            return;
        }

        jQuery('.tfp-facilitator-filters select').each(function () {
            var $select = jQuery(this);

            if ($select.hasClass('select2-hidden-accessible')) {
                return;
            }

            $select.selectWoo({
                width: '100%',
                minimumResultsForSearch: Infinity,
                dropdownAutoWidth: false,
            });
        });
    });
})(jQuery);
