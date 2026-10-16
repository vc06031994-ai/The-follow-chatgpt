<?php
if (!defined('ABSPATH')) exit;

/**
 * Facilitator Attendance Dashboard.
 *
 * The page keeps the existing Figma-oriented markup, but all report values are
 * derived from the real facilitator attendance records saved against students.
 */

function tfp_facilitator_attendance_get_courses($user_id = 0)
{
    $user_id = $user_id ?: get_current_user_id();

    $cohorts = function_exists('tfp_facilitator_current_cohorts')
        ? tfp_facilitator_current_cohorts($user_id)
        : [];

    $course_ids = [];
    foreach ((array) $cohorts as $cohort) {
        $course_id = (int) get_post_meta($cohort->ID, '_related_course', true);
        if ($course_id) {
            $course_ids[$course_id] = true;
        }
    }

    if (!$course_ids) {
        return [];
    }

    $courses = get_posts([
        'post_type'      => 'sfwd-courses',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'post__in'       => array_keys($course_ids),
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    return is_array($courses) ? $courses : [];
}

function tfp_facilitator_attendance_get_cohorts($user_id = 0, $selected_course_id = 0)
{
    $cohorts = function_exists('tfp_facilitator_current_cohorts')
        ? tfp_facilitator_current_cohorts($user_id)
        : get_posts([
            'post_type'      => 'tfp_cohort',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

    if ($selected_course_id > 0) {
        $cohorts = array_filter($cohorts, static function ($cohort) use ($selected_course_id) {
            return (int) get_post_meta($cohort->ID, '_related_course', true) === (int) $selected_course_id;
        });
    }

    return array_values($cohorts);
}

function tfp_facilitator_attendance_get_weeks($course_id)
{
    $course_id = absint($course_id);

    if (!$course_id) {
        return [];
    }

    $weeks = function_exists('tfp_ld_get_weeks') ? tfp_ld_get_weeks($course_id) : [];

    if (!$weeks && function_exists('learndash_course_get_steps_by_type')) {
        $ids = learndash_course_get_steps_by_type($course_id, 'sfwd-lessons');
        $weeks = [];
        foreach ((array) $ids as $id) {
            $post = get_post($id);
            if ($post instanceof WP_Post) {
                $weeks[] = $post;
            }
        }
    }

    if (!$weeks) {
        $weeks = get_posts([
            'post_type'      => 'sfwd-lessons',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => 'course_id',
            'meta_value'     => $course_id,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ]);
    }

    $normalised = [];
    foreach ((array) $weeks as $week) {
        if (is_numeric($week)) {
            $week = get_post((int) $week);
        }

        if ($week instanceof WP_Post && $week->post_type === 'sfwd-lessons') {
            $normalised[] = $week;
        }
    }

    return $normalised;
}

function tfp_facilitator_attendance_get_record($student_id, $lesson_id)
{
    if (function_exists('tfp_facilitator_student_attendance')) {
        return tfp_facilitator_student_attendance($student_id, $lesson_id);
    }

    $key = 'tfp_facilitator_student_attendance_' . absint($lesson_id);
    $value = get_user_meta((int) $student_id, $key, true);

    return is_array($value) ? wp_parse_args($value, [
        'status'     => '',
        'note'       => '',
        'updated_at' => '',
        'author_id'  => 0,
    ]) : [
        'status'     => '',
        'note'       => '',
        'updated_at' => '',
        'author_id'  => 0,
    ];
}

/**
 * Build the real attendance report once. The returned logs are grouped by
 * cohort + week, while the student stats are used for health metrics.
 */
function tfp_facilitator_attendance_report($cohorts)
{
    static $cache = [];

    $cohort_ids = array_map(static function ($cohort) {
        return (int) $cohort->ID;
    }, (array) $cohorts);

    sort($cohort_ids);
    $cache_key = implode(',', $cohort_ids);

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $logs = [];
    $student_stats = [];
    $weeks_by_cohort = [];
    $week_ids_by_cohort = [];

    foreach ((array) $cohorts as $cohort) {
        $cohort_id = (int) $cohort->ID;
        $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
        $weeks_by_cohort[$cohort_id] = tfp_facilitator_attendance_get_weeks($course_id);
        $week_ids_by_cohort[$cohort_id] = array_fill_keys(
            wp_list_pluck($weeks_by_cohort[$cohort_id], 'ID'),
            true
        );

        $student_ids = function_exists('tfp_facilitator_cohort_students')
            ? tfp_facilitator_cohort_students($cohort_id)
            : [];

        foreach ($student_ids as $student_id) {
            $student_id = (int) $student_id;
            $student_stats[$student_id] = [
                'cohort_id' => $cohort_id,
                'present'   => 0,
                'recorded'  => 0,
            ];

            $user_meta = get_user_meta($student_id);

            foreach ($user_meta as $meta_key => $meta_values) {
                if (!preg_match('/^tfp_facilitator_student_attendance_(\d+)$/', (string) $meta_key, $matches)) {
                    continue;
                }

                $lesson_id = (int) $matches[1];
                $record = isset($meta_values[0]) ? maybe_unserialize($meta_values[0]) : [];

                if (!is_array($record) || empty($record['status'])) {
                    continue;
                }

                if (
                    empty($week_ids_by_cohort[$cohort_id])
                    || empty($week_ids_by_cohort[$cohort_id][$lesson_id])
                ) {
                    continue;
                }

                if (function_exists('learndash_get_course_id')) {
                    $lesson_course_id = (int) learndash_get_course_id($lesson_id);
                    if ($course_id && $lesson_course_id && $lesson_course_id !== $course_id) {
                        continue;
                    }
                }

                $status = strtoupper((string) $record['status']);
                if (!in_array($status, ['P', 'EA', 'UA'], true)) {
                    continue;
                }

                $student_stats[$student_id]['recorded']++;
                if ($status === 'P') {
                    $student_stats[$student_id]['present']++;
                }

                $lesson = get_post($lesson_id);
                $week_title = $lesson instanceof WP_Post ? $lesson->post_title : __('Attendance', 'tfp-dashboard');
                $group_key = $cohort_id . ':' . $lesson_id;

                if (!isset($logs[$group_key])) {
                    $logs[$group_key] = [
                        'cohort_name'     => $cohort->post_title,
                        'cohort_id'       => $cohort_id,
                        'course_id'       => $course_id,
                        'course'          => $course_id ? get_the_title($course_id) : __('Course', 'tfp-dashboard'),
                        'section_module'  => $week_title,
                        'lesson_id'       => $lesson_id,
                        'student_ids'     => [],
                        'student_total'   => 0,
                        'present'         => 0,
                        'excused'         => 0,
                        'unexcused'       => 0,
                        'latest_timestamp'=> 0,
                    ];
                }

                $logs[$group_key]['student_ids'][$student_id] = true;
                $logs[$group_key]['student_total'] = max(
                    (int) $logs[$group_key]['student_total'],
                    count((array) $student_ids)
                );

                if ($status === 'P') {
                    $logs[$group_key]['present']++;
                } elseif ($status === 'EA') {
                    $logs[$group_key]['excused']++;
                } else {
                    $logs[$group_key]['unexcused']++;
                }

                $timestamp = !empty($record['updated_at']) ? strtotime($record['updated_at']) : 0;
                if ($timestamp && $timestamp > $logs[$group_key]['latest_timestamp']) {
                    $logs[$group_key]['latest_timestamp'] = $timestamp;
                }
            }
        }
    }

    $log_rows = array_values($logs);

    foreach ($log_rows as &$log) {
        $total = $log['present'] + $log['excused'] + $log['unexcused'];
        $log['total'] = $total;
        $log['meeting_date'] = $log['latest_timestamp']
            ? wp_date('M j, Y, g:i A T', $log['latest_timestamp'])
            : '—';
        $log['breakdown'] = sprintf(
            '%d PRE • %d EA • %d UA',
            $log['present'],
            $log['excused'],
            $log['unexcused']
        );
        $log['action_url'] = '';
    }
    unset($log);

    usort($log_rows, static function ($a, $b) {
        return ($b['latest_timestamp'] ?? 0) <=> ($a['latest_timestamp'] ?? 0);
    });

    $course_ids = [];
    $recorded_present = 0;
    $recorded_total = 0;

    foreach ($log_rows as $log) {
        $course_ids[(int) $log['course_id']] = true;
        $recorded_present += (int) $log['present'];
        $recorded_total += (int) $log['total'];
    }

    $stalled_count = 0;
    $blocked_count = 0;

    foreach ($student_stats as $stats) {
        $recorded = (int) $stats['recorded'];
        if ($recorded < 2) {
            continue;
        }

        $rate = ((int) $stats['present'] / $recorded) * 100;
        if ((int) $stats['present'] === 0) {
            $blocked_count++;
        } elseif ($rate < 70) {
            $stalled_count++;
        }
    }

    $cache[$cache_key] = [
        'logs' => $log_rows,
        'students' => count($student_stats),
        'courses' => count(array_filter(array_keys($course_ids))),
        'avg_attendance' => $recorded_total > 0
            ? round(($recorded_present / $recorded_total) * 100)
            : 0,
        'stalled' => $stalled_count,
        'blocked' => $blocked_count,
        'student_stats' => $student_stats,
        'weeks_by_cohort' => $weeks_by_cohort,
    ];

    return $cache[$cache_key];
}

function tfp_facilitator_attendance_cohort_health($cohorts, $report)
{
    $rows = [];

    foreach ((array) $cohorts as $cohort) {
        $cohort_id = (int) $cohort->ID;
        $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
        $weeks = $report['weeks_by_cohort'][$cohort_id] ?? [];
        $student_ids = function_exists('tfp_facilitator_cohort_students')
            ? array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))
            : [];

        $present = 0;
        $recorded = 0;
        $latest_timestamp = 0;
        $latest_log = null;

        foreach ((array) ($report['logs'] ?? []) as $log) {
            if ((int) $log['cohort_id'] !== $cohort_id) {
                continue;
            }

            $present += (int) ($log['present'] ?? 0);
            $recorded += (int) ($log['total'] ?? 0);

            if (($log['latest_timestamp'] ?? 0) > $latest_timestamp) {
                $latest_timestamp = (int) $log['latest_timestamp'];
                $latest_log = $log;
            }
        }

        $stalled = 0;
        $blocked = 0;
        foreach ((array) ($report['student_stats'] ?? []) as $student_id => $stats) {
            if ((int) ($stats['cohort_id'] ?? 0) !== $cohort_id) {
                continue;
            }

            $student_recorded = (int) ($stats['recorded'] ?? 0);
            if ($student_recorded < 2) {
                continue;
            }

            $student_present = (int) ($stats['present'] ?? 0);
            if ($student_present === 0) {
                $blocked++;
            } elseif (($student_present / $student_recorded) * 100 < 70) {
                $stalled++;
            }
        }

        $avg = $recorded > 0 ? round(($present / $recorded) * 100) : 0;
        $current_week = $course_id && function_exists('tfp_facilitator_cohort_week')
            ? max(1, (int) tfp_facilitator_cohort_week($cohort_id, $course_id))
            : 1;
        $current_index = min(max(0, $current_week - 1), max(0, count($weeks) - 1));
        $current_module = !empty($weeks[$current_index])
            ? $weeks[$current_index]->post_title
            : __('Not started', 'tfp-dashboard');

        $rows[] = [
            'cohort_id'      => $cohort_id,
            'cohort_name'    => $cohort->post_title,
            'student_count'  => count($student_ids),
            'avg'            => $avg,
            'stalled'        => $stalled,
            'blocked'        => $blocked,
            'flags'          => $stalled + $blocked,
            'current_module' => $current_module,
            'latest_log'     => $latest_log,
            'latest_date'    => $latest_timestamp
                ? wp_date('M j, Y, g:i A T', $latest_timestamp)
                : '—',
        ];
    }

    usort($rows, static function ($a, $b) {
        return [$b['flags'], $a['cohort_name']] <=> [$a['flags'], $b['cohort_name']];
    });

    return $rows;
}

function tfp_facilitator_attendance_upcoming_submissions($cohorts, $report)
{
    $rows = [];

    foreach ((array) $cohorts as $cohort) {
        $cohort_id = (int) $cohort->ID;
        $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
        $weeks = isset($report['weeks_by_cohort'][$cohort_id]) ? $report['weeks_by_cohort'][$cohort_id] : [];

        if (!$weeks) {
            continue;
        }

        $student_ids = function_exists('tfp_facilitator_cohort_students')
            ? tfp_facilitator_cohort_students($cohort_id)
            : [];

        $student_count = count($student_ids);
        if ($student_count < 1) {
            continue;
        }

        $current_week = function_exists('tfp_facilitator_cohort_week')
            ? max(1, (int) tfp_facilitator_cohort_week($cohort_id, $course_id))
            : 1;

        $candidate_indexes = range(min($current_week - 1, count($weeks) - 1), count($weeks) - 1);

        foreach ($candidate_indexes as $index) {
            $lesson = $weeks[$index];
            $key = $cohort_id . ':' . (int) $lesson->ID;
            $submitted = isset($report['logs']) ? array_values(array_filter($report['logs'], static function ($log) use ($key, $cohort_id, $lesson) {
                return (int) $log['cohort_id'] === $cohort_id && (int) $log['lesson_id'] === (int) $lesson->ID;
            })) : [];

            $submitted_count = !empty($submitted) ? count($submitted[0]['student_ids']) : 0;

            if ($submitted_count >= $student_count) {
                continue;
            }

            $start = get_post_meta($cohort_id, '_start_date', true);
            $meeting_timestamp = $start && strtotime($start)
                ? strtotime($start) + ($index * WEEK_IN_SECONDS)
                : 0;

            $meeting_date = $meeting_timestamp
                ? wp_date('M j, Y, g:i A T', $meeting_timestamp)
                : __('Date to be scheduled', 'tfp-dashboard');

            $rows[] = [
                'course' => $course_id ? get_the_title($course_id) : __('Course', 'tfp-dashboard'),
                'cohort' => $cohort->post_title,
                'section_module' => $lesson->post_title,
                'meeting_date' => $meeting_date,
                'action_url' => '',
            ];

            break;
        }
    }

    usort($rows, static function ($a, $b) {
        return strcmp((string) $a['meeting_date'], (string) $b['meeting_date']);
    });

    return $rows;
}

function tfp_facilitator_attendance_format_breakdown($log)
{
    $parts = [];

    if ((int) $log['present'] > 0) {
        $parts[] = (int) $log['present'] . ' PRE';
    }
    if ((int) $log['excused'] > 0) {
        $parts[] = (int) $log['excused'] . ' EA';
    }
    if ((int) $log['unexcused'] > 0) {
        $parts[] = (int) $log['unexcused'] . ' UA';
    }

    return $parts ? implode(' • ', $parts) : '—';
}

/**
 * Handle CSV export using the same live report data shown on the page.
 */
add_action('init', function () {
    if (($_GET['tfp_export'] ?? '') !== 'attendance_csv' || !is_user_logged_in()) {
        return;
    }

    if (!function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        return;
    }

    $current_user_id = get_current_user_id();
    $course_id = absint($_GET['course_id'] ?? 0);
    $cohort_id = absint($_GET['cohort_id'] ?? 0);

    $cohorts = tfp_facilitator_attendance_get_cohorts($current_user_id, $course_id);
    if ($cohort_id) {
        $cohorts = array_values(array_filter($cohorts, static function ($cohort) use ($cohort_id) {
            return (int) $cohort->ID === $cohort_id;
        }));
    }

    $report = tfp_facilitator_attendance_report($cohorts);

    nocache_headers();
    $filename = 'attendance-report-' . wp_date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Course',
        'Cohort',
        'Section Module',
        'Meeting Date / Submitted On',
        'Present (PRE)',
        'Excused (EA)',
        'Unexcused (UA)',
        'Total',
    ]);

    foreach ($report['logs'] as $log) {
        fputcsv($output, [
            $log['course'],
            $log['cohort_name'],
            $log['section_module'],
            $log['meeting_date'],
            $log['present'],
            $log['excused'],
            $log['unexcused'],
            $log['total'],
        ]);
    }

    fclose($output);
    exit;
});

function tfp_facilitator_attendance_status_badge($status)
{
    $status = strtoupper((string) $status);

    $map = [
        'P'  => ['label' => 'PRE', 'class' => 'present'],
        'EA' => ['label' => 'EA',  'class' => 'excused'],
        'UA' => ['label' => 'UEA', 'class' => 'unexcused'],
        ''   => ['label' => '—',   'class' => 'na'],
    ];

    $item = $map[$status] ?? $map[''];

    return sprintf(
        '<span class="tfp-attendance-status-badge tfp-attendance-status-badge--%1$s">%2$s</span>',
        esc_attr($item['class']),
        esc_html($item['label'])
    );
}

function tfp_facilitator_attendance_cohort_week_rate($weeks, $week_index, $student_ids)
{
    if (!$weeks || !$student_ids || !isset($weeks[$week_index])) {
        return null;
    }

    $present = 0;
    $recorded = 0;
    $lesson_id = (int) $weeks[$week_index]->ID;

    foreach ($student_ids as $student_id) {
        $record = tfp_facilitator_attendance_get_record($student_id, $lesson_id);
        $status = strtoupper((string) ($record['status'] ?? ''));

        if (!in_array($status, ['P', 'EA', 'UA'], true)) {
            continue;
        }

        $recorded++;
        if ($status === 'P') {
            $present++;
        }
    }

    return $recorded > 0 ? round(($present / $recorded) * 100) : null;
}

function tfp_facilitator_attendance_cohort_detail_report($cohort, $course_id)
{
    $cohort_id = (int) $cohort->ID;
    $course_id = (int) $course_id;

    $weeks = tfp_facilitator_attendance_get_weeks($course_id);
    $student_ids = function_exists('tfp_facilitator_cohort_students')
        ? array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))
        : [];

    usort($student_ids, static function ($a, $b) {
        $user_a = get_userdata($a);
        $user_b = get_userdata($b);
        return strcasecmp(
            $user_a ? $user_a->display_name : '',
            $user_b ? $user_b->display_name : ''
        );
    });

    $present = 0;
    $recorded = 0;
    $student_stats = [];

    foreach ($student_ids as $student_id) {
        $student_stats[$student_id] = [
            'present' => 0,
            'recorded' => 0,
            'ua' => 0,
        ];

        foreach ($weeks as $week) {
            $record = tfp_facilitator_attendance_get_record($student_id, $week->ID);
            $status = strtoupper((string) ($record['status'] ?? ''));

            if (!in_array($status, ['P', 'EA', 'UA'], true)) {
                continue;
            }

            $recorded++;
            $student_stats[$student_id]['recorded']++;

            if ($status === 'P') {
                $present++;
                $student_stats[$student_id]['present']++;
            }

            if ($status === 'UA') {
                $student_stats[$student_id]['ua']++;
            }
        }
    }

    $stalled = 0;
    $absence_limit = 0;

    foreach ($student_stats as $stats) {
        if ($stats['recorded'] >= 2) {
            $rate = ($stats['present'] / $stats['recorded']) * 100;
            if ($stats['present'] === 0 || $rate < 70) {
                $stalled++;
            }
        }

        if ($stats['ua'] >= 2) {
            $absence_limit++;
        }
    }

    $current_week = $course_id && function_exists('tfp_facilitator_cohort_week')
        ? max(1, (int) tfp_facilitator_cohort_week($cohort_id, $course_id))
        : 1;

    $total_weeks = count($weeks);
    $current_index = $total_weeks > 0 ? min($total_weeks - 1, $current_week - 1) : 0;

    $current_rate = tfp_facilitator_attendance_cohort_week_rate(
        $weeks,
        $current_index,
        $student_ids
    );

    $previous_rate = $current_index > 0
        ? tfp_facilitator_attendance_cohort_week_rate($weeks, $current_index - 1, $student_ids)
        : null;

    $attendance_average = $recorded > 0 ? round(($present / $recorded) * 100) : 0;
    $delta = ($current_rate !== null && $previous_rate !== null)
        ? ($current_rate - $previous_rate)
        : null;

    if ($attendance_average >= 90) {
        $health_label = __('Healthy', 'tfp-dashboard');
    } elseif ($attendance_average >= 70) {
        $health_label = __('Watch', 'tfp-dashboard');
    } elseif ($recorded > 0) {
        $health_label = __('Needs Attention', 'tfp-dashboard');
    } else {
        $health_label = __('No Data Yet', 'tfp-dashboard');
    }

    $module = !empty($weeks[$current_index])
        ? $weeks[$current_index]->post_title
        : __('Not started', 'tfp-dashboard');

    $facilitator_id = (int) get_post_meta($cohort_id, '_facilitator_user_id', true);
    $facilitator = $facilitator_id ? get_userdata($facilitator_id) : null;
    $meeting_url = get_post_meta($cohort_id, '_meeting_url', true);
    $schedule = get_post_meta($cohort_id, '_schedule_text', true);
    $start_date = get_post_meta($cohort_id, '_start_date', true);

    $next_meeting_timestamp = ($start_date && strtotime($start_date))
        ? strtotime($start_date) + ($current_index * WEEK_IN_SECONDS)
        : 0;

    return [
        'weeks' => $weeks,
        'student_ids' => $student_ids,
        'attendance_average' => $attendance_average,
        'health_label' => $health_label,
        'delta' => $delta,
        'stalled' => $stalled,
        'absence_limit' => $absence_limit,
        'current_week' => $current_week,
        'total_weeks' => $total_weeks,
        'current_index' => $current_index,
        'module' => $module,
        'facilitator' => $facilitator ? $facilitator->display_name : '',
        'meeting_url' => $meeting_url,
        'schedule' => $schedule,
        'next_meeting_timestamp' => $next_meeting_timestamp,
    ];
}

function tfp_dashboard_render_facilitator_attendance_cohort_content($course, $cohort, $back_url)
{
    $course_id = (int) $course->ID;
    $cohort_id = (int) $cohort->ID;
    $detail = tfp_facilitator_attendance_cohort_detail_report($cohort, $course_id);

    $total_students = count($detail['student_ids']);
    $week_count = $detail['total_weeks'];

    $requested_start = absint($_GET['tfp_attendance_week_start'] ?? 0);

    if ($week_count > 7) {
        $default_start = max(1, min($week_count - 6, $detail['current_week'] - 1));
        $week_start = $requested_start > 0 ? $requested_start : $default_start;
        $week_start = max(1, min($week_start, $week_count - 6));
    } else {
        $week_start = 1;
    }

    $visible_weeks = $week_count
        ? array_slice($detail['weeks'], $week_start - 1, 7)
        : [];

    $week_end = $visible_weeks
        ? $week_start + count($visible_weeks) - 1
        : $week_start;

    $student_per_page = 17;
    $student_page = max(1, absint($_GET['tfp_attendance_student_page'] ?? 1));
    $student_total = count($detail['student_ids']);
    $student_pages = max(1, (int) ceil($student_total / $student_per_page));
    $student_page = min($student_page, $student_pages);

    $visible_students = array_slice(
        $detail['student_ids'],
        ($student_page - 1) * $student_per_page,
        $student_per_page
    );

    $prev_week_url = add_query_arg([
        'course_id' => $course_id,
        'cohort_id' => $cohort_id,
        'tfp_attendance_week_start' => max(1, $week_start - 7),
        'tfp_attendance_student_page' => $student_page,
    ], $back_url);

    $next_week_url = add_query_arg([
        'course_id' => $course_id,
        'cohort_id' => $cohort_id,
        'tfp_attendance_week_start' => min(max(1, $week_count - 6), $week_start + 7),
        'tfp_attendance_student_page' => $student_page,
    ], $back_url);

    $prev_student_url = add_query_arg([
        'course_id' => $course_id,
        'cohort_id' => $cohort_id,
        'tfp_attendance_week_start' => $week_start,
        'tfp_attendance_student_page' => max(1, $student_page - 1),
    ], $back_url);

    $next_student_url = add_query_arg([
        'course_id' => $course_id,
        'cohort_id' => $cohort_id,
        'tfp_attendance_week_start' => $week_start,
        'tfp_attendance_student_page' => min($student_pages, $student_page + 1),
    ], $back_url);

    $student_start = $student_total ? (($student_page - 1) * $student_per_page) + 1 : 0;
    $student_end = min($student_page * $student_per_page, $student_total);

    $meeting_date = $detail['next_meeting_timestamp']
        ? wp_date('l, M j', $detail['next_meeting_timestamp'])
        : __('Next Upcoming Meeting', 'tfp-dashboard');

    $meeting_label = $meeting_date;
    if ($detail['schedule']) {
        $meeting_label .= ' • ' . $detail['schedule'];
    }

    $meetings_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-facilitator-meetings')
        : $back_url;

    $join_url = $detail['meeting_url'] ?: $meetings_url;
    ?>
    <div class="tfp-attendance-page-wrap tfp-attendance-page-wrap--cohort">
        <button type="button" class="tfp-attendance-mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open dashboard navigation', 'tfp-dashboard'); ?>">
            <?php echo tfp_dashboard_icon('toggle'); ?>
        </button>

        <nav class="tfp-attendance-detail-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url($facilitator_home_url ?? $back_url); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <span><?php echo esc_html($course->post_title); ?></span>
            <span>/</span>
            <strong><?php echo esc_html($cohort->post_title); ?></strong>
        </nav>

        <header class="tfp-attendance-detail-header">
            <div>
                <h2><?php echo esc_html($cohort->post_title); ?></h2>
                <p>
                    <?php printf(
                        esc_html__('Current Module: %1$s • %2$s • %3$s', 'tfp-dashboard'),
                        $detail['module'],
                        $detail['schedule'] ?: __('Schedule not set', 'tfp-dashboard'),
                        $detail['facilitator'] ?: __('Facilitator not assigned', 'tfp-dashboard')
                    ); ?>
                </p>
            </div>

            <a
                href="<?php echo esc_url($join_url); ?>"
                class="tfp-dash-btn tfp-dash-btn--primary tfp-attendance-join-button"
                <?php echo $detail['meeting_url'] ? ' target="_blank" rel="noopener"' : ''; ?>>
                <?php echo tfp_dashboard_icon('program'); ?>
                <?php esc_html_e('Join Meeting', 'tfp-dashboard'); ?>
            </a>
        </header>

        <section class="tfp-attendance-metrics tfp-attendance-metrics--detail">
            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Cohort Attendance', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html($detail['attendance_average']); ?>%</strong>
                <small class="tfp-attendance-metric-card__sub">
                    <?php echo esc_html($detail['health_label']); ?>
                    <?php if ($detail['delta'] !== null): ?>
                        • <?php echo esc_html(($detail['delta'] >= 0 ? '+' : '') . $detail['delta']); ?>% <?php esc_html_e('from last wk', 'tfp-dashboard'); ?>
                    <?php endif; ?>
                </small>
            </div>

            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Total Disciples', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html($total_students); ?></strong>
                <small class="tfp-attendance-metric-card__sub"><?php esc_html_e('Active in Program Track', 'tfp-dashboard'); ?></small>
            </div>

            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Pastoral Flags (Stalled • Blocked)', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value tfp-attendance-metric-card__value--red"><?php echo esc_html($detail['stalled'] + $detail['absence_limit']); ?></strong>
                <small class="tfp-attendance-metric-card__sub">
                    <?php echo esc_html($detail['stalled']); ?> <?php esc_html_e('Stalled', 'tfp-dashboard'); ?>
                    • <span class="tfp-red-text"><?php echo esc_html($detail['absence_limit']); ?> <?php esc_html_e('Absence Limit', 'tfp-dashboard'); ?></span>
                </small>
            </div>

            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Journey Progress', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html('Wk ' . $detail['current_week'] . '/' . max(1, $detail['total_weeks'])); ?></strong>
                <small class="tfp-attendance-metric-card__sub"><?php esc_html_e('Current in Journey', 'tfp-dashboard'); ?></small>
            </div>
        </section>

        <section class="tfp-attendance-next-meeting">
            <div class="tfp-attendance-next-meeting__date" aria-hidden="true">
                <?php if ($detail['next_meeting_timestamp']): ?>
                    <strong><?php echo esc_html(strtoupper(wp_date('M', $detail['next_meeting_timestamp']))); ?></strong>
                    <span><?php echo esc_html(wp_date('j', $detail['next_meeting_timestamp'])); ?></span>
                <?php else: ?>
                    <strong>—</strong>
                    <span>—</span>
                <?php endif; ?>
            </div>

            <div class="tfp-attendance-next-meeting__info">
                <span><?php esc_html_e('Next Upcoming Meeting', 'tfp-dashboard'); ?></span>
                <strong><?php echo esc_html('Purpose — Week ' . $detail['current_week']); ?></strong>
                <small><?php echo esc_html($meeting_label); ?></small>
            </div>

            <a
                href="<?php echo esc_url($join_url); ?>"
                class="tfp-dash-btn tfp-dash-btn--primary"
                <?php echo $detail['meeting_url'] ? ' target="_blank" rel="noopener"' : ''; ?>>
                <?php echo tfp_dashboard_icon('program'); ?>
                <?php esc_html_e('Join Meeting', 'tfp-dashboard'); ?>
            </a>
        </section>

        <section class="tfp-attendance-history-panel">
            <header class="tfp-attendance-history-panel__head">
                <h3>
                    <?php printf(
                        esc_html__('Attendance History: Weeks %1$d–%2$d', 'tfp-dashboard'),
                        $week_start,
                        $week_end
                    ); ?>
                </h3>

                <div class="tfp-attendance-history-panel__section-actions">
                    <?php if ($week_start > 1): ?>
                        <a href="<?php echo esc_url($prev_week_url); ?>" class="tfp-attendance-section-btn tfp-attendance-section-btn--outline">
                            <span aria-hidden="true">‹</span>
                            <?php esc_html_e('Prev Section', 'tfp-dashboard'); ?>
                        </a>
                    <?php else: ?>
                        <span class="tfp-attendance-section-btn tfp-attendance-section-btn--outline is-disabled">‹ <?php esc_html_e('Prev Section', 'tfp-dashboard'); ?></span>
                    <?php endif; ?>

                    <?php if ($week_end < $detail['total_weeks']): ?>
                        <a href="<?php echo esc_url($next_week_url); ?>" class="tfp-attendance-section-btn tfp-attendance-section-btn--primary">
                            <?php esc_html_e('Next Section', 'tfp-dashboard'); ?>
                            <span aria-hidden="true">›</span>
                        </a>
                    <?php else: ?>
                        <span class="tfp-attendance-section-btn tfp-attendance-section-btn--primary is-disabled"><?php esc_html_e('Next Section', 'tfp-dashboard'); ?> ›</span>
                    <?php endif; ?>
                </div>
            </header>

            <div class="tfp-dash-table-wrap tfp-attendance-history-table-wrap">
                <table class="tfp-dash-table tfp-attendance-history-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Disciple', 'tfp-dashboard'); ?></th>
                            <?php foreach ($visible_weeks as $week): ?>
                                <?php
                                $week_index = array_search(
                                    (int) $week->ID,
                                    wp_list_pluck($detail['weeks'], 'ID'),
                                    true
                                );
                                $week_number = $week_index === false ? '' : ((int) $week_index + 1);
                                ?>
                                <th scope="col"><?php echo esc_html('WK ' . $week_number); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($visible_students): ?>
                            <?php foreach ($visible_students as $student_id): ?>
                                <?php $student = get_userdata($student_id); ?>
                                <tr>
                                    <td class="tfp-attendance-history-student">
                                        <?php echo esc_html($student ? $student->display_name : __('Unknown Student', 'tfp-dashboard')); ?>
                                    </td>
                                    <?php foreach ($visible_weeks as $week): ?>
                                        <?php
                                        $record = tfp_facilitator_attendance_get_record($student_id, $week->ID);
                                        $status = strtoupper((string) ($record['status'] ?? ''));
                                        ?>
                                        <td class="tfp-attendance-history-status">
                                            <?php echo tfp_facilitator_attendance_status_badge($status); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo esc_attr(1 + count($visible_weeks)); ?>">
                                    <div class="tfp-facilitator-empty"><?php esc_html_e('No disciples are assigned to this cohort.', 'tfp-dashboard'); ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="tfp-attendance-student-pagination">
                <span>
                    <?php printf(
                        esc_html__('%1$d - %2$d of %3$d Disciples', 'tfp-dashboard'),
                        $student_start,
                        $student_end,
                        $student_total
                    ); ?>
                </span>

                <div class="tfp-attendance-student-pagination__actions">
                    <?php if ($student_page > 1): ?>
                        <a href="<?php echo esc_url($prev_student_url); ?>" class="tfp-attendance-student-pagination__prev">‹ <?php esc_html_e('Previous', 'tfp-dashboard'); ?></a>
                    <?php else: ?>
                        <span class="tfp-attendance-student-pagination__prev is-disabled">‹ <?php esc_html_e('Previous', 'tfp-dashboard'); ?></span>
                    <?php endif; ?>

                    <?php if ($student_page < $student_pages): ?>
                        <a href="<?php echo esc_url($next_student_url); ?>" class="tfp-attendance-student-pagination__next"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</a>
                    <?php else: ?>
                        <span class="tfp-attendance-student-pagination__next is-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</span>
                    <?php endif; ?>
                </div>
            </footer>
        </section>
    </div>
    <?php
}

function tfp_dashboard_render_facilitator_attendance_content()
{
    $current_user_id = get_current_user_id();

    $attendance_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-facilitator-attendance')
        : get_permalink();

    $home_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-facilitator-home')
        : $attendance_url;

    $filter_course = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    $filter_cohort = isset($_GET['cohort_id']) ? absint($_GET['cohort_id']) : 0;

    $all_courses = tfp_facilitator_attendance_get_courses($current_user_id);
    $all_cohorts = tfp_facilitator_attendance_get_cohorts($current_user_id, $filter_course);

    /*
     * A cohort is selected only when the user has explicitly selected one.
     * The attendance navigation itself must always open the course-level
     * landing screen first.
     */
    if ($filter_cohort) {
        $selected_cohort = null;

        foreach ($all_cohorts as $cohort) {
            if ((int) $cohort->ID === $filter_cohort) {
                $selected_cohort = $cohort;
                break;
            }
        }

        if (!$selected_cohort) {
            return;
        }

        $selected_course_id = (int) get_post_meta($selected_cohort->ID, '_related_course', true);
        $selected_course = $selected_course_id ? get_post($selected_course_id) : null;

        if (!$selected_course instanceof WP_Post) {
            return;
        }

        tfp_dashboard_render_facilitator_attendance_cohort_content(
            $selected_course,
            $selected_cohort,
            $attendance_url
        );

        return;
    }

    $report_cohorts = $filter_course
        ? $all_cohorts
        : tfp_facilitator_attendance_get_cohorts($current_user_id, 0);

    $report = tfp_facilitator_attendance_report($report_cohorts);

    $avg_attendance = (int) $report['avg_attendance'];
    $total_disciples = (int) $report['students'];
    $stalled_count = (int) $report['stalled'];
    $blocked_count = (int) $report['blocked'];
    $active_cohorts_count = count($report_cohorts);

    $export_url = add_query_arg([
        'tfp_export' => 'attendance_csv',
        'course_id'  => $filter_course,
        'cohort_id'  => 0,
    ], $attendance_url);

    $upcoming_submissions = tfp_facilitator_attendance_upcoming_submissions($report_cohorts, $report);

    /*
     * Submission logs are live and paginated. Eight is the working page size;
     * pagination stays hidden when fewer than eight records exist.
     */
    $log_rows = [];

    foreach ((array) ($report['logs'] ?? []) as $log) {
        $course_weeks = $report['weeks_by_cohort'][(int) $log['cohort_id']] ?? [];
        $week_index = array_search(
            (int) $log['lesson_id'],
            wp_list_pluck($course_weeks, 'ID'),
            true
        );

        $week_number = $week_index === false ? 0 : ((int) $week_index + 1);
        $student_total = (int) ($log['student_total'] ?? 0);
        $submitted_count = count((array) ($log['student_ids'] ?? []));
        $complete = $student_total > 0 && $submitted_count >= $student_total;

        $log_rows[] = [
            'cohort_name'   => $log['cohort_name'],
            'cohort_id'     => (int) $log['cohort_id'],
            'section_module'=> $week_number
                ? sprintf('Week %d: %s', $week_number, $log['section_module'])
                : $log['section_module'],
            'submitted_on'  => !empty($log['latest_timestamp'])
                ? wp_date('D, M j, Y g:i A T', $log['latest_timestamp'])
                : '—',
            'breakdown'     => tfp_facilitator_attendance_format_breakdown($log),
            'status'        => $complete ? 'completed' : 'in_progress',
        ];
    }

    usort($log_rows, static function ($a, $b) {
        return strcmp((string) $b['submitted_on'], (string) $a['submitted_on']);
    });

    $log_per_page = 8;
    $log_page = max(1, absint($_GET['tfp_attendance_log_page'] ?? 1));
    $log_total = count($log_rows);
    $log_pages = max(1, (int) ceil($log_total / $log_per_page));
    $log_page = min($log_page, $log_pages);

    $log_rows_page = array_slice(
        $log_rows,
        ($log_page - 1) * $log_per_page,
        $log_per_page
    );

    $range_start = $log_total ? (($log_page - 1) * $log_per_page) + 1 : 0;
    $range_end = min($log_page * $log_per_page, $log_total);

    $selected_course = $filter_course ? get_post($filter_course) : null;
    $selected_course_label = $selected_course instanceof WP_Post
        ? $selected_course->post_title
        : __('All Courses', 'tfp-dashboard');

    $pagination_args = [];
    if ($filter_course) {
        $pagination_args['course_id'] = $filter_course;
    }

    $prev_log_url = add_query_arg(
        array_merge($pagination_args, ['tfp_attendance_log_page' => max(1, $log_page - 1)]),
        $attendance_url
    );

    $next_log_url = add_query_arg(
        array_merge($pagination_args, ['tfp_attendance_log_page' => min($log_pages, $log_page + 1)]),
        $attendance_url
    );
    ?>
    <div class="tfp-attendance-page-wrap">
        <button
            type="button"
            class="tfp-attendance-mobile-toggle"
            data-tfp-sidebar-toggle
            aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>">
            <?php echo tfp_dashboard_icon('toggle'); ?>
        </button>

        <header class="tfp-dash-pageheader tfp-attendance-main-pageheader">
            <div class="tfp-dash-pageheader__crumb tfp-facilitator-breadcrumb tfp-attendance-main-breadcrumb">
                <a href="<?php echo esc_url($home_url); ?>">
                    <?php esc_html_e('Facilitator', 'tfp-dashboard'); ?>
                </a>
                <span aria-hidden="true">/</span>
                <strong><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></strong>
            </div>

            <h1 class="tfp-dash-pageheader__title"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></h1>

            <p class="tfp-dash-pageheader__subtitle">
                <?php esc_html_e('Monitor engagement and consistency across all active cohorts.', 'tfp-dashboard'); ?>
            </p>
        </header>

        <div class="tfp-attendance-accent-divider" aria-hidden="true"></div>

        <section class="tfp-attendance-course-filters">
            <form method="get" class="tfp-attendance-filters-form tfp-facilitator-filters">
                <div class="tfp-attendance-filter-item">
                    <label for="tfp_filter_course" class="screen-reader-text"><?php esc_html_e('Select course', 'tfp-dashboard'); ?></label>
                    <select
                        name="course_id"
                        id="tfp_filter_course"
                        class="tfp-attendance-select"
                        aria-label="<?php esc_attr_e('Select course', 'tfp-dashboard'); ?>"
                        onchange="this.form.submit();">
                        <option value="0"><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option>
                        <?php foreach ($all_courses as $course): ?>
                            <option value="<?php echo esc_attr($course->ID); ?>" <?php selected($filter_course, $course->ID); ?>>
                                <?php echo esc_html($course->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="tfp-attendance-filter-item">
                    <label for="tfp_filter_cohort" class="screen-reader-text"><?php esc_html_e('Select cohort', 'tfp-dashboard'); ?></label>
                    <select
                        name="cohort_id"
                        id="tfp_filter_cohort"
                        class="tfp-attendance-select"
                        aria-label="<?php esc_attr_e('Select cohort', 'tfp-dashboard'); ?>"
                        onchange="this.form.submit();">
                        <option value="0"><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
                        <?php foreach ($all_cohorts as $cohort): ?>
                            <option value="<?php echo esc_attr($cohort->ID); ?>" <?php selected($filter_cohort, $cohort->ID); ?>>
                                <?php echo esc_html($cohort->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </section>

        <section class="tfp-attendance-metrics">
            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Avg Attendance', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html($avg_attendance); ?>%</strong>
                <small class="tfp-attendance-metric-card__sub">
                    <?php echo esc_html($filter_course ? $selected_course_label . ' Avg Attendance' : 'All Courses Avg Attendance'); ?>
                </small>
            </div>

            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Total Disciples', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html($total_disciples); ?></strong>
                <small class="tfp-attendance-metric-card__sub">
                    <?php echo esc_html($filter_course ? 'Across Active ' . $selected_course_label : 'Across Active Courses'); ?>
                </small>
            </div>

            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Pastoral Flags (Stalled • Blocked)', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value tfp-attendance-metric-card__value--red"><?php echo esc_html($stalled_count + $blocked_count); ?></strong>
                <small class="tfp-attendance-metric-card__sub">
                    <?php echo esc_html($stalled_count); ?> <?php esc_html_e('Stalled', 'tfp-dashboard'); ?>
                    • <span class="tfp-red-text"><?php echo esc_html($blocked_count); ?> <?php esc_html_e('Blocked', 'tfp-dashboard'); ?></span>
                </small>
            </div>

            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html($active_cohorts_count); ?></strong>
                <small class="tfp-attendance-metric-card__sub"><?php esc_html_e('Currently Assigned', 'tfp-dashboard'); ?></small>
            </div>
        </section>

        <section class="tfp-attendance-banner-card">
            <div>
                <h3><?php esc_html_e('All Active Cohorts', 'tfp-dashboard'); ?></h3>
                <p><?php esc_html_e('Attendance Health Rank', 'tfp-dashboard'); ?></p>
            </div>

            <a href="<?php echo esc_url($export_url); ?>" class="tfp-attendance-export-btn tfp-dash-btn tfp-dash-btn--primary" download="attendance-report.csv">
                <?php echo tfp_dashboard_icon('doc'); ?>
                <?php esc_html_e('Export Report', 'tfp-dashboard'); ?>
            </a>
        </section>

        <section class="tfp-attendance-submission-card tfp-attendance-upcoming-card">
            <header class="tfp-attendance-submission-card__head">
                <h3><?php esc_html_e('Upcoming Submissions', 'tfp-dashboard'); ?></h3>
            </header>

            <div class="tfp-dash-table-wrap tfp-attendance-submission-table-wrap">
                <table class="tfp-dash-table tfp-attendance-submission-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Course', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Cohort', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Section Module', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Meeting Date', 'tfp-dashboard'); ?></th>
                            <th scope="col" class="tfp-col-action"><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($upcoming_submissions): ?>
                            <?php foreach ($upcoming_submissions as $upcoming): ?>
                                <?php
                                $upcoming_cohort = null;
                                foreach ($report_cohorts as $cohort) {
                                    if ((string) $cohort->post_title === (string) ($upcoming['cohort'] ?? '')) {
                                        $upcoming_cohort = $cohort;
                                        break;
                                    }
                                }

                                $view_url = $upcoming_cohort
                                    ? add_query_arg([
                                        'course_id' => (int) get_post_meta($upcoming_cohort->ID, '_related_course', true),
                                        'cohort_id' => (int) $upcoming_cohort->ID,
                                    ], $attendance_url)
                                    : $attendance_url;
                                ?>
                                <tr>
                                    <td class="tfp-td-bold"><?php echo esc_html($upcoming['course']); ?></td>
                                    <td class="tfp-td-normal"><?php echo esc_html($upcoming['cohort']); ?></td>
                                    <td class="tfp-td-normal"><?php echo esc_html($upcoming['section_module']); ?></td>
                                    <td class="tfp-td-date"><?php echo esc_html($upcoming['meeting_date']); ?></td>
                                    <td class="tfp-col-action">
                                        <a href="<?php echo esc_url($view_url); ?>" class="tfp-attendance-btn-view tfp-dash-btn-small tfp-dash-btn--primary">
                                            <?php esc_html_e('View', 'tfp-dashboard'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="tfp-facilitator-empty"><?php esc_html_e('No upcoming attendance submissions found.', 'tfp-dashboard'); ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tfp-attendance-submission-card tfp-attendance-logs-card">
            <header class="tfp-attendance-submission-card__head">
                <h3><?php esc_html_e('Submission Logs', 'tfp-dashboard'); ?></h3>
                <a href="<?php echo esc_url($attendance_url); ?>" class="tfp-attendance-view-all">
                    <?php esc_html_e('View All', 'tfp-dashboard'); ?>
                </a>
            </header>

            <div class="tfp-dash-table-wrap tfp-attendance-submission-table-wrap">
                <table class="tfp-dash-table tfp-attendance-submission-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Cohort Name', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Section Module', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Submitted On', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Breakdown', 'tfp-dashboard'); ?></th>
                            <th scope="col" class="tfp-col-action"><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($log_rows_page): ?>
                            <?php foreach ($log_rows_page as $row): ?>
                                <?php
                                $row_view_url = add_query_arg([
                                    'course_id' => $row['cohort_id'] ? (int) get_post_meta($row['cohort_id'], '_related_course', true) : $filter_course,
                                    'cohort_id' => $row['cohort_id'],
                                    'tfp_attendance_week_start' => 1,
                                    'tfp_attendance_student_page' => 1,
                                ], $attendance_url);
                                ?>
                                <tr>
                                    <td class="tfp-td-bold"><?php echo esc_html($row['cohort_name']); ?></td>
                                    <td class="tfp-td-normal"><?php echo esc_html($row['section_module']); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'in_progress'): ?>
                                            <span class="tfp-attendance-log-status tfp-attendance-log-status--progress"><?php esc_html_e('In Progress', 'tfp-dashboard'); ?></span>
                                        <?php else: ?>
                                            <span class="tfp-attendance-log-date"><?php echo esc_html($row['submitted_on']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tfp-td-breakdown"><?php echo wp_kses_post($row['breakdown']); ?></td>
                                    <td class="tfp-col-action">
                                        <a href="<?php echo esc_url($row_view_url); ?>" class="tfp-attendance-btn-view tfp-dash-btn-small tfp-dash-btn--primary">
                                            <?php esc_html_e('View', 'tfp-dashboard'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="tfp-facilitator-empty"><?php esc_html_e('No attendance submission logs found.', 'tfp-dashboard'); ?></div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($log_total > $log_per_page): ?>
                <footer class="tfp-attendance-pagination">
                    <div class="tfp-attendance-pagination__count">
                        <?php printf(
                            esc_html__('%1$d - %2$d of %3$d items', 'tfp-dashboard'),
                            $range_start,
                            $range_end,
                            $log_total
                        ); ?>
                    </div>
                    <div class="tfp-attendance-pagination__controls">
                        <?php if ($log_page > 1): ?>
                            <a href="<?php echo esc_url($prev_log_url); ?>" class="tfp-attendance-pagination__prev" rel="prev">
                                ‹ <?php esc_html_e('Previous', 'tfp-dashboard'); ?>
                            </a>
                        <?php else: ?>
                            <span class="tfp-attendance-pagination__prev is-disabled">‹ <?php esc_html_e('Previous', 'tfp-dashboard'); ?></span>
                        <?php endif; ?>

                        <?php if ($log_page < $log_pages): ?>
                            <a href="<?php echo esc_url($next_log_url); ?>" class="tfp-attendance-pagination__next" rel="next">
                                <?php esc_html_e('Next', 'tfp-dashboard'); ?> ›
                            </a>
                        <?php else: ?>
                            <span class="tfp-attendance-pagination__next is-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</span>
                        <?php endif; ?>
                    </div>
                </footer>
            <?php endif; ?>
        </section>
    </div>
    <?php
}
