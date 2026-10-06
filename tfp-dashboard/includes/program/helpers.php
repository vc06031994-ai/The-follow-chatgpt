<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Program pacing helpers — weekly due-dates + overdue detection.
 *
 * A cohort's `_start_date` anchors the schedule: the week at 0-based index $i
 * is "due" at the end of week ($i + 1) — i.e. start + ($i + 1) * 7 days. A week
 * is OVERDUE when its due date has passed AND the student hasn't submitted its
 * homework. Weeks with no homework configured are never overdue (there is
 * nothing to submit, so they'd otherwise be flagged forever).
 *
 * When the enrolled cohort has no start date, pacing disables gracefully: no
 * due dates, no overdue weeks, and the calendar "current week" falls back to
 * the completion-based current week used elsewhere.
 */

/**
 * The enrolled cohort's start timestamp, or 0 if unavailable.
 */
function tfp_program_start_ts($state)
{
    if (!empty($state['cohort']) && !empty($state['cohort']['start_date'])) {
        $ts = strtotime($state['cohort']['start_date']);
        if ($ts) {
            return $ts;
        }
    }

    if (!empty($state['cohort_id'])) {
        $cohort_post = get_post($state['cohort_id']);
        if ($cohort_post && !empty($cohort_post->post_date)) {
            $ts = strtotime($cohort_post->post_date);
            if ($ts) {
                return $ts;
            }
        }
    }

    return 0;
}

/**
 * Due timestamp for the week at the given 0-based index: the end of that week
 * relative to the cohort start (start + ($index + 1) weeks). 0 when no start.
 */
function tfp_program_week_due_ts($start_ts, $index)
{
    if ($start_ts <= 0) {
        return 0;
    }
    return $start_ts + (($index + 1) * WEEK_IN_SECONDS);
}

/**
 * The 1-based calendar week the cohort is currently in, clamped to [1, total].
 * Returns 0 when there's no start date or no weeks (caller falls back).
 */
function tfp_program_calendar_week($start_ts, $total)
{
    if ($start_ts <= 0 || $total <= 0) {
        return 0;
    }
    $now = current_time('timestamp');
    $week = (int) floor(($now - $start_ts) / WEEK_IN_SECONDS) + 1;
    return max(1, min($total, $week));
}

/**
 * Weeks whose homework or requirements are overdue: due date passed AND not completed.
 * Kept in course order (ascending due date — most overdue first).
 *
 * @return array[] each: ['week' => WP_Post, 'index' => int, 'due_ts' => int]
 */
function tfp_program_overdue_weeks($user_id, $course_id, $state)
{
    if (!$course_id || !function_exists('tfp_ld_get_weeks')) {
        return [];
    }

    $weeks = tfp_ld_get_weeks($course_id);
    if (empty($weeks)) {
        return [];
    }

    $start_ts = tfp_program_start_ts($state);
    $now = current_time('timestamp');
    $overdue = [];

    $calendar_week = ($start_ts > 0) ? tfp_program_calendar_week($start_ts, count($weeks)) : 0;

    foreach ($weeks as $index => $week) {
        $due_ts = $start_ts > 0 ? tfp_program_week_due_ts($start_ts, $index) : 0;

        $is_past = false;
        if ($due_ts > 0 && $due_ts < $now) {
            $is_past = true;
        } elseif ($calendar_week > 0 && ($index + 1) < $calendar_week) {
            $is_past = true;
            if ($due_ts <= 0) {
                $due_ts = $now - (($calendar_week - ($index + 1)) * WEEK_IN_SECONDS);
            }
        }

        if (!$is_past) {
            continue; // Not due yet
        }

        // Check overall week completion
        $is_week_complete = function_exists('tfp_ld_is_week_complete')
            ? tfp_ld_is_week_complete($user_id, $week->ID)
            : false;

        if ($is_week_complete) {
            continue; // Already complete
        }

        // Check homework progress specifically
        $progress = function_exists('tfp_ld_get_week_progress')
            ? tfp_ld_get_week_progress($user_id, $week->ID)
            : [];

        if (!empty($progress['homework'])) {
            continue; // Homework already submitted
        }

        if ($due_ts <= 0) {
            $due_ts = $now - WEEK_IN_SECONDS;
        }

        $overdue[] = [
            'week' => $week,
            'index' => $index,
            'due_ts' => $due_ts,
        ];
    }

    return $overdue;
}

/**
 * Gather all pending student tasks for the current program timeline.
 * Returns an array of task items:
 * [
 *     'id'         => string,
 *     'title'      => string,
 *     'meta'       => string,
 *     'btn_text'   => string,
 *     'btn_type'   => 'teal' | 'gray',
 *     'url'        => string,
 *     'is_overdue' => bool,
 *     'type'       => string,
 * ]
 */
function tfp_program_get_pending_tasks($user_id, $course_id, $state)
{
    $tasks = [];
    $week_url = function_exists('tfp_dashboard_get_url') ? tfp_dashboard_get_url('tfp-dashboard-week') : '#';
    $cohort_name = !empty($state['cohort']['name']) ? $state['cohort']['name'] : '';

    if (!$course_id || !function_exists('tfp_ld_get_weeks')) {
        return $tasks;
    }

    $weeks = tfp_ld_get_weeks($course_id);
    if (empty($weeks)) {
        return $tasks;
    }

    $overdue_weeks = tfp_program_overdue_weeks($user_id, $course_id, $state);

    // 1. Overdue Homework Tasks
    foreach ($overdue_weeks as $item) {
        $w = $item['week'];
        $idx = (int) $item['index'];
        $due = date_i18n(get_option('date_format'), $item['due_ts']);
        $url = add_query_arg(['lesson_id' => $w->ID, 'tab' => 'homework'], $week_url);

        $tasks[] = [
            'id' => 'overdue_hw_' . $w->ID,
            'title' => sprintf(__('Overdue, Week %d Homework', 'tfp-dashboard'), $idx + 1),
            'meta' => $cohort_name ? sprintf(__('%s · Due %s', 'tfp-dashboard'), $cohort_name, $due) : sprintf(__('Due %s', 'tfp-dashboard'), $due),
            'btn_text' => __('Review', 'tfp-dashboard'),
            'btn_type' => 'teal',
            'url' => $url,
            'is_overdue' => true,
            'type' => 'overdue',
        ];
    }

    // Determine current week
    $start_ts = tfp_program_start_ts($state);
    $total_weeks = count($weeks);
    $current_week_num = function_exists('tfp_program_calendar_week') ? tfp_program_calendar_week($start_ts, $total_weeks) : 0;
    if ($current_week_num <= 0) {
        $cw = function_exists('tfp_ld_get_current_week') ? tfp_ld_get_current_week($user_id, $course_id) : null;
        if ($cw) {
            foreach ($weeks as $i => $w) {
                if ($w->ID === $cw->ID) {
                    $current_week_num = $i + 1;
                    break;
                }
            }
        }
        if ($current_week_num <= 0) {
            $current_week_num = 1;
        }
    }

    $max_check_week = min($total_weeks, max(1, $current_week_num));

    // 2. Check previous and current weeks for failed tests, quizzes, attendance, notes
    for ($i = 0; $i < $max_check_week; $i++) {
        $week = $weeks[$i];
        $week_num = $i + 1;

        // Failed Tests
        if (function_exists('tfp_week_get_test_result')) {
            $test_result = tfp_week_get_test_result($user_id, $week->ID);
            if ($test_result && empty($test_result['passed'])) {
                $tasks[] = [
                    'id' => 'failed_test_' . $week->ID,
                    'title' => sprintf(__('Failed – Test – Week %d', 'tfp-dashboard'), $week_num),
                    'meta' => !empty($state['cohort']['facilitator']) ? sprintf(__('Facilitator: %s', 'tfp-dashboard'), $state['cohort']['facilitator']) : ($cohort_name ?: $week->post_title),
                    'btn_text' => __('Action', 'tfp-dashboard'),
                    'btn_type' => 'teal',
                    'url' => add_query_arg(['lesson_id' => $week->ID, 'tab' => 'test'], $week_url),
                    'is_overdue' => true,
                    'type' => 'test',
                ];
            }
        }

        // Quizzes needing retake
        if (function_exists('tfp_week_get_quiz_result')) {
            $quiz_result = tfp_week_get_quiz_result($user_id, $week->ID);
            if ($quiz_result && empty($quiz_result['passed'])) {
                $tasks[] = [
                    'id' => 'quiz_retake_' . $week->ID,
                    'title' => sprintf(__('Unlock retake – Quiz – Week %d', 'tfp-dashboard'), $week_num),
                    'meta' => $cohort_name ?: $week->post_title,
                    'btn_text' => __('Update', 'tfp-dashboard'),
                    'btn_type' => 'gray',
                    'url' => add_query_arg(['lesson_id' => $week->ID, 'tab' => 'quiz'], $week_url),
                    'is_overdue' => false,
                    'type' => 'quiz',
                ];
            }
        }
    }

    // 3. Current-week student work: Reading + Homework.
    // These are student tasks that should be completed before the next
    // weekly meeting. Facilitator-owned attendance/meeting-note tasks are
    // intentionally excluded from this list.
    if ($current_week_num > 0 && $current_week_num <= $total_weeks) {
        $cur_week = $weeks[$current_week_num - 1];
        $cur_progress = function_exists('tfp_ld_get_week_progress') ? tfp_ld_get_week_progress($user_id, $cur_week->ID) : [];

        // Reading: show the task until every reading assigned to this week
        // has been completed by the student.
        $readings = get_posts([
            'post_type'      => 'tfp_reading',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_key'       => '_tfp_lesson_id',
            'meta_value'     => $cur_week->ID,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);

        if (!empty($readings)) {
            $completed_readings = get_user_meta($user_id, 'tfp_reading_progress_' . $cur_week->ID, true);
            $completed_readings = is_array($completed_readings) ? $completed_readings : [];
            $reading_ids = wp_list_pluck($readings, 'ID');
            $completed_readings = array_intersect($reading_ids, $completed_readings);

            if (count($completed_readings) < count($reading_ids)) {
                $tasks[] = [
                    'id' => 'current_reading_' . $cur_week->ID,
                    'title' => sprintf(__('Week %d Reading', 'tfp-dashboard'), $current_week_num),
                    'meta' => $cohort_name ?: $cur_week->post_title,
                    'btn_text' => __('Review', 'tfp-dashboard'),
                    'btn_type' => 'teal',
                    'url' => add_query_arg(['lesson_id' => $cur_week->ID, 'tab' => 'reading'], $week_url),
                    'is_overdue' => false,
                    'type' => 'reading',
                ];
            }
        }

        // Homework: show until the current week's homework has been submitted.
        if (empty($cur_progress['homework'])) {
            $tasks[] = [
                'id' => 'current_hw_' . $cur_week->ID,
                'title' => sprintf(__('Week %d Homework', 'tfp-dashboard'), $current_week_num),
                'meta' => $cohort_name ? sprintf(__('%s · %s', 'tfp-dashboard'), $cohort_name, $cur_week->post_title) : $cur_week->post_title,
                'btn_text' => __('Complete', 'tfp-dashboard'),
                'btn_type' => 'teal',
                'url' => add_query_arg(['lesson_id' => $cur_week->ID, 'tab' => 'homework'], $week_url),
                'is_overdue' => false,
                'type' => 'homework',
            ];
        }
    }

    return apply_filters('tfp_program_pending_tasks', $tasks, $user_id, $course_id, $state);
}
