<?php
if (!defined('ABSPATH')) exit;

/**
 * Facilitator Homework Review
 *
 * Review flow:
 *   All Homework -> Submitted -> Graded -> Single Student Review
 *
 * Review decisions are stored against the student and week so one student's
 * review never marks another student's work as graded.
 */

function tfp_facilitator_homework_review_key($lesson_id) {
    return 'tfp_facilitator_homework_review_' . absint($lesson_id);
}

function tfp_facilitator_homework_review_data($student_id, $lesson_id) {
    $default = [
        'questions' => [],
        'completed_at' => '',
        'reviewer_id' => 0,
    ];

    $data = get_user_meta(
        absint($student_id),
        tfp_facilitator_homework_review_key($lesson_id),
        true
    );

    if (!is_array($data)) {
        return $default;
    }

    $data['questions'] = isset($data['questions']) && is_array($data['questions'])
        ? $data['questions']
        : [];

    $data['completed_at'] = !empty($data['completed_at'])
        ? (string) $data['completed_at']
        : '';

    $data['reviewer_id'] = !empty($data['reviewer_id'])
        ? absint($data['reviewer_id'])
        : 0;

    return wp_parse_args($data, $default);
}

function tfp_facilitator_homework_question_answer($question, $answer) {
    $type = (string) ($question['type'] ?? '');
    $options = isset($question['options']) && is_array($question['options'])
        ? $question['options']
        : [];

    if ($type === 'multiple_choice') {
        $selected = isset($answer['selected_index']) ? (int) $answer['selected_index'] : -1;
        return ($selected >= 0 && isset($options[$selected]))
            ? (string) $options[$selected]
            : '—';
    }

    if ($type === 'both') {
        $yes_no = !empty($answer['yes_no'])
            ? ucfirst((string) $answer['yes_no'])
            : '';
        $text = trim((string) ($answer['text'] ?? ''));

        if ($yes_no && $text) {
            return $yes_no . ' — ' . $text;
        }

        return $yes_no ?: ($text ?: '—');
    }

    return trim((string) ($answer['text'] ?? '')) ?: '—';
}

function tfp_facilitator_homework_correct_answer($question) {
    $type = (string) ($question['type'] ?? '');
    $options = isset($question['options']) && is_array($question['options'])
        ? $question['options']
        : [];

    if ($type === 'multiple_choice' && isset($question['correct_index'])) {
        $correct = (int) $question['correct_index'];
        if (isset($options[$correct])) {
            return (string) $options[$correct];
        }
    }

    foreach (['correct_answer_text', 'correct_answer', 'answer'] as $key) {
        if (!empty($question[$key])) {
            return (string) $question[$key];
        }
    }

    return '';
}

function tfp_facilitator_homework_question_explanation($question) {
    foreach (['explanation', 'feedback', 'rationale'] as $key) {
        if (!empty($question[$key])) {
            return (string) $question[$key];
        }
    }

    return __('Your response is available here for review and discussion during the weekly meeting.', 'tfp-dashboard');
}

function tfp_facilitator_homework_question_scripture($question) {
    foreach (['scripture', 'scripture_reference', 'curriculum_reference'] as $key) {
        if (!empty($question[$key])) {
            return (string) $question[$key];
        }
    }

    return '';
}

function tfp_facilitator_homework_is_graded($student_id, $lesson_id) {
    $review = tfp_facilitator_homework_review_data($student_id, $lesson_id);

    // "Graded" is a submission-level state. It is reached only after the
    // facilitator explicitly completes the review; saved drafts remain
    // "Submitted" even when individual questions already have decisions.
    return !empty($review['completed_at']);
}

function tfp_facilitator_homework_grade_letter($student_id, $lesson_id) {
    if (!tfp_facilitator_homework_is_graded($student_id, $lesson_id)) {
        return '';
    }

    $questions = function_exists('tfp_week_get_homework_questions')
        ? tfp_week_get_homework_questions($lesson_id, false)
        : [];
    $review = tfp_facilitator_homework_review_data($student_id, $lesson_id);

    $correct = 0;
    $total = 0;

    foreach ($questions as $question) {
        $question_id = (string) ($question['id'] ?? '');
        $decision = $question_id && !empty($review['questions'][$question_id]['decision'])
            ? $review['questions'][$question_id]['decision']
            : '';

        if (!$decision) {
            continue;
        }

        $total++;
        if ($decision === 'correct') {
            $correct++;
        }
    }

    if (!$total) {
        return '';
    }

    $percent = ($correct / $total) * 100;

    if ($percent >= 90) return 'A';
    if ($percent >= 80) return 'B';
    if ($percent >= 70) return 'C';
    if ($percent >= 60) return 'D';

    return 'F';
}

function tfp_facilitator_homework_row_items($current_user_id, $course_filter = 0, $cohort_filter = 0, $search = '') {
    $cohorts = function_exists('tfp_facilitator_current_cohorts')
        ? (array) tfp_facilitator_current_cohorts($current_user_id)
        : [];

    $items = [];
    $seen = [];

    foreach ($cohorts as $cohort) {
        $cohort_id = (int) $cohort->ID;
        $course_id = (int) get_post_meta($cohort_id, '_related_course', true);

        if ($course_filter && $course_id !== (int) $course_filter) {
            continue;
        }

        if ($cohort_filter && $cohort_id !== (int) $cohort_filter) {
            continue;
        }

        if (!$course_id || !get_post($course_id)) {
            continue;
        }

        $students = function_exists('tfp_facilitator_cohort_students')
            ? array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))
            : [];

        $weeks = function_exists('tfp_facilitator_student_weeks')
            ? (array) tfp_facilitator_student_weeks($course_id)
            : [];

        foreach ($students as $student_id) {
            $student = get_userdata($student_id);
            if (!$student) {
                continue;
            }

            $search_text = strtolower(
                $student->display_name . ' ' .
                $student->user_email . ' ' .
                get_the_title($cohort_id) . ' ' .
                get_the_title($course_id)
            );

            if ($search !== '' && strpos($search_text, strtolower($search)) === false) {
                continue;
            }

            foreach ($weeks as $week_index => $week) {
                $lesson_id = (int) $week->ID;
                $questions = function_exists('tfp_week_get_homework_questions')
                    ? tfp_week_get_homework_questions($lesson_id, false)
                    : [];

                if (!$questions) {
                    continue;
                }

                $progress = function_exists('tfp_ld_get_week_progress')
                    ? (array) tfp_ld_get_week_progress($student_id, $lesson_id)
                    : [];

                if (empty($progress['homework'])) {
                    continue;
                }

                $key = $student_id . ':' . $lesson_id;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $graded = tfp_facilitator_homework_is_graded($student_id, $lesson_id);
                $submitted_at = 0;

                $answers = function_exists('tfp_week_get_homework_answers')
                    ? tfp_week_get_homework_answers($student_id, $lesson_id)
                    : [];

                foreach ($answers as $answer) {
                    if (!empty($answer['submitted_at'])) {
                        $submitted_at = max($submitted_at, strtotime((string) $answer['submitted_at']));
                    }
                }

                if (!$submitted_at) {
                    $submitted_at = get_post_timestamp($lesson_id, 'modified');
                }

                $items[] = [
                    'key' => $key,
                    'student_id' => $student_id,
                    'student_name' => $student->display_name,
                    'course_id' => $course_id,
                    'course_name' => get_the_title($course_id),
                    'cohort_id' => $cohort_id,
                    'cohort_name' => get_the_title($cohort_id),
                    'lesson_id' => $lesson_id,
                    'week' => $week_index + 1,
                    'module' => $week->post_title,
                    'status' => $graded ? 'graded' : 'submitted',
                    'submitted_at' => $submitted_at,
                    'grade' => $graded ? tfp_facilitator_homework_grade_letter($student_id, $lesson_id) : '',
                    'question_count' => count($questions),
                ];
            }
        }
    }

    usort($items, static function ($a, $b) {
        return ($b['submitted_at'] ?? 0) <=> ($a['submitted_at'] ?? 0);
    });

    return $items;
}

function tfp_facilitator_homework_courses($current_user_id) {
    $courses = [];
    $seen = [];

    $cohorts = function_exists('tfp_facilitator_current_cohorts')
        ? (array) tfp_facilitator_current_cohorts($current_user_id)
        : [];

    foreach ($cohorts as $cohort) {
        $course_id = (int) get_post_meta($cohort->ID, '_related_course', true);
        if ($course_id && !isset($seen[$course_id])) {
            $course = get_post($course_id);
            if ($course) {
                $courses[] = $course;
                $seen[$course_id] = true;
            }
        }
    }

    usort($courses, static function ($a, $b) {
        return strcasecmp($a->post_title, $b->post_title);
    });

    return $courses;
}

function tfp_facilitator_homework_cohorts($current_user_id, $course_filter = 0) {
    $cohorts = function_exists('tfp_facilitator_current_cohorts')
        ? (array) tfp_facilitator_current_cohorts($current_user_id)
        : [];

    if ($course_filter) {
        $cohorts = array_filter($cohorts, static function ($cohort) use ($course_filter) {
            return (int) get_post_meta($cohort->ID, '_related_course', true) === (int) $course_filter;
        });
    }

    usort($cohorts, static function ($a, $b) {
        return strcasecmp($a->post_title, $b->post_title);
    });

    return $cohorts;
}

function tfp_facilitator_homework_build_review_url($item, $question = 0) {
    return add_query_arg([
        'tfp_homework_student' => (int) $item['student_id'],
        'tfp_homework_lesson'  => (int) $item['lesson_id'],
        'tfp_homework_cohort'  => (int) $item['cohort_id'],
        'tfp_homework_question' => max(0, (int) $question),
    ], tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'));
}

function tfp_facilitator_homework_validate_context($student_id, $lesson_id, $cohort_id) {
    $student_id = absint($student_id);
    $lesson_id = absint($lesson_id);
    $cohort_id = absint($cohort_id);

    if (!$student_id || !$lesson_id || !$cohort_id) {
        return false;
    }

    if (!function_exists('tfp_facilitator_student_allowed') || !tfp_facilitator_student_allowed($student_id)) {
        return false;
    }

    $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
    if (!$course_id) {
        return false;
    }

    $students = function_exists('tfp_facilitator_cohort_students')
        ? array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))
        : [];

    if (!in_array($student_id, $students, true)) {
        return false;
    }

    if (function_exists('learndash_get_course_id') && (int) learndash_get_course_id($lesson_id) !== $course_id) {
        return false;
    }

    $questions = function_exists('tfp_week_get_homework_questions')
        ? tfp_week_get_homework_questions($lesson_id, false)
        : [];

    if (!$questions) {
        return false;
    }

    return [
        'student_id' => $student_id,
        'lesson_id' => $lesson_id,
        'cohort_id' => $cohort_id,
        'course_id' => $course_id,
    ];
}

function tfp_facilitator_homework_save_question_review($student_id, $lesson_id, $question_id, $decision, $mastery, $feedback) {
    $student_id = absint($student_id);
    $lesson_id = absint($lesson_id);
    $question_id = sanitize_key($question_id);
    $decision = in_array($decision, ['correct', 'incorrect'], true) ? $decision : '';
    $mastery = is_array($mastery) ? array_values(array_filter(array_map('sanitize_key', $mastery))) : [];
    $feedback = sanitize_textarea_field($feedback);

    // Drafts may contain feedback/mastery without a final Correct/Incorrect
    // decision. Completion is still blocked until every question is decided.
    if (!$student_id || !$lesson_id || !$question_id) {
        return false;
    }

    $questions = function_exists('tfp_week_get_homework_questions')
        ? tfp_week_get_homework_questions($lesson_id, false)
        : [];

    $found = false;
    foreach ($questions as $question) {
        if ((string) ($question['id'] ?? '') === $question_id) {
            $found = true;
            break;
        }
    }

    if (!$found) {
        return false;
    }

    $review = tfp_facilitator_homework_review_data($student_id, $lesson_id);
    $review['questions'][$question_id] = [
        'decision' => $decision,
        'mastery' => $mastery,
        'feedback' => $feedback,
        'updated_at' => current_time('mysql'),
        'reviewer_id' => get_current_user_id(),
    ];

    /*
     * A previously graded submission is reopened when a facilitator changes
     * any question. The final completion is set again only when all questions
     * have a valid decision.
     */
    $review['completed_at'] = '';
    $review['reviewer_id'] = get_current_user_id();

    update_user_meta(
        $student_id,
        tfp_facilitator_homework_review_key($lesson_id),
        $review
    );

    return true;
}

function tfp_facilitator_homework_mark_complete($student_id, $lesson_id) {
    $questions = function_exists('tfp_week_get_homework_questions')
        ? tfp_week_get_homework_questions($lesson_id, false)
        : [];

    if (!$questions) {
        return false;
    }

    $review = tfp_facilitator_homework_review_data($student_id, $lesson_id);

    foreach ($questions as $question) {
        $question_id = (string) ($question['id'] ?? '');
        if (!$question_id || empty($review['questions'][$question_id]['decision'])) {
            return false;
        }
    }

    $review['completed_at'] = current_time('mysql');
    $review['reviewer_id'] = get_current_user_id();

    update_user_meta(
        $student_id,
        tfp_facilitator_homework_review_key($lesson_id),
        $review
    );

    return true;
}

function tfp_facilitator_homework_find_next_item($items, $student_id, $lesson_id) {
    foreach ($items as $index => $item) {
        if ((int) $item['student_id'] === (int) $student_id && (int) $item['lesson_id'] === (int) $lesson_id) {
            return isset($items[$index + 1]) ? $items[$index + 1] : null;
        }
    }

    return null;
}

function tfp_dashboard_render_facilitator_homework_review_content($item) {
    $student_id = (int) $item['student_id'];
    $lesson_id = (int) $item['lesson_id'];
    $cohort_id = (int) $item['cohort_id'];
    $student = get_userdata($student_id);

    $questions = function_exists('tfp_week_get_homework_questions')
        ? tfp_week_get_homework_questions($lesson_id, false)
        : [];

    $answers = function_exists('tfp_week_get_homework_answers')
        ? tfp_week_get_homework_answers($student_id, $lesson_id)
        : [];

    $review = tfp_facilitator_homework_review_data($student_id, $lesson_id);
    $back_url = tfp_dashboard_get_url('tfp-dashboard-facilitator-homework');
    $course = get_post($item['course_id']);
    $week_number = (int) $item['week'];
    $question_count = count($questions);

    $active_index = max(0, absint($_GET['tfp_homework_question'] ?? 0));
    if ($active_index >= $question_count) {
        $active_index = 0;
    }

    $reviewed_count = 0;
    foreach ($questions as $q) {
        $qid = (string) ($q['id'] ?? '');
        if ($qid && !empty($review['questions'][$qid]['decision'])) {
            $reviewed_count++;
        }
    }

    $last_active = get_user_meta($student_id, 'last_login', true);
    $last_active_timestamp = is_numeric($last_active)
        ? (int) $last_active
        : (($last_active && strtotime((string) $last_active)) ? strtotime((string) $last_active) : 0);

    if (!$last_active_timestamp) {
        $last_active_timestamp = !empty($item['submitted_at'])
            ? (int) $item['submitted_at']
            : current_time('timestamp');
    }
    ?>
    <div
        class="tfp-homework-review-page"
        data-homework-review-page
        data-student-id="<?php echo esc_attr($student_id); ?>"
        data-lesson-id="<?php echo esc_attr($lesson_id); ?>"
        data-cohort-id="<?php echo esc_attr($cohort_id); ?>"
        data-question-index="<?php echo esc_attr($active_index); ?>"
        data-question-count="<?php echo esc_attr($question_count); ?>"
        data-completed-count="<?php echo esc_attr($reviewed_count); ?>">

        <nav class="tfp-homework-detail-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <span><?php echo esc_html($course ? $course->post_title : __('Course', 'tfp-dashboard')); ?></span>
            <span>/</span>
            <span><?php echo esc_html(get_the_title($cohort_id)); ?></span>
            <span>/</span>
            <strong><?php echo esc_html('Week ' . $week_number); ?></strong>
        </nav>

        <header class="tfp-homework-student-header">
            <div class="tfp-homework-student-header__person">
                <div class="tfp-homework-student-avatar">
                    <?php echo get_avatar($student_id, 80, '', '', ['class' => ['tfp-homework-avatar-image']]); ?>
                </div>
                <div>
                    <h2><?php echo esc_html($student ? $student->display_name : __('Student', 'tfp-dashboard')); ?></h2>
                    <p><?php echo esc_html($student ? $student->user_email : ''); ?></p>
                    <small>
                        <?php
                        printf(
                            esc_html__('Last Active: %s', 'tfp-dashboard'),
                            esc_html(wp_date('M j, Y g:i A T', $last_active_timestamp))
                        );
                        ?>
                    </small>
                </div>
            </div>

            <a href="<?php echo esc_url($back_url); ?>" class="tfp-dash-btn tfp-dash-btn--secondary tfp-homework-back-list">
                <span aria-hidden="true">←</span>
                <?php esc_html_e('Back to List', 'tfp-dashboard'); ?>
            </a>
        </header>

        <section class="tfp-homework-review-surface">
            <div class="tfp-homework-review-columns">
                <div class="tfp-homework-submission-panel">
                    <?php if ($questions): ?>
                        <?php foreach ($questions as $index => $question): ?>
                            <?php
                            $question_id = (string) ($question['id'] ?? '');
                            $answer = ($question_id && isset($answers[$question_id]) && is_array($answers[$question_id]))
                                ? $answers[$question_id]
                                : [];
                            $review_item = ($question_id && isset($review['questions'][$question_id]) && is_array($review['questions'][$question_id]))
                                ? $review['questions'][$question_id]
                                : [];
                            $user_answer = tfp_facilitator_homework_question_answer($question, $answer);
                            $decision = (string) ($review_item['decision'] ?? '');
                            $saved_mastery = isset($review_item['mastery']) && is_array($review_item['mastery'])
                                ? $review_item['mastery']
                                : [];
                            ?>
                            <article
                                class="tfp-homework-question-panel<?php echo $index === $active_index ? ' is-active' : ''; ?>"
                                data-homework-question-panel="<?php echo esc_attr($index); ?>"
                                data-question-id="<?php echo esc_attr($question_id); ?>"
                                <?php echo $index === $active_index ? '' : 'hidden'; ?>>

                                <div class="tfp-homework-review-question-meta">
                                    <strong>
                                        <?php printf(
                                            esc_html__('Section Name: %s', 'tfp-dashboard'),
                                            esc_html($item['module'])
                                        ); ?>
                                    </strong>
                                    <div class="tfp-homework-question-progress">
                                        <span><?php esc_html_e('Questions', 'tfp-dashboard'); ?></span>
                                        <b data-homework-current-question><?php echo esc_html($index + 1); ?></b>
                                        <span>/</span>
                                        <b><?php echo esc_html($question_count); ?></b>
                                    </div>
                                </div>

                                <h1 class="tfp-homework-question-title">
                                    <?php printf(
                                        esc_html__('Q%d. %s', 'tfp-dashboard'),
                                        $index + 1,
                                        $question['prompt'] ?? ''
                                    ); ?>
                                </h1>

                                <div class="tfp-homework-answer-section">
                                    <div class="tfp-homework-answer-heading<?php echo $decision === 'incorrect' ? ' is-incorrect' : ''; ?>">
                                        <span><?php echo $decision === 'incorrect' ? '!' : '✓'; ?></span>
                                        <?php esc_html_e('Your Answer', 'tfp-dashboard'); ?>
                                    </div>
                                    <div class="tfp-homework-answer-box">
                                        <?php echo nl2br(esc_html($user_answer)); ?>
                                    </div>
                                </div>

                                <div class="tfp-homework-feedback-section">
                                    <label for="<?php echo esc_attr('tfp-homework-feedback-' . $index); ?>">
                                        <?php esc_html_e('Feedback', 'tfp-dashboard'); ?>
                                    </label>
                                    <textarea
                                        id="<?php echo esc_attr('tfp-homework-feedback-' . $index); ?>"
                                        data-homework-feedback
                                        placeholder="<?php esc_attr_e('Add Facilitator feedback here...', 'tfp-dashboard'); ?>"><?php echo esc_textarea($review_item['feedback'] ?? ''); ?></textarea>
                                </div>

                                <div class="tfp-homework-mastery-card">
                                    <div class="tfp-homework-mastery-head">
                                        <strong><?php esc_html_e('Mastery', 'tfp-dashboard'); ?></strong>
                                        <div class="tfp-homework-master-correctness">
                                            <label>
                                                <input type="radio" name="<?php echo esc_attr('decision-' . $index); ?>" value="correct" data-homework-decision <?php checked($decision, 'correct'); ?>>
                                                <?php esc_html_e('Correct', 'tfp-dashboard'); ?>
                                            </label>
                                            <label>
                                                <input type="radio" name="<?php echo esc_attr('decision-' . $index); ?>" value="incorrect" data-homework-decision <?php checked($decision, 'incorrect'); ?>>
                                                <?php esc_html_e('Incorrect', 'tfp-dashboard'); ?>
                                            </label>
                                        </div>
                                    </div>

                                    <p><?php esc_html_e('Identify Underlining Depth/Issue:', 'tfp-dashboard'); ?></p>

                                    <div class="tfp-homework-mastery-options">
                                        <?php
                                        $mastery_options = [
                                            'misunderstood-concept' => __('Misunderstood Concept', 'tfp-dashboard'),
                                            'confused-needs-help' => __('Confused / Needs Help', 'tfp-dashboard'),
                                            'surface-level' => __('Surface Level', 'tfp-dashboard'),
                                            'needs-discussion' => __('Needs Discussion', 'tfp-dashboard'),
                                        ];
                                        foreach ($mastery_options as $key => $label):
                                            ?>
                                            <label class="tfp-homework-mastery-option">
                                                <input
                                                    type="checkbox"
                                                    value="<?php echo esc_attr($key); ?>"
                                                    data-homework-mastery
                                                    <?php checked(in_array($key, $saved_mastery, true), true); ?>>
                                                <span><?php echo esc_html($label); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="tfp-facilitator-empty-state">
                            <?php esc_html_e('No homework questions are available for this submission.', 'tfp-dashboard'); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($questions): ?>
                    <aside class="tfp-homework-curriculum-panel" aria-label="<?php esc_attr_e('Curriculum reference', 'tfp-dashboard'); ?>">
                        <?php foreach ($questions as $index => $question): ?>
                            <?php
                            $correct_answer = tfp_facilitator_homework_correct_answer($question);
                            $explanation = tfp_facilitator_homework_question_explanation($question);
                            $scripture = tfp_facilitator_homework_question_scripture($question);
                            ?>
                            <div
                                class="tfp-homework-curriculum-panel__item<?php echo $index === $active_index ? ' is-active' : ''; ?>"
                                data-homework-curriculum-panel="<?php echo esc_attr($index); ?>"
                                <?php echo $index === $active_index ? '' : 'hidden'; ?>>
                                <h3><?php esc_html_e('Curriculum Reference', 'tfp-dashboard'); ?></h3>

                                <h4><?php esc_html_e('Correct Answer', 'tfp-dashboard'); ?></h4>
                                <div class="tfp-homework-reference-copy">
                                    <?php echo wp_kses_post(wpautop($correct_answer ?: __('No reference answer was provided for this question.', 'tfp-dashboard'))); ?>
                                </div>

                                <h4><?php esc_html_e('Explanation:', 'tfp-dashboard'); ?></h4>
                                <div class="tfp-homework-reference-copy tfp-homework-explanation-copy">
                                    <?php echo wp_kses_post(wpautop($explanation)); ?>
                                </div>

                                <?php if ($scripture): ?>
                                    <h4><?php esc_html_e('Scripture:', 'tfp-dashboard'); ?></h4>
                                    <div class="tfp-homework-scripture">
                                        <?php echo wp_kses_post(wpautop($scripture)); ?>
                                    </div>
                                <?php endif; ?>

                                <small>
                                    <?php
                                    $question_id = (string) ($question['id'] ?? '');
                                    $reviewed_question = $question_id && isset($review['questions'][$question_id]) && is_array($review['questions'][$question_id])
                                        ? $review['questions'][$question_id]
                                        : [];
                                    $reviewed_by = !empty($reviewed_question['reviewer_id'])
                                        ? get_userdata((int) $reviewed_question['reviewer_id'])
                                        : null;

                                    if ($reviewed_by && !empty($reviewed_question['updated_at'])) {
                                        printf(
                                            esc_html__('Saved %s by %s', 'tfp-dashboard'),
                                            esc_html(date_i18n('m/d/Y', strtotime($reviewed_question['updated_at']))),
                                            esc_html($reviewed_by->display_name)
                                        );
                                    }
                                    ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </aside>
                <?php endif; ?>
            </div>

            <?php if ($questions): ?>
                <footer class="tfp-homework-review-footer">
                    <div class="tfp-homework-review-footer__left">
                        <button
                            type="button"
                            class="tfp-homework-question-nav tfp-homework-question-nav--previous<?php echo $active_index === 0 ? ' is-disabled' : ''; ?>"
                            data-homework-prev-question>
                            <span aria-hidden="true">←</span>
                            <?php esc_html_e('Previous Question', 'tfp-dashboard'); ?>
                        </button>
                    </div>

                    <div class="tfp-homework-question-picker" aria-label="<?php esc_attr_e('Homework questions', 'tfp-dashboard'); ?>">
                        <?php foreach ($questions as $index => $q): ?>
                            <?php $qid = (string) ($q['id'] ?? ''); ?>
                            <button
                                type="button"
                                class="tfp-homework-question-number<?php echo $index === $active_index ? ' is-active' : ''; ?><?php echo $qid && !empty($review['questions'][$qid]['decision']) ? ' is-reviewed' : ''; ?>"
                                data-homework-question="<?php echo esc_attr($index); ?>">
                                <?php echo esc_html($index + 1); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="tfp-homework-review-footer__right">
                        <button type="button" class="tfp-dash-btn tfp-dash-btn--secondary tfp-homework-save-draft" data-homework-save-draft>
                            <?php esc_html_e('Save Draft', 'tfp-dashboard'); ?>
                        </button>

                        <button type="button" class="tfp-dash-btn tfp-dash-btn--primary tfp-homework-submit-next" data-homework-submit-next>
                            <?php echo $active_index < $question_count - 1
                                ? esc_html__('Submit & Next Question', 'tfp-dashboard')
                                : esc_html__('Submit & Finish Review', 'tfp-dashboard'); ?>
                        </button>
                    </div>
                </footer>
            <?php endif; ?>
        </section>
    </div>
    <?php
}

function tfp_dashboard_render_facilitator_homework_content() {
    $current_user_id = get_current_user_id();

    $student_id = absint($_GET['tfp_homework_student'] ?? 0);
    $lesson_id = absint($_GET['tfp_homework_lesson'] ?? 0);
    $cohort_id = absint($_GET['tfp_homework_cohort'] ?? 0);

    if ($student_id && $lesson_id && $cohort_id) {
        $context = tfp_facilitator_homework_validate_context($student_id, $lesson_id, $cohort_id);
        if (!$context) {
            wp_safe_redirect(tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'));
            exit;
        }

        $items = tfp_facilitator_homework_row_items($current_user_id);
        foreach ($items as $item) {
            if ((int) $item['student_id'] === $student_id && (int) $item['lesson_id'] === $lesson_id && (int) $item['cohort_id'] === $cohort_id) {
                tfp_dashboard_render_facilitator_homework_review_content($item);
                return;
            }
        }

        wp_safe_redirect(tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'));
        exit;
    }

    $course_filter = absint($_GET['tfp_homework_course'] ?? 0);
    $cohort_filter = absint($_GET['tfp_homework_cohort_filter'] ?? 0);
    $search = sanitize_text_field(wp_unslash($_GET['tfp_homework_search'] ?? ''));
    $status = sanitize_key(wp_unslash($_GET['tfp_homework_status'] ?? 'all'));

    if (!in_array($status, ['all', 'submitted', 'graded'], true)) {
        $status = 'all';
    }

    $courses = tfp_facilitator_homework_courses($current_user_id);
    $cohorts = tfp_facilitator_homework_cohorts($current_user_id, $course_filter);

    // Keep the cohort filter in sync with the selected course. A previously
    // selected cohort must not leave the list empty when that cohort belongs
    // to a different course.
    if ($cohort_filter) {
        $cohort_available = false;
        foreach ($cohorts as $cohort) {
            if ((int) $cohort->ID === (int) $cohort_filter) {
                $cohort_available = true;
                break;
            }
        }

        if (!$cohort_available) {
            $cohort_filter = 0;
        }
    }

    $items = tfp_facilitator_homework_row_items($current_user_id, $course_filter, $cohort_filter, $search);

    if ($status !== 'all') {
        $items = array_values(array_filter($items, static function ($item) use ($status) {
            return $item['status'] === $status;
        }));
    }

    $per_page = 18;
    $page = max(1, absint($_GET['tfp_homework_page'] ?? 1));
    $total = count($items);
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($page, $pages);
    $paged_items = array_slice($items, ($page - 1) * $per_page, $per_page);

    $range_start = $total ? (($page - 1) * $per_page) + 1 : 0;
    $range_end = min($page * $per_page, $total);

    $base_args = [
        'tfp_homework_status' => $status,
        'tfp_homework_course' => $course_filter,
        'tfp_homework_cohort_filter' => $cohort_filter,
        'tfp_homework_search' => $search,
    ];

    $prev_url = add_query_arg(
        array_merge($base_args, ['tfp_homework_page' => max(1, $page - 1)]),
        tfp_dashboard_get_url('tfp-dashboard-facilitator-homework')
    );

    $next_url = add_query_arg(
        array_merge($base_args, ['tfp_homework_page' => min($pages, $page + 1)]),
        tfp_dashboard_get_url('tfp-dashboard-facilitator-homework')
    );
    ?>
    <div class="tfp-homework-review-page-wrap">
        <header class="tfp-dash-pageheader tfp-homework-main-pageheader">
            <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb">
                <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
                <span>/</span>
                <strong><?php esc_html_e('Homework', 'tfp-dashboard'); ?></strong>
            </div>

            <h1 class="tfp-dash-pageheader__title"><?php esc_html_e('Homework Review', 'tfp-dashboard'); ?></h1>
            <p class="tfp-dash-pageheader__subtitle"><?php esc_html_e('Review class performance across all lessons and assignments.', 'tfp-dashboard'); ?></p>
        </header>

        <nav class="tfp-homework-review-tabs" aria-label="<?php esc_attr_e('Homework review status', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['tfp_homework_status' => 'all', 'tfp_homework_page' => 1]), tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'))); ?>" class="<?php echo $status === 'all' ? 'is-active' : ''; ?>">
                <?php esc_html_e('All Homework', 'tfp-dashboard'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['tfp_homework_status' => 'submitted', 'tfp_homework_page' => 1]), tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'))); ?>" class="<?php echo $status === 'submitted' ? 'is-active' : ''; ?>">
                <?php esc_html_e('Submitted', 'tfp-dashboard'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['tfp_homework_status' => 'graded', 'tfp_homework_page' => 1]), tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'))); ?>" class="<?php echo $status === 'graded' ? 'is-active' : ''; ?>">
                <?php esc_html_e('Graded', 'tfp-dashboard'); ?>
            </a>
        </nav>

        <form method="get" class="tfp-facilitator-filters tfp-homework-filters">
            <input type="hidden" name="tfp_homework_status" value="<?php echo esc_attr($status); ?>">
            <input type="hidden" name="tfp_homework_page" value="1">

            <div class="tfp-homework-filter-item">
                <label for="tfp_homework_course" class="screen-reader-text"><?php esc_html_e('Course', 'tfp-dashboard'); ?></label>
                <select name="tfp_homework_course" id="tfp_homework_course" aria-label="<?php esc_attr_e('Course', 'tfp-dashboard'); ?>">
                    <option value="0"><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo esc_attr($course->ID); ?>" <?php selected($course_filter, $course->ID); ?>>
                            <?php echo esc_html($course->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tfp-homework-filter-item">
                <label for="tfp_homework_cohort_filter" class="screen-reader-text"><?php esc_html_e('Cohort', 'tfp-dashboard'); ?></label>
                <select name="tfp_homework_cohort_filter" id="tfp_homework_cohort_filter" aria-label="<?php esc_attr_e('Cohort', 'tfp-dashboard'); ?>">
                    <option value="0"><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
                    <?php foreach ($cohorts as $cohort): ?>
                        <option value="<?php echo esc_attr($cohort->ID); ?>" <?php selected($cohort_filter, $cohort->ID); ?>>
                            <?php echo esc_html($cohort->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="tfp-homework-filter-search">
                <span aria-hidden="true">⌕</span>
                <input type="search" name="tfp_homework_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>">
            </div>
        </form>

        <section class="tfp-homework-list-card">
            <div class="tfp-homework-list-card__head">
                <h2><?php esc_html_e('All Homework', 'tfp-dashboard'); ?></h2>
            </div>

            <div class="tfp-dash-table-wrap tfp-homework-table-wrap">
                <table class="tfp-dash-table tfp-homework-review-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Course', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Module', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Submitted On', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Grade', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($paged_items): ?>
                            <?php foreach ($paged_items as $item): ?>
                                <tr>
                                    <td class="tfp-homework-student-cell"><?php echo esc_html($item['student_name']); ?></td>
                                    <td><?php echo esc_html($item['course_name']); ?></td>
                                    <td>
                                        <span class="tfp-homework-module-name"><?php echo esc_html($item['module']); ?></span>
                                        <small><?php echo esc_html('Week ' . $item['week']); ?></small>
                                    </td>
                                    <td>
                                        <span class="tfp-homework-table-status tfp-homework-table-status--<?php echo esc_attr($item['status']); ?>">
                                            <?php echo esc_html($item['status'] === 'graded' ? 'Graded' : 'Submitted'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        echo $item['submitted_at']
                                            ? esc_html(wp_date('D, M j, Y g:i A T', $item['submitted_at']))
                                            : '—';
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($item['grade'] ?: '—'); ?></td>
                                    <td class="tfp-homework-action-cell">
                                        <a
                                            href="<?php echo esc_url(tfp_facilitator_homework_build_review_url($item)); ?>"
                                            class="tfp-dash-btn-small tfp-dash-btn--primary">
                                            <?php esc_html_e('View', 'tfp-dashboard'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="tfp-facilitator-empty">
                                        <?php
                                        echo $status === 'graded'
                                            ? esc_html__('No graded homework found.', 'tfp-dashboard')
                                            : ($status === 'submitted'
                                                ? esc_html__('No submitted homework found.', 'tfp-dashboard')
                                                : esc_html__('No submitted homework is available yet.', 'tfp-dashboard'));
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="tfp-homework-pagination">
                <span class="tfp-homework-pagination__details">
                    <?php if ($total): ?>
                        <?php printf(
                            esc_html__('%1$d - %2$d of %3$d items', 'tfp-dashboard'),
                            $range_start,
                            $range_end,
                            $total
                        ); ?>
                    <?php else: ?>
                        <?php esc_html_e('0 of 0 items', 'tfp-dashboard'); ?>
                    <?php endif; ?>
                </span>

                <div class="tfp-homework-pagination__actions">
                    <?php if ($page > 1): ?>
                        <a
                            class="tfp-homework-pagination__prev"
                            href="<?php echo esc_url($prev_url); ?>"
                            rel="prev"
                            aria-label="<?php esc_attr_e('Previous page', 'tfp-dashboard'); ?>">
                            <span aria-hidden="true">‹</span>
                            <?php esc_html_e('Previous', 'tfp-dashboard'); ?>
                        </a>
                    <?php else: ?>
                        <span class="is-disabled" aria-disabled="true">
                            <span aria-hidden="true">‹</span>
                            <?php esc_html_e('Previous', 'tfp-dashboard'); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($page < $pages): ?>
                        <a
                            class="tfp-homework-pagination__next"
                            href="<?php echo esc_url($next_url); ?>"
                            rel="next"
                            aria-label="<?php esc_attr_e('Next page', 'tfp-dashboard'); ?>">
                            <?php esc_html_e('Next', 'tfp-dashboard'); ?>
                            <span aria-hidden="true">›</span>
                        </a>
                    <?php else: ?>
                        <span class="is-disabled" aria-disabled="true">
                            <?php esc_html_e('Next', 'tfp-dashboard'); ?>
                            <span aria-hidden="true">›</span>
                        </span>
                    <?php endif; ?>
                </div>
            </footer>
        </section>
    </div>
    <?php
}

/**
 * Save a review question without reloading the page.
 */
function tfp_facilitator_homework_ajax_save_review() {
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_send_json_error(['message' => __('Unauthorized.', 'tfp-dashboard')], 403);
    }

    check_ajax_referer('tfp_facilitator_student_ajax', 'nonce');

    $student_id = absint($_POST['student_id'] ?? 0);
    $lesson_id = absint($_POST['lesson_id'] ?? 0);
    $cohort_id = absint($_POST['cohort_id'] ?? 0);
    $question_id = sanitize_key(wp_unslash($_POST['question_id'] ?? ''));
    $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
    $feedback = sanitize_textarea_field(wp_unslash($_POST['feedback'] ?? ''));
    $mastery = isset($_POST['mastery'])
        ? (array) $_POST['mastery']
        : [];
    $finalize = !empty($_POST['finalize']);

    $context = tfp_facilitator_homework_validate_context($student_id, $lesson_id, $cohort_id);
    if (!$context) {
        wp_send_json_error(['message' => __('Invalid homework review context.', 'tfp-dashboard')], 400);
    }

    if (!function_exists('tfp_week_get_homework_answers') || !tfp_week_get_homework_answers($student_id, $lesson_id)) {
        wp_send_json_error(['message' => __('No submitted homework answers were found.', 'tfp-dashboard')], 400);
    }

    if ($finalize && !in_array($decision, ['correct', 'incorrect'], true)) {
        wp_send_json_error(['message' => __('Select Correct or Incorrect before submitting this question.', 'tfp-dashboard')], 400);
    }

    if (!tfp_facilitator_homework_save_question_review(
        $student_id,
        $lesson_id,
        $question_id,
        $decision,
        $mastery,
        $feedback
    )) {
        wp_send_json_error(['message' => __('Unable to save this review.', 'tfp-dashboard')], 400);
    }

    $review = tfp_facilitator_homework_review_data($student_id, $lesson_id);

    wp_send_json_success([
        'status' => tfp_facilitator_homework_is_graded($student_id, $lesson_id) ? 'graded' : 'submitted',
        'grade' => tfp_facilitator_homework_grade_letter($student_id, $lesson_id),
        'review' => $review['questions'][$question_id] ?? [],
    ]);
}
add_action('wp_ajax_tfp_facilitator_homework_save_review', 'tfp_facilitator_homework_ajax_save_review');

/**
 * Mark a submission Graded only after every question has a decision.
 */
function tfp_facilitator_homework_ajax_complete_review() {
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_send_json_error(['message' => __('Unauthorized.', 'tfp-dashboard')], 403);
    }

    check_ajax_referer('tfp_facilitator_student_ajax', 'nonce');

    $student_id = absint($_POST['student_id'] ?? 0);
    $lesson_id = absint($_POST['lesson_id'] ?? 0);
    $cohort_id = absint($_POST['cohort_id'] ?? 0);

    $context = tfp_facilitator_homework_validate_context($student_id, $lesson_id, $cohort_id);
    if (!$context) {
        wp_send_json_error(['message' => __('Invalid homework review context.', 'tfp-dashboard')], 400);
    }

    if (!tfp_facilitator_homework_mark_complete($student_id, $lesson_id)) {
        wp_send_json_error(['message' => __('Review every homework question before completing the submission.', 'tfp-dashboard')], 400);
    }

    wp_send_json_success([
        'status' => 'graded',
        'grade' => tfp_facilitator_homework_grade_letter($student_id, $lesson_id),
        'redirect' => tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'),
    ]);
}
add_action('wp_ajax_tfp_facilitator_homework_complete_review', 'tfp_facilitator_homework_ajax_complete_review');
