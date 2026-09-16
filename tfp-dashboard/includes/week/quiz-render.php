<?php
if (!defined('ABSPATH')) exit;

/**
 * Quiz tab for the Week (lesson) player.
 *
 * Layout + copy mirror the Figma quiz screens: a single centered column
 * (no sidebar) with four states — Start → Question → Result → Review.
 *
 *   - Start    "Ready for the Quiz:" + quiz details + Start Quiz + Back to Homework
 *   - Question one question at a time, Previous / Next / Submit Answers
 *   - Result   text summary (Total / Correct / Score) + pass/fail badge + actions
 *   - Review   one answer at a time: Your Answer / Correct Answer / Explanation
 *
 * Buttons reuse the existing brand classes: gold `.tfp-reded-btn` (primary)
 * and teal `.tfp-dash-btn--primary` (secondary), so no new button CSS is needed.
 */

/**
 * Render the quiz tab content.
 *
 * @param WP_Post $week    The LearnDash lesson (week).
 * @param int     $user_id Current student.
 */
function tfp_dashboard_render_week_quiz_tab($week, $user_id)
{
    $lesson_id = $week->ID;

    if (!tfp_ld_user_can_access_week($user_id, $lesson_id)) {
        ?>
        <div class="tfp-quiz-locked" role="status">
            <h3 class="tfp-quiz-title"><?php esc_html_e('Quiz Locked', 'tfp-dashboard'); ?></h3>
            <p><?php esc_html_e('This quiz is not available for your account.', 'tfp-dashboard'); ?></p>
        </div>
        <?php
        return;
    }

    $progress = tfp_ld_get_week_progress($user_id, $lesson_id);
    if (!tfp_ld_is_step_unlocked($progress, 'quiz')) {
        $homework_url = add_query_arg(['lesson_id' => $lesson_id, 'tab' => 'homework']);
        ?>
        <div class="tfp-quiz-locked" role="status">
            <h3 class="tfp-quiz-title"><?php esc_html_e('Complete Homework First', 'tfp-dashboard'); ?></h3>
            <p><?php esc_html_e('Please answer every homework question and submit your homework before starting this quiz.', 'tfp-dashboard'); ?></p>
            <a href="<?php echo esc_url($homework_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Back to Homework', 'tfp-dashboard'); ?></a>
        </div>
        <?php
        return;
    }

    $questions = tfp_week_get_quiz_questions($lesson_id, true);

    if (empty($questions)) {
        tfp_dashboard_render_week_placeholder_tab('quiz', __('Quiz', 'tfp-dashboard'));
        return;
    }

    $answers       = tfp_week_get_quiz_answers($user_id, $lesson_id);
    $result        = tfp_week_get_quiz_result($user_id, $lesson_id);
    $quiz_step_complete = !empty($progress['quiz']);
    $pass_pct      = tfp_week_get_quiz_pass_percentage($lesson_id);
    $total         = count($questions);

    $has_result  = is_array($result) && isset($result['score']);

    // Backfill the gated step for results created before the unconditional
    // pass/fail unlock rule, so existing students can continue to Test too.
    if ($has_result && !$quiz_step_complete) {
        tfp_ld_mark_step_complete($user_id, $lesson_id, 'quiz');
        $quiz_step_complete = true;
    }

    $passed      = $has_result ? !empty($result['passed']) : false;
    $score       = $has_result ? (int) $result['score'] : 0;
    $correct_n   = $has_result ? (int) $result['correct'] : 0;
    $retake_ok     = $has_result && !$passed && tfp_week_can_retake_quiz($user_id, $lesson_id);
    // Test unlocks after a pass, or after the final allowed attempt even if
    // the student did not pass the quiz.
    // Any submitted result unlocks the Test step, whether passed or failed.
    $test_unlocked = $has_result || $quiz_step_complete;
    $graded_at     = $has_result && !empty($result['graded_at']) ? $result['graded_at'] : '';

    // Initial state for the JS engine.
    if ($has_result) {
        $initial_state = 'state-result';
    } else {
        // Always show the screenshot-style entry screen when the quiz page is
        // opened. Saved answers remain available and are resumed after the
        // student clicks Start Quiz.
        $initial_state = 'state-start';
    }

    $homework_url = "?lesson_id={$lesson_id}&tab=homework";
    $test_url     = add_query_arg(['lesson_id' => $lesson_id, 'tab' => 'test']);
    ?>
    <div class="tfp-week__quiz"
         data-lesson-id="<?php echo esc_attr($lesson_id); ?>"
         data-state="<?php echo esc_attr($initial_state); ?>"
         data-total="<?php echo esc_attr($total); ?>"
         data-has-result="<?php echo $has_result ? '1' : '0'; ?>"
         data-has-saved-answers="<?php echo !empty($answers) ? '1' : '0'; ?>"
         data-passed="<?php echo $passed ? '1' : '0'; ?>"
         data-retake-allowed="<?php echo $retake_ok ? '1' : '0'; ?>">

        <!-- ============================= STATE 1: START ============================= -->
        <div class="tfp-quiz-panel tfp-quiz-state-start" <?php echo ($initial_state === 'state-start') ? '' : 'hidden'; ?>>
            <h2 class="tfp-quiz-title"><?php esc_html_e('Ready for the Quiz:', 'tfp-dashboard'); ?></h2>

            <p class="tfp-quiz-desc">
                <?php esc_html_e("You’ve completed your readings and homework for this section. Now it’s time to test your understanding. Take your time and reflect on what you’ve learned. You’ll need to pass this quiz to unlock the next section", 'tfp-dashboard'); ?>
            </p>

            <div class="tfp-quiz-details">
                <h4 class="tfp-quiz-details__title"><?php esc_html_e('Quiz Details:', 'tfp-dashboard'); ?></h4>
                <ul class="tfp-quiz-details__list">
                    <li><?php printf(esc_html__('%d multiple-choice questions', 'tfp-dashboard'), $total); ?></li>
                    <li><?php printf(esc_html__('Passing score: %d%% or higher', 'tfp-dashboard'), $pass_pct); ?></li>
                    <li><?php esc_html_e('Two attempts only', 'tfp-dashboard'); ?></li>
                </ul>
            </div>

            <div class="tfp-quiz-actions">
                <button type="button" class="tfp-dash-btn tfp-reded-btn tfp-quiz-start-btn">
                    <?php echo !empty($answers) ? esc_html__('Start Quiz', 'tfp-dashboard') : esc_html__('Start Quiz', 'tfp-dashboard'); ?>
                </button>
            </div>

        </div>

        <!-- ============================ STATE 2: QUESTIONS ============================ -->
        <div class="tfp-quiz-panel tfp-quiz-card tfp-quiz-state-question" <?php echo ($initial_state === 'state-question') ? '' : 'hidden'; ?>>
            <?php foreach ($questions as $index => $q) :
                $qid          = isset($q['id']) ? $q['id'] : '';
                $ans          = isset($answers[$qid]) ? $answers[$qid] : [];
                $checked_idx  = isset($ans['selected_index']) ? (int) $ans['selected_index'] : -1;
                $is_first     = ($index === 0);
                $is_last      = ($index === $total - 1);
                ?>
                <div class="tfp-quiz-question" data-index="<?php echo esc_attr($index); ?>" data-question-id="<?php echo esc_attr($qid); ?>" <?php echo $is_first ? '' : 'hidden'; ?>>
                    <h3 class="tfp-quiz-counter"><?php printf(esc_html__('Question %1$d of %2$d', 'tfp-dashboard'), $index + 1, $total); ?></h3>

                    <div class="tfp-quiz-q tfp-quiz-details__title">
                        <?php echo esc_html(($index + 1) . '. ' . $q['prompt']); ?>
                    </div>

                    <div class="tfp-quiz-options">
                        <?php if (!empty($q['options']) && is_array($q['options'])) : ?>
                            <?php foreach ($q['options'] as $opt_idx => $opt_text) : ?>
                                <label class="tfp-week__homework-option tfp-quiz-option">
                                    <input type="radio" name="qz_<?php echo esc_attr($qid); ?>" value="<?php echo esc_attr($opt_idx); ?>" <?php checked($checked_idx, $opt_idx); ?>>
                                    <span><?php echo esc_html($opt_text); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="tfp-quiz-nav">
                        <?php if (!$is_first) : ?>
                            <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-quiz-prev" data-target="<?php echo esc_attr($questions[$index - 1]['id']); ?>"><?php esc_html_e('Previous', 'tfp-dashboard'); ?></button>
                        <?php endif; ?>

                        <?php if (!$is_last) : ?>
                            <button type="button" class="tfp-dash-btn tfp-reded-btn tfp-quiz-next" data-target="<?php echo esc_attr($questions[$index + 1]['id']); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?></button>
                        <?php else : ?>
                            <button type="button" class="tfp-dash-btn tfp-reded-btn tfp-quiz-submit-btn"><?php esc_html_e('Submit Answers', 'tfp-dashboard'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ============================= STATE 3: RESULT ============================= -->
        <div class="tfp-quiz-panel tfp-quiz-state-result" <?php echo ($initial_state === 'state-result') ? '' : 'hidden'; ?>>
            <?php if ($has_result) : ?>
                <?php if ($passed && $graded_at) : ?>
                    <div class="tfp-quiz-result__date">
                        <?php printf(esc_html__('Date completed: %s', 'tfp-dashboard'), esc_html(date_i18n(get_option('date_format'), strtotime($graded_at)))); ?>
                    </div>
                <?php endif; ?>

                <div class="tfp-quiz-card tfp-quiz-result tfp-quiz-result--<?php echo $passed ? 'passed' : 'failed'; ?>">
                    <h2 class="tfp-quiz-title"><?php esc_html_e('Quiz Results:', 'tfp-dashboard'); ?></h2>

                    <p class="tfp-quiz-result__sub tfp-quiz-details__title">
                        <?php echo $passed ? esc_html__("You've completed the quiz!", 'tfp-dashboard') : esc_html__('Quiz Failed', 'tfp-dashboard'); ?>
                    </p>

                    <p class="tfp-quiz-result__here tfp-quiz-details__title"><?php esc_html_e("Here's how you did:", 'tfp-dashboard'); ?></p>

                    <ul class="tfp-quiz-details__list tfp-quiz-result__list">
                        <li><?php printf(esc_html__('Total Questions: %d', 'tfp-dashboard'), $total); ?></li>
                        <li><?php printf(esc_html__('Correct Answers: %d', 'tfp-dashboard'), $correct_n); ?></li>
                        <li><?php printf(esc_html__('Score: %d%%', 'tfp-dashboard'), $score); ?></li>
                    </ul>

                    <p class="tfp-quiz-result__status">
                        <?php if ($passed) : ?>
                            <span class="tfp-quiz-badge tfp-quiz-badge--passed" aria-hidden="true">&#10003;</span>
                            <?php esc_html_e('Passed - Next section unlocked', 'tfp-dashboard'); ?>
                        <?php else : ?>
                            <span class="tfp-quiz-badge tfp-quiz-badge--failed" aria-hidden="true">&#10005;</span>
                            <?php echo $test_unlocked ? esc_html__('Quiz Failed - Next section unlocked', 'tfp-dashboard') : esc_html__('Quiz Failed - Review and Retake Quiz', 'tfp-dashboard'); ?>
                        <?php endif; ?>
                    </p>

                    <div class="tfp-quiz-result__actions">
                        <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-quiz-review-answers-btn"><?php esc_html_e('Review Answers', 'tfp-dashboard'); ?></button>
                        <a href="<?php echo esc_url($test_url); ?>" class="tfp-dash-btn tfp-reded-btn<?php echo $test_unlocked ? '' : ' is-disabled'; ?>" aria-disabled="<?php echo $test_unlocked ? 'false' : 'true'; ?>"<?php echo $test_unlocked ? '' : ' tabindex="-1"'; ?>><?php esc_html_e('Continue to Test', 'tfp-dashboard'); ?></a>
                        <?php if ($retake_ok) : ?>
                            <button type="button" class="tfp-dash-btn tfp-reded-btn tfp-quiz-retake-btn"><?php esc_html_e('Retake Quiz', 'tfp-dashboard'); ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ============================= STATE 4: REVIEW ============================= -->
        <div class="tfp-quiz-panel tfp-quiz-state-review" hidden>
            <?php if ($has_result && !empty($result['results'])) : ?>
                <h2 class="tfp-quiz-title"><?php esc_html_e('Review Answers:', 'tfp-dashboard'); ?></h2>

                <div class="tfp-quiz-card tfp-quiz-review-list">
                    <?php foreach ($result['results'] as $index => $r) :
                        $is_correct   = !empty($r['is_correct']);
                        $selected     = isset($r['selected_index']) ? (int) $r['selected_index'] : null;
                        $correct_idx  = isset($r['correct_index']) ? (int) $r['correct_index'] : null;
                        $options      = !empty($r['options']) && is_array($r['options']) ? $r['options'] : [];
                        $selected_txt = ($selected !== null && isset($options[$selected])) ? $options[$selected] : '—';
                        $correct_txt  = ($correct_idx !== null && isset($options[$correct_idx])) ? $options[$correct_idx] : '—';
                        $is_first     = ($index === 0);
                        $is_last      = ($index === count($result['results']) - 1);
                        ?>
                        <div class="tfp-quiz-review-item" data-index="<?php echo esc_attr($index); ?>" <?php echo $is_first ? '' : 'hidden'; ?>>
                            <div class="tfp-quiz-review-q">
                                <?php printf(esc_html__('Q%d. %s', 'tfp-dashboard'), $index + 1, esc_html($r['prompt'])); ?>
                            </div>

                            <p class="tfp-quiz-review-your tfp-quiz-review-your--<?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                                <?php esc_html_e('Your Answer:', 'tfp-dashboard'); ?> <?php echo esc_html($selected_txt); ?>
                                <?php echo $is_correct ? esc_html__('(Correct)', 'tfp-dashboard') : esc_html__('(Incorrect)', 'tfp-dashboard'); ?>
                            </p>

                            <p class="tfp-quiz-review-correct">
                                <?php esc_html_e('Correct Answer:', 'tfp-dashboard'); ?> <?php echo esc_html($correct_txt); ?>
                            </p>

                            <?php if (!empty($r['explanation'])) : ?>
                                <div class="tfp-quiz-review-explanation">
                                    <h5><?php esc_html_e('Explanation:', 'tfp-dashboard'); ?></h5>
                                    <p><?php echo esc_html($r['explanation']); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="tfp-quiz-nav tfp-quiz-review-nav">
                                <?php if (!$is_first) : ?>
                                    <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-quiz-review-prev" data-target="<?php echo esc_attr($result['results'][$index - 1]['id']); ?>"><?php esc_html_e('Previous Answer', 'tfp-dashboard'); ?></button>
                                <?php endif; ?>

                                <?php if (!$is_last) : ?>
                                    <button type="button" class="tfp-dash-btn tfp-reded-btn tfp-quiz-review-next" data-target="<?php echo esc_attr($result['results'][$index + 1]['id']); ?>"><?php esc_html_e('Next Answer', 'tfp-dashboard'); ?></button>
                                <?php else : ?>
                                    <button type="button" class="tfp-dash-btn tfp-reded-btn tfp-quiz-review-result-btn"><?php esc_html_e('Show Result', 'tfp-dashboard'); ?></button>
                                    <?php if ($retake_ok) : ?>
                                        <button type="button" class="tfp-dash-btn tfp-reded-btn tfp-quiz-retake-btn"><?php esc_html_e('Retake Quiz', 'tfp-dashboard'); ?></button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Keep this footer outside the centered 803px quiz content column. -->
    <div class="tfp-quiz-back" <?php echo ($initial_state === 'state-start') ? '' : 'hidden'; ?>>
        <a href="<?php echo esc_url($homework_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary tfp-quiz-back-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="5" height="8" viewBox="0 0 5 8" fill="none" aria-hidden="true"><path d="M4.93994 0.94L1.88661 4L4.93994 7.06L3.99994 8L-5.88141e-05 4L3.99994 -4.10887e-08L4.93994 0.94Z" fill="currentColor"/></svg>
            <?php esc_html_e('Back to Homework', 'tfp-dashboard'); ?>
        </a>
    </div>
    <?php
}
