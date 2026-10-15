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
                        'present'         => 0,
                        'excused'         => 0,
                        'unexcused'       => 0,
                        'latest_timestamp'=> 0,
                    ];
                }

                $logs[$group_key]['student_ids'][$student_id] = true;

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
        'weeks_by_cohort' => $weeks_by_cohort,
    ];

    return $cache[$cache_key];
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

function tfp_dashboard_render_facilitator_attendance_content()
{
    $current_user_id = get_current_user_id();
    $facilitator_home_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-facilitator-home')
        : home_url('/facilitator-console/');
    $roster_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-facilitator-roster')
        : home_url('/facilitator-roster/');

    // Filter values
    $filter_course = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    $filter_cohort = isset($_GET['cohort_id']) ? absint($_GET['cohort_id']) : 0;

    $all_courses = tfp_facilitator_attendance_get_courses($current_user_id);

    $all_cohorts = tfp_facilitator_attendance_get_cohorts($current_user_id, $filter_course);
    if ($filter_cohort) {
        $all_cohorts = array_values(array_filter($all_cohorts, static function ($cohort) use ($filter_cohort) {
            return (int) $cohort->ID === $filter_cohort;
        }));
    }

    $report = tfp_facilitator_attendance_report($all_cohorts);
    $active_cohorts_count = count($all_cohorts);
    $total_disciples = (int) $report['students'];
    $avg_attendance = (int) $report['avg_attendance'];
    $stalled_count = (int) $report['stalled'];
    $blocked_count = (int) $report['blocked'];
    $total_flags = $stalled_count + $blocked_count;

    $export_url = add_query_arg([
        'tfp_export' => 'attendance_csv',
        'course_id'  => $filter_course,
        'cohort_id'  => $filter_cohort,
    ]);

    $upcoming_submissions = tfp_facilitator_attendance_upcoming_submissions($all_cohorts, $report);

    foreach ($upcoming_submissions as &$upcoming) {
        $params = ['cohort_id' => 0];
        foreach ($all_cohorts as $cohort) {
            if ((string) $cohort->post_title === (string) $upcoming['cohort']) {
                $params['cohort_id'] = (int) $cohort->ID;
                break;
            }
        }
        $upcoming['action_url'] = add_query_arg($params, $roster_url);
    }
    unset($upcoming);

    $submission_logs = [];
    foreach ($report['logs'] as $log) {
        $submission_logs[] = [
            'cohort_name'    => $log['cohort_name'],
            'section_module' => $log['section_module'],
            'submitted_on'   => $log['meeting_date'],
            'breakdown'      => tfp_facilitator_attendance_format_breakdown($log),
            'action_url'     => add_query_arg(['cohort_id' => $log['cohort_id']], $roster_url),
        ];
    }

    $attendance_per_page = 4;
    $attendance_page = max(1, absint($_GET['attendance_page'] ?? 1));
    $attendance_total = count($submission_logs);
    $attendance_pages = max(1, (int) ceil($attendance_total / $attendance_per_page));
    $attendance_page = min($attendance_page, $attendance_pages);
    $submission_logs_page = array_slice(
        $submission_logs,
        ($attendance_page - 1) * $attendance_per_page,
        $attendance_per_page
    );
    ?>
    <div class="tfp-attendance-page-wrap">
        <!-- Top Breadcrumbs -->
        <nav class="tfp-attendance-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url($facilitator_home_url); ?>" class="tfp-attendance-breadcrumb__parent">
                <?php esc_html_e('Facilitator', 'tfp-dashboard'); ?>
            </a>
            <span class="tfp-attendance-breadcrumb__sep" aria-hidden="true">/</span>
            <span class="tfp-attendance-breadcrumb__current">
                <?php esc_html_e('Attendance', 'tfp-dashboard'); ?>
            </span>
        </nav>

        <!-- Page Header -->
        <header class="tfp-attendance-header">
            <h2 class="tfp-attendance-header__title"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></h2>
            <p class="tfp-attendance-header__subtitle">
                <?php esc_html_e('Monitor engagement and consistency across all active cohorts.', 'tfp-dashboard'); ?>
            </p>
        </header>

        <!-- Thin Accent Divider Line -->
        <div class="tfp-attendance-accent-divider" aria-hidden="true"></div>

        <!-- Filters Bar (All Courses / All Cohorts) -->
        <section class="tfp-attendance-filters-bar" aria-label="<?php esc_attr_e('Attendance filters', 'tfp-dashboard'); ?>">
            <form method="get" class="tfp-attendance-filters-form" id="tfpAttendanceFilterForm">
                <div class="tfp-attendance-filter-item">
                    <label for="tfp_filter_course" class="screen-reader-text"><?php esc_html_e('Filter by Course', 'tfp-dashboard'); ?></label>
                    <select name="course_id" id="tfp_filter_course" class="tfp-attendance-select" onchange="document.getElementById('tfpAttendanceFilterForm').submit();">
                        <option value="0"><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option>
                        <?php foreach ($all_courses as $c) : ?>
                            <option value="<?php echo esc_attr($c->ID); ?>" <?php selected($filter_course, $c->ID); ?>>
                                <?php echo esc_html($c->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tfp-select-chevron" aria-hidden="true">
                        <svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1L5 5L9 1" stroke="#00666E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </div>

                <div class="tfp-attendance-filter-item">
                    <label for="tfp_filter_cohort" class="screen-reader-text"><?php esc_html_e('Filter by Cohort', 'tfp-dashboard'); ?></label>
                    <select name="cohort_id" id="tfp_filter_cohort" class="tfp-attendance-select" onchange="document.getElementById('tfpAttendanceFilterForm').submit();">
                        <option value="0"><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
                        <?php foreach ($all_cohorts as $ch) : ?>
                            <option value="<?php echo esc_attr($ch->ID); ?>" <?php selected($filter_cohort, $ch->ID); ?>>
                                <?php echo esc_html($ch->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tfp-select-chevron" aria-hidden="true">
                        <svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1L5 5L9 1" stroke="#00666E" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                </div>
            </form>
        </section>

        <!-- KPI Metrics (4 Cards) -->
        <section class="tfp-attendance-metrics" aria-label="<?php esc_attr_e('Attendance metrics', 'tfp-dashboard'); ?>">
            <!-- Card 1 -->
            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Avg Attendance', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html($avg_attendance); ?>%</strong>
                <small class="tfp-attendance-metric-card__sub"><?php esc_html_e('All Courses Avg Attendance', 'tfp-dashboard'); ?></small>
            </div>

            <!-- Card 2 -->
            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Total Disciples', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html($total_disciples); ?></strong>
                <small class="tfp-attendance-metric-card__sub"><?php esc_html_e('Across All Course', 'tfp-dashboard'); ?></small>
            </div>

            <!-- Card 3 -->
            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Pastoral Flags (Stalled • Blocked)', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value tfp-attendance-metric-card__value--red"><?php echo esc_html($total_flags); ?></strong>
                <small class="tfp-attendance-metric-card__sub">
                    <?php printf(esc_html__('%d Stalled • ', 'tfp-dashboard'), $stalled_count); ?>
                    <span class="tfp-red-text"><?php printf(esc_html__('%d Blocked', 'tfp-dashboard'), $blocked_count); ?></span>
                </small>
            </div>

            <!-- Card 4 -->
            <div class="tfp-attendance-metric-card tfp-facilitator-metric">
                <span class="tfp-attendance-metric-card__label"><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></span>
                <strong class="tfp-attendance-metric-card__value"><?php echo esc_html($active_cohorts_count); ?></strong>
                <small class="tfp-attendance-metric-card__sub"><?php esc_html_e('Currently Assigned', 'tfp-dashboard'); ?></small>
            </div>
        </section>

        <!-- All Active Cohorts Header Banner Card -->
        <section class="tfp-attendance-banner-card">
            <div class="tfp-attendance-banner-card__info">
                <h5 class="tfp-attendance-banner-card__title"><?php esc_html_e('All Active Cohorts', 'tfp-dashboard'); ?></h5>
                <span class="tfp-attendance-banner-card__subtitle"><?php esc_html_e('Attendance Health Rank', 'tfp-dashboard'); ?></span>
            </div>
            <div class="tfp-attendance-banner-card__action">
                <a href="<?php echo esc_url($export_url); ?>" class="tfp-attendance-export-btn tfp-dash-btn-second tfp-dash-btn--primary" download="attendance-report.csv">
                    <svg class="tfp-export-icon" width="16" height="18" viewBox="0 0 16 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 1H3C1.89543 1 1 1.89543 1 3V15C1 16.1046 1.89543 17 3 17H13C14.1046 17 15 16.1046 15 15V7L9 1Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 1V7H15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 10V14M8 14L6 12M8 14L10 12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Export Report', 'tfp-dashboard'); ?></span>
                </a>
            </div>
        </section>

        <!-- Upcoming Submissions Card -->
        <section class="tfp-attendance-card-panel">
            <h5 class="tfp-attendance-panel-title"><?php esc_html_e('Upcoming Submissions', 'tfp-dashboard'); ?></h5>
            <div class="tfp-dash-table-wrap tfp-attendance-table-responsive">
                <table class="tfp-dash-table tfp-attendance-table">
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
                        <?php foreach ($upcoming_submissions as $upcoming) : ?>
                            <tr>
                                <td class="tfp-td-bold"><?php echo esc_html($upcoming['course']); ?></td>
                                <td class="tfp-td-normal"><?php echo esc_html($upcoming['cohort']); ?></td>
                                <td class="tfp-td-normal"><?php echo esc_html($upcoming['section_module']); ?></td>
                                <td class="tfp-td-date"><?php echo esc_html($upcoming['meeting_date']); ?></td>
                                <td class="tfp-col-action">
                                    <a href="<?php echo esc_url($upcoming['action_url']); ?>" class="tfp-attendance-btn-view tfp-dash-btn-small tfp-dash-btn--primary">
                                        <?php esc_html_e('View', 'tfp-dashboard'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Submission Logs Card -->
        <section class="tfp-attendance-card-panel">
            <div class="tfp-attendance-panel-head-row">
                <h5 class="tfp-attendance-panel-title"><?php esc_html_e('Submission Logs', 'tfp-dashboard'); ?></h5>
                <a href="<?php echo esc_url($roster_url); ?>" class="tfp-attendance-view-all-link">
                    <?php esc_html_e('View All', 'tfp-dashboard'); ?>
                </a>
            </div>

            <div class="tfp-attendance-table-responsive">
                <table class="tfp-attendance-table">
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
                        <?php foreach ($submission_logs_page as $log) : ?>
                            <tr>
                                <td class="tfp-td-bold"><?php echo esc_html($log['cohort_name']); ?></td>
                                <td class="tfp-td-normal"><?php echo esc_html($log['section_module']); ?></td>
                                <td class="tfp-td-date"><?php echo esc_html($log['submitted_on']); ?></td>
                                <td class="tfp-td-breakdown"><?php echo wp_kses_post($log['breakdown']); ?></td>
                                <td class="tfp-col-action">
                                    <a href="<?php echo esc_url($log['action_url']); ?>" class="tfp-attendance-btn-view tfp-dash-btn-small tfp-dash-btn--primary">
                                        <?php esc_html_e('View', 'tfp-dashboard'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($attendance_total > 0): ?>
                <?php
                $range_start = (($attendance_page - 1) * $attendance_per_page) + 1;
                $range_end = min($attendance_page * $attendance_per_page, $attendance_total);
                $pagination_args = [
                    'course_id' => $filter_course,
                    'cohort_id' => $filter_cohort,
                ];
                ?>
                <footer class="tfp-attendance-pagination">
                    <div class="tfp-attendance-pagination__count">
                        <?php printf(esc_html__('%1$d - %2$d of %3$d items', 'tfp-dashboard'), $range_start, $range_end, $attendance_total); ?>
                    </div>
                    <div class="tfp-attendance-pagination__controls">
                        <?php if ($attendance_page > 1): ?>
                            <a
                                href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['attendance_page' => $attendance_page - 1]))); ?>"
                                class="tfp-attendance-pagination__prev">
                                <svg width="7" height="10" viewBox="0 0 7 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 1L2 5L6 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php esc_html_e('Previous', 'tfp-dashboard'); ?>
                            </a>
                        <?php else: ?>
                            <span class="tfp-attendance-pagination__prev is-disabled">
                                <svg width="7" height="10" viewBox="0 0 7 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 1L2 5L6 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php esc_html_e('Previous', 'tfp-dashboard'); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($attendance_page < $attendance_pages): ?>
                            <a
                                href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['attendance_page' => $attendance_page + 1]))); ?>"
                                class="tfp-attendance-pagination__next">
                                <span><?php esc_html_e('Next', 'tfp-dashboard'); ?></span>
                                <svg width="7" height="10" viewBox="0 0 7 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1L5 5L1 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        <?php else: ?>
                            <span class="tfp-attendance-pagination__next is-disabled">
                                <span><?php esc_html_e('Next', 'tfp-dashboard'); ?></span>
                                <svg width="7" height="10" viewBox="0 0 7 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1L5 5L1 9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        <?php endif; ?>
                    </div>
                </footer>
            <?php endif; ?>        </section>
    </div>
    <?php
}
