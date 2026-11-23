<?php
/**
 * Admin cohort detail screen.
 * Reads the existing cohort CPT, LearnDash progress and facilitator attendance.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_cohort_detail_url($cohort_id) {
    return home_url('/admin-cohort/' . absint($cohort_id) . '/');
}

function tfp_admin_cohort_detail_request_id() {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    return preg_match('~(?:^|/)admin-cohort/(\d+)/?$~', $path, $match) ? absint($match[1]) : 0;
}

add_filter('pre_handle_404', function ($preempt, $query) {
    if (tfp_admin_cohort_detail_request_id()) {
        if ($query) { $query->is_404 = false; $query->is_page = true; }
        status_header(200);
        return true;
    }
    return $preempt;
}, 13, 2);

add_action('template_redirect', function () {
    if (!tfp_admin_cohort_detail_request_id()) { return; }
    status_header(200);
    nocache_headers();
    $template = TFP_DASH_PATH . 'templates/template-admin-cohort-detail.php';
    if (is_readable($template)) { include $template; exit; }
}, 2);

// Set the browser tab title to the actual cohort name instead of the fallback WordPress page title.
add_filter('pre_get_document_title', function ($document_title) {
    $cohort_id = tfp_admin_cohort_detail_request_id();
    if (!$cohort_id) { return $document_title; }
    $cohort_title = get_the_title($cohort_id);
    return $cohort_title ? sprintf('%s – %s', $cohort_title, get_bloginfo('name')) : $document_title;
}, 20);

function tfp_admin_cohort_detail_data($cohort_id) {
    $cohort = get_post(absint($cohort_id));
    if (!$cohort || $cohort->post_type !== 'tfp_cohort') { return null; }

    $course_id = absint(get_post_meta($cohort->ID, '_related_course', true));
    $course = $course_id ? get_post($course_id) : null;
    $facilitator_id = function_exists('tfp_cohort_get_facilitator_user_id')
        ? absint(tfp_cohort_get_facilitator_user_id($cohort->ID))
        : absint(get_post_meta($cohort->ID, '_facilitator_user_id', true));
    $facilitator = $facilitator_id ? get_userdata($facilitator_id) : false;
    $students = function_exists('tfp_facilitator_cohort_students')
        ? tfp_facilitator_cohort_students($cohort->ID)
        : get_users(['meta_key' => 'tfp_cohort_choice', 'meta_value' => $cohort->ID, 'fields' => 'ID']);
    $students = array_values(array_unique(array_map('absint', (array) $students)));
    $weeks = ($course_id && function_exists('tfp_ld_get_weeks')) ? (array) tfp_ld_get_weeks($course_id) : [];

    $completed_total = 0;
    $possible_total = 0;
    $student_progress = [];
    foreach ($students as $student_id) {
        $journey = function_exists('tfp_ld_get_journey_progress')
            ? tfp_ld_get_journey_progress($student_id, $course_id)
            : ['completed' => 0, 'total' => count($weeks)];
        $done = absint($journey['completed'] ?? 0);
        $total = absint($journey['total'] ?? count($weeks));
        $completed_total += $done;
        $possible_total += $total;
        $student_progress[$student_id] = ['completed' => $done, 'total' => $total];
    }
    $average_progress = $possible_total > 0 ? (int) round(($completed_total / $possible_total) * 100) : 0;

    $attendance_present = 0;
    $attendance_recorded = 0;
    $student_attendance = [];
    foreach ($students as $student_id) {
        $present = 0;
        $recorded = 0;
        foreach ($weeks as $week) {
            $record = get_user_meta($student_id, 'tfp_facilitator_student_attendance_' . absint($week->ID), true);
            $status = is_array($record) ? strtoupper((string) ($record['status'] ?? '')) : '';
            if (!in_array($status, ['P', 'EA', 'UA'], true)) { continue; }
            $recorded++;
            if ($status === 'P') { $present++; }
        }
        $attendance_present += $present;
        $attendance_recorded += $recorded;
        $student_attendance[$student_id] = ['present' => $present, 'recorded' => $recorded];
    }
    $attendance_rate = $attendance_recorded > 0 ? (int) round(($attendance_present / $attendance_recorded) * 100) : null;

    $start_date = (string) get_post_meta($cohort->ID, '_start_date', true);
    $elapsed_weeks = ($start_date && strtotime($start_date))
        ? max(1, (int) floor(max(0, current_time('timestamp') - strtotime($start_date)) / WEEK_IN_SECONDS) + 1)
        : 1;
    $current_week = $weeks ? min($elapsed_weeks, count($weeks)) : 0;
    $week_completion = [];
    foreach ($weeks as $index => $week) {
        $done = 0;
        foreach ($students as $student_id) {
            if (function_exists('tfp_ld_is_week_complete') && tfp_ld_is_week_complete($student_id, $week->ID)) { $done++; }
        }
        $week_completion[] = [
            'number' => $index + 1,
            'title' => get_the_title($week),
            'completed' => $done,
            'total' => count($students),
            'complete' => count($students) > 0 && $done === count($students),
            'current' => ($index + 1) === $current_week,
        ];
    }

    $homework_reviewed = 0;
    if ($facilitator_id && $weeks) {
        foreach ($weeks as $week) {
            if (get_user_meta($facilitator_id, 'tfp_facilitator_review_homework_' . absint($week->ID), true)) {
                $homework_reviewed++;
            }
        }
    }
    $homework_review_rate = $weeks ? (int) round(($homework_reviewed / count($weeks)) * 100) : null;

    $alerts = [];
    $inactive_count = 0;
    foreach ($student_progress as $progress) {
        if ($progress['completed'] === 0 && $elapsed_weeks > 2) { $inactive_count++; }
    }
    if ($inactive_count > 0) {
        $alerts[] = ['type' => 'warning', 'icon' => '!', 'title' => sprintf(_n('%d student has not completed a week yet', '%d students have not completed a week yet', $inactive_count, 'tfp-dashboard'), $inactive_count), 'detail' => __('Based on recorded course progress', 'tfp-dashboard'), 'link' => 'roster', 'link_label' => __('View Roster', 'tfp-dashboard')];
    }
    if ($attendance_rate !== null && $attendance_rate < 70) {
        $alerts[] = ['type' => 'warning', 'icon' => '↘', 'title' => __('Attendance is below the 70% threshold', 'tfp-dashboard'), 'detail' => sprintf(__('Recorded attendance: %s%%', 'tfp-dashboard'), $attendance_rate), 'link' => 'roster', 'link_label' => __('View Roster', 'tfp-dashboard')];
    }
    if (!$alerts) {
        $alerts[] = ['type' => 'success', 'icon' => '✓', 'title' => __('No cohort health flags detected', 'tfp-dashboard'), 'detail' => __('Current progress and attendance records are within the available reporting data.', 'tfp-dashboard'), 'link' => '', 'link_label' => ''];
    }

    return [
        'cohort' => $cohort, 'course' => $course, 'course_id' => $course_id,
        'facilitator' => $facilitator, 'facilitator_id' => $facilitator_id,
        'students' => $students, 'student_progress' => $student_progress,
        'weeks' => $weeks, 'week_completion' => $week_completion,
        'average_progress' => $average_progress, 'attendance_rate' => $attendance_rate,
        'attendance_present' => $attendance_present, 'attendance_recorded' => $attendance_recorded,
        'student_attendance' => $student_attendance, 'start_date' => $start_date,
        'schedule' => (string) get_post_meta($cohort->ID, '_schedule_text', true),
        'meeting_url' => (string) get_post_meta($cohort->ID, '_meeting_url', true),
        'capacity' => function_exists('tfp_cohort_seats_total') ? tfp_cohort_seats_total($cohort->ID) : absint(get_post_meta($cohort->ID, '_seats_total', true)),
        'current_week' => $current_week, 'elapsed_weeks' => $elapsed_weeks,
        'homework_review_rate' => $homework_review_rate, 'alerts' => $alerts,
        'status' => $cohort->post_status === 'publish' ? __('Active', 'tfp-dashboard') : __('Draft', 'tfp-dashboard'),
    ];
}


// Save a cohort-specific holiday/skip from the Schedule modal.
add_action('admin_post_tfp_admin_cohort_add_skip', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to manage cohort schedules.', 'tfp-dashboard'), '', ['response' => 403]);
    }

    $cohort_id = absint($_POST['cohort_id'] ?? 0);
    check_admin_referer('tfp_admin_cohort_add_skip_' . $cohort_id, 'tfp_cohort_skip_nonce');

    $cohort = get_post($cohort_id);
    if (!$cohort || $cohort->post_type !== 'tfp_cohort') {
        wp_die(esc_html__('The selected cohort could not be found.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    $week_number = absint($_POST['week_number'] ?? 0);
    $reason = sanitize_text_field(wp_unslash($_POST['skip_reason'] ?? ''));
    $meeting_room = esc_url_raw(wp_unslash($_POST['meeting_room'] ?? ''));
    $push_progression = !empty($_POST['push_progression']) ? 1 : 0;
    $weeks = tfp_admin_cohort_detail_data($cohort_id);

    if (!$week_number || $week_number > count($weeks['weeks']) || $reason === '') {
        wp_die(esc_html__('Please provide a valid cohort, meeting week and skip reason.', 'tfp-dashboard'), '', ['response' => 400]);
    }

    $start_date = (string) $weeks['start_date'];
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $start_date, wp_timezone());
    if (!$date) {
        wp_die(esc_html__('Set a valid cohort start date before adding a schedule skip.', 'tfp-dashboard'), '', ['response' => 400]);
    }
    $date = $date->modify('+' . ($week_number - 1) . ' weeks')->format('Y-m-d');

    $post_id = wp_insert_post([
        'post_type' => 'tfp_off_day',
        'post_status' => 'publish',
        'post_title' => $reason,
    ], true);

    if (is_wp_error($post_id)) {
        wp_die(esc_html__('The holiday / skip could not be saved. Please try again.', 'tfp-dashboard'), '', ['response' => 500]);
    }

    update_post_meta($post_id, '_date', $date);
    update_post_meta($post_id, '_cohort_id', $cohort_id);
    update_post_meta($post_id, '_week_number', $week_number);
    update_post_meta($post_id, '_reason', $reason);
    update_post_meta($post_id, '_push_progression', $push_progression);

    if ($meeting_room) {
        update_post_meta($cohort_id, '_meeting_url', $meeting_room);
    }

    wp_safe_redirect(add_query_arg(['tab' => 'schedule', 'schedule_notice' => 'saved'], tfp_admin_cohort_detail_url($cohort_id)));
    exit;
});


add_action('admin_post_tfp_admin_save_cohort_governance', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to update cohort governance.', 'tfp-dashboard'), '', ['response' => 403]);
    }

    $cohort_id = absint($_POST['cohort_id'] ?? 0);
    check_admin_referer('tfp_admin_save_cohort_governance_' . $cohort_id, 'tfp_governance_nonce');
    $cohort = get_post($cohort_id);
    if (!$cohort || $cohort->post_type !== 'tfp_cohort') {
        wp_die(esc_html__('Cohort not found.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    $trigger = sanitize_key(wp_unslash($_POST['unlock_trigger'] ?? 'meeting_day'));
    $block_clear = sanitize_key(wp_unslash($_POST['block_clear'] ?? 'admin'));
    $settings = [
        'auto_unlock' => !empty($_POST['auto_unlock']) ? 1 : 0,
        'unlock_trigger' => in_array($trigger, ['meeting_day', '24_hours', 'manual'], true) ? $trigger : 'meeting_day',
        'prerequisite_lock' => !empty($_POST['prerequisite_lock']) ? 1 : 0,
        'flags_enabled' => !empty($_POST['flags_enabled']) ? 1 : 0,
        'flag_inactive_days' => min(365, max(1, absint($_POST['flag_inactive_days'] ?? 7))),
        'flag_missed_meetings' => min(52, max(1, absint($_POST['flag_missed_meetings'] ?? 1))),
        'flag_weeks_behind' => min(52, max(1, absint($_POST['flag_weeks_behind'] ?? 1))),
        'block_enabled' => !empty($_POST['block_enabled']) ? 1 : 0,
        'block_weeks_behind' => min(52, max(1, absint($_POST['block_weeks_behind'] ?? 3))),
        'block_missed_meetings' => min(52, max(1, absint($_POST['block_missed_meetings'] ?? 2))),
        'block_clear' => in_array($block_clear, ['admin', 'facilitator'], true) ? $block_clear : 'admin',
    ];

    update_post_meta($cohort_id, '_tfp_cohort_governance', $settings);
    wp_safe_redirect(add_query_arg(['tab' => 'settings', 'governance_notice' => 'saved'], tfp_admin_cohort_detail_url($cohort_id)));
    exit;
});

/**
 * Return genuine activity events for students enrolled in this cohort.
 * Uses the existing audit table; no sample/demo activity is generated.
 */
function tfp_admin_cohort_activity_demo_rows() {
    // Local-only preview rows: never inserted into the database.
    $samples = [
        ['Sarah Jenkins', 'progress', 'Auto-Block triggered: 2+ Weeks Behind', 'Blocked', 0],
        ['Marcus Reed', 'attendance', 'Admin Flag: Pastoral Concern', 'Attention', 1],
        ['John Doe', 'quiz', 'Failed Module 1 Final Quiz', 'Attention', 2],
        ['David Miller', 'quiz', 'Week 23 – Purpose unlocked', 'On Track', 3],
        ['Rachel Smith', 'login', 'No activity in 7 days', 'Attention', 4],
        ['Chris Evans', 'course', '1 Week behind schedule', 'Attention', 5],
        ['Jason Bourne', 'test', 'Required Reflection not submitted', 'Blocked', 6],
        ['Mary Jane', 'course', 'Week 24 content auto-unlocked', 'On Track', 7],
        ['Farris Bingo', 'attendance', '3 Total missed sessions', 'Blocked', 8],
        ['June Tango', 'profile', 'Discussion requirements unmet', 'Attention', 9],
    ];
    $rows = [];
    foreach ($samples as $index => $sample) {
        $created = gmdate('Y-m-d H:i:s', time() - ($index * DAY_IN_SECONDS));
        $rows[] = (object) [
            'id' => 0,
            'user_id' => 900001 + $index,
            'event_type' => $sample[1],
            'event_label' => $sample[2],
            'context' => '',
            'created_at' => $created,
            'display_name' => $sample[0],
            'demo_status' => strtolower(str_replace(' ', '-', $sample[3])),
            'is_demo' => true,
        ];
    }
    return $rows;
}

/**
 * Return real cohort activity, or local-only preview rows when no real events exist.
 */
function tfp_admin_cohort_activity_query($cohort_id, $student_ids) {
    global $wpdb;
    $student_ids = array_values(array_unique(array_filter(array_map('absint', (array) $student_ids))));
    $per_page = 10;
    $page = max(1, absint($_GET['activity_page'] ?? 1));
    $type = sanitize_key(wp_unslash($_GET['activity_type'] ?? ''));
    $user_id = absint($_GET['activity_user'] ?? 0);
    $search = sanitize_text_field(wp_unslash($_GET['activity_search'] ?? ''));
    $type_map = [
        'progress' => ['course', 'homework'],
        'attendance' => ['attendance', 'flag'],
        'assessment' => ['quiz', 'test'],
        'engagement' => ['login', 'logout', 'profile'],
        'access' => ['registration', 'role', 'payment', 'document', 'account', 'cohort'],
    ];
    $empty = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => $per_page, 'is_demo' => false];

    // Check the real activity log first. Preview data is only used when it has no records.
    $real_rows = [];
    if ($student_ids && function_exists('tfp_user_activity_table')) {
        $table = tfp_user_activity_table();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($exists === $table) {
            $where = ['a.user_id IN (' . implode(',', array_fill(0, count($student_ids), '%d')) . ')'];
            $args = $student_ids;
            if ($user_id && in_array($user_id, $student_ids, true)) {
                $where[] = 'a.user_id = %d';
                $args[] = $user_id;
            }
            if (isset($type_map[$type])) {
                $where[] = 'a.event_type IN (' . implode(',', array_fill(0, count($type_map[$type]), '%s')) . ')';
                $args = array_merge($args, $type_map[$type]);
            }
            $cohort_title = get_the_title($cohort_id);
            $searches_cohort = ($search !== '' && $cohort_title && stripos($cohort_title, $search) !== false);
            if ($search !== '' && !$searches_cohort) {
                $like = '%' . $wpdb->esc_like($search) . '%';
                $where[] = '(u.display_name LIKE %s OR a.event_label LIKE %s OR a.context LIKE %s)';
                array_push($args, $like, $like, $like);
            }
            $join = " FROM {$table} a INNER JOIN {$wpdb->users} u ON u.ID = a.user_id ";
            $condition = ' WHERE ' . implode(' AND ', $where);
            $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*)' . $join . $condition, $args));
            if ($total > 0) {
                $pages = max(1, (int) ceil($total / $per_page));
                $page = min($page, $pages);
                $query = 'SELECT a.id, a.user_id, a.event_type, a.event_label, a.context, a.created_at, u.display_name' . $join . $condition . ' ORDER BY a.created_at DESC, a.id DESC LIMIT %d OFFSET %d';
                $real_rows = $wpdb->get_results($wpdb->prepare($query, array_merge($args, [$per_page, ($page - 1) * $per_page])));
                return ['rows' => $real_rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per_page, 'is_demo' => false];
            }
        }
    }

    // Demo rows are in-memory only and are never saved to WordPress or the activity table.
    $demo_rows = tfp_admin_cohort_activity_demo_rows();
    if (isset($type_map[$type])) {
        $demo_rows = array_values(array_filter($demo_rows, static function ($row) use ($type_map, $type) {
            return in_array($row->event_type, $type_map[$type], true);
        }));
    }
    if ($user_id) {
        $demo_rows = array_values(array_filter($demo_rows, static function ($row) use ($user_id) {
            return (int) $row->user_id === $user_id;
        }));
    }
    if ($search !== '') {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
        $demo_rows = array_values(array_filter($demo_rows, static function ($row) use ($needle, $cohort_id) {
            $haystack = $row->display_name . ' ' . $row->event_label . ' ' . get_the_title($cohort_id);
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
            return strpos($haystack, $needle) !== false;
        }));
    }
    $total = count($demo_rows);
    $pages = max(1, (int) ceil($total / $per_page));
    $page = min($page, $pages);
    $demo_rows = array_slice($demo_rows, ($page - 1) * $per_page, $per_page);
    return ['rows' => $demo_rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $per_page, 'is_demo' => true];
}

function tfp_admin_cohort_activity_type_label($event_type) {
    $map = [
        'course' => 'Progress', 'homework' => 'Progress',
        'attendance' => 'Attendance', 'flag' => 'Attendance',
        'quiz' => 'Assessment', 'test' => 'Assessment',
        'login' => 'Engagement', 'logout' => 'Engagement', 'profile' => 'Engagement',
        'registration' => 'Access', 'role' => 'Access', 'payment' => 'Access',
        'document' => 'Access', 'account' => 'Access', 'cohort' => 'Access',
    ];
    return $map[$event_type] ?? 'Activity';
}

function tfp_admin_cohort_activity_status($row) {
    $text = strtolower((string) $row->event_label . ' ' . (string) $row->context);
    if (strpos($text, 'blocked') !== false || strpos($text, 'hard block') !== false) { return 'blocked'; }
    if (in_array($row->event_type, ['flag', 'attendance'], true) || strpos($text, 'failed') !== false || strpos($text, 'missed') !== false || strpos($text, 'inactive') !== false || strpos($text, 'flag') !== false) { return 'attention'; }
    return 'on-track';
}

function tfp_admin_cohort_detail_render() {
    if (!is_user_logged_in()) { auth_redirect(); }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }
    $id = tfp_admin_cohort_detail_request_id();
    $data = tfp_admin_cohort_detail_data($id);
    $governance_defaults = [
        'auto_unlock' => 1, 'unlock_trigger' => 'meeting_day', 'prerequisite_lock' => 0,
        'flags_enabled' => 1, 'flag_inactive_days' => 7, 'flag_missed_meetings' => 1, 'flag_weeks_behind' => 1,
        'block_enabled' => 1, 'block_weeks_behind' => 3, 'block_missed_meetings' => 2, 'block_clear' => 'admin',
    ];
    $governance = wp_parse_args(get_post_meta($id, '_tfp_cohort_governance', true), $governance_defaults);
    $governance_saved = isset($_GET['governance_notice']) && sanitize_key($_GET['governance_notice']) === 'saved';
    if (!$data) { wp_die(esc_html__('Cohort not found.', 'tfp-dashboard'), esc_html__('Not found', 'tfp-dashboard'), ['response' => 404]); }

    $user = wp_get_current_user();
    $nav = function_exists('tfp_admin_dashboard_nav_items') ? tfp_admin_dashboard_nav_items() : [];
    foreach ($nav as &$item) {
        $item['active'] = ($item['slug'] ?? '') === 'cohorts';
        if (($item['slug'] ?? '') === 'cohorts') { $item['url'] = tfp_admin_cohorts_url(); }
    }
    unset($item);

    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
    $allowed_tabs = ['overview', 'roster', 'schedule', 'settings', 'activity'];
    if (!in_array($tab, $allowed_tabs, true)) { $tab = 'overview'; }
    $activity_data = ($tab === 'activity') ? tfp_admin_cohort_activity_query($id, $data['students']) : null;
    $activity_type = sanitize_key(wp_unslash($_GET['activity_type'] ?? ''));
    $activity_user = absint($_GET['activity_user'] ?? 0);
    $activity_search = sanitize_text_field(wp_unslash($_GET['activity_search'] ?? ''));
    $edit_url = get_edit_post_link($id, 'raw');
    $facilitator_url = function_exists('tfp_dashboard_get_url')
        ? add_query_arg('tfp_cohort_id', $id, tfp_dashboard_get_url('tfp-dashboard-facilitator-cohorts'))
        : '';
    $add_student_url = admin_url('users.php');

    // Build a searchable, paginated roster using enrolled students only.
    $roster_search = isset($_GET['roster_search']) ? sanitize_text_field(wp_unslash($_GET['roster_search'])) : '';
    $roster_page = max(1, absint($_GET['roster_page'] ?? 1));
    $roster_per_page = 10;
    $roster_students = $data['students'];

    if ($roster_search !== '') {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($roster_search) : strtolower($roster_search);
        $roster_students = array_values(array_filter($roster_students, static function ($student_id) use ($needle) {
            $student = get_userdata($student_id);
            if (!$student) { return false; }
            $haystack = $student->display_name . ' ' . $student->user_email;
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
            return strpos($haystack, $needle) !== false;
        }));
    }

    $roster_total = count($roster_students);
    $roster_pages = max(1, (int) ceil($roster_total / $roster_per_page));
    $roster_page = min($roster_page, $roster_pages);
    $roster_page_students = array_slice($roster_students, ($roster_page - 1) * $roster_per_page, $roster_per_page);

    // Build the cohort schedule from the course's real week records and cohort start date.
    $schedule_page = max(1, absint($_GET['schedule_page'] ?? 1));
    $schedule_per_page = 10;
    $schedule_weeks = array_values($data['weeks']);
    $schedule_total = count($schedule_weeks);
    $schedule_pages = max(1, (int) ceil($schedule_total / $schedule_per_page));
    $schedule_page = min($schedule_page, $schedule_pages);
    $schedule_page_weeks = array_slice($schedule_weeks, ($schedule_page - 1) * $schedule_per_page, $schedule_per_page);
    $schedule_meeting_time = '';
    $schedule_timezone = '';
    if (preg_match('/\\b(\\d{1,2}:\\d{2}\\s*[AP]M)\\b/i', $data['schedule'], $meeting_time_match)) {
        $schedule_meeting_time = strtoupper(preg_replace('/\\s+/', ' ', $meeting_time_match[1]));
    }
    if (preg_match('/\\b(PST|PDT|MST|MDT|CST|CDT|EST|EDT|PT|MT|CT|ET)\\b/i', $data['schedule'], $timezone_match)) {
        $schedule_timezone = strtoupper($timezone_match[1]);
    }
    $schedule_manage_url = admin_url('edit.php?post_type=tfp_off_day');
    $cohort_skip_posts = get_posts([
        'post_type' => 'tfp_off_day',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [
            ['key' => '_cohort_id', 'value' => $id, 'type' => 'NUMERIC'],
            ['key' => '_push_progression', 'value' => '1'],
        ],
    ]);
    $cohort_skip_weeks = [];
    foreach ($cohort_skip_posts as $skip_post) {
        $skip_week = absint(get_post_meta($skip_post->ID, '_week_number', true));
        if ($skip_week) { $cohort_skip_weeks[] = $skip_week; }
    }
    $schedule_notice_saved = isset($_GET['schedule_notice']) && sanitize_key($_GET['schedule_notice']) === 'saved';
    ?>
    <div class="tfp-admin-app tfp-admin-cohort-detail-app">
      <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
        <div class="tfp-admin-sidebar-top"><button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M6 2v14"/></svg></button></div>
        <div class="tfp-admin-brand-row"><a class="tfp-admin-brand" href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt=""></a></div>
        <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
          <?php foreach ($nav as $item) : ?><a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>"><span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo function_exists('tfp_admin_dashboard_nav_icon') ? tfp_admin_dashboard_nav_icon($item['slug']) : ''; ?></span><span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span></a><?php endforeach; ?>
        </nav>
        <div class="tfp-admin-sidebar-bottom"><div class="tfp-admin-profile"><img src="<?php echo esc_url(get_avatar_url($user->ID, ['size' => 96])); ?>" alt=""><span><strong><?php echo esc_html($user->display_name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span></div><a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span class="tfp-admin-logout-icon" aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a></div>
      </aside>
      <div class="tfp-admin-main">
        <button type="button" class="tfp-dash-pageheader__mobile-toggle tfp-student-mobile-toggle tfp-admin-cohort-mobile-toggle" data-tfp-sidebar-toggle data-tfp-admin-toggle aria-controls="tfp-admin-sidebar" aria-expanded="false" aria-label="<?php esc_attr_e('Open admin menu', 'tfp-dashboard'); ?>">
          <?php echo tfp_dashboard_icon('toggle'); ?>
        </button>
        <div class="tfp-admin-breadcrumb"><a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a><b>/</b><a href="<?php echo esc_url(tfp_admin_cohorts_url()); ?>"><?php esc_html_e('Cohorts', 'tfp-dashboard'); ?></a><b>/</b><strong><?php echo esc_html(get_the_title($id)); ?></strong><?php if (in_array($tab, ['roster', 'schedule'], true)) : ?><b>/</b><strong><?php echo esc_html($tab === 'roster' ? __('Roster', 'tfp-dashboard') : __('Schedule', 'tfp-dashboard')); ?></strong><?php endif; ?><?php if ($tab === 'activity') : ?><b>/</b><strong><?php esc_html_e('Activity', 'tfp-dashboard'); ?></strong><form class="tfp-admin-cohort-top-search" method="get" action="<?php echo esc_url(tfp_admin_cohort_detail_url($id)); ?>"><input type="hidden" name="tab" value="activity"><input type="search" name="activity_search" value="<?php echo esc_attr($activity_search); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>" aria-label="<?php esc_attr_e('Search cohort activity', 'tfp-dashboard'); ?>"><button type="submit" aria-label="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"></circle><path d="m13 13 4 4"></path></svg></button></form><?php endif; ?></div>
        <header class="tfp-admin-page-header tfp-admin-cohort-detail-header">
          <div class="tfp-admin-cohort-heading"><h1><?php echo esc_html(get_the_title($id)); ?></h1><p><?php echo esc_html($data['course'] ? $data['course']->post_title : __('Course not assigned', 'tfp-dashboard')); ?> · <?php echo esc_html(sprintf(__('Facilitator: %s', 'tfp-dashboard'), $data['facilitator'] ? $data['facilitator']->display_name : '—')); ?> · <?php echo esc_html($data['schedule'] ?: __('Schedule not set', 'tfp-dashboard')); ?></p>
            <div class="tfp-admin-cohort-header-actions"><span class="tfp-admin-cohort-live-status"><?php echo esc_html($data['status']); ?></span><a class="tfp-admin-cohort-secondary" href="<?php echo esc_url(add_query_arg('post', $id, admin_url('post.php')) . '&action=edit'); ?>"><?php esc_html_e('Reassign Facilitator', 'tfp-dashboard'); ?></a><a class="tfp-admin-cohort-secondary" href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit Settings', 'tfp-dashboard'); ?></a><?php if ($facilitator_url) : ?><a class="tfp-admin-cohort-primary" href="<?php echo esc_url($facilitator_url); ?>"><?php esc_html_e('Open Facilitator View', 'tfp-dashboard'); ?> <span aria-hidden="true">↗</span></a><?php endif; ?></div>
          </div><a class="tfp-admin-cohort-add-student" href="<?php echo esc_url($add_student_url); ?>"><span aria-hidden="true">＋</span><?php esc_html_e('Add Student', 'tfp-dashboard'); ?></a>
        </header>
        <main class="tfp-admin-content tfp-admin-cohort-detail-content">

          <nav class="tfp-admin-cohort-tabs" aria-label="<?php esc_attr_e('Cohort sections', 'tfp-dashboard'); ?>">
            <?php foreach (['overview' => 'Overview', 'roster' => 'Roster', 'schedule' => 'Schedule', 'settings' => 'Settings', 'activity' => 'Activity'] as $key => $label) : ?><a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('tab', $key, tfp_admin_cohort_detail_url($id))); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?>
          </nav>
          <?php if ($tab === 'overview') : ?>
            <section class="tfp-admin-cohort-metrics">
              <article><small><?php esc_html_e('Active Students', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(count($data['students']) . ($data['capacity'] ? '/' . $data['capacity'] : '')); ?></strong><p><?php esc_html_e('Enrolled students', 'tfp-dashboard'); ?></p></article>
              <article><small><?php esc_html_e('Avg Progress', 'tfp-dashboard'); ?></small><strong class="is-teal"><?php echo $data['weeks'] ? esc_html(sprintf(__('Wk %1$d / %2$d', 'tfp-dashboard'), min($data['current_week'], count($data['weeks'])), count($data['weeks']))) : '—'; ?></strong><p><?php echo $data['weeks'] ? esc_html(sprintf(__('%d%% average completion', 'tfp-dashboard'), $data['average_progress'])) : esc_html__('No course weeks available', 'tfp-dashboard'); ?></p></article>
              <article><small><?php esc_html_e('Attendance Rate', 'tfp-dashboard'); ?></small><strong class="is-gold"><?php echo $data['attendance_rate'] === null ? '—' : esc_html($data['attendance_rate'] . '%'); ?></strong><p><?php esc_html_e('Based on recorded attendance', 'tfp-dashboard'); ?></p></article>
              <article><small><?php esc_html_e('Stalled', 'tfp-dashboard'); ?></small><strong class="<?php echo $data['average_progress'] < 40 ? 'is-red' : 'is-teal'; ?>"><?php echo $data['average_progress'] < 40 && $data['students'] ? esc_html__('High', 'tfp-dashboard') : esc_html__('Low', 'tfp-dashboard'); ?></strong><p><?php esc_html_e('Based on course completion', 'tfp-dashboard'); ?></p></article>
            </section>
            <section class="tfp-admin-cohort-section"><h2><?php esc_html_e('Alerts & Flags', 'tfp-dashboard'); ?></h2><div class="tfp-admin-cohort-alerts">
              <?php $alert_icons = ['https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Group-297402.png', 'https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Group-297402-1.png', 'https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Group-297402-2.png']; foreach ($data['alerts'] as $alert_index => $alert) : ?><article class="is-<?php echo esc_attr($alert['type']); ?>"><span class="tfp-admin-cohort-alert-icon" aria-hidden="true"><img src="<?php echo esc_url($alert_icons[$alert_index] ?? $alert_icons[0]); ?>" alt=""></span><div><strong><?php echo esc_html($alert['title']); ?></strong><small><?php echo esc_html($alert['detail']); ?></small></div><?php if ($alert['link']) : ?><a href="<?php echo esc_url(add_query_arg('tab', $alert['link'], tfp_admin_cohort_detail_url($id))); ?>"><?php echo esc_html($alert['link_label']); ?> <span aria-hidden="true">→</span></a><?php endif; ?></article><?php endforeach; ?>
            </div></section>
            <section class="tfp-admin-cohort-section tfp-admin-cohort-progress-section"><h2><?php esc_html_e('Cohort Progress', 'tfp-dashboard'); ?> <span>— <?php echo esc_html($data['course'] ? $data['course']->post_title : __('Course', 'tfp-dashboard')); ?></span></h2>
              <div class="tfp-admin-cohort-progress-card"><div class="tfp-admin-cohort-week-list"><?php foreach (array_slice($data['week_completion'], 0, 13) as $week) : ?><span class="<?php echo $week['complete'] ? 'is-complete' : ($week['current'] ? 'is-current' : 'is-upcoming'); ?>" title="<?php echo esc_attr($week['title']); ?>"><?php echo esc_html($week['number']); ?></span><?php endforeach; ?></div><div class="tfp-admin-cohort-legend"><span><i class="is-complete"></i><?php esc_html_e('Complete', 'tfp-dashboard'); ?></span><span><i class="is-current"></i><?php esc_html_e('Current', 'tfp-dashboard'); ?></span><span><i class="is-upcoming"></i><?php esc_html_e('Upcoming', 'tfp-dashboard'); ?></span></div><div class="tfp-admin-cohort-average"><div><span><?php esc_html_e('Cohort avg completion', 'tfp-dashboard'); ?></span><strong><?php echo esc_html($data['average_progress']); ?>%</strong></div><span class="tfp-admin-cohort-progress-track"><i style="width:<?php echo esc_attr($data['average_progress']); ?>%"></i></span></div></div>
            </section>
            <section class="tfp-admin-cohort-section"><h2><?php esc_html_e('Facilitator Snapshot', 'tfp-dashboard'); ?></h2><div class="tfp-admin-cohort-facilitator-grid"><article class="tfp-admin-cohort-facilitator-card"><img src="<?php echo esc_url($data['facilitator'] ? get_avatar_url($data['facilitator_id'], ['size' => 96]) : ''); ?>" alt=""><div><strong><?php echo esc_html($data['facilitator'] ? $data['facilitator']->display_name : __('No facilitator assigned', 'tfp-dashboard')); ?></strong><small><?php esc_html_e('Assigned facilitator', 'tfp-dashboard'); ?></small></div><?php if ($data['facilitator']) : ?><a href="<?php echo esc_url(add_query_arg(['user_id' => $data['facilitator_id']], admin_url('user-edit.php'))); ?>"><?php esc_html_e('View Facilitator', 'tfp-dashboard'); ?></a><?php endif; ?></article><article class="tfp-admin-cohort-mini-stat"><strong><?php echo esc_html(($data['current_week'] ?: '—')); ?></strong><small><?php esc_html_e('Weeks Led', 'tfp-dashboard'); ?></small></article><article class="tfp-admin-cohort-mini-stat"><strong><?php echo $data['homework_review_rate'] === null ? '—' : esc_html($data['homework_review_rate'] . '%'); ?></strong><small><?php esc_html_e('HW Reviewed', 'tfp-dashboard'); ?></small></article><article class="tfp-admin-cohort-mini-stat"><strong><?php echo $data['attendance_rate'] === null ? '—' : esc_html($data['attendance_rate'] . '%'); ?></strong><small><?php esc_html_e('Avg Attendance', 'tfp-dashboard'); ?></small></article></div></section>
          <?php elseif ($tab === 'roster') : ?>
            <section class="tfp-admin-cohort-roster-panel">
              <div class="tfp-admin-cohort-roster-title"><h2><?php esc_html_e('Cohort Roster', 'tfp-dashboard'); ?></h2></div>
              <form class="tfp-admin-cohort-roster-search" method="get" action="<?php echo esc_url(tfp_admin_cohort_detail_url($id)); ?>">
                <input type="hidden" name="tab" value="roster">
                <label class="screen-reader-text" for="tfp-cohort-roster-search"><?php esc_html_e('Search students', 'tfp-dashboard'); ?></label>
                <input id="tfp-cohort-roster-search" type="search" name="roster_search" value="<?php echo esc_attr($roster_search); ?>" placeholder="<?php esc_attr_e('Search', 'tfp-dashboard'); ?>">
                <span class="tfp-admin-search-png" aria-hidden="true"><img src="<?php echo esc_url('https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png'); ?>" alt=""></span>
              </form>
              <div class="tfp-admin-cohort-table-wrap tfp-admin-cohort-roster-table-wrap">
                <table class="tfp-admin-cohort-roster-table">
                  <thead><tr>
                    <th><?php esc_html_e('Student', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Progress', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Last Activity', 'tfp-dashboard'); ?></th>
                    <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                  </tr></thead>
                  <tbody>
                    <?php foreach ($roster_page_students as $student_id) :
                      $student = get_userdata($student_id);
                      if (!$student) { continue; }
                      $progress = $data['student_progress'][$student_id] ?? ['completed' => 0, 'total' => count($data['weeks'])];
                      $attendance = $data['student_attendance'][$student_id] ?? ['present' => 0, 'recorded' => 0];
                      $progress_percent = !empty($progress['total']) ? (int) round(($progress['completed'] / $progress['total']) * 100) : 0;
                      $attendance_percent = !empty($attendance['recorded']) ? (int) round(($attendance['present'] / $attendance['recorded']) * 100) : null;
                      $locked = (bool) get_user_meta($student_id, '_tfp_deactivated', true);
                      $at_risk = !$locked && (($attendance_percent !== null && $attendance_percent < 70) || $progress_percent < 40);
                      $status_label = $locked ? __('Locked', 'tfp-dashboard') : ($at_risk ? __('At Risk', 'tfp-dashboard') : __('Active', 'tfp-dashboard'));
                      $status_class = $locked ? 'is-locked' : ($at_risk ? 'is-risk' : 'is-active');
                      $last_login = get_user_meta($student_id, '_tfp_last_login', true);
                      $last_activity = $last_login && strtotime($last_login) ? wp_date('M j, g:i A', strtotime($last_login)) : '—';
                      $student_url = add_query_arg('user_id', $student_id, admin_url('user-edit.php'));
                    ?>
                      <tr>
                        <td class="tfp-roster-student"><strong><?php echo esc_html($student->display_name); ?></strong></td>
                        <td><span class="tfp-admin-roster-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                        <td><?php echo esc_html(sprintf(__('Week %1$d / %2$d', 'tfp-dashboard'), (int) $progress['completed'], (int) $progress['total'])); ?></td>
                        <td><?php echo $attendance_percent === null ? '—' : esc_html($attendance_percent . '%'); ?></td>
                        <td><?php echo esc_html($last_activity); ?></td>
                        <td><a class="tfp-admin-roster-view" href="<?php echo esc_url($student_url); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                      </tr>
                    <?php endforeach; ?>

                    <?php if (!$roster_page_students) : ?>
                      <tr><td colspan="6" class="tfp-admin-roster-empty"><?php echo $roster_search !== '' ? esc_html__('No students match your search.', 'tfp-dashboard') : esc_html__('No students are enrolled in this cohort yet.', 'tfp-dashboard'); ?></td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <div class="tfp-admin-cohort-roster-pagination">
                <span><?php echo $roster_total ? esc_html(sprintf(__('%1$d - %2$d of %3$d items', 'tfp-dashboard'), (($roster_page - 1) * $roster_per_page) + 1, min($roster_page * $roster_per_page, $roster_total), $roster_total)) : esc_html__('0 items', 'tfp-dashboard'); ?></span>
                <div>
                  <?php if ($roster_page > 1) : ?><a class="tfp-roster-page-prev" href="<?php echo esc_url(add_query_arg(['tab' => 'roster', 'roster_search' => $roster_search, 'roster_page' => $roster_page - 1], tfp_admin_cohort_detail_url($id))); ?>">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></a><?php else : ?><span class="tfp-roster-page-disabled">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></span><?php endif; ?>
                  <?php if ($roster_page < $roster_pages) : ?><a class="tfp-roster-page-next" href="<?php echo esc_url(add_query_arg(['tab' => 'roster', 'roster_search' => $roster_search, 'roster_page' => $roster_page + 1], tfp_admin_cohort_detail_url($id))); ?>"><span><?php esc_html_e('Next', 'tfp-dashboard'); ?></span> ›</a><?php else : ?><span class="tfp-roster-page-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</span><?php endif; ?>
                </div>
              </div>
            </section>
          <?php elseif ($tab === 'schedule') : ?>
            <section class="tfp-admin-cohort-schedule-page">
              <?php if ($schedule_notice_saved) : ?><div class="tfp-admin-cohort-schedule-notice" role="status"><?php esc_html_e('Holiday / skip saved successfully.', 'tfp-dashboard'); ?></div><?php endif; ?>
              <div class="tfp-admin-cohort-meeting-bar">
                <label for="tfp-cohort-meeting-link"><?php esc_html_e('Meeting Link:', 'tfp-dashboard'); ?></label>
                <input id="tfp-cohort-meeting-link" type="url" value="<?php echo esc_attr($data['meeting_url']); ?>" readonly placeholder="<?php esc_attr_e('No meeting link added', 'tfp-dashboard'); ?>">
                <button type="button" class="tfp-admin-cohort-manage-days" data-tfp-open-skip-modal><?php esc_html_e('Manage Holidays / Skips', 'tfp-dashboard'); ?></button>
              </div>

              <section class="tfp-admin-cohort-schedule-panel">
                <div class="tfp-admin-cohort-schedule-title"><h2><?php esc_html_e('Cohort Schedule', 'tfp-dashboard'); ?></h2></div>
                <div class="tfp-admin-cohort-table-wrap tfp-admin-cohort-schedule-table-wrap">
                  <table class="tfp-admin-cohort-schedule-table">
                    <thead><tr>
                      <th><?php esc_html_e('Week #', 'tfp-dashboard'); ?></th>
                      <th><?php esc_html_e('Session / Lesson Name', 'tfp-dashboard'); ?></th>
                      <th><?php echo esc_html($schedule_timezone ? sprintf(__('Meeting Date (%s)', 'tfp-dashboard'), $schedule_timezone) : __('Meeting Date', 'tfp-dashboard')); ?></th>
                      <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                    </tr></thead>
                    <tbody>
                      <?php foreach ($schedule_page_weeks as $week_index => $week) :
                        $absolute_week = (($schedule_page - 1) * $schedule_per_page) + $week_index + 1;
                        $week_date = ($data['start_date'] && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $data['start_date'])) ? DateTimeImmutable::createFromFormat('!Y-m-d', $data['start_date'], wp_timezone()) : false;
                        $week_date = $week_date ? $week_date->modify('+' . ($absolute_week - 1) . ' weeks') : false;
                        $shift_count = count(array_filter($cohort_skip_weeks, static function ($skip_week) use ($absolute_week) { return $skip_week <= $absolute_week; }));
                        $week_date = $week_date && $shift_count ? $week_date->modify('+' . $shift_count . ' weeks') : $week_date;
                        $week_timestamp = $week_date ? $week_date->getTimestamp() : false;
                        $meeting_date = $week_timestamp ? wp_date('M j', $week_timestamp) . ($schedule_meeting_time ? ', ' . $schedule_meeting_time : '') . ($schedule_timezone ? ' ' . $schedule_timezone : '') : '—';
                        $week_status = $absolute_week < $data['current_week'] ? 'completed' : ($absolute_week === $data['current_week'] ? 'in-progress' : 'not-started');
                        $week_status_label = $week_status === 'completed' ? __('Completed', 'tfp-dashboard') : ($week_status === 'in-progress' ? __('In Progress', 'tfp-dashboard') : __('Not Started', 'tfp-dashboard'));
                      ?>
                        <tr>
                          <td><strong><?php echo esc_html(sprintf(__('Week %d', 'tfp-dashboard'), $absolute_week)); ?></strong></td>
                          <td><?php echo esc_html(get_the_title($week)); ?></td>
                          <td><?php echo esc_html($meeting_date); ?></td>
                          <td><span class="tfp-admin-cohort-week-status is-<?php echo esc_attr($week_status); ?>"><?php echo esc_html($week_status_label); ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                      <?php if (!$schedule_page_weeks) : ?>
                        <tr><td colspan="4" class="tfp-admin-cohort-schedule-empty"><?php esc_html_e('No course weeks are available for this cohort yet.', 'tfp-dashboard'); ?></td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <div class="tfp-admin-cohort-schedule-pagination">
                  <span><?php echo $schedule_total ? esc_html(sprintf(__('%1$d - %2$d of %3$d items', 'tfp-dashboard'), (($schedule_page - 1) * $schedule_per_page) + 1, min($schedule_page * $schedule_per_page, $schedule_total), $schedule_total)) : esc_html__('0 items', 'tfp-dashboard'); ?></span>
                  <div>
                    <?php if ($schedule_page > 1) : ?><a class="tfp-schedule-page-prev" href="<?php echo esc_url(add_query_arg(['tab' => 'schedule', 'schedule_page' => $schedule_page - 1], tfp_admin_cohort_detail_url($id))); ?>">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></a><?php else : ?><span class="tfp-schedule-page-disabled">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></span><?php endif; ?>
                    <?php if ($schedule_page < $schedule_pages) : ?><a class="tfp-schedule-page-next" href="<?php echo esc_url(add_query_arg(['tab' => 'schedule', 'schedule_page' => $schedule_page + 1], tfp_admin_cohort_detail_url($id))); ?>"><span><?php esc_html_e('Next', 'tfp-dashboard'); ?></span> ›</a><?php else : ?><span class="tfp-schedule-page-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</span><?php endif; ?>
                  </div>
                </div>
              </section>
              <div class="tfp-admin-cohort-modal" data-tfp-skip-modal aria-hidden="true">
                <div class="tfp-admin-cohort-modal-backdrop" data-tfp-close-skip-modal></div>
                <section class="tfp-admin-cohort-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tfp-skip-modal-title">
                  <div class="tfp-admin-cohort-modal-heading">
                    <h2 id="tfp-skip-modal-title"><?php esc_html_e('Add Holiday / Skip', 'tfp-dashboard'); ?></h2>
                    <p><?php esc_html_e('Manage breaks in the 58-week progression.', 'tfp-dashboard'); ?></p>
                  </div>
                  <div class="tfp-admin-cohort-calendar-impact">
                    <span class="tfp-admin-cohort-impact-icon" aria-hidden="true">!</span>
                    <div><strong><?php esc_html_e('Calendar Impact:', 'tfp-dashboard'); ?></strong><span><?php esc_html_e('Adding a skip will automatically shift all future sessions forward by one week.', 'tfp-dashboard'); ?></span></div>
                  </div>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tfp-admin-cohort-skip-form">
                    <input type="hidden" name="action" value="tfp_admin_cohort_add_skip">
                    <input type="hidden" name="cohort_id" value="<?php echo esc_attr($id); ?>">
                    <?php wp_nonce_field('tfp_admin_cohort_add_skip_' . $id, 'tfp_cohort_skip_nonce'); ?>
                    <label for="tfp-skip-reason"><?php esc_html_e('Reason For Skip', 'tfp-dashboard'); ?></label>
                    <input id="tfp-skip-reason" name="skip_reason" type="text" required maxlength="180" placeholder="<?php esc_attr_e('eg. Christmas Break, Resurrection Sunday', 'tfp-dashboard'); ?>">
                    <label for="tfp-skip-week"><?php esc_html_e('Date of Canceled Meeting', 'tfp-dashboard'); ?></label>
                    <select id="tfp-skip-week" name="week_number" required>
                      <option value=""><?php esc_html_e('Select a scheduled meeting', 'tfp-dashboard'); ?></option>
                      <?php foreach ($data['weeks'] as $week_index => $week) :
                        $option_week = $week_index + 1;
                        $option_date = ($data['start_date'] && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $data['start_date'])) ? DateTimeImmutable::createFromFormat('!Y-m-d', $data['start_date'], wp_timezone()) : false;
                        $option_date = $option_date ? $option_date->modify('+' . ($option_week - 1) . ' weeks') : false;
                        $option_shifts = count(array_filter($cohort_skip_weeks, static function ($skip_week) use ($option_week) { return $skip_week <= $option_week; }));
                        $option_date = $option_date && $option_shifts ? $option_date->modify('+' . $option_shifts . ' weeks') : $option_date;
                        $option_label = $option_date ? wp_date(get_option('date_format') . ', ' . get_option('time_format'), $option_date->getTimestamp()) : sprintf(__('Week %d', 'tfp-dashboard'), $option_week);
                      ?>
                        <option value="<?php echo esc_attr($option_week); ?>"><?php echo esc_html(get_the_title($week) . ' — ' . $option_label); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <label for="tfp-skip-meeting-room"><?php esc_html_e('Discord Meeting Room', 'tfp-dashboard'); ?></label>
                    <input id="tfp-skip-meeting-room" name="meeting_room" type="url" value="<?php echo esc_attr($data['meeting_url']); ?>" placeholder="<?php esc_attr_e('https://discord.gg/...', 'tfp-dashboard'); ?>">
                    <label class="tfp-admin-cohort-push-label" for="tfp-push-progression">
                      <span><?php esc_html_e('Push Progression', 'tfp-dashboard'); ?></span>
                      <input id="tfp-push-progression" name="push_progression" type="checkbox" value="1" checked>
                      <span class="tfp-admin-cohort-toggle-ui" aria-hidden="true"></span>
                      <small><?php esc_html_e('On', 'tfp-dashboard'); ?></small>
                    </label>
                    <div class="tfp-admin-cohort-modal-actions">
                      <button type="submit" class="tfp-admin-cohort-modal-create"><span class="tfp-modal-action-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.25 2.75h11.1l2.9 2.9v11.6H2.75V3.25c0-.28.22-.5.5-.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M6 2.9v4.4h7.5V2.9M6 17.1v-6.2h8v6.2" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M11.8 3.8h.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span><?php esc_html_e('Create', 'tfp-dashboard'); ?></button>
                      <button type="button" class="tfp-admin-cohort-modal-cancel" data-tfp-close-skip-modal><span class="tfp-modal-action-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="8.1" stroke="currentColor" stroke-width="1.7"/><path d="M4.3 4.3 15.7 15.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
                    </div>
                  </form>
                </section>
              </div>
              <script>
              (function(){
                var modal=document.querySelector('[data-tfp-skip-modal]');
                if(!modal)return;

                // Render the modal directly under <body> so fixed positioning is viewport-centered.
                if(modal.parentNode!==document.body){document.body.appendChild(modal);}

                var opener=document.querySelector('[data-tfp-open-skip-modal]');
                var closeButtons=modal.querySelectorAll('[data-tfp-close-skip-modal]');
                function closeModal(){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('tfp-cohort-modal-open');}
                function openModal(){modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.classList.add('tfp-cohort-modal-open');var field=modal.querySelector('input[name="skip_reason"]');if(field)field.focus();}
                if(opener)opener.addEventListener('click',openModal);
                closeButtons.forEach(function(button){button.addEventListener('click',closeModal);});
                document.addEventListener('keydown',function(event){if(event.key==='Escape'&&modal.classList.contains('is-open'))closeModal();});
              })();
              </script>
            </section>
          <?php elseif ($tab === 'settings') : ?>
            <?php if ($governance_saved) : ?><div class="tfp-admin-cohort-schedule-notice" role="status"><?php esc_html_e('Cohort governance settings saved.', 'tfp-dashboard'); ?></div><?php endif; ?>
            <section class="tfp-admin-cohort-data-panel tfp-admin-cohort-governance-panel">
              <h2><?php esc_html_e('Cohort Governance', 'tfp-dashboard'); ?></h2>
              <div class="tfp-admin-cohort-ruleset">
                <div class="tfp-admin-cohort-ruleset-heading"><div><strong><?php esc_html_e('Current Ruleset', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Standard 58-Week Journey', 'tfp-dashboard'); ?></small></div><button type="button" class="tfp-admin-cohort-edit-ruleset" data-tfp-open-governance-modal><span class="tfp-cohort-edit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 20H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span> <?php esc_html_e('Edit', 'tfp-dashboard'); ?></button></div>
                <dl>
                  <div><dt><?php esc_html_e('Course Mode', 'tfp-dashboard'); ?></dt><dd><?php esc_html_e('Facilitation-Based (Full 58-Week)', 'tfp-dashboard'); ?></dd></div>
                  <div><dt><?php esc_html_e('Unlock Rule', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html(['meeting_day' => 'Auto-Unlock on Meeting Day', '24_hours' => '24 Hours Before Meeting', 'manual' => 'Manual Release by Facilitator'][$governance['unlock_trigger']]); ?></dd></div>
                  <div><dt><?php esc_html_e('Warning Flag', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($governance['flags_enabled'] ? sprintf(__('After %d days / %d missed meetings', 'tfp-dashboard'), $governance['flag_inactive_days'], $governance['flag_missed_meetings']) : __('Disabled', 'tfp-dashboard')); ?></dd></div>
                  <div><dt><?php esc_html_e('Hard Block', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($governance['block_enabled'] ? sprintf(__('After %d weeks / %d missed meetings', 'tfp-dashboard'), $governance['block_weeks_behind'], $governance['block_missed_meetings']) : __('Disabled', 'tfp-dashboard')); ?></dd></div>
                  <div><dt><?php esc_html_e('Attendance Block', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($governance['block_clear'] === 'admin' ? __('Admin Approval Required', 'tfp-dashboard') : __('Facilitator Can Clear', 'tfp-dashboard')); ?></dd></div>
                </dl>
              </div>
            </section>
            <div class="tfp-admin-cohort-modal tfp-admin-cohort-governance-modal" data-tfp-governance-modal aria-hidden="true">
              <div class="tfp-admin-cohort-modal-backdrop" data-tfp-close-governance-modal></div>
              <section class="tfp-admin-cohort-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tfp-governance-title">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="tfp-admin-governance-form">
                  <input type="hidden" name="action" value="tfp_admin_save_cohort_governance">
                  <input type="hidden" name="cohort_id" value="<?php echo esc_attr($id); ?>">
                  <?php wp_nonce_field('tfp_admin_save_cohort_governance_' . $id, 'tfp_governance_nonce'); ?>
                  <header class="tfp-admin-governance-heading"><h2 id="tfp-governance-title"><?php esc_html_e('Cohort Command Center', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Define the behavior and rules for this specific 58-week discipleship cohort.', 'tfp-dashboard'); ?></p></header>
                  <section class="tfp-admin-governance-section">
                    <h3><?php esc_html_e('Timeline & Progression', 'tfp-dashboard'); ?></h3><p><?php esc_html_e('How and when students access their lessons.', 'tfp-dashboard'); ?></p>
                    <label class="tfp-admin-governance-toggle"><input type="checkbox" name="auto_unlock" value="1" <?php checked($governance['auto_unlock'], 1); ?>><span></span><strong><?php esc_html_e('Auto-Unlock Master Rule', 'tfp-dashboard'); ?></strong></label>
                    <div class="tfp-admin-governance-subtitle"><?php esc_html_e('Trigger Logic', 'tfp-dashboard'); ?></div>
                    <label class="tfp-admin-governance-radio"><input type="radio" name="unlock_trigger" value="meeting_day" <?php checked($governance['unlock_trigger'], 'meeting_day'); ?>><?php esc_html_e('On Meeting Day (Standard)', 'tfp-dashboard'); ?></label>
                    <label class="tfp-admin-governance-radio"><input type="radio" name="unlock_trigger" value="24_hours" <?php checked($governance['unlock_trigger'], '24_hours'); ?>><?php esc_html_e('24 Hours Before Meeting', 'tfp-dashboard'); ?></label>
                    <label class="tfp-admin-governance-radio"><input type="radio" name="unlock_trigger" value="manual" <?php checked($governance['unlock_trigger'], 'manual'); ?>><?php esc_html_e('Manual Release by Facilitator.', 'tfp-dashboard'); ?></label>
                    <label class="tfp-admin-governance-toggle tfp-admin-governance-prerequisite"><input type="checkbox" name="prerequisite_lock" value="1" <?php checked($governance['prerequisite_lock'], 1); ?>><span></span><strong><?php esc_html_e('Prerequisite Lock', 'tfp-dashboard'); ?></strong></label>
                  </section>
                  <section class="tfp-admin-governance-section">
                    <h3><?php esc_html_e('Signals & Health Monitoring', 'tfp-dashboard'); ?></h3><p><?php esc_html_e('Rules for flagging & Locking students who may be slipping away.', 'tfp-dashboard'); ?></p>
                    <label class="tfp-admin-governance-toggle"><input type="checkbox" name="flags_enabled" value="1" <?php checked($governance['flags_enabled'], 1); ?>><span></span><strong><?php esc_html_e('Flags Students', 'tfp-dashboard'); ?></strong></label>
                    <div class="tfp-admin-governance-number-row"><span><?php esc_html_e('Flag after', 'tfp-dashboard'); ?></span><input type="number" min="1" max="365" name="flag_inactive_days" value="<?php echo esc_attr($governance['flag_inactive_days']); ?>"><span><?php esc_html_e('days of inactivity', 'tfp-dashboard'); ?></span></div>
                    <div class="tfp-admin-governance-number-row"><span><?php esc_html_e('Flag after', 'tfp-dashboard'); ?></span><input type="number" min="1" max="52" name="flag_missed_meetings" value="<?php echo esc_attr($governance['flag_missed_meetings']); ?>"><span><?php esc_html_e('unexcused missed meeting', 'tfp-dashboard'); ?></span></div>
                    <div class="tfp-admin-governance-number-row"><span><?php esc_html_e('Falls', 'tfp-dashboard'); ?></span><input type="number" min="1" max="52" name="flag_weeks_behind" value="<?php echo esc_attr($governance['flag_weeks_behind']); ?>"><span><?php esc_html_e('week behind schedule', 'tfp-dashboard'); ?></span></div>
                    <label class="tfp-admin-governance-toggle tfp-admin-governance-block-toggle"><input type="checkbox" name="block_enabled" value="1" <?php checked($governance['block_enabled'], 1); ?>><span></span><strong><?php esc_html_e('Block (Hard Stop)', 'tfp-dashboard'); ?></strong></label>
                    <div class="tfp-admin-governance-number-row"><span><?php esc_html_e('Block after', 'tfp-dashboard'); ?></span><input type="number" min="1" max="52" name="block_weeks_behind" value="<?php echo esc_attr($governance['block_weeks_behind']); ?>"><span><?php esc_html_e('weeks behind', 'tfp-dashboard'); ?></span></div>
                    <div class="tfp-admin-governance-number-row"><span><?php esc_html_e('Block after', 'tfp-dashboard'); ?></span><input type="number" min="1" max="52" name="block_missed_meetings" value="<?php echo esc_attr($governance['block_missed_meetings']); ?>"><span><?php esc_html_e('unexcused missed meeting', 'tfp-dashboard'); ?></span></div>
                  </section>
                  <section class="tfp-admin-governance-section tfp-admin-governance-last-section">
                    <h3><?php esc_html_e('Block Management', 'tfp-dashboard'); ?></h3>
                    <label class="tfp-admin-governance-radio"><input type="radio" name="block_clear" value="admin" <?php checked($governance['block_clear'], 'admin'); ?>><?php esc_html_e('Require Admin Approval to clear blocks', 'tfp-dashboard'); ?></label>
                    <label class="tfp-admin-governance-radio"><input type="radio" name="block_clear" value="facilitator" <?php checked($governance['block_clear'], 'facilitator'); ?>><?php esc_html_e('Facilitators can manually clear blocks', 'tfp-dashboard'); ?></label>
                  </section>
                  <div class="tfp-admin-governance-actions"><button type="submit" class="tfp-admin-cohort-modal-create"><span class="tfp-modal-action-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.25 2.75h11.1l2.9 2.9v11.6H2.75V3.25c0-.28.22-.5.5-.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M6 2.9v4.4h7.5V2.9M6 17.1v-6.2h8v6.2" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M11.8 3.8h.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span><?php esc_html_e('Save', 'tfp-dashboard'); ?></button><button type="button" class="tfp-admin-cohort-modal-cancel" data-tfp-close-governance-modal><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button></div>
                </form>
              </section>
            </div>
            <script>
            (function(){
              var modal=document.querySelector('[data-tfp-governance-modal]');if(!modal)return;
              if(modal.parentNode!==document.body){document.body.appendChild(modal);}
              var opener=document.querySelector('[data-tfp-open-governance-modal]');
              function close(){modal.classList.remove('is-open');modal.setAttribute('aria-hidden','true');document.body.classList.remove('tfp-cohort-modal-open');}
              function open(){modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.classList.add('tfp-cohort-modal-open');}
              if(opener)opener.addEventListener('click',open);
              modal.querySelectorAll('[data-tfp-close-governance-modal]').forEach(function(el){el.addEventListener('click',close);});
              document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal.classList.contains('is-open'))close();});
            })();
            </script>
          <?php elseif ($tab === 'activity') : ?>
            <form class="tfp-admin-cohort-activity-filters" method="get" action="<?php echo esc_url(tfp_admin_cohort_detail_url($id)); ?>">
              <input type="hidden" name="tab" value="activity">
              <label class="screen-reader-text" for="tfp-cohort-activity-type"><?php esc_html_e('Activity type', 'tfp-dashboard'); ?></label>
              <select id="tfp-cohort-activity-type" name="activity_type" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('All Types', 'tfp-dashboard'); ?></option>
                <?php foreach (['progress' => 'Progress', 'attendance' => 'Attendance', 'assessment' => 'Assessment', 'engagement' => 'Engagement', 'access' => 'Access'] as $type_key => $type_label) : ?>
                  <option value="<?php echo esc_attr($type_key); ?>" <?php selected($activity_type, $type_key); ?>><?php echo esc_html($type_label); ?></option>
                <?php endforeach; ?>
              </select>
              <label class="screen-reader-text" for="tfp-cohort-activity-user"><?php esc_html_e('Cohort user', 'tfp-dashboard'); ?></label>
              <select id="tfp-cohort-activity-user" name="activity_user" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('All Users', 'tfp-dashboard'); ?></option>
                <?php foreach ($data['students'] as $student_id) : $activity_student = get_userdata($student_id); if (!$activity_student) { continue; } ?>
                  <option value="<?php echo esc_attr($student_id); ?>" <?php selected($activity_user, $student_id); ?>><?php echo esc_html($activity_student->display_name); ?></option>
                <?php endforeach; ?>
                <?php if (!empty($activity_data['is_demo'])) : foreach (tfp_admin_cohort_activity_demo_rows() as $demo_user) : ?>
                  <option value="<?php echo esc_attr($demo_user->user_id); ?>" <?php selected($activity_user, $demo_user->user_id); ?>><?php echo esc_html($demo_user->display_name); ?></option>
                <?php endforeach; endif; ?>
              </select>
              <label class="tfp-admin-cohort-activity-search" for="tfp-cohort-activity-search">
                <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"></circle><path d="m13 13 4 4"></path></svg>
                <input id="tfp-cohort-activity-search" type="search" name="activity_search" value="<?php echo esc_attr($activity_search); ?>" placeholder="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>">
              </label>
            </form>
            <section class="tfp-admin-cohort-activity-panel">
              <div class="tfp-admin-cohort-activity-table-scroll">
                <table class="tfp-admin-cohort-activity-table">
                  <thead><tr><th><?php esc_html_e('User', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Type', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Activity', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Time', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                  <tbody>
                    <?php if (!empty($activity_data['is_demo'])) : ?><tr><td colspan="6" class="tfp-admin-cohort-activity-preview-note"><?php esc_html_e('Preview data — sample records shown for design review only. Real activity will replace these automatically.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                    <?php if (!empty($activity_data['rows'])) : foreach ($activity_data['rows'] as $activity_row) :
                      $activity_status = !empty($activity_row->is_demo) ? $activity_row->demo_status : tfp_admin_cohort_activity_status($activity_row);
                      $activity_description = trim((string) $activity_row->event_label . ((string) $activity_row->context !== '' ? ' — ' . (string) $activity_row->context : ''));
                      $activity_timestamp = strtotime(get_date_from_gmt($activity_row->created_at));
                    ?>
                      <tr>
                        <td><strong><?php echo esc_html($activity_row->display_name); ?></strong></td>
                        <td><?php echo esc_html(tfp_admin_cohort_activity_type_label($activity_row->event_type)); ?></td>
                        <td><span class="tfp-admin-cohort-activity-status is-<?php echo esc_attr($activity_status); ?>"><?php echo esc_html($activity_status === 'blocked' ? __('Blocked', 'tfp-dashboard') : ($activity_status === 'attention' ? __('Attention', 'tfp-dashboard') : __('On Track', 'tfp-dashboard'))); ?></span></td>
                        <td><?php echo esc_html($activity_description); ?></td>
                        <td><time datetime="<?php echo esc_attr(gmdate('c', strtotime($activity_row->created_at . ' UTC'))); ?>"><?php echo esc_html(wp_date('M j, g:i A T', $activity_timestamp)); ?></time></td>
                        <td><?php if (!empty($activity_row->is_demo)) : ?><span class="tfp-admin-cohort-activity-view is-preview" aria-disabled="true"><?php esc_html_e('View', 'tfp-dashboard'); ?></span><?php else : ?><a class="tfp-admin-cohort-activity-view" href="<?php echo esc_url(add_query_arg(['user_id' => $activity_row->user_id], function_exists('tfp_admin_users_page_url') ? tfp_admin_users_page_url() : admin_url('user-edit.php'))); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a><?php endif; ?></td>
                      </tr>
                    <?php endforeach; else : ?>
                      <tr><td colspan="6" class="tfp-admin-cohort-activity-empty"><?php esc_html_e('No recorded activity is available for this cohort yet.', 'tfp-dashboard'); ?></td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <footer class="tfp-admin-cohort-activity-pagination">
                <span><?php echo esc_html(sprintf(__('%1$d - %2$d of %3$d items', 'tfp-dashboard'), $activity_data['total'] ? (($activity_data['page'] - 1) * $activity_data['per_page']) + 1 : 0, min($activity_data['page'] * $activity_data['per_page'], $activity_data['total']), $activity_data['total'])); ?></span>
                <nav aria-label="<?php esc_attr_e('Cohort activity pages', 'tfp-dashboard'); ?>">
                  <?php if ($activity_data['page'] > 1) : ?><a href="<?php echo esc_url(add_query_arg('activity_page', $activity_data['page'] - 1, remove_query_arg('activity_page'))); ?>" class="is-previous">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></a><?php else : ?><span class="is-disabled">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></span><?php endif; ?>
                  <?php if ($activity_data['page'] < $activity_data['pages']) : ?><a href="<?php echo esc_url(add_query_arg('activity_page', $activity_data['page'] + 1, remove_query_arg('activity_page'))); ?>" class="is-next"><span><?php esc_html_e('Next', 'tfp-dashboard'); ?></span> ›</a><?php else : ?><span class="is-disabled"><span><?php esc_html_e('Next', 'tfp-dashboard'); ?></span> ›</span><?php endif; ?>
                </nav>
              </footer>
            </section>
          <?php else : ?>
            <section class="tfp-admin-cohort-data-panel"><h2><?php esc_html_e('Recent Activity', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Cohort created', 'tfp-dashboard'); ?> · <?php echo esc_html(get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $id)); ?></p><p><?php esc_html_e('Last updated', 'tfp-dashboard'); ?> · <?php echo esc_html(get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $id)); ?></p></section>
          <?php endif; ?>
        </main>
      </div>
    </div>
    <?php
}
