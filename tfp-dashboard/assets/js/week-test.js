/**
 * Week player → Test tab.
 *
 * Matches the Figma test flow:
 *   start → question (one at a time, Previous / Next / Submit Answers)
 *         → result (text summary + pass/fail badge)
 *         → review (one answer at a time: Your Answer / Correct Answer / Explanation)
 *
 * Answers auto-save silently via AJAX; grading happens server-side on submit.
 * Submit and Retake are direct actions (no confirmation popup, matching Figma).
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof tfpWeekSettings === 'undefined') return;

        var testWrap = document.querySelector('.tfp-week__test');
        if (!testWrap) return;

        var lessonId = testWrap.getAttribute('data-lesson-id');
        var currentState = testWrap.getAttribute('data-state') || 'state-start';
        var totalQuestions = parseInt(testWrap.getAttribute('data-total') || '0', 10);
        var hasResult = testWrap.getAttribute('data-has-result') === '1';

        var panels = {
            'state-start': testWrap.querySelector('.tfp-test-state-start'),
            'state-question': testWrap.querySelector('.tfp-test-state-question'),
            'state-result': testWrap.querySelector('.tfp-test-state-result'),
            'state-review': testWrap.querySelector('.tfp-test-state-review')
        };

        var qContainers = testWrap.querySelectorAll('.tfp-test-question');
        var reviewItems = testWrap.querySelectorAll('.tfp-test-review-item');
        var backFooter = document.querySelector('.tfp-test-back');
        var activeQuestionIndex = 0;
        var activeReviewIndex = 0;

        function switchState(newState) {
            currentState = newState;
            for (var key in panels) {
                if (panels[key]) panels[key].hidden = (key !== newState);
            }
            if (backFooter) backFooter.hidden = newState !== 'state-start';
        }

        function showQuestion(index) {
            activeQuestionIndex = index;
            qContainers.forEach(function (el) {
                el.hidden = (parseInt(el.getAttribute('data-index'), 10) !== index);
            });
        }

        function showReview(index) {
            activeReviewIndex = index;
            reviewItems.forEach(function (el) {
                el.hidden = (parseInt(el.getAttribute('data-index'), 10) !== index);
            });
        }

        function post(action, data, onDone) {
            var body = new URLSearchParams();
            body.append('action', action);
            body.append('tfp_week_nonce', tfpWeekSettings.nonce);
            body.append('lesson_id', lessonId);
            for (var k in data) {
                body.append(k, data[k]);
            }

            fetch(tfpWeekSettings.ajaxUrl, {
                method: 'POST',
                body: body
            })
                .then(function (r) { return r.json(); })
                .then(onDone)
                .catch(function (err) {
                    console.error(err);
                    alert(tfpWeekSettings.networkError || 'A network error occurred.');
                });
        }

        // --- Start Test ----------------------------------------------------
        var startBtn = testWrap.querySelector('.tfp-test-start-btn');
        if (startBtn) {
            startBtn.addEventListener('click', function () {
                switchState('state-question');
                if (totalQuestions > 0) showQuestion(0);
            });
        }

        // --- Question navigation -------------------------------------------
        var prevBtns = testWrap.querySelectorAll('.tfp-test-prev');
        var nextBtns = testWrap.querySelectorAll('.tfp-test-next');

        prevBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { showQuestion(activeQuestionIndex - 1); });
        });

        nextBtns.forEach(function (btn) {
            btn.addEventListener('click', function () { showQuestion(activeQuestionIndex + 1); });
        });

        // --- Auto-save on answer change (silent, no indicator) -------------
        qContainers.forEach(function (container) {
            var qId = container.getAttribute('data-question-id');
            container.querySelectorAll('input[type="radio"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (hasResult) return;
                    post('tfp_week_save_test_answer', { question_id: qId, selected_index: input.value }, function () {
                        // Silently persisted — resume-safe.
                    });
                });
            });
        });

        // --- Submit Answers ------------------------------------------------
        var submitBtn = testWrap.querySelector('.tfp-test-submit-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                var original = submitBtn.textContent;
                submitBtn.textContent = tfpWeekSettings.submittingText || 'Submitting...';
                submitBtn.disabled = true;

                post('tfp_week_submit_test', {}, function (res) {
                    if (res.success) {
                        window.location.reload(); // server-rendered result appears
                    } else {
                        alert(res.message || tfpWeekSettings.submitError || 'Error submitting test.');
                        submitBtn.textContent = original;
                        submitBtn.disabled = false;
                    }
                });
            });
        }

        // --- Result → Review Answers ---------------------------------------
        var reviewBtn = testWrap.querySelector('.tfp-test-review-answers-btn');
        if (reviewBtn) {
            reviewBtn.addEventListener('click', function () {
                showReview(0);
                switchState('state-review');
            });
        }

        // --- Review navigation ---------------------------------------------
        testWrap.querySelectorAll('.tfp-test-review-prev').forEach(function (btn) {
            btn.addEventListener('click', function () { showReview(activeReviewIndex - 1); });
        });

        testWrap.querySelectorAll('.tfp-test-review-next').forEach(function (btn) {
            btn.addEventListener('click', function () { showReview(activeReviewIndex + 1); });
        });

        // --- Review → Result -----------------------------------------------
        testWrap.querySelectorAll('.tfp-test-review-result-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchState('state-result');
            });
        });

        // --- Retake Test (direct) ------------------------------------------
        testWrap.querySelectorAll('.tfp-test-retake-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var original = btn.textContent;
                btn.textContent = tfpWeekSettings.submittingText || 'Submitting...';
                btn.disabled = true;

                post('tfp_week_reset_test', {}, function (res) {
                    if (res.success) {
                        window.location.reload(); // back to the Start screen
                    } else {
                        alert(res.message || tfpWeekSettings.submitError || 'Error.');
                        btn.textContent = original;
                        btn.disabled = false;
                    }
                });
            });
        });

        // Initialise the active question / review view on load.
        if (currentState === 'state-question' && totalQuestions > 0) {
            showQuestion(0);
        }
        if (currentState === 'state-review') {
            showReview(0);
        }
    });
})();
