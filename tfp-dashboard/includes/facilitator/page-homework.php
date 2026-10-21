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

    $context_courses = function_exists('tfp_facilitator_homework_courses')
        ? tfp_facilitator_homework_courses(get_current_user_id())
        : [];
    $context_cohorts = function_exists('tfp_facilitator_homework_cohorts')
        ? tfp_facilitator_homework_cohorts(get_current_user_id(), (int) $item['course_id'])
        : [];
    $context_weeks = function_exists('tfp_ld_get_weeks')
        ? (array) tfp_ld_get_weeks((int) $item['course_id'])
        : [];

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

        <header class="tfp-dash-pageheader tfp-homework-student-pageheader">
            <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb tfp-homework-detail-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
                <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></a>
                <span aria-hidden="true">/</span>
                <strong><?php echo esc_html($student ? $student->display_name : __('Student', 'tfp-dashboard')); ?></strong>
            </div>

            <div class="tfp-homework-student-header">
                <div class="tfp-homework-student-header__person">
                    <div class="tfp-homework-student-avatar">
                        <?php echo get_avatar($student_id, 100, '', '', ['class' => ['tfp-homework-avatar-image']]); ?>
                    </div>
                    <div>
                        <h2><?php echo esc_html($student ? $student->display_name : __('Student', 'tfp-dashboard')); ?></h2>
                        <div class="tfp-homework-student-contact">
                            <span>
                                <span class="tfp-homework-contact-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="14" viewBox="0 0 17 14" fill="none">
<path d="M1.83333 1.83333L6.92303 5.67689L6.92473 5.6783C7.48987 6.09274 7.77261 6.30008 8.0823 6.38018C8.35602 6.45098 8.64376 6.45098 8.91748 6.38018C9.22744 6.30001 9.511 6.09206 10.0771 5.67689C10.0771 5.67689 13.3417 3.17162 15.1667 1.83333M1 10.0002V3.66683C1 2.73341 1 2.26635 1.18166 1.90983C1.34144 1.59623 1.59623 1.34144 1.90983 1.18166C2.26635 1 2.73341 1 3.66683 1H13.3335C14.2669 1 14.733 1 15.0895 1.18166C15.4031 1.34144 15.6587 1.59623 15.8185 1.90983C16 2.266 16 2.7325 16 3.66409V10.003C16 10.9346 16 11.4004 15.8185 11.7566C15.6587 12.0702 15.4031 12.3254 15.0895 12.4852C14.7333 12.6667 14.2675 12.6667 13.3359 12.6667H3.66409C2.73249 12.6667 2.266 12.6667 1.90983 12.4852C1.59623 12.3254 1.34144 12.0702 1.18166 11.7566C1 11.4 1 10.9336 1 10.0002Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
</svg>
                                </span>
                                <?php echo esc_html($student ? $student->user_email : ''); ?>
                            </span>
                            <span>
                                <span class="tfp-homework-contact-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
<path d="M8.62808 11.1601L13.4742 6.31397M18.4316 3.35645L14.341 16.651C13.9744 17.8425 13.7909 18.4385 13.4748 18.636C13.2005 18.8074 12.8609 18.836 12.5623 18.7121C12.2178 18.5692 11.9383 18.0111 11.3807 16.8958L8.7897 11.7139C8.7012 11.5369 8.65691 11.4488 8.5978 11.3721C8.54535 11.304 8.48481 11.2427 8.41676 11.1903C8.34182 11.1325 8.25517 11.0892 8.08608 11.0046L2.89224 8.40772C1.77693 7.85006 1.21923 7.57098 1.07632 7.22656C0.95238 6.92787 0.980645 6.588 1.152 6.31375C1.34959 5.99751 1.94555 5.8138 3.13735 5.44709L16.4319 1.35645C17.3689 1.06815 17.8376 0.924119 18.154 1.0403C18.4297 1.1415 18.647 1.35861 18.7482 1.63428C18.8644 1.9506 18.7202 2.41904 18.4322 3.35506L18.4316 3.35645Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
</svg>
                                </span>
                                <?php echo esc_html($student ? $student->user_email : ''); ?>
                            </span>
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
                </div>

                <a href="<?php echo esc_url($back_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary tfp-homework-back-list">
                    <span aria-hidden="true">←</span>
                    <?php esc_html_e('Back to List', 'tfp-dashboard'); ?>
                </a>
            </div>
        </header>

        <div class="tfp-facilitator-filters tfp-homework-context-filters" aria-label="<?php esc_attr_e('Homework review context', 'tfp-dashboard'); ?>">
            <select id="tfp_homework_review_course" name="tfp_homework_review_course" aria-label="<?php esc_attr_e('Course', 'tfp-dashboard'); ?>">
                <?php foreach ($context_courses as $context_course): ?>
                    <option value="<?php echo esc_attr($context_course->ID); ?>" <?php selected((int) $item['course_id'], (int) $context_course->ID); ?>>
                        <?php echo esc_html($context_course->post_title); ?>
                    </option>
                <?php endforeach; ?>
                <?php if (!$context_courses): ?>
                    <option><?php echo esc_html($course ? $course->post_title : __('Course', 'tfp-dashboard')); ?></option>
                <?php endif; ?>
            </select>

            <select id="tfp_homework_review_cohort" name="tfp_homework_review_cohort" aria-label="<?php esc_attr_e('Cohort', 'tfp-dashboard'); ?>">
                <?php foreach ($context_cohorts as $context_cohort): ?>
                    <option value="<?php echo esc_attr($context_cohort->ID); ?>" <?php selected((int) $cohort_id, (int) $context_cohort->ID); ?>>
                        <?php echo esc_html($context_cohort->post_title); ?>
                    </option>
                <?php endforeach; ?>
                <?php if (!$context_cohorts): ?>
                    <option><?php echo esc_html(get_the_title($cohort_id)); ?></option>
                <?php endif; ?>
            </select>

            <select id="tfp_homework_review_week" name="tfp_homework_review_week" aria-label="<?php esc_attr_e('Week', 'tfp-dashboard'); ?>">
                <?php foreach ($context_weeks as $context_index => $context_week): ?>
                    <option value="<?php echo esc_attr($context_week->ID); ?>" <?php selected((int) $lesson_id, (int) $context_week->ID); ?>>
                        <?php echo esc_html('Week ' . ((int) $context_index + 1)); ?>
                    </option>
                <?php endforeach; ?>
                <?php if (!$context_weeks): ?>
                    <option><?php echo esc_html('Week ' . $week_number); ?></option>
                <?php endif; ?>
            </select>
        </div>

        <section class="tfp-homework-review-surface">
            <header class="tfp-homework-review-section-header">
                <div class="tfp-homework-review-section-title">
                    <?php printf(
                        esc_html__('Section Name: %s', 'tfp-dashboard'),
                        esc_html($item['module'])
                    ); ?>
                </div>
                <div class="tfp-homework-section-progress">
                    <div class="tfp-homework-section-progress__label">
                        <strong><?php esc_html_e('Questions', 'tfp-dashboard'); ?></strong>
                        <span data-homework-current-question><?php echo esc_html($active_index + 1); ?></span>
                        <span>/</span>
                        <span><?php echo esc_html($question_count); ?></span>
                    </div>
                    <div class="tfp-homework-progress-bars" aria-hidden="true">
                        <?php for ($progress_index = 0; $progress_index < $question_count; $progress_index++): ?>
                            <span class="<?php echo $progress_index <= $active_index ? 'is-complete' : ''; ?><?php echo $progress_index === $question_count - 1 ? ' is-current' : ''; ?>"></span>
                        <?php endfor; ?>
                    </div>
                </div>
            </header>

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
                                            'mastered' => __('Mastered', 'tfp-dashboard'),
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
                                <div class="tfp-homework-feedback-section">
                                    <label for="<?php echo esc_attr('tfp-homework-feedback-' . $index); ?>">
                                        <?php esc_html_e('Feedback', 'tfp-dashboard'); ?>
                                    </label>
                                    <textarea
                                        id="<?php echo esc_attr('tfp-homework-feedback-' . $index); ?>"
                                        data-homework-feedback
                                        placeholder="<?php esc_attr_e('Add Facilitator feedback here...', 'tfp-dashboard'); ?>"><?php echo esc_textarea($review_item['feedback'] ?? ''); ?></textarea>
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

                    <div class="tfp-homework-review-footer__right">
                        <button type="button" class="tfp-dash-btn tfp-dash-btn--secondary tfp-homework-save-draft" data-homework-save-draft>
                            <span class="tfp-homework-save-icon" aria-hidden="true">▣</span>
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

        /*
         * A review page can be reached from the week selector even when the
         * selected week is not present in the submitted-homework list (for
         * example, the student has not marked that homework step complete
         * yet). The context validator has already confirmed that the lesson
         * belongs to this cohort's course and contains homework questions, so
         * build the review context directly instead of redirecting to a blank
         * page.
         */
        $course_id = (int) $context['course_id'];
        $week_number = 0;
        $selected_week = null;
        $weeks = function_exists('tfp_ld_get_weeks')
            ? (array) tfp_ld_get_weeks($course_id)
            : [];

        foreach ($weeks as $index => $week) {
            if ((int) $week->ID === $lesson_id) {
                $selected_week = $week;
                $week_number = (int) $index + 1;
                break;
            }
        }

        if ($selected_week) {
            $answers = function_exists('tfp_week_get_homework_answers')
                ? tfp_week_get_homework_answers($student_id, $lesson_id)
                : [];

            $submitted_at = 0;
            foreach ((array) $answers as $answer) {
                if (!empty($answer['submitted_at'])) {
                    $submitted_at = max($submitted_at, strtotime((string) $answer['submitted_at']));
                }
            }

            $item = [
                'student_id' => $student_id,
                'student_name' => get_the_author_meta('display_name', $student_id),
                'course_id' => $course_id,
                'course_name' => get_the_title($course_id),
                'cohort_id' => $cohort_id,
                'cohort_name' => get_the_title($cohort_id),
                'lesson_id' => $lesson_id,
                'week' => $week_number,
                'module' => $selected_week->post_title,
                'status' => tfp_facilitator_homework_is_graded($student_id, $lesson_id) ? 'graded' : 'submitted',
                'submitted_at' => $submitted_at,
                'grade' => tfp_facilitator_homework_is_graded($student_id, $lesson_id)
                    ? tfp_facilitator_homework_grade_letter($student_id, $lesson_id)
                    : '',
                'question_count' => count((array) tfp_week_get_homework_questions($lesson_id, false)),
            ];

            tfp_dashboard_render_facilitator_homework_review_content($item);
            return;
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

        <form class="tfp-facilitator-filters tfp-homework-filters" method="get" action="<?php echo esc_url(get_permalink()); ?>">
            <input type="hidden" name="tfp_homework_status" value="<?php echo esc_attr($status); ?>">
            <input type="hidden" name="tfp_homework_page" value="1">

            <select name="tfp_homework_course" aria-label="<?php esc_attr_e('Course', 'tfp-dashboard'); ?>" onchange="this.form.submit()">
                <option value="0"><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo esc_attr($course->ID); ?>" <?php selected($course_filter, $course->ID); ?>><?php echo esc_html($course->post_title); ?></option>
                <?php endforeach; ?>
            </select>

            <select name="tfp_homework_cohort_filter" aria-label="<?php esc_attr_e('Cohort', 'tfp-dashboard'); ?>" onchange="this.form.submit()">
                <option value="0"><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
                <?php foreach ($cohorts as $cohort): ?>
                    <option value="<?php echo esc_attr($cohort->ID); ?>" <?php selected($cohort_filter, $cohort->ID); ?>><?php echo esc_html($cohort->post_title); ?></option>
                <?php endforeach; ?>
            </select>

            <div class="tfp-dash-search tfp-facilitator-search">
                <span class="tfp-dash-search__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M16 16L13 13M7.0002 17H4.19692C3.07901 17 2.5192 17 2.0918 16.7822C1.71547 16.5905 1.40973 16.2842 1.21799 15.9079C1 15.4801 1 14.9203 1 13.8002V4.2002C1 3.08009 1 2.51962 1.21799 2.0918C1.40973 1.71547 1.71547 1.40973 2.0918 1.21799C2.51962 1 3.08009 1 4.2002 1H13.8002C14.9203 1 15.4796 1 15.9074 1.21799C16.2837 1.40973 16.5905 1.71547 16.7822 2.0918C17 2.5192 17 3.07899 17 4.19691V7.0002M10.5 14C8.567 14 7 12.433 7 10.5C7 8.567 8.567 7 10.5 7C12.433 7 14 8.567 14 10.5C14 12.433 12.433 14 10.5 14Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </span>
                <input type="search" name="tfp_homework_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>" aria-label="<?php esc_attr_e('Search students', 'tfp-dashboard'); ?>">
            </div>

            <?php if ($course_filter || $cohort_filter || $search): ?>
                <a class="tfp-facilitator-clear-filter" href="<?php echo esc_url(remove_query_arg(['tfp_homework_course', 'tfp_homework_cohort_filter', 'tfp_homework_search', 'tfp_homework_page'])); ?>"><?php esc_html_e('Clear Filters', 'tfp-dashboard'); ?></a>
            <?php endif; ?>
        </form>

        <section class="tfp-homework-list-card">
            <div class="tfp-homework-list-card__head">
                <h5><?php esc_html_e('All Homework', 'tfp-dashboard'); ?></h5>
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
                                    <td class="tfp-homework-course-cell"><span><?php echo esc_html($item['course_name']); ?></span></td>
                                    <td>
                                        <span class="tfp-homework-module-name"><?php echo esc_html($item['module']); ?></span>
                                        <small><?php echo esc_html('Week ' . $item['week']); ?></small>
                                    </td>
                                    <td>
                                        <span class="tfp-homework-table-status tfp-homework-table-status--<?php echo esc_attr($item['status']); ?>">
                                            <?php echo esc_html($item['status'] === 'graded' ? 'Graded' : 'Submitted'); ?>
                                        </span>
                                    </td>
                                    <td class="tfp-homework-submitted-cell">
                                        <span>
                                            <?php
                                            echo $item['submitted_at']
                                                ? esc_html(wp_date('D, M j, Y g:i A T', $item['submitted_at']))
                                                : '—';
                                            ?>
                                        </span>
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
                            <span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?>
                        </a>
                    <?php else: ?>
                        <span class="is-disabled" aria-disabled="true">
                            <span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($page < $pages): ?>
                        <a
                            class="tfp-homework-pagination__next tfp-dash-btn-small tfp-dash-btn--primary"
                            href="<?php echo esc_url($next_url); ?>"
                            rel="next"
                            aria-label="<?php esc_attr_e('Next page', 'tfp-dashboard'); ?>">
                            <?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span>
                        </a>
                    <?php else: ?>
                        <span class="tfp-homework-pagination__next tfp-dash-btn-small tfp-dash-btn--primary is-disabled" aria-disabled="true">
                            <?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span>
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
