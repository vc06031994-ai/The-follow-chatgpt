<?php
if (!defined('ABSPATH')) exit;

/**
 * Facilitator Roster > Student Profile
 *
 * Uses the existing facilitator shell, design tokens and SelectWoo filters.
 * The student detail page supports course/week navigation, program progress,
 * attendance, notes, homework/quiz/test review and full activity history.
 */

function tfp_facilitator_student_note_key($lesson_id) {
    return 'tfp_facilitator_student_notes_' . absint($lesson_id);
}

function tfp_facilitator_student_attendance_key($lesson_id) {
    return 'tfp_facilitator_student_attendance_' . absint($lesson_id);
}

function tfp_facilitator_student_review_key($type, $lesson_id) {
    return 'tfp_facilitator_review_' . sanitize_key($type) . '_' . absint($lesson_id);
}

function tfp_facilitator_student_notes($student_id, $lesson_id) {
    $notes = get_user_meta((int) $student_id, tfp_facilitator_student_note_key($lesson_id), true);
    return is_array($notes) ? array_reverse($notes) : [];
}

function tfp_facilitator_student_attendance($student_id, $lesson_id) {
    $value = get_user_meta((int) $student_id, tfp_facilitator_student_attendance_key($lesson_id), true);
    return is_array($value) ? wp_parse_args($value, [
        'status' => '', 'note' => '', 'updated_at' => '', 'author_id' => 0,
    ]) : [
        'status' => '', 'note' => '', 'updated_at' => '', 'author_id' => 0,
    ];
}

function tfp_facilitator_student_reviewed($type, $lesson_id) {
    return (bool) get_user_meta(
        get_current_user_id(),
        tfp_facilitator_student_review_key($type, $lesson_id),
        true
    );
}

function tfp_facilitator_student_allowed($student_id, $current_user_id = 0) {
    $student_id = absint($student_id);
    $current_user_id = $current_user_id ?: get_current_user_id();

    if (!$student_id || !$current_user_id || !function_exists('tfp_facilitator_current_cohorts')) {
        return false;
    }

    foreach (tfp_facilitator_current_cohorts($current_user_id) as $cohort) {
        if (in_array($student_id, tfp_facilitator_cohort_students($cohort->ID), true)) {
            return true;
        }
    }
    return false;
}

function tfp_facilitator_student_course_context($student_id) {
    $course_id = 0;
    $cohort_id = 0;

    foreach (tfp_facilitator_current_cohorts() as $cohort) {
        if (in_array((int) $student_id, tfp_facilitator_cohort_students($cohort->ID), true)) {
            $cohort_id = (int) $cohort->ID;
            $course_id = (int) get_post_meta($cohort->ID, '_related_course', true);
            break;
        }
    }

    return ['course_id' => $course_id, 'cohort_id' => $cohort_id];
}

function tfp_facilitator_student_score_letter($score) {
    if ($score === '' || $score === null || !is_numeric($score)) return '';
    $score = (float) $score;
    if ($score >= 90) return 'A';
    if ($score >= 80) return 'B';
    if ($score >= 70) return 'C';
    if ($score >= 60) return 'D';
    return 'F';
}

function tfp_facilitator_student_reading_status($student_id, $lesson_id) {
    $readings = get_posts([
        'post_type' => 'tfp_reading',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [[
            'key' => '_tfp_lesson_id',
            'value' => absint($lesson_id),
            'type' => 'NUMERIC',
        ]],
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    if (!$readings) {
        return ['label' => 'Not Started', 'tone' => 'neutral', 'value' => 0];
    }

    $completed = get_user_meta($student_id, 'tfp_reading_progress_' . absint($lesson_id), true);
    $completed = is_array($completed) ? array_map('absint', $completed) : [];
    $count = count(array_intersect(wp_list_pluck($readings, 'ID'), $completed));
    $percent = (int) round(($count / count($readings)) * 100);

    if ($percent >= 100) return ['label' => 'Completed', 'tone' => 'success', 'value' => 100];
    if ($percent > 0) return ['label' => 'In Progress', 'tone' => 'warning', 'value' => $percent];
    return ['label' => 'Not Started', 'tone' => 'neutral', 'value' => 0];
}

function tfp_facilitator_student_step_statuses($student_id, $lesson_id) {
    $progress = function_exists('tfp_ld_get_week_progress') ? tfp_ld_get_week_progress($student_id, $lesson_id) : [];
    $homework = function_exists('tfp_week_homework_progress') ? tfp_week_homework_progress($student_id, $lesson_id) : ['completed' => 0, 'total' => 0];
    $quiz = function_exists('tfp_week_get_quiz_result') ? tfp_week_get_quiz_result($student_id, $lesson_id) : null;
    $test = function_exists('tfp_week_get_test_result') ? tfp_week_get_test_result($student_id, $lesson_id) : null;
    $attendance = tfp_facilitator_student_attendance($student_id, $lesson_id);
    $reading = tfp_facilitator_student_reading_status($student_id, $lesson_id);

    return [
        'reading' => $reading,
        'homework' => [
            'label' => !empty($progress['homework']) ? 'Completed' : (($homework['completed'] > 0) ? 'In Progress' : 'Not Started'),
            'tone' => !empty($progress['homework']) ? 'success' : (($homework['completed'] > 0) ? 'warning' : 'neutral'),
            'value' => $homework['total'] > 0 ? (int) round(($homework['completed'] / $homework['total']) * 100) : (!empty($progress['homework']) ? 100 : 0),
        ],
        'quiz' => [
            'label' => $quiz ? (tfp_facilitator_student_score_letter($quiz['score'] ?? '') ?: '—') : '—',
            'tone' => $quiz ? (!empty($quiz['passed']) ? 'success' : 'danger') : 'neutral',
            'value' => $quiz ? (int) ($quiz['score'] ?? 0) : 0,
        ],
        'test' => [
            'label' => $test ? (tfp_facilitator_student_score_letter($test['score'] ?? '') ?: '—') : '—',
            'tone' => $test ? (!empty($test['passed']) ? 'success' : 'danger') : 'neutral',
            'value' => $test ? (int) ($test['score'] ?? 0) : 0,
        ],
        'attendance' => [
            'label' => $attendance['status'] ? strtoupper((string) $attendance['status']) : '—',
            'tone' => $attendance['status'] === 'P' ? 'success' : (($attendance['status'] === 'UA') ? 'danger' : (($attendance['status'] === 'EA') ? 'warning' : 'neutral')),
            'value' => $attendance['status'],
        ],
    ];
}

function tfp_facilitator_student_weeks($course_id) {
    return $course_id && function_exists('tfp_ld_get_weeks') ? tfp_ld_get_weeks($course_id) : [];
}

function tfp_facilitator_student_program_progress($student_id, $course_id) {
    $weeks = tfp_facilitator_student_weeks($course_id);
    $completed = 0;

    foreach ($weeks as $week) {
        if (function_exists('tfp_ld_is_week_complete') && tfp_ld_is_week_complete($student_id, $week->ID)) {
            $completed++;
        }
    }

    return [
        'completed' => $completed,
        'total' => count($weeks),
        'percent' => count($weeks) ? (int) round(($completed / count($weeks)) * 100) : 0,
    ];
}

function tfp_facilitator_student_assessment_progress($student_id, $course_id) {
    $weeks = tfp_facilitator_student_weeks($course_id);
    $total = 0;
    $completed = 0;

    foreach ($weeks as $week) {
        $progress = function_exists('tfp_ld_get_week_progress') ? tfp_ld_get_week_progress($student_id, $week->ID) : [];
        foreach (['video', 'reading', 'homework', 'quiz', 'test'] as $step) {
            $total++;
            if ($step === 'reading') {
                if (tfp_facilitator_student_reading_status($student_id, $week->ID)['value'] >= 100) {
                    $completed++;
                }
            } elseif (!empty($progress[$step])) {
                $completed++;
            }
        }
    }

    return ['completed' => $completed, 'total' => $total];
}

function tfp_facilitator_student_week_completion_for_cohort($student_id, $cohort_id, $course_id) {
    $cohort_students = tfp_facilitator_cohort_students($cohort_id);
    if (!$cohort_students) {
        return tfp_facilitator_student_program_progress($student_id, $course_id)['percent'];
    }

    $percents = [];
    foreach ($cohort_students as $uid) {
        $percents[] = tfp_facilitator_student_program_progress($uid, $course_id)['percent'];
    }

    return $percents ? (int) round(array_sum($percents) / count($percents)) : 0;
}

function tfp_facilitator_student_activity_items($student_id, $course_id) {
    $events = [];

    foreach (tfp_facilitator_student_weeks($course_id) as $index => $week) {
        $lesson_id = (int) $week->ID;
        $week_no = $index + 1;
        $progress = function_exists('tfp_ld_get_week_progress') ? tfp_ld_get_week_progress($student_id, $lesson_id) : [];

        $readings = get_posts([
            'post_type' => 'tfp_reading',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [[
                'key' => '_tfp_lesson_id',
                'value' => $lesson_id,
                'type' => 'NUMERIC',
            ]],
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);

        $done_readings = get_user_meta($student_id, 'tfp_reading_progress_' . $lesson_id, true);
        $done_readings = is_array($done_readings) ? array_map('absint', $done_readings) : [];

        foreach ($readings as $reading) {
            if (!in_array((int) $reading->ID, $done_readings, true)) continue;
            $events[] = [
                'type' => 'READING',
                'title' => get_the_title($reading->ID),
                'date' => get_the_modified_date('M j, Y', $reading->ID),
                'time' => get_the_modified_date('g:i A T', $reading->ID),
                'status' => 'Completed',
                'tone' => 'success',
                'timestamp' => get_post_timestamp($reading->ID, 'modified'),
                'week' => $week_no,
            ];
        }

        if (!empty($progress['homework'])) {
            $events[] = [
                'type' => 'HOMEWORK',
                'title' => 'Homework submitted for Week ' . $week_no,
                'date' => get_the_date('M j, Y', $week),
                'time' => get_the_date('g:i A T', $week),
                'status' => 'Completed',
                'tone' => 'success',
                'timestamp' => get_post_timestamp($lesson_id, 'modified'),
                'week' => $week_no,
            ];
        }

        $quiz = function_exists('tfp_week_get_quiz_result') ? tfp_week_get_quiz_result($student_id, $lesson_id) : null;
        if ($quiz) {
            $events[] = [
                'type' => 'QUIZ',
                'title' => 'Quiz review for Week ' . $week_no,
                'date' => !empty($quiz['graded_at']) ? date_i18n('M j, Y', strtotime($quiz['graded_at'])) : get_the_date('M j, Y', $week),
                'time' => !empty($quiz['graded_at']) ? date_i18n('g:i A T', strtotime($quiz['graded_at'])) : get_the_date('g:i A T', $week),
                'status' => !empty($quiz['passed']) ? 'Completed' : 'Needs Review',
                'tone' => !empty($quiz['passed']) ? 'success' : 'danger',
                'timestamp' => !empty($quiz['graded_at']) ? strtotime($quiz['graded_at']) : get_post_timestamp($lesson_id, 'modified'),
                'week' => $week_no,
            ];
        }

        $test = function_exists('tfp_week_get_test_result') ? tfp_week_get_test_result($student_id, $lesson_id) : null;
        if ($test) {
            $events[] = [
                'type' => 'TEST',
                'title' => 'Test review for Week ' . $week_no,
                'date' => !empty($test['graded_at']) ? date_i18n('M j, Y', strtotime($test['graded_at'])) : get_the_date('M j, Y', $week),
                'time' => !empty($test['graded_at']) ? date_i18n('g:i A T', strtotime($test['graded_at'])) : get_the_date('g:i A T', $week),
                'status' => !empty($test['passed']) ? 'Completed' : 'Needs Review',
                'tone' => !empty($test['passed']) ? 'success' : 'danger',
                'timestamp' => !empty($test['graded_at']) ? strtotime($test['graded_at']) : get_post_timestamp($lesson_id, 'modified'),
                'week' => $week_no,
            ];
        }

        $attendance = tfp_facilitator_student_attendance($student_id, $lesson_id);
        if (!empty($attendance['status'])) {
            $events[] = [
                'type' => 'ATTENDANCE',
                'title' => 'Week ' . $week_no . ' attendance',
                'date' => $attendance['updated_at'] ? date_i18n('M j, Y', strtotime($attendance['updated_at'])) : get_the_date('M j, Y', $week),
                'time' => $attendance['updated_at'] ? date_i18n('g:i A T', strtotime($attendance['updated_at'])) : get_the_date('g:i A T', $week),
                'status' => strtoupper($attendance['status']) === 'P' ? 'Completed' : 'Scheduled',
                'tone' => strtoupper($attendance['status']) === 'P' ? 'success' : 'warning',
                'timestamp' => $attendance['updated_at'] ? strtotime($attendance['updated_at']) : get_post_timestamp($lesson_id, 'modified'),
                'week' => $week_no,
            ];
        }

        foreach (tfp_facilitator_student_notes($student_id, $lesson_id) as $note) {
            $ts = !empty($note['created_at']) ? strtotime($note['created_at']) : current_time('timestamp');
            $events[] = [
                'type' => 'NOTES',
                'title' => wp_trim_words(wp_strip_all_tags($note['text'] ?? ''), 10),
                'date' => date_i18n('M j, Y', $ts),
                'time' => date_i18n('g:i A T', $ts),
                'status' => 'Completed',
                'tone' => 'success',
                'timestamp' => $ts,
                'week' => $week_no,
            ];
        }
    }

    usort($events, static function ($a, $b) {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });

    return $events;
}

function tfp_facilitator_student_post_action() {
    if (empty($_POST['tfp_student_action'])) return;
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) return;
    if (!isset($_POST['tfp_student_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tfp_student_nonce'])), 'tfp_student_detail')) return;

    $student_id = absint($_POST['tfp_student_id'] ?? 0);
    $lesson_id = absint($_POST['tfp_student_lesson_id'] ?? 0);
    if (!$student_id || !tfp_facilitator_student_allowed($student_id)) return;

    $base = add_query_arg(
        ['tfp_student_id' => $student_id],
        tfp_dashboard_get_url('tfp-dashboard-facilitator-student')
    );

    switch (sanitize_key(wp_unslash($_POST['tfp_student_action']))) {
        case 'save_attendance':
            $status = strtoupper(sanitize_text_field(wp_unslash($_POST['attendance_status'] ?? '')));
            if (!in_array($status, ['P', 'EA', 'UA'], true) || !$lesson_id) return;

            update_user_meta($student_id, tfp_facilitator_student_attendance_key($lesson_id), [
                'status' => $status,
                'note' => sanitize_textarea_field(wp_unslash($_POST['attendance_note'] ?? '')),
                'updated_at' => current_time('mysql'),
                'author_id' => get_current_user_id(),
            ]);

            wp_safe_redirect(add_query_arg([
                'tfp_student_week' => $lesson_id,
                'tfp_student_panel' => 'attendance',
            ], $base));
            exit;

        case 'save_note':
            if (!$lesson_id) return;
            $text = trim(wp_kses_post(wp_unslash($_POST['note_text'] ?? '')));
            if ($text === '') return;

            $notes = get_user_meta($student_id, tfp_facilitator_student_note_key($lesson_id), true);
            $notes = is_array($notes) ? $notes : [];
            $notes[] = [
                'author_id' => get_current_user_id(),
                'text' => $text,
                'created_at' => current_time('mysql'),
            ];
            update_user_meta($student_id, tfp_facilitator_student_note_key($lesson_id), $notes);

            wp_safe_redirect(add_query_arg([
                'tfp_student_week' => $lesson_id,
                'tfp_student_panel' => 'notes',
            ], $base));
            exit;

        case 'complete_review':
            $type = sanitize_key(wp_unslash($_POST['review_type'] ?? ''));
            if (!$lesson_id || !in_array($type, ['homework', 'quiz', 'test'], true)) return;

            update_user_meta(
                $student_id,
                tfp_facilitator_student_review_key($type, $lesson_id),
                current_time('mysql')
            );

            wp_safe_redirect(add_query_arg([
                'tfp_student_week' => $lesson_id,
                'tfp_student_panel' => $type,
            ], $base));
            exit;
    }
}
add_action('template_redirect', 'tfp_facilitator_student_post_action', 1);

function tfp_facilitator_student_status_badge($status, $tone = 'neutral', $class = '') {
    echo '<span class="tfp-student-status tfp-student-status--' . esc_attr($tone) . ' ' . esc_attr($class) . '">' . esc_html($status) . '</span>';
}

function tfp_facilitator_student_render_filters($student_id, $course_id, $cohort_id, $weeks, $selected_week = 0) {
    $course_title = $course_id ? get_the_title($course_id) : '';
    $cohort_title = $cohort_id ? get_the_title($cohort_id) : '';
    ?>
    <form class="tfp-student-filters tfp-facilitator-filters" method="get" action="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-student')); ?>">
        <input type="hidden" name="tfp_student_id" value="<?php echo esc_attr($student_id); ?>">
        <select name="tfp_student_course" aria-label="<?php esc_attr_e('Course', 'tfp-dashboard'); ?>">
            <option value="<?php echo esc_attr($course_id); ?>"><?php echo esc_html($course_title ?: __('Course', 'tfp-dashboard')); ?></option>
        </select>
        <select name="tfp_student_cohort" aria-label="<?php esc_attr_e('Cohort', 'tfp-dashboard'); ?>">
            <option value="<?php echo esc_attr($cohort_id); ?>"><?php echo esc_html($cohort_title ?: __('Cohort', 'tfp-dashboard')); ?></option>
        </select>
        <select name="tfp_student_week" aria-label="<?php esc_attr_e('Week', 'tfp-dashboard'); ?>" onchange="this.form.submit()">
            <option value="0"><?php esc_html_e('All Weeks', 'tfp-dashboard'); ?></option>
            <?php foreach ($weeks as $index => $week): ?>
                <option value="<?php echo esc_attr($week->ID); ?>" <?php selected($selected_week, $week->ID); ?>>
                    <?php echo esc_html__('Week ' . ($index + 1), 'tfp-dashboard'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
}

function tfp_facilitator_student_close_url($extra = []) {
    $base = add_query_arg(
        ['tfp_student_id' => absint($_GET['tfp_student_id'] ?? 0)],
        tfp_dashboard_get_url('tfp-dashboard-facilitator-student')
    );
    return $extra ? add_query_arg($extra, $base) : $base;
}

function tfp_dashboard_render_facilitator_student_content() {
    $student_id = absint($_GET['tfp_student_id'] ?? 0);

    if (!$student_id || !tfp_facilitator_student_allowed($student_id)) {
        wp_safe_redirect(tfp_dashboard_get_url('tfp-dashboard-facilitator-roster'));
        exit;
    }

    $student = get_userdata($student_id);
    $context = tfp_facilitator_student_course_context($student_id);
    $course_id = $context['course_id'];
    $cohort_id = $context['cohort_id'];
    $GLOBALS['tfp_student_course_id'] = $course_id;

    $weeks = tfp_facilitator_student_weeks($course_id);
    $selected_week_id = absint($_GET['tfp_student_week'] ?? 0);
    $selected_week = $selected_week_id ? get_post($selected_week_id) : null;

    if (!$selected_week || $selected_week->post_type !== 'sfwd-lessons') {
        $selected_week = null;
        $selected_week_id = 0;
    }

    if ($selected_week && function_exists('learndash_get_course_id') && (int) learndash_get_course_id($selected_week->ID) !== (int) $course_id) {
        $selected_week = null;
        $selected_week_id = 0;
    }

    $profile_progress = tfp_facilitator_student_program_progress($student_id, $course_id);
    $assessment = tfp_facilitator_student_assessment_progress($student_id, $course_id);
    $overall_grade = function_exists('tfp_grade_overall') ? tfp_grade_overall($student_id, $course_id) : '';

    $attendance_values = [];
    foreach ($weeks as $week) {
        $attendance = tfp_facilitator_student_attendance($student_id, $week->ID);
        if ($attendance['status'] === 'P') $attendance_values[] = 100;
        elseif ($attendance['status'] === 'EA') $attendance_values[] = 50;
        elseif ($attendance['status'] === 'UA') $attendance_values[] = 0;
    }
    $attendance_percent = $attendance_values ? (int) round(array_sum($attendance_values) / count($attendance_values)) : '';

    $activities = tfp_facilitator_student_activity_items($student_id, $course_id);
    $last_activity = $activities ? $activities[0] : null;
    $active_panel = sanitize_key($_GET['tfp_student_panel'] ?? '');
    $activity_view = sanitize_key($_GET['tfp_student_activity'] ?? '');

    ?>
    <div class="tfp-student-profile">
        <button type="button" class="tfp-dash-pageheader__mobile-toggle tfp-student-mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>">
            <?php echo tfp_dashboard_icon('toggle'); ?>
        </button>
        <div class="tfp-student-breadcrumb">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-roster')); ?>"><?php esc_html_e('Roster', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <strong><?php echo esc_html($student->display_name); ?></strong>
            <?php if ($selected_week): ?>
                <span>/</span>
                <strong><?php printf(esc_html__('Week %02d', 'tfp-dashboard'), max(1, (int) array_search($selected_week->ID, wp_list_pluck($weeks, 'ID'), true) + 1)); ?></strong>
            <?php endif; ?>
        </div>

        <div class="tfp-student-profile-head">
            <div class="tfp-student-profile-person">
                <div class="tfp-student-avatar"><?php echo get_avatar($student_id, 94, '', $student->display_name, ['class' => 'tfp-student-avatar-img']); ?></div>
                <div>
                    <h1><?php echo esc_html($student->display_name); ?></h1>
                    <div class="tfp-student-contact-row">
                        <span><?php echo tfp_dashboard_icon('mail'); ?><?php echo esc_html($student->user_email); ?></span>
                        <span><?php echo tfp_dashboard_icon('chat'); ?><?php echo esc_html($student->user_email); ?></span>
                    </div>
                </div>
            </div>
            <div class="tfp-student-head-side">
                <?php if ($last_activity): ?><small><?php esc_html_e('Last Active:', 'tfp-dashboard'); ?> <?php echo esc_html($last_activity['date'] . ' ' . $last_activity['time']); ?></small><?php endif; ?>
                <a class="tfp-dash-btn tfp-dash-btn--primary tfp-student-back" href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-roster')); ?>">← <?php esc_html_e('Back to Roster', 'tfp-dashboard'); ?></a>
            </div>
        </div>

        <?php tfp_facilitator_student_render_filters($student_id, $course_id, $cohort_id, $weeks, $selected_week_id); ?>

        <?php
        if ($selected_week) {
            tfp_facilitator_student_render_week_view($student_id, $course_id, $cohort_id, $weeks, $selected_week, $activities);
        } else {
            echo '<h2 class="tfp-student-overview-title">' . esc_html__('All Weeks Overview — Full Program Performance', 'tfp-dashboard') . '</h2>';
            tfp_facilitator_student_render_overview($student_id, $course_id, $cohort_id, $weeks, $profile_progress, $assessment, $attendance_percent, $overall_grade, $activities);
        }

        if ($activity_view === 'all') {
            tfp_facilitator_student_render_activity_overlay($student_id, $activities);
        }

        if (in_array($active_panel, ['attendance', 'notes', 'homework', 'quiz', 'test'], true) && $selected_week) {
            tfp_facilitator_student_render_panel_overlay($student_id, $selected_week, $active_panel);
        }
        ?>
    </div>
    <?php
}

function tfp_facilitator_student_render_overview($student_id, $course_id, $cohort_id, $weeks, $progress, $assessment, $attendance_percent, $overall_grade, $activities) {
    $show_weeks = array_slice($weeks, 0, min(13, count($weeks)));
    $current_week = function_exists('tfp_ld_get_current_week') ? tfp_ld_get_current_week($student_id, $course_id) : null;
    $current_id = $current_week ? (int) $current_week->ID : 0;
    $cohort_average = tfp_facilitator_student_week_completion_for_cohort($student_id, $cohort_id, $course_id);
    ?>
    <section class="tfp-student-overview">
        <div class="tfp-student-metrics tfp-facilitator-metrics">
            <article class="tfp-facilitator-metric tfp-student-metric--gold"><span><?php esc_html_e('Program Progress', 'tfp-dashboard'); ?></span><strong><?php echo esc_html($progress['percent']); ?>%</strong><small><?php printf(esc_html__('%d Week Program Progress', 'tfp-dashboard'), $progress['total']); ?></small></article>
            <article class="tfp-facilitator-metric tfp-facilitator-metric--teal"><span><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></span><strong><?php echo $attendance_percent === '' ? '—' : esc_html($attendance_percent . '%'); ?></strong><small><?php esc_html_e('Across Entire Course', 'tfp-dashboard'); ?></small></article>
            <article class="tfp-facilitator-metric tfp-facilitator-metric--teal"><span><?php esc_html_e('Overall Grade', 'tfp-dashboard'); ?></span><strong><?php echo esc_html($overall_grade ?: '—'); ?></strong><small><?php esc_html_e('Current Course Average', 'tfp-dashboard'); ?></small></article>
            <article class="tfp-facilitator-metric tfp-facilitator-metric--gold"><span><?php esc_html_e('Assessments', 'tfp-dashboard'); ?></span><strong><?php echo esc_html($assessment['completed'] . '/' . $assessment['total']); ?></strong><small><?php esc_html_e('Active Module Parts Completed', 'tfp-dashboard'); ?></small></article>
        </div>

        <div class="tfp-student-overview-grid">
            <div class="tfp-student-overview-left">
                <section class="tfp-dash-panel tfp-student-panel">
                    <div class="tfp-student-panel-head">
                        <div>
                            <h2><?php printf(esc_html__('Program Progress: Sections 1–%d', 'tfp-dashboard'), count($show_weeks)); ?></h2>
                            <p><?php esc_html_e('Current Active Module', 'tfp-dashboard'); ?> – <?php echo esc_html($current_week ? $current_week->post_title : '—'); ?></p>
                        </div>
                    </div>
                    <div class="tfp-student-week-dots">
                        <?php foreach ($show_weeks as $index => $week):
                            $state = 'upcoming';
                            if (function_exists('tfp_ld_is_week_complete') && tfp_ld_is_week_complete($student_id, $week->ID)) $state = 'complete';
                            elseif ((int) $week->ID === $current_id) $state = 'current';
                            $href = add_query_arg(['tfp_student_id' => $student_id, 'tfp_student_week' => $week->ID], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'));
                            ?>
                            <a class="tfp-student-week-dot tfp-student-week-dot--<?php echo esc_attr($state); ?>" href="<?php echo esc_url($href); ?>"><?php echo esc_html($index + 1); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="tfp-student-legend"><span><i class="is-complete"></i><?php esc_html_e('Complete', 'tfp-dashboard'); ?></span><span><i class="is-current"></i><?php esc_html_e('Current', 'tfp-dashboard'); ?></span><span><i class="is-upcoming"></i><?php esc_html_e('Upcoming', 'tfp-dashboard'); ?></span></div>
                    <div class="tfp-student-progress-line"><span><?php esc_html_e('Cohort avg completion', 'tfp-dashboard'); ?></span><strong><?php echo esc_html($cohort_average); ?>%</strong><div><i style="width:<?php echo esc_attr($cohort_average); ?>%"></i></div></div>
                </section>

                <section class="tfp-dash-panel tfp-student-panel tfp-student-performance">
                    <h2><?php esc_html_e('Current Performance Reports', 'tfp-dashboard'); ?></h2>
                    <div class="tfp-student-table-wrap">
                        <table class="tfp-student-performance-table">
                            <thead><tr><th>Week</th><th>Reading</th><th>Homework</th><th>Quiz</th><th>Tests</th><th>Attend.</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($weeks, 0, 7) as $index => $week):
                                $statuses = tfp_facilitator_student_step_statuses($student_id, $week->ID);
                                $week_href = add_query_arg(['tfp_student_id' => $student_id, 'tfp_student_week' => $week->ID], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'));
                                ?>
                                <tr>
                                    <td><a href="<?php echo esc_url($week_href); ?>"><?php printf(esc_html__('Week %d', 'tfp-dashboard'), $index + 1); ?></a></td>
                                    <?php foreach (['reading','homework','quiz','test','attendance'] as $type):
                                        if ($type === 'attendance') {
                                            $url = add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_week'=>$week->ID,'tfp_student_panel'=>'attendance'], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'));
                                        } elseif (in_array($type, ['homework','quiz','test'], true)) {
                                            $url = add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_week'=>$week->ID,'tfp_student_panel'=>$type], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'));
                                        } else {
                                            $url = $week_href;
                                        }
                                        $item = $statuses[$type];
                                        ?>
                                        <td><a href="<?php echo esc_url($url); ?>" class="tfp-student-pill-link"><?php tfp_facilitator_student_status_badge($item['label'],$item['tone']); ?></a></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <section class="tfp-dash-panel tfp-student-panel tfp-student-notes-panel">
                <div class="tfp-student-panel-head"><div><h2><?php esc_html_e('Program Notes', 'tfp-dashboard'); ?></h2><p><?php esc_html_e("Review all notes written throughout the student's journey.", 'tfp-dashboard'); ?></p></div></div>
                <div class="tfp-student-notes-scroll">
                    <?php $has_notes = false; foreach ($weeks as $index => $week): $notes = tfp_facilitator_student_notes($student_id, $week->ID); if ($notes): $has_notes = true; ?>
                        <div class="tfp-student-week-note">
                            <h3><?php printf(esc_html__('Week %d', 'tfp-dashboard'), $index + 1); ?></h3>
                            <?php foreach (array_slice($notes, 0, 2) as $note): ?>
                                <p><?php echo esc_html(wp_strip_all_tags($note['text'] ?? '')); ?></p>
                                <small><?php echo esc_html(!empty($note['created_at']) ? date_i18n('m/d/Y', strtotime($note['created_at'])) : ''); ?> <?php esc_html_e('by', 'tfp-dashboard'); ?> <?php echo esc_html(get_the_author_meta('display_name', (int)($note['author_id'] ?? 0))); ?></small>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; endforeach; ?>
                    <?php if (!$has_notes): ?><div class="tfp-student-empty-state"><?php esc_html_e('No notes have been added yet.', 'tfp-dashboard'); ?></div><?php endif; ?>
                </div>
            </section>
        </div>

        <section class="tfp-dash-panel tfp-student-panel tfp-student-recent">
            <div class="tfp-student-panel-head">
                <div><h2><?php esc_html_e('Recent Course Activity', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Use the dropdown above to view a specific week · Scroll for full history', 'tfp-dashboard'); ?></p></div>
                <a href="<?php echo esc_url(add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_activity'=>'all'], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'))); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
            </div>
            <div class="tfp-student-activity-list">
                <?php foreach (array_slice($activities, 0, 6) as $activity): ?>
                    <div class="tfp-student-activity-row">
                        <strong><?php echo esc_html($activity['type']); ?></strong>
                        <div><b><?php echo esc_html($activity['title']); ?></b><small><?php echo esc_html($activity['date'].' · '.$activity['time']); ?></small></div>
                        <?php tfp_facilitator_student_status_badge($activity['status'],$activity['tone']); ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$activities): ?><div class="tfp-student-empty-state"><?php esc_html_e('No course activity recorded yet.', 'tfp-dashboard'); ?></div><?php endif; ?>
            </div>
        </section>
    </section>
    <?php
}

function tfp_facilitator_student_render_week_view($student_id, $course_id, $cohort_id, $weeks, $week, $activities) {
    $week_index = array_search($week->ID, wp_list_pluck($weeks, 'ID'), true);
    $week_number = false === $week_index ? 1 : $week_index + 1;
    $status = tfp_facilitator_student_step_statuses($student_id, $week->ID);
    $progress = function_exists('tfp_ld_get_week_progress') ? tfp_ld_get_week_progress($student_id, $week->ID) : [];
    $notes = tfp_facilitator_student_notes($student_id, $week->ID);
    $week_activity = array_values(array_filter($activities, static function ($item) use ($week_number) { return (int) ($item['week'] ?? 0) === (int) $week_number; }));
    ?>
    <section class="tfp-student-week-view">
        <h2 class="tfp-student-week-title"><?php printf(esc_html__('Week %02d: %s', 'tfp-dashboard'), $week_number, esc_html($week->post_title)); ?></h2>
        <div class="tfp-student-week-grid">
            <div class="tfp-student-week-left">
                <?php
                $cards = [
                    ['key'=>'reading','label'=>'Reading','value'=>$status['reading']['value'],'url'=>add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_week'=>$week->ID]),'tone'=>$status['reading']['tone']],
                    ['key'=>'homework','label'=>'Homework','value'=>$status['homework']['value'],'url'=>add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_week'=>$week->ID,'tfp_student_panel'=>'homework']),'tone'=>$status['homework']['tone']],
                    ['key'=>'quiz','label'=>'Quiz','value'=>$status['quiz']['value'],'url'=>add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_week'=>$week->ID,'tfp_student_panel'=>'quiz']),'tone'=>$status['quiz']['tone']],
                    ['key'=>'test','label'=>'Test','value'=>$status['test']['value'],'url'=>add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_week'=>$week->ID,'tfp_student_panel'=>'test']),'tone'=>$status['test']['tone']],
                    ['key'=>'attendance','label'=>'Attendance','value'=>$status['attendance']['label'],'url'=>add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_week'=>$week->ID,'tfp_student_panel'=>'attendance']),'tone'=>$status['attendance']['tone']],
                ];
                foreach ($cards as $card): ?>
                    <a class="tfp-student-week-card" href="<?php echo esc_url($card['url']); ?>">
                        <span class="tfp-student-week-ring tfp-student-week-ring--<?php echo esc_attr($card['tone']); ?>"><?php echo esc_html($card['value']); ?><?php if (is_numeric($card['value']) && in_array($card['key'], ['reading','homework'], true)) echo '%'; ?></span>
                        <span><strong><?php echo esc_html($card['label']); ?></strong><small><?php echo !empty($progress[$card['key']]) ? esc_html__('Completed','tfp-dashboard') : esc_html__('Review this week','tfp-dashboard'); ?></small></span>
                        <?php tfp_facilitator_student_status_badge($status[$card['key']]['label'],$status[$card['key']]['tone']); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <section class="tfp-dash-panel tfp-student-panel tfp-student-week-notes">
                <h2><?php esc_html_e('Weekly Notes','tfp-dashboard'); ?></h2>
                <p><?php esc_html_e('Use this space to record your weekly notes, for this student.','tfp-dashboard'); ?></p>
                <div class="tfp-student-notes-scroll tfp-student-notes-scroll--week">
                    <?php if ($notes): foreach (array_slice($notes,0,4) as $note): ?>
                        <div class="tfp-student-note-entry"><div class="tfp-student-note-avatar"><?php echo get_avatar((int)($note['author_id'] ?? 0),42); ?></div><div><p><?php echo esc_html(wp_strip_all_tags($note['text'])); ?></p><small><?php echo esc_html(!empty($note['created_at']) ? date_i18n('m/d/Y',strtotime($note['created_at'])) : ''); ?></small></div></div>
                    <?php endforeach; else: ?><div class="tfp-student-empty-state"><?php esc_html_e('No notes have been added yet.','tfp-dashboard'); ?></div><?php endif; ?>
                </div>
                <form method="post" class="tfp-student-note-form">
                    <?php wp_nonce_field('tfp_student_detail','tfp_student_nonce'); ?>
                    <input type="hidden" name="tfp_student_action" value="save_note">
                    <input type="hidden" name="tfp_student_id" value="<?php echo esc_attr($student_id); ?>">
                    <input type="hidden" name="tfp_student_lesson_id" value="<?php echo esc_attr($week->ID); ?>">
                    <h3><?php esc_html_e('Add a New Note','tfp-dashboard'); ?></h3>
                    <textarea name="note_text" placeholder="<?php esc_attr_e('Type your answer here...', 'tfp-dashboard'); ?>"></textarea>
                    <button class="tfp-dash-btn tfp-dash-btn--primary" type="submit">▣ <?php esc_html_e('Save Note','tfp-dashboard'); ?></button>
                </form>
            </section>
        </div>

        <div class="tfp-student-metrics tfp-facilitator-metrics tfp-student-week-metrics">
            <article class="tfp-facilitator-metric tfp-facilitator-metric--teal"><span>Weekly Progress</span><strong><?php echo esc_html((int) round((count(array_filter($progress)) / 5) * 100)); ?>%</strong><small>Completed This Week</small></article>
            <article class="tfp-facilitator-metric tfp-facilitator-metric--teal"><span>Attendance</span><strong><?php echo esc_html($status['attendance']['label']); ?></strong><small>Across Entire Course</small></article>
            <article class="tfp-facilitator-metric tfp-facilitator-metric--gold"><span>Overall Grade</span><strong><?php $grade = function_exists('tfp_grade_overall') ? tfp_grade_overall($student_id,$course_id) : ''; echo esc_html($grade ?: '—'); ?></strong><small>Current Course Average</small></article>
            <article class="tfp-facilitator-metric tfp-facilitator-metric--red"><span>Awaiting Review</span><strong><?php echo esc_html(($status['homework']['tone']==='warning'?1:0)+($status['quiz']['tone']==='danger'?1:0)+($status['test']['tone']==='danger'?1:0)); ?></strong><small>Homework: <?php echo esc_html($status['homework']['tone']==='warning'?1:0); ?> · Quiz: <?php echo esc_html($status['quiz']['tone']==='danger'?1:0); ?> · Test: <?php echo esc_html($status['test']['tone']==='danger'?1:0); ?></small></article>
        </div>

        <section class="tfp-dash-panel tfp-student-panel tfp-student-recent">
            <div class="tfp-student-panel-head"><div><h2>Recent Course Activity</h2><p>Use the dropdown above to view a specific week · Scroll for full history</p></div><a href="<?php echo esc_url(add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_activity'=>'all'], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'))); ?>">View All</a></div>
            <div class="tfp-student-activity-list">
                <?php foreach (array_slice($week_activity,0,6) as $item): ?><div class="tfp-student-activity-row"><strong><?php echo esc_html($item['type']); ?></strong><div><b><?php echo esc_html($item['title']); ?></b><small><?php echo esc_html($item['date'].' · '.$item['time']); ?></small></div><?php tfp_facilitator_student_status_badge($item['status'],$item['tone']); ?></div><?php endforeach; ?>
                <?php if (!$week_activity): ?><div class="tfp-student-empty-state">No activity recorded for this week yet.</div><?php endif; ?>
            </div>
        </section>
    </section>
    <?php
}

function tfp_facilitator_student_render_activity_overlay($student_id, $activities) {
    $close = tfp_facilitator_student_close_url();
    ?>
    <div class="tfp-student-overlay">
        <div class="tfp-student-overlay-card tfp-student-overlay-card--activity">
            <div class="tfp-student-overlay-head">
                <div><h2>All Course Activity</h2><p>Scroll for full history of all Students Activities</p></div>
                <a href="<?php echo esc_url($close); ?>" class="tfp-dash-btn tfp-dash-btn--primary">× Close</a>
            </div>
            <div class="tfp-student-activity-scroll">
                <?php foreach ($activities as $item): ?>
                    <div class="tfp-student-activity-row tfp-student-activity-row--large"><strong><?php echo esc_html($item['type']); ?></strong><div><b><?php echo esc_html($item['title']); ?></b><small><?php echo esc_html($item['date'].' · '.$item['time']); ?></small></div><?php tfp_facilitator_student_status_badge($item['status'],$item['tone']); ?></div>
                <?php endforeach; ?>
                <?php if (!$activities): ?><div class="tfp-student-empty-state">No course activity recorded yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function tfp_facilitator_student_render_panel_overlay($student_id, $week, $panel) {
    $close = tfp_facilitator_student_close_url(['tfp_student_week' => $week->ID]);
    $notes = tfp_facilitator_student_notes($student_id, $week->ID);
    $attendance = tfp_facilitator_student_attendance($student_id, $week->ID);
    $edit_attendance = isset($_GET['tfp_student_attendance_edit']) && absint($_GET['tfp_student_attendance_edit']) === 1;
    ?>
    <div class="tfp-student-overlay">
        <div class="tfp-student-overlay-card tfp-student-overlay-card--panel">
            <div class="tfp-student-overlay-head">
                <div><h2><?php echo esc_html($panel === 'attendance' ? 'Weekly Attendance' : ($panel === 'notes' ? 'Weekly Notes' : ucfirst($panel) . ' Review')); ?></h2><p><?php echo esc_html($panel === 'attendance' ? 'Use this area to record this students weekly attendance, for the selected week.' : ($panel === 'notes' ? 'Use this space to record your weekly notes, for this student.' : 'Review the student’s submitted work for this week.')); ?></p></div>
                <a href="<?php echo esc_url($close); ?>" class="tfp-dash-btn tfp-dash-btn--primary">× Close</a>
            </div>

            <?php if ($panel === 'attendance'): ?>
                <div class="tfp-student-review-body">
                    <small class="tfp-student-muted-label">Weekly Attendance</small>
                    <?php if ($attendance['status'] && !$edit_attendance): ?>
                        <div class="tfp-student-submitted"><div class="tfp-student-submitted-icon">◯</div><div><h3>Attendance Submitted</h3><p><?php echo esc_html($attendance['status'] === 'P' ? 'Present' : ($attendance['status'] === 'EA' ? 'Excused Absence' : 'Unexcused Absence')); ?></p></div></div>
                        <h3>Saved Note</h3><p class="tfp-student-note-text"><?php echo esc_html($attendance['note'] ?: '—'); ?></p>
                        <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url(add_query_arg(['tfp_student_id'=>$student_id,'tfp_student_week'=>$week->ID,'tfp_student_panel'=>'attendance','tfp_student_attendance_edit'=>1], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'))); ?>">Edit Attendance</a>
                    <?php else: ?>
                        <form method="post" class="tfp-student-form-panel">
                            <?php wp_nonce_field('tfp_student_detail','tfp_student_nonce'); ?>
                            <input type="hidden" name="tfp_student_action" value="save_attendance">
                            <input type="hidden" name="tfp_student_id" value="<?php echo esc_attr($student_id); ?>">
                            <input type="hidden" name="tfp_student_lesson_id" value="<?php echo esc_attr($week->ID); ?>">
                            <label><input type="radio" name="attendance_status" value="P" <?php checked($attendance['status'],'P'); ?>> Present</label>
                            <label><input type="radio" name="attendance_status" value="EA" <?php checked($attendance['status'],'EA'); ?>> Excused Absence</label>
                            <label><input type="radio" name="attendance_status" value="UA" <?php checked($attendance['status'],'UA'); ?>> Unexcused Absence</label>
                            <h3>Optional Notes</h3>
                            <textarea name="attendance_note" placeholder="Type your answer here..."><?php echo esc_textarea($attendance['note']); ?></textarea>
                            <button class="tfp-dash-btn tfp-dash-btn--primary" type="submit">Save Attendance</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php elseif ($panel === 'notes'): ?>
                <div class="tfp-student-notes-scroll tfp-student-notes-scroll--modal">
                    <?php if ($notes): foreach ($notes as $note): ?>
                        <div class="tfp-student-note-entry"><div class="tfp-student-note-avatar"><?php echo get_avatar((int)($note['author_id'] ?? 0),42); ?></div><div><p><?php echo esc_html(wp_strip_all_tags($note['text'])); ?></p><small><?php echo esc_html(!empty($note['created_at']) ? date_i18n('m/d/Y',strtotime($note['created_at'])) : ''); ?></small></div></div>
                    <?php endforeach; else: ?><div class="tfp-student-empty-state">No notes yet.</div><?php endif; ?>
                </div>
                <form method="post" class="tfp-student-note-form tfp-student-note-form--modal">
                    <?php wp_nonce_field('tfp_student_detail','tfp_student_nonce'); ?>
                    <input type="hidden" name="tfp_student_action" value="save_note">
                    <input type="hidden" name="tfp_student_id" value="<?php echo esc_attr($student_id); ?>">
                    <input type="hidden" name="tfp_student_lesson_id" value="<?php echo esc_attr($week->ID); ?>">
                    <h3>Add a New Note</h3>
                    <textarea name="note_text" placeholder="Type your answer here..."></textarea>
                    <button class="tfp-dash-btn tfp-dash-btn--primary" type="submit">▣ Save Note</button>
                </form>
            <?php elseif (in_array($panel,['homework','quiz','test'],true)): ?>
                <?php tfp_facilitator_student_render_review_body($student_id,$week,$panel); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function tfp_facilitator_student_render_review_body($student_id, $week, $panel) {
    if ($panel === 'homework') {
        $questions = function_exists('tfp_week_get_homework_questions') ? tfp_week_get_homework_questions($week->ID, false) : [];
        $answers = function_exists('tfp_week_get_homework_answers') ? tfp_week_get_homework_answers($student_id, $week->ID) : [];
    } elseif ($panel === 'quiz') {
        $result = function_exists('tfp_week_get_quiz_result') ? tfp_week_get_quiz_result($student_id,$week->ID) : null;
        $questions = $result['results'] ?? [];
        $answers = [];
    } else {
        $result = function_exists('tfp_week_get_test_result') ? tfp_week_get_test_result($student_id,$week->ID) : null;
        $questions = $result['results'] ?? [];
        $answers = [];
    }
    $reviewed = tfp_facilitator_student_reviewed($panel,$week->ID);
    ?>
    <div class="tfp-student-review-body tfp-student-review-body--questions">
        <div class="tfp-student-review-scroll">
            <?php if ($questions): foreach($questions as $index=>$q):
                $answer = isset($answers[$q['id']]) ? $answers[$q['id']] : [];
                $selected = isset($answer['selected_index']) ? (int)$answer['selected_index'] : (isset($q['selected_index']) ? (int)$q['selected_index'] : -1);
                $options = isset($q['options']) && is_array($q['options']) ? $q['options'] : [];
                $correct = isset($q['correct_index']) ? (int)$q['correct_index'] : -1;
                $is_correct = isset($q['is_correct']) ? (bool)$q['is_correct'] : ($selected >= 0 && $correct >= 0 && $selected === $correct);
                ?>
                <article class="tfp-student-review-question"><span class="tfp-student-review-number"><?php echo esc_html($index+1); ?></span><div><strong>Q<?php echo esc_html($index+1); ?>. <?php echo esc_html($q['prompt'] ?? ''); ?></strong>
                    <?php if($options): ?><p class="tfp-student-answer-line">Your Answer: <?php echo esc_html($selected >= 0 && isset($options[$selected]) ? $options[$selected] : '—'); ?><?php if($panel!=='homework'): ?> <em class="<?php echo $is_correct?'is-correct':'is-incorrect'; ?>"><?php echo $is_correct?'Correct':'Incorrect'; ?></em><?php endif; ?></p><?php endif; ?>
                    <?php if($panel!=='homework' && !$is_correct && $correct>=0): ?><p class="tfp-student-correct-answer">Correct Answer: <?php echo esc_html(isset($options[$correct]) ? $options[$correct] : ''); ?></p><?php endif; ?>
                    <?php if(!empty($q['explanation'])): ?><h3>Explanation:</h3><p><?php echo esc_html($q['explanation']); ?></p><?php endif; ?>
                </div></article>
            <?php endforeach; else: ?><div class="tfp-student-empty-state">No submitted review data is available for this week.</div><?php endif; ?>
        </div>
        <?php if ($questions): ?>
            <form method="post" class="tfp-student-review-complete-form">
                <?php wp_nonce_field('tfp_student_detail','tfp_student_nonce'); ?>
                <input type="hidden" name="tfp_student_action" value="complete_review">
                <input type="hidden" name="tfp_student_id" value="<?php echo esc_attr($student_id); ?>">
                <input type="hidden" name="tfp_student_lesson_id" value="<?php echo esc_attr($week->ID); ?>">
                <input type="hidden" name="review_type" value="<?php echo esc_attr($panel); ?>">
                <button class="tfp-dash-btn tfp-dash-btn--primary" type="submit">✓ <?php echo esc_html($reviewed ? ucfirst($panel) . ' Review Complete' : ucfirst($panel) . ' Review Complete'); ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
