<?php
if (!defined('ABSPATH')) exit;

/**
 * Facilitator Meetings.
 * Uses real cohort schedules, LearnDash weeks and stored assessment/attendance data.
 */

add_action('init', function () {
    register_post_type('tfp_meeting_request', [
        'labels' => [
            'name' => __('Meeting Requests', 'tfp-dashboard'),
            'singular_name' => __('Meeting Request', 'tfp-dashboard'),
        ],
        'public' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
});

function tfp_facilitator_meetings_cohorts($user_id = 0)
{
    $user_id = $user_id ?: get_current_user_id();
    return function_exists('tfp_facilitator_current_cohorts')
        ? array_values((array) tfp_facilitator_current_cohorts($user_id))
        : [];
}

function tfp_facilitator_meetings_schedule($schedule)
{
    $schedule = trim((string) $schedule);
    $out = [
        'weekday' => 0,
        'time' => '12:00 PM',
        'timezone' => wp_timezone(),
        'label' => wp_date('T'),
    ];

    $days = [
        'sunday'=>0,'sun'=>0,'monday'=>1,'mon'=>1,'tuesday'=>2,'tue'=>2,
        'wednesday'=>3,'wed'=>3,'thursday'=>4,'thu'=>4,'friday'=>5,'fri'=>5,
        'saturday'=>6,'sat'=>6,
    ];

    if (preg_match('/(sunday|sun|monday|mon|tuesday|tue|wednesday|wed|thursday|thu|friday|fri|saturday|sat)/i', $schedule, $m)) {
        $out['weekday'] = $days[strtolower($m[1])] ?? 0;
    }

    if (preg_match('/(d{1,2})(?::(d{2}))?s*:?s*(am|pm)/i', $schedule, $m)) {
        $out['time'] = max(1, min(12, (int) $m[1])) . ':' . (isset($m[2]) && $m[2] !== '' ? str_pad($m[2], 2, '0', STR_PAD_LEFT) : '00') . ' ' . strtoupper($m[3]);
    }

    foreach ([
        'pt' => ['America/Los_Angeles', 'PT'], 'pst' => ['America/Los_Angeles', 'PT'], 'pdt' => ['America/Los_Angeles', 'PT'],
        'et' => ['America/New_York', 'ET'], 'est' => ['America/New_York', 'ET'], 'edt' => ['America/New_York', 'ET'],
        'ct' => ['America/Chicago', 'CT'], 'cst' => ['America/Chicago', 'CT'], 'cdt' => ['America/Chicago', 'CT'],
        'mt' => ['America/Denver', 'MT'], 'mst' => ['America/Denver', 'MT'], 'mdt' => ['America/Denver', 'MT'],
        'utc' => ['UTC', 'UTC'], 'gmt' => ['UTC', 'UTC'],
    ] as $needle => $zone) {
        if (preg_match('/' . preg_quote($needle, '/') . '/i', $schedule)) {
            $out['timezone'] = new DateTimeZone($zone[0]);
            $out['label'] = $zone[1];
            break;
        }
    }

    return $out;
}

function tfp_facilitator_meetings_timestamp($start_date, $schedule, $week_index)
{
    if (!$start_date || !strtotime($start_date)) return 0;

    $parts = tfp_facilitator_meetings_schedule($schedule);

    try {
        $date = new DateTimeImmutable($start_date . ' 00:00:00', $parts['timezone']);
        $days_until = ($parts['weekday'] - (int) $date->format('w') + 7) % 7;
        $date = $date->modify('+' . $days_until . ' days')->modify('+' . max(0, (int) $week_index) . ' weeks');

        if (preg_match('/^(d{1,2}):(d{2})s+(AM|PM)$/', $parts['time'], $m)) {
            $hour = (int) $m[1];
            if (strtoupper($m[3]) === 'PM' && $hour < 12) $hour += 12;
            if (strtoupper($m[3]) === 'AM' && $hour === 12) $hour = 0;
            $date = $date->setTime($hour, (int) $m[2], 0);
        }

        return $date->getTimestamp();
    } catch (Throwable $e) {
        return 0;
    }
}

function tfp_facilitator_meetings_letter($percent)
{
    if ($percent === null || !is_numeric($percent)) return '—';
    $percent = (float) $percent;
    if ($percent >= 90) return 'A';
    if ($percent >= 80) return 'B';
    if ($percent >= 70) return 'C';
    if ($percent >= 60) return 'D';
    return 'F';
}

function tfp_facilitator_meetings_scores($student_ids, $lesson_id)
{
    $homework = [];
    $test = [];

    foreach (array_map('absint', (array) $student_ids) as $student_id) {
        if (function_exists('tfp_week_homework_progress')) {
            $progress = tfp_week_homework_progress($student_id, $lesson_id);
            if (!empty($progress['total'])) {
                $homework[] = ((int) $progress['completed'] / (int) $progress['total']) * 100;
            }
        }

        if (function_exists('tfp_week_get_test_result')) {
            $result = tfp_week_get_test_result($student_id, $lesson_id);
            if (is_array($result) && isset($result['score'], $result['total']) && is_numeric($result['score']) && is_numeric($result['total']) && (float) $result['total'] > 0) {
                $test[] = ((float) $result['score'] / (float) $result['total']) * 100;
            }
        }
    }

    return [
        'homework' => $homework ? array_sum($homework) / count($homework) : null,
        'test' => $test ? array_sum($test) / count($test) : null,
    ];
}

function tfp_facilitator_meetings_attendance($student_ids, $lesson_id)
{
    $present = 0;
    $recorded = 0;

    foreach (array_map('absint', (array) $student_ids) as $student_id) {
        $record = function_exists('tfp_facilitator_student_attendance')
            ? tfp_facilitator_student_attendance($student_id, $lesson_id)
            : get_user_meta($student_id, 'tfp_facilitator_student_attendance_' . absint($lesson_id), true);

        $status = is_array($record) ? strtoupper((string) ($record['status'] ?? '')) : '';
        if (!in_array($status, ['P', 'EA', 'UA'], true)) continue;

        $recorded++;
        if ($status === 'P') $present++;
    }

    return $recorded ? round(($present / $recorded) * 100) : null;
}

function tfp_facilitator_meetings_rows($cohorts)
{
    $rows = [];

    foreach ((array) $cohorts as $cohort) {
        $cohort_id = absint($cohort->ID);
        $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
        $schedule = (string) get_post_meta($cohort_id, '_schedule_text', true);
        $start_date = (string) get_post_meta($cohort_id, '_start_date', true);

        if (!$course_id || !function_exists('tfp_ld_get_weeks')) continue;

        $weeks = array_values((array) tfp_ld_get_weeks($course_id));
        $student_ids = function_exists('tfp_facilitator_cohort_students')
            ? (array) tfp_facilitator_cohort_students($cohort_id)
            : [];
        $zone = tfp_facilitator_meetings_schedule($schedule);

        foreach ($weeks as $index => $lesson) {
            if (!$lesson instanceof WP_Post) continue;

            $timestamp = tfp_facilitator_meetings_timestamp($start_date, $schedule, $index);
            if (!$timestamp) continue;

            $scores = tfp_facilitator_meetings_scores($student_ids, $lesson->ID);
            $attendance = tfp_facilitator_meetings_attendance($student_ids, $lesson->ID);

            $rows[] = [
                'cohort_id' => $cohort_id,
                'cohort_name' => wp_strip_all_tags($cohort->post_title),
                'course_id' => $course_id,
                'week_number' => $index + 1,
                'lesson_id' => absint($lesson->ID),
                'module' => wp_strip_all_tags($lesson->post_title),
                'timestamp' => $timestamp,
                'date_label' => wp_date('M j, Y', $timestamp, $zone['timezone']),
                'time_label' => wp_date('g:i A T', $timestamp, $zone['timezone']),
                'homework_label' => tfp_facilitator_meetings_letter($scores['homework']),
                'test_label' => tfp_facilitator_meetings_letter($scores['test']),
                'attendance_percent' => $attendance,
                'attendance_label' => $attendance !== null ? $attendance . '%' : '—',
                'meeting_url' => esc_url_raw((string) get_post_meta($lesson->ID, 'tfp_week_meeting_discord_url', true)),
                'description' => wp_strip_all_tags((string) get_post_meta($lesson->ID, 'tfp_week_meeting_description', true)),
                'facilitator' => wp_strip_all_tags((string) get_post_meta($lesson->ID, 'tfp_week_facilitator_name', true)),
                'notes' => wp_strip_all_tags((string) get_post_meta($lesson->ID, 'tfp_week_meeting_facilitator_notes', true)),
            ];
        }
    }

    usort($rows, static fn($a, $b) => ((int) $a['timestamp']) <=> ((int) $b['timestamp']));
    return $rows;
}

function tfp_facilitator_meetings_request_label($status)
{
    $labels = [
        'pending' => __('Pending', 'tfp-dashboard'),
        'in_progress' => __('In Progress', 'tfp-dashboard'),
        'needs_review' => __('Needs Review', 'tfp-dashboard'),
        'acknowledged' => __('Acknowledged', 'tfp-dashboard'),
        'approved' => __('Approved', 'tfp-dashboard'),
        'denied' => __('Denied', 'tfp-dashboard'),
    ];
    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function tfp_facilitator_meetings_requests($args = [])
{
    $args = wp_parse_args($args, [
        'status' => '',
        'type' => '',
        'cohort_id' => 0,
    ]);

    $meta_query = ['relation' => 'AND'];

    foreach ([
        '_tfp_request_status' => $args['status'],
        '_tfp_request_type' => $args['type'],
        '_tfp_request_cohort_id' => $args['cohort_id'],
    ] as $key => $value) {
        if ($value !== '' && $value !== 0) {
            $meta_query[] = [
                'key' => $key,
                'value' => is_numeric($value) ? absint($value) : sanitize_key($value),
            ];
        }
    }

    $posts = get_posts([
        'post_type' => 'tfp_meeting_request',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => count($meta_query) > 1 ? $meta_query : [],
    ]);

    $rows = [];
    foreach ($posts as $post) {
        $type = sanitize_key((string) get_post_meta($post->ID, '_tfp_request_type', true));
        $status = sanitize_key((string) get_post_meta($post->ID, '_tfp_request_status', true));
        $student_id = absint(get_post_meta($post->ID, '_tfp_request_student_id', true));
        $requester_id = absint(get_post_meta($post->ID, '_tfp_requester_id', true));
        $cohort_id = absint(get_post_meta($post->ID, '_tfp_request_cohort_id', true));
        $date = (string) get_post_meta($post->ID, '_tfp_request_date', true);
        $user = get_userdata($student_id ?: $requester_id);

        $rows[] = [
            'id' => absint($post->ID),
            'type' => $type,
            'type_label' => $type === 'student_absence' ? __('Student Absence', 'tfp-dashboard') : __('Cohort Skip', 'tfp-dashboard'),
            'requester' => $user ? $user->display_name : __('Facilitator', 'tfp-dashboard'),
            'student_id' => $student_id,
            'cohort_id' => $cohort_id,
            'cohort' => $cohort_id ? wp_strip_all_tags(get_the_title($cohort_id)) : '—',
            'date' => $date && strtotime($date) ? wp_date('M j, Y, g:i A', strtotime($date)) : '—',
            'reason' => (string) get_post_meta($post->ID, '_tfp_request_reason', true),
            'status' => $status ?: 'needs_review',
            'status_label' => tfp_facilitator_meetings_request_label($status ?: 'needs_review'),
        ];
    }

    return $rows;
}

function tfp_facilitator_meetings_create_request()
{
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_send_json_error(['message' => __('Permission denied.', 'tfp-dashboard')], 403);
    }
    check_ajax_referer('tfp_facilitator_meetings_nonce', 'nonce');

    $type = sanitize_key($_POST['type'] ?? 'cohort_skip');
    $cohort_id = absint($_POST['cohort_id'] ?? 0);
    $student_id = absint($_POST['student_id'] ?? 0);
    $date = sanitize_text_field($_POST['date'] ?? '');
    $reason = sanitize_textarea_field($_POST['reason'] ?? '');

    if (!in_array($type, ['cohort_skip', 'student_absence'], true)) {
        wp_send_json_error(['message' => __('Invalid request type.', 'tfp-dashboard')], 400);
    }

    $allowed_cohorts = array_map('absint', wp_list_pluck(tf_facilitator_meetings_cohorts_safe(), 'ID'));
    if (!$cohort_id || !in_array($cohort_id, $allowed_cohorts, true)) {
        wp_send_json_error(['message' => __('Please select a valid active cohort.', 'tfp-dashboard')], 400);
    }

    if (!$date || strtotime($date) === false || $reason === '') {
        wp_send_json_error(['message' => __('Please provide a valid date and reason.', 'tfp-dashboard')], 400);
    }

    if ($type === 'student_absence') {
        $students = function_exists('tfp_facilitator_cohort_students')
            ? array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))
            : [];
        if (!$student_id || !in_array($student_id, $students, true)) {
            wp_send_json_error(['message' => __('Please select a student from the selected cohort.', 'tfp-dashboard')], 400);
        }
    } else {
        $student_id = 0;
    }

    $request_id = wp_insert_post([
        'post_type' => 'tfp_meeting_request',
        'post_status' => 'publish',
        'post_title' => sprintf(
            '%s — %s — %s',
            $type === 'student_absence' ? __('Student Absence', 'tfp-dashboard') : __('Cohort Skip', 'tfp-dashboard'),
            get_the_title($cohort_id),
            $date
        ),
    ], true);

    if (is_wp_error($request_id)) {
        wp_send_json_error(['message' => $request_id->get_error_message()], 500);
    }

    update_post_meta($request_id, '_tfp_request_type', $type);
    update_post_meta($request_id, '_tfp_requester_id', get_current_user_id());
    update_post_meta($request_id, '_tfp_request_student_id', $student_id);
    update_post_meta($request_id, '_tfp_request_cohort_id', $cohort_id);
    update_post_meta($request_id, '_tfp_request_date', gmdate('Y-m-d H:i:s', strtotime($date)));
    update_post_meta($request_id, '_tfp_request_reason', $reason);
    update_post_meta($request_id, '_tfp_request_status', 'needs_review');

    wp_send_json_success(['message' => __('Request created.', 'tfp-dashboard')]);
}

function tfp_facilitator_meetings_update_request()
{
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_send_json_error(['message' => __('Permission denied.', 'tfp-dashboard')], 403);
    }
    check_ajax_referer('tfp_facilitator_meetings_nonce', 'nonce');

    $request_id = absint($_POST['request_id'] ?? 0);
    $status = sanitize_key($_POST['status'] ?? '');
    $allowed_statuses = ['pending','in_progress','needs_review','acknowledged','approved','denied'];

    if (!$request_id || get_post_type($request_id) !== 'tfp_meeting_request' || !in_array($status, $allowed_statuses, true)) {
        wp_send_json_error(['message' => __('Invalid request update.', 'tfp-dashboard')], 400);
    }

    $cohort_id = absint(get_post_meta($request_id, '_tfp_request_cohort_id', true));
    $allowed_cohorts = array_map('absint', wp_list_pluck(tf_facilitator_meetings_cohorts_safe(), 'ID'));
    if (!in_array($cohort_id, $allowed_cohorts, true)) {
        wp_send_json_error(['message' => __('You cannot update this request.', 'tfp-dashboard')], 403);
    }

    update_post_meta($request_id, '_tfp_request_status', $status);
    wp_send_json_success(['message' => __('Request updated.', 'tfp-dashboard')]);
}

function tf_facilitator_meetings_cohorts_safe()
{
    return tfp_facilitator_meetings_cohorts(get_current_user_id());
}

add_action('wp_ajax_tfp_facilitator_meeting_create_request', 'tfp_facilitator_meetings_create_request');
add_action('wp_ajax_tfp_facilitator_meeting_update_request', 'tfp_facilitator_meetings_update_request');

function tfp_facilitator_meetings_url($args = [])
{
    $url = tfp_dashboard_get_url('tfp-dashboard-facilitator-meetings');
    return $args ? add_query_arg($args, $url) : $url;
}

function tfp_facilitator_meetings_render()
{
    if (!function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_safe_redirect(tfp_dashboard_get_url('tfp-dashboard-facilitator-home'));
        exit;
    }

    $cohorts = tfp_facilitator_meetings_cohorts();
    $rows = tfp_facilitator_meetings_rows($cohorts);
    $now = current_time('timestamp');

    $upcoming_all = array_values(array_filter($rows, static fn($row) => (int) $row['timestamp'] >= $now));
    $past_all = array_values(array_filter($rows, static fn($row) => (int) $row['timestamp'] < $now));

    $tab = sanitize_key($_GET['tfp_meeting_tab'] ?? 'upcoming');
    if (!in_array($tab, ['upcoming','past','requests'], true)) $tab = 'upcoming';

    $search = sanitize_text_field($_GET['tfp_meeting_search'] ?? '');
    $cohort_filter = absint($_GET['tfp_meeting_cohort'] ?? 0);
    $sort = sanitize_key($_GET['tfp_meeting_sort'] ?? 'date');

    $filter_rows = static function ($items) use ($search, $cohort_filter, $sort) {
        $items = array_values(array_filter($items, static function ($row) use ($search, $cohort_filter) {
            if ($cohort_filter && (int) $row['cohort_id'] !== $cohort_filter) return false;
            if ($search !== '' && strpos(strtolower($row['cohort_name'] . ' ' . $row['module']), strtolower($search)) === false) return false;
            return true;
        }));

        usort($items, static function ($a, $b) use ($sort) {
            if ($sort === 'cohort') return strcasecmp($a['cohort_name'], $b['cohort_name']);
            if ($sort === 'module') return strcasecmp($a['module'], $b['module']);
            return (int) $a['timestamp'] <=> (int) $b['timestamp'];
        });
        return $items;
    };

    $upcoming = $filter_rows($upcoming_all);
    $past = array_reverse($filter_rows($past_all));

    $week_start = strtotime('monday this week', $now);
    $week_end = strtotime('sunday this week 23:59:59', $now);
    $upcoming_this_week = array_values(array_filter($upcoming_all, static fn($row) => (int) $row['timestamp'] >= $now && (int) $row['timestamp'] >= $week_start && (int) $row['timestamp'] <= $week_end));
    $next = $upcoming_all[0] ?? null;

    $thirty_days_ago = $now - (30 * DAY_IN_SECONDS);
    $attendance_values = [];
    foreach ($past_all as $row) {
        if ((int) $row['timestamp'] >= $thirty_days_ago && $row['attendance_percent'] !== null) {
            $attendance_values[] = (int) $row['attendance_percent'];
        }
    }
    $consistency = $attendance_values ? round(array_sum($attendance_values) / count($attendance_values)) : null;

    $unique_students = [];
    foreach ($cohorts as $cohort) {
        foreach ((array) (function_exists('tfp_facilitator_cohort_students') ? tfp_facilitator_cohort_students($cohort->ID) : []) as $student_id) {
            $unique_students[absint($student_id)] = true;
        }
    }

    $request_rows = tfp_facilitator_meetings_requests([
        'status' => sanitize_key($_GET['tfp_request_status'] ?? ''),
        'type' => sanitize_key($_GET['tfp_request_type'] ?? ''),
        'cohort_id' => $cohort_filter,
    ]);

    $request_pending = $request_skip = $request_absence = 0;
    foreach ($request_rows as $request) {
        if (!in_array($request['status'], ['approved','denied','acknowledged'], true)) {
            $request_pending++;
            if ($request['type'] === 'cohort_skip') $request_skip++;
            if ($request['type'] === 'student_absence') $request_absence++;
        }
    }

    $page = max(1, absint($_GET['tfp_meeting_page'] ?? 1));
    $per_page = 16;
    $current_rows = $tab === 'past' ? $past : $upcoming;
    $total = count($current_rows);
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($page, $pages);
    $page_rows = array_slice($current_rows, ($page - 1) * $per_page, $per_page);
    $start = $total ? (($page - 1) * $per_page) + 1 : 0;
    $end = min($page * $per_page, $total);

    $students_by_cohort = [];
    foreach ($cohorts as $cohort) {
        $students_by_cohort[(int) $cohort->ID] = [];
        foreach ((array) (function_exists('tfp_facilitator_cohort_students') ? tfp_facilitator_cohort_students($cohort->ID) : []) as $student_id) {
            $user = get_userdata($student_id);
            if ($user) {
                $students_by_cohort[(int) $cohort->ID][] = ['id' => (int) $student_id, 'name' => $user->display_name];
            }
        }
    }

    $meeting_url = tfp_facilitator_meetings_url();
    $nonce = wp_create_nonce('tfp_facilitator_meetings_nonce');
    ?>
    <div class="tfp-meetings-page" data-tfp-meetings-page>
        <header class="tfp-dash-pageheader tfp-meetings-header">
            <div class="tfp-dash-pageheader__crumb">
                <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
                <span>/</span><strong><?php esc_html_e('Meetings', 'tfp-dashboard'); ?></strong>
                <?php if ($tab === 'requests'): ?><span>/</span><strong><?php esc_html_e('Requests', 'tfp-dashboard'); ?></strong><?php endif; ?>
            </div>
            <h1 class="tfp-dash-pageheader__title"><?php esc_html_e('Meetings Overview', 'tfp-dashboard'); ?></h1>
            <p class="tfp-dash-pageheader__subtitle"><?php esc_html_e('View and manage upcoming and past meetings across all cohorts.', 'tfp-dashboard'); ?></p>
        </header>

        <section class="tfp-facilitator-metrics tfp-meetings-metrics">
            <div class="tfp-facilitator-metric">
                <span><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></span>
                <strong><?php echo esc_html(count($cohorts)); ?></strong>
                <small><?php echo esc_html(count($unique_students)); ?> <?php esc_html_e('Total Students', 'tfp-dashboard'); ?></small>
            </div>
            <div class="tfp-facilitator-metric tfp-facilitator-metric--teal">
                <span><?php esc_html_e('Upcoming Meetings This Week', 'tfp-dashboard'); ?></span>
                <strong><?php echo esc_html(count($upcoming_this_week)); ?></strong>
                <small><?php echo $next ? esc_html__('Next: ', 'tfp-dashboard') . esc_html($next['date_label'] . ' • ' . $next['cohort_name']) : esc_html__('No upcoming meetings scheduled.', 'tfp-dashboard'); ?></small>
            </div>
            <div class="tfp-facilitator-metric tfp-facilitator-metric--teal">
                <span><?php esc_html_e('Meeting Consistency', 'tfp-dashboard'); ?></span>
                <strong><?php echo $consistency !== null ? esc_html($consistency . '%') : '—'; ?></strong>
                <small><?php esc_html_e('Attendance Rate (Last 30 Days)', 'tfp-dashboard'); ?></small>
            </div>
            <div class="tfp-facilitator-metric tfp-facilitator-metric--gold">
                <span><?php esc_html_e('Schedule Requests', 'tfp-dashboard'); ?></span>
                <strong><?php echo esc_html($request_pending); ?></strong>
                <small><?php printf(esc_html__('Skip: %d • Absence: %d', 'tfp-dashboard'), $request_skip, $request_absence); ?></small>
            </div>
        </section>

        <nav class="tfp-meetings-tabs" aria-label="<?php esc_attr_e('Meeting sections', 'tfp-dashboard'); ?>">
            <a class="<?php echo $tab === 'upcoming' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tfp_meeting_tab'=>'upcoming'], $meeting_url)); ?>"><?php esc_html_e('Upcoming Meetings', 'tfp-dashboard'); ?></a>
            <a class="<?php echo $tab === 'past' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tfp_meeting_tab'=>'past'], $meeting_url)); ?>"><?php esc_html_e('Past Meetings', 'tfp-dashboard'); ?></a>
            <a class="<?php echo $tab === 'requests' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tfp_meeting_tab'=>'requests'], $meeting_url)); ?>"><?php esc_html_e('Requests', 'tfp-dashboard'); ?></a>
        </nav>

        <?php if ($tab === 'requests'): ?>
            <section class="tfp-meetings-panel">
                <div class="tfp-meetings-panel__head tfp-meetings-panel__head--requests">
                    <form method="get" class="tfp-meetings-filter-form tfp-facilitator-filters tfp-meetings-filter-form--requests">
                        <input type="hidden" name="tfp_meeting_tab" value="requests">
                        <select name="tfp_request_type" aria-label="<?php esc_attr_e('Request type', 'tfp-dashboard'); ?>">
                            <option value=""><?php esc_html_e('All Request Types', 'tfp-dashboard'); ?></option>
                            <option value="cohort_skip" <?php selected($_GET['tfp_request_type'] ?? '', 'cohort_skip'); ?>><?php esc_html_e('Cohort Skip', 'tfp-dashboard'); ?></option>
                            <option value="student_absence" <?php selected($_GET['tfp_request_type'] ?? '', 'student_absence'); ?>><?php esc_html_e('Student Absence', 'tfp-dashboard'); ?></option>
                        </select>
                        <select name="tfp_request_status" aria-label="<?php esc_attr_e('Request status', 'tfp-dashboard'); ?>">
                            <option value=""><?php esc_html_e('Status: All Pending', 'tfp-dashboard'); ?></option>
                            <option value="pending" <?php selected($_GET['tfp_request_status'] ?? '', 'pending'); ?>><?php esc_html_e('Pending', 'tfp-dashboard'); ?></option>
                            <option value="in_progress" <?php selected($_GET['tfp_request_status'] ?? '', 'in_progress'); ?>><?php esc_html_e('In Progress', 'tfp-dashboard'); ?></option>
                            <option value="needs_review" <?php selected($_GET['tfp_request_status'] ?? '', 'needs_review'); ?>><?php esc_html_e('Needs Review', 'tfp-dashboard'); ?></option>
                            <option value="acknowledged" <?php selected($_GET['tfp_request_status'] ?? '', 'acknowledged'); ?>><?php esc_html_e('Acknowledged', 'tfp-dashboard'); ?></option>
                            <option value="approved" <?php selected($_GET['tfp_request_status'] ?? '', 'approved'); ?>><?php esc_html_e('Approved', 'tfp-dashboard'); ?></option>
                            <option value="denied" <?php selected($_GET['tfp_request_status'] ?? '', 'denied'); ?>><?php esc_html_e('Denied', 'tfp-dashboard'); ?></option>
                        </select>
                    </form>
                    <button type="button" class="tfp-meetings-create-btn" data-tfp-open-request-create><span aria-hidden="true">+</span><?php esc_html_e('Create New Skip Request', 'tfp-dashboard'); ?></button>
                </div>

                <div class="tfp-dash-table-wrap tfp-meetings-table-wrap">
                    <table class="tfp-dash-table tfp-meetings-table">
                        <thead><tr>
                            <th><?php esc_html_e('Request Category', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Requested By', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Affected Date', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Reason', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if ($request_rows): foreach ($request_rows as $request): ?>
                            <tr>
                                <td><span class="tfp-meetings-request-tag tfp-meetings-request-tag--<?php echo esc_attr($request['type']); ?>"><?php echo esc_html($request['type_label']); ?></span></td>
                                <td><?php echo esc_html($request['requester']); ?></td>
                                <td class="tfp-meetings-date"><?php echo esc_html($request['date']); ?></td>
                                <td><?php echo esc_html($request['reason']); ?></td>
                                <td><span class="tfp-meetings-status tfp-meetings-status--<?php echo esc_attr($request['status']); ?>"><?php echo esc_html($request['status_label']); ?></span></td>
                                <td><button type="button" class="tfp-meetings-action-btn is-outline" data-tfp-request="<?php echo esc_attr(wp_json_encode($request)); ?>"><?php echo in_array($request['status'], ['pending','in_progress','needs_review'], true) ? esc_html__('Add', 'tfp-dashboard') : esc_html__('View', 'tfp-dashboard'); ?></button></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6"><div class="tfp-facilitator-empty"><?php esc_html_e('No meeting requests found.', 'tfp-dashboard'); ?></div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <footer class="tfp-meetings-pagination"><span><?php printf(esc_html__('%1$d - %2$d of %3$d items', 'tfp-dashboard'), $request_rows ? 1 : 0, count($request_rows), count($request_rows)); ?></span></footer>
            </section>
        <?php else: ?>
            <section class="tfp-meetings-panel">
                <div class="tfp-meetings-panel__head">
                    <h2><?php echo $tab === 'past' ? esc_html__('Active Cohorts', 'tfp-dashboard') : esc_html__('All Upcoming Meetings', 'tfp-dashboard'); ?></h2>
                    <form method="get" class="tfp-meetings-filter-form tfp-facilitator-filters">
                        <input type="hidden" name="tfp_meeting_tab" value="<?php echo esc_attr($tab); ?>">
                        <div class="tfp-dash-search tfp-facilitator-search">
                            <span class="tfp-dash-search__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                    <path d="M16 16L13 13M7.0002 17H4.19692C3.07901 17 2.5192 17 2.0918 16.7822C1.71547 16.5905 1.40973 16.2842 1.21799 15.9079C1 15.4801 1 14.9203 1 13.8002V4.2002C1 3.08009 1 2.51962 1.21799 2.0918C1.40973 1.71547 1.71547 1.40973 2.0918 1.21799C2.51962 1 3.08009 1 4.2002 1H13.8002C14.9203 1 15.4796 1 15.9074 1.21799C16.2837 1.40973 16.5905 1.71547 16.7822 2.0918C17 2.5192 17 3.07899 17 4.19691V7.0002M10.5 14C8.567 14 7 12.433 7 10.5C7 8.567 8.567 7 10.5 7C12.433 7 14 8.567 14 10.5C14 12.433 12.433 14 10.5 14Z" stroke="#151411" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </span>
                            <input type="search" name="tfp_meeting_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>" aria-label="<?php esc_attr_e('Search meetings', 'tfp-dashboard'); ?>">
                        </div>
                        <select name="tfp_meeting_cohort" aria-label="<?php esc_attr_e('Filter by cohort', 'tfp-dashboard'); ?>">
                            <option value="0"><?php esc_html_e('All Cohorts', 'tfp-dashboard'); ?></option>
                            <?php foreach ($cohorts as $cohort): ?>
                                <option value="<?php echo esc_attr($cohort->ID); ?>" <?php selected($cohort_filter, $cohort->ID); ?>><?php echo esc_html(wp_strip_all_tags($cohort->post_title)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="tfp_meeting_sort" aria-label="<?php esc_attr_e('Sort meetings', 'tfp-dashboard'); ?>">
                            <option value="date" <?php selected($sort, 'date'); ?>><?php esc_html_e('Sort: Date', 'tfp-dashboard'); ?></option>
                            <option value="cohort" <?php selected($sort, 'cohort'); ?>><?php esc_html_e('Sort: Cohort', 'tfp-dashboard'); ?></option>
                            <option value="module" <?php selected($sort, 'module'); ?>><?php esc_html_e('Sort: Module', 'tfp-dashboard'); ?></option>
                        </select>
                    </form>
                </div>
                <div class="tfp-dash-table-wrap tfp-meetings-table-wrap">
                    <table class="tfp-dash-table tfp-meetings-table">
                        <thead><tr>
                            <th><?php esc_html_e('Meeting Date', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Cohort', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Meeting Module', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Homework Avg.', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Test Avg.', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if ($page_rows): foreach ($page_rows as $row): ?>
                            <tr>
                                <td class="tfp-meetings-date-cell"><strong><?php echo esc_html($row['date_label']); ?></strong><small><?php echo esc_html($row['time_label']); ?></small></td>
                                <td><?php echo esc_html($row['cohort_name']); ?></td>
                                <td><?php echo esc_html($row['module']); ?></td>
                                <td><span class="tfp-meetings-grade"><?php echo esc_html($row['homework_label']); ?></span></td>
                                <td><span class="tfp-meetings-grade"><?php echo esc_html($row['test_label']); ?></span></td>
                                <td><span class="tfp-meetings-attendance"><?php echo esc_html($row['attendance_label']); ?></span></td>
                                <td><button type="button" class="tfp-meetings-action-btn" data-tfp-meeting="<?php echo esc_attr(wp_json_encode($row)); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></button></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="7"><div class="tfp-facilitator-empty"><?php echo $tab === 'past' ? esc_html__('No past meetings found.', 'tfp-dashboard') : esc_html__('No upcoming meetings found.', 'tfp-dashboard'); ?></div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <footer class="tfp-meetings-pagination">
                    <span><?php printf(esc_html__('%1$d - %2$d of %3$d items', 'tfp-dashboard'), $start, $end, $total); ?></span>
                    <div>
                        <?php
                        $base = ['tfp_meeting_tab' => $tab, 'tfp_meeting_search' => $search, 'tfp_meeting_cohort' => $cohort_filter, 'tfp_meeting_sort' => $sort];
                        $prev = add_query_arg(array_merge($base, ['tfp_meeting_page' => max(1, $page - 1)]), $meeting_url);
                        $next_url = add_query_arg(array_merge($base, ['tfp_meeting_page' => min($pages, $page + 1)]), $meeting_url);
                        ?>
                        <a class="<?php echo $page <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo esc_url($prev); ?>">‹ <?php esc_html_e('Previous', 'tfp-dashboard'); ?></a>
                        <a class="<?php echo $page >= $pages ? 'is-disabled' : ''; ?>" href="<?php echo esc_url($next_url); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</a>
                    </div>
                </footer>
            </section>
        <?php endif; ?>

        <div class="tfp-meetings-modal" data-tfp-meeting-modal aria-hidden="true">
            <div class="tfp-meetings-modal__backdrop" data-tfp-modal-close></div>
            <div class="tfp-meetings-modal__dialog" role="dialog" aria-modal="true">
                <button type="button" class="tfp-meetings-modal__close" data-tfp-modal-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
                <h2 data-tfp-modal-title></h2>
                <div class="tfp-meetings-modal__body" data-tfp-modal-body></div>
                <div class="tfp-meetings-modal__actions" data-tfp-modal-actions></div>
            </div>
        </div>

        <div class="tfp-meetings-modal" data-tfp-create-modal aria-hidden="true">
            <div class="tfp-meetings-modal__backdrop" data-tfp-modal-close></div>
            <div class="tfp-meetings-modal__dialog" role="dialog" aria-modal="true">
                <button type="button" class="tfp-meetings-modal__close" data-tfp-modal-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button>
                <h2><?php esc_html_e('Create New Skip Request', 'tfp-dashboard'); ?></h2>
                <form data-tfp-request-form>
                    <label><?php esc_html_e('Request Type', 'tfp-dashboard'); ?>
                        <select name="type" data-tfp-request-type>
                            <option value="cohort_skip"><?php esc_html_e('Cohort Skip', 'tfp-dashboard'); ?></option>
                            <option value="student_absence"><?php esc_html_e('Student Absence', 'tfp-dashboard'); ?></option>
                        </select>
                    </label>
                    <label><?php esc_html_e('Cohort', 'tfp-dashboard'); ?>
                        <select name="cohort_id" data-tfp-request-cohort required>
                            <option value=""><?php esc_html_e('Select Cohort', 'tfp-dashboard'); ?></option>
                            <?php foreach ($cohorts as $cohort): ?>
                                <option value="<?php echo esc_attr($cohort->ID); ?>"><?php echo esc_html(wp_strip_all_tags($cohort->post_title)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label data-tfp-student-wrap hidden><?php esc_html_e('Student', 'tfp-dashboard'); ?>
                        <select name="student_id" data-tfp-request-student><option value=""><?php esc_html_e('Select Student', 'tfp-dashboard'); ?></option></select>
                    </label>
                    <label><?php esc_html_e('Affected Date', 'tfp-dashboard'); ?><input type="datetime-local" name="date" required></label>
                    <label><?php esc_html_e('Reason', 'tfp-dashboard'); ?><textarea name="reason" rows="4" required></textarea></label>
                    <button type="submit" class="tfp-meetings-create-btn"><?php esc_html_e('Create Request', 'tfp-dashboard'); ?></button>
                </form>
            </div>
        </div>

        <script type="application/json" data-tfp-meetings-config><?php echo wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'studentsByCohort' => $students_by_cohort,
        ]); ?></script>
    </div>
    <?php
}
