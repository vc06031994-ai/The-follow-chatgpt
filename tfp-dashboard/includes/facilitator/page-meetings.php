<?php
if (!defined('ABSPATH'))
    exit;

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
        'sunday' => 0,
        'sun' => 0,
        'monday' => 1,
        'mon' => 1,
        'tuesday' => 2,
        'tue' => 2,
        'wednesday' => 3,
        'wed' => 3,
        'thursday' => 4,
        'thu' => 4,
        'friday' => 5,
        'fri' => 5,
        'saturday' => 6,
        'sat' => 6,
    ];

    if (preg_match('/\b(sunday|sun|monday|mon|tuesday|tue|wednesday|wed|thursday|thu|friday|fri|saturday|sat)\b/i', $schedule, $m)) {
        $out['weekday'] = $days[strtolower($m[1])] ?? 0;
    }

    if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*:?\s*(am|pm)\b/i', $schedule, $m)) {
        $out['time'] = max(1, min(12, (int) $m[1])) . ':' . (isset($m[2]) && $m[2] !== '' ? str_pad($m[2], 2, '0', STR_PAD_LEFT) : '00') . ' ' . strtoupper($m[3]);
    }

    foreach ([
        'pt' => ['America/Los_Angeles', 'PT'],
        'pst' => ['America/Los_Angeles', 'PT'],
        'pdt' => ['America/Los_Angeles', 'PT'],
        'et' => ['America/New_York', 'ET'],
        'est' => ['America/New_York', 'ET'],
        'edt' => ['America/New_York', 'ET'],
        'ct' => ['America/Chicago', 'CT'],
        'cst' => ['America/Chicago', 'CT'],
        'cdt' => ['America/Chicago', 'CT'],
        'mt' => ['America/Denver', 'MT'],
        'mst' => ['America/Denver', 'MT'],
        'mdt' => ['America/Denver', 'MT'],
        'utc' => ['UTC', 'UTC'],
        'gmt' => ['UTC', 'UTC'],
    ] as $needle => $zone) {
        if (preg_match('/\b' . preg_quote($needle, '/') . '\b/i', $schedule)) {
            $out['timezone'] = new DateTimeZone($zone[0]);
            $out['label'] = $zone[1];
            break;
        }
    }

    return $out;
}

function tfp_facilitator_meetings_timestamp($start_date, $schedule, $week_index)
{
    if (!$start_date || !strtotime($start_date))
        return 0;

    $parts = tfp_facilitator_meetings_schedule($schedule);

    try {
        $date = new DateTimeImmutable($start_date . ' 00:00:00', $parts['timezone']);
        $days_until = ($parts['weekday'] - (int) $date->format('w') + 7) % 7;
        $date = $date->modify('+' . $days_until . ' days')->modify('+' . max(0, (int) $week_index) . ' weeks');

        if (preg_match('/^(\d{1,2}):(\d{2})\s+(AM|PM)$/', $parts['time'], $m)) {
            $hour = (int) $m[1];
            if (strtoupper($m[3]) === 'PM' && $hour < 12)
                $hour += 12;
            if (strtoupper($m[3]) === 'AM' && $hour === 12)
                $hour = 0;
            $date = $date->setTime($hour, (int) $m[2], 0);
        }

        return $date->getTimestamp();
    } catch (Throwable $e) {
        return 0;
    }
}

function tfp_facilitator_meetings_letter($percent)
{
    if ($percent === null || !is_numeric($percent))
        return '—';
    $percent = (float) $percent;
    if ($percent >= 90)
        return 'A';
    if ($percent >= 80)
        return 'B';
    if ($percent >= 70)
        return 'C';
    if ($percent >= 60)
        return 'D';
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
        if (!in_array($status, ['P', 'EA', 'UA'], true))
            continue;

        $recorded++;
        if ($status === 'P')
            $present++;
    }

    return $recorded ? round(($present / $recorded) * 100) : null;
}

function tfp_facilitator_meetings_status($timestamp, $attendance = null, $now = null)
{
    $timestamp = (int) $timestamp;
    $now = $now !== null ? (int) $now : current_time('timestamp', true);

    if ($timestamp > $now) {
        return 'upcoming';
    }

    if ($timestamp <= $now && $now < ($timestamp + HOUR_IN_SECONDS)) {
        return 'in_progress';
    }

    return $attendance !== null ? 'in_progress' : 'needs_log';
}

function tfp_facilitator_meetings_rows($cohorts)
{
    $rows = [];

    foreach ((array) $cohorts as $cohort) {
        $cohort_id = absint($cohort->ID);
        $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
        $schedule = (string) get_post_meta($cohort_id, '_schedule_text', true);
        $start_date = (string) get_post_meta($cohort_id, '_start_date', true);

        if (!$course_id || !function_exists('tfp_ld_get_weeks'))
            continue;

        $weeks = array_values((array) tfp_ld_get_weeks($course_id));
        $student_ids = function_exists('tfp_facilitator_cohort_students')
            ? (array) tfp_facilitator_cohort_students($cohort_id)
            : [];
        $zone = tfp_facilitator_meetings_schedule($schedule);

        foreach ($weeks as $index => $lesson) {
            if (!$lesson instanceof WP_Post)
                continue;

            $timestamp = tfp_facilitator_meetings_timestamp($start_date, $schedule, $index);
            if (!$timestamp)
                continue;

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
                'request_date' => wp_date('Y-m-d\TH:i', $timestamp, $zone['timezone']),
                'homework_label' => tfp_facilitator_meetings_letter($scores['homework']),
                'test_label' => tfp_facilitator_meetings_letter($scores['test']),
                'attendance_percent' => $attendance,
                'attendance_label' => $attendance !== null ? $attendance . '%' : '—',
                'meeting_status' => tfp_facilitator_meetings_status($timestamp, $attendance),
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

    // Restrict every request query to cohorts assigned to the current facilitator.
    // This prevents the "All Cohorts" view from exposing other facilitators' requests.
    $allowed_cohort_ids = array_values(array_filter(array_map(
        'absint',
        wp_list_pluck(tf_facilitator_meetings_cohorts_safe(), 'ID')
    )));

    if (!$allowed_cohort_ids) {
        return [];
    }

    $requested_cohort_id = absint($args['cohort_id']);
    if ($requested_cohort_id && !in_array($requested_cohort_id, $allowed_cohort_ids, true)) {
        return [];
    }

    $meta_query = [
        'relation' => 'AND',
        [
            'key' => '_tfp_request_cohort_id',
            'value' => $requested_cohort_id ? [$requested_cohort_id] : $allowed_cohort_ids,
            'compare' => 'IN',
            'type' => 'NUMERIC',
        ],
    ];

    foreach ([
        '_tfp_request_status' => $args['status'],
        '_tfp_request_type' => $args['type'],
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
            'date' => $date && strtotime($date)
                ? wp_date('M j, Y, g:i A', (new DateTimeImmutable($date, new DateTimeZone('UTC')))->getTimestamp(), wp_timezone())
                : '—',
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

    // Interpret datetime-local input in the WordPress site timezone, then store UTC.
    $request_datetime = $date
        ? DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $date, wp_timezone())
        : false;
    $date_errors = DateTimeImmutable::getLastErrors();
    if (
        !$request_datetime
        || ($date_errors && ($date_errors['warning_count'] || $date_errors['error_count']))
        || $reason === ''
    ) {
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
    update_post_meta($request_id, '_tfp_request_date', $request_datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
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
    $allowed_statuses = ['pending', 'in_progress', 'needs_review', 'acknowledged', 'approved', 'denied'];

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


/**
 * Render the cohort-specific weekly dashboard inside the existing Meetings route.
 * All values are resolved from the assigned cohort, LearnDash lessons and saved student progress.
 */
/**
 * Render the cohort-specific weekly dashboard using the existing facilitator data services.
 * The markup follows Figma node 1172:785; no static sample/student data is used.
 */

/**
 * Render the week-level attendance workspace shown in the Facilitator Course View.
 * Existing attendance meta and audit-history helpers are reused without changing
 * the storage format used by the student profile attendance editor.
 */
function tfp_facilitator_meetings_render_attendance($cohort, $course_id, $weeks, $week_number, $lesson, $students)
{
    $base_url = tfp_facilitator_meetings_url();
    $page_url = add_query_arg([
        'tfp_meeting_cohort_id' => (int) $cohort->ID,
        'tfp_meeting_week' => (int) $week_number,
        'tfp_meeting_view' => 'attendance',
    ], $base_url);
    $back_url = remove_query_arg(['tfp_meeting_cohort_id', 'tfp_meeting_week', 'tfp_meeting_student_page', 'tfp_meeting_view'], $base_url);
    $saved_message = '';
    $error_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tfp_meeting_save_attendance'])) {
        if (!isset($_POST['tfp_meeting_attendance_nonce']) || !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['tfp_meeting_attendance_nonce'])),
            'tfp_meeting_attendance_' . (int) $cohort->ID . '_' . (int) $lesson->ID
        )) {
            $error_message = __('Security check failed. Please refresh and try again.', 'tfp-dashboard');
        } elseif (!$lesson || !in_array((int) $lesson->ID, array_map('absint', wp_list_pluck($weeks, 'ID')), true)) {
            $error_message = __('The selected session is not valid for this course.', 'tfp-dashboard');
        } else {
            $choices = isset($_POST['attendance_choice']) && is_array($_POST['attendance_choice'])
                ? wp_unslash($_POST['attendance_choice'])
                : [];
            $notes = isset($_POST['attendance_note']) && is_array($_POST['attendance_note'])
                ? wp_unslash($_POST['attendance_note'])
                : [];
            $allowed_students = array_fill_keys(array_map('absint', $students), true);
            $saved_count = 0;
            $author_id = get_current_user_id();
            $updated_at = current_time('mysql');

            foreach ($allowed_students as $student_id => $_allowed) {
                $previous = function_exists('tfp_facilitator_student_attendance')
                    ? tfp_facilitator_student_attendance($student_id, (int) $lesson->ID)
                    : [];
                $previous_status = strtoupper((string) ($previous['status'] ?? ''));
                $raw_choice = $choices[$student_id] ?? '';
                $raw_note = $notes[$student_id] ?? '';
                $choice = is_scalar($raw_choice) ? sanitize_key((string) $raw_choice) : '';
                $note = is_scalar($raw_note) ? sanitize_textarea_field((string) $raw_note) : '';

                // Preserve an existing excused absence unless the facilitator changes the selection.
                if ($choice === 'P') {
                    $status = 'P';
                } elseif ($choice === 'A') {
                    $status = $previous_status === 'EA' ? 'EA' : 'UA';
                } else {
                    $status = in_array($previous_status, ['P', 'EA', 'UA'], true) ? $previous_status : '';
                }

                if ($status === '') {
                    continue;
                }

                $previous_note = (string) ($previous['note'] ?? '');
                if ($previous_status === $status && $previous_note === $note) {
                    continue;
                }

                $current = [
                    'status' => $status,
                    'note' => $note,
                    'updated_at' => $updated_at,
                    'author_id' => $author_id,
                ];

                if (function_exists('tfp_facilitator_student_attendance_history')) {
                    $history = tfp_facilitator_student_attendance_history($student_id, (int) $lesson->ID);
                    $history[] = [
                        'action' => $previous_status === '' ? 'created' : 'updated',
                        'old_status' => $previous_status,
                        'old_note' => $previous_note,
                        'status' => $status,
                        'note' => $note,
                        'changed_at' => $updated_at,
                        'author_id' => $author_id,
                    ];
                    update_user_meta($student_id, tfp_facilitator_student_attendance_history_key((int) $lesson->ID), $history);
                }

                update_user_meta($student_id, tfp_facilitator_student_attendance_key((int) $lesson->ID), $current);
                $saved_count++;
            }

            $saved_message = sprintf(
                _n('%d attendance record updated.', '%d attendance records updated.', $saved_count, 'tfp-dashboard'),
                $saved_count
            );
        }
    }

    $course_title = get_the_title($course_id);
    $student_rows = [];
    foreach ($students as $student_id) {
        $student = get_userdata($student_id);
        if (!$student) {
            continue;
        }
        $record = function_exists('tfp_facilitator_student_attendance')
            ? tfp_facilitator_student_attendance($student_id, (int) $lesson->ID)
            : [];
        $status = strtoupper((string) ($record['status'] ?? ''));
        $choice = $status === 'P' ? 'P' : (in_array($status, ['EA', 'UA'], true) ? 'A' : '');
        $student_rows[] = [
            'id' => (int) $student_id,
            'name' => $student->display_name ?: $student->user_email,
            'choice' => $choice,
            'note' => (string) ($record['note'] ?? ''),
        ];
    }
    usort($student_rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    $homework_url = add_query_arg([
        'tfp_homework_course' => (int) $course_id,
        'tfp_homework_cohort_filter' => (int) $cohort->ID,
    ], tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'));
    $tests_url = tfp_facilitator_meetings_session_url((int) $cohort->ID, (int) $week_number, 'tests');
    ?>
    <div class="tfp-course-attendance-page" data-tfp-course-attendance>
        <button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>"><?php echo tfp_dashboard_icon('toggle'); ?></button>

        <nav class="tfp-course-attendance-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url(add_query_arg(['tfp_meeting_tab' => 'upcoming'], $base_url)); ?>"><?php esc_html_e('Meetings', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => (int) $cohort->ID, 'tfp_meeting_week' => (int) $week_number], $base_url)); ?>"><?php echo esc_html($cohort->post_title); ?></a>
            <span>/</span>
            <strong><?php echo esc_html(sprintf(__('Week %d Session', 'tfp-dashboard'), $week_number)); ?></strong>
        </nav>

        <header class="tfp-course-attendance-header">
            <div>
                <h1><?php echo esc_html($cohort->post_title); ?></h1>
                <p><?php echo esc_html($course_title); ?></p>
            </div>
            <a href="<?php echo esc_url($back_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary"><span aria-hidden="true">↩</span><?php esc_html_e('Back to All Meetings', 'tfp-dashboard'); ?></a>
        </header>

        <nav class="tfp-course-view-tabs" aria-label="<?php esc_attr_e('Course view sections', 'tfp-dashboard'); ?>">
            <a class="is-active" href="<?php echo esc_url($page_url); ?>" aria-current="page"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url($homework_url); ?>"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url($tests_url); ?>"><?php esc_html_e('Tests', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url((int) $cohort->ID, $week_number, 'exercise')); ?>"><?php esc_html_e('Exercises', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url((int) $cohort->ID, $week_number, 'notes')); ?>"><?php esc_html_e('Notes', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url((int) $cohort->ID, $week_number, 'closed-session')); ?>"><?php esc_html_e('Closed Session', 'tfp-dashboard'); ?></a>
        </nav>

        <?php if ($saved_message !== ''): ?><div class="tfp-course-attendance-notice is-success" role="status"><?php echo esc_html($saved_message); ?></div><?php endif; ?>
        <?php if ($error_message !== ''): ?><div class="tfp-course-attendance-notice is-error" role="alert"><?php echo esc_html($error_message); ?></div><?php endif; ?>

        <form method="post" action="<?php echo esc_url($page_url); ?>" class="tfp-course-attendance-card" data-tfp-attendance-form>
            <input type="hidden" name="tfp_meeting_save_attendance" value="1">
            <?php wp_nonce_field('tfp_meeting_attendance_' . (int) $cohort->ID . '_' . (int) $lesson->ID, 'tfp_meeting_attendance_nonce'); ?>
            <div class="tfp-course-attendance-card__head">
                <h2><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></h2>
                <label class="tfp-course-attendance-search">
                    <span class="screen-reader-text"><?php esc_html_e('Search by name', 'tfp-dashboard'); ?></span>
                    <?php echo tfp_dashboard_icon('search'); ?>
                    <input type="search" placeholder="<?php esc_attr_e('Search by Name', 'tfp-dashboard'); ?>" data-tfp-attendance-search>
                </label>
            </div>

            <div class="tfp-course-attendance-table-wrap">
                <table class="tfp-course-attendance-table">
                    <thead><tr>
                        <th scope="col"><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                        <th scope="col"><?php esc_html_e('Mark Status', 'tfp-dashboard'); ?></th>
                        <th scope="col"><?php esc_html_e('Session Notes', 'tfp-dashboard'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($student_rows as $row): ?>
                        <tr data-tfp-attendance-row data-student-name="<?php echo esc_attr(strtolower($row['name'])); ?>">
                            <td class="tfp-course-attendance-student"><?php echo esc_html($row['name']); ?></td>
                            <td>
                                <div class="tfp-attendance-choice" role="group" aria-label="<?php echo esc_attr(sprintf(__('Attendance status for %s', 'tfp-dashboard'), $row['name'])); ?>">
                                    <button type="button" class="tfp-attendance-choice__button is-present <?php echo $row['choice'] === 'P' ? 'is-selected' : ''; ?>" data-tfp-attendance-choice="P" aria-pressed="<?php echo $row['choice'] === 'P' ? 'true' : 'false'; ?>"><?php esc_html_e('Present', 'tfp-dashboard'); ?></button>
                                    <button type="button" class="tfp-attendance-choice__button is-absent <?php echo $row['choice'] === 'A' ? 'is-selected' : ''; ?>" data-tfp-attendance-choice="A" aria-pressed="<?php echo $row['choice'] === 'A' ? 'true' : 'false'; ?>"><?php esc_html_e('Absent', 'tfp-dashboard'); ?></button>
                                    <input type="hidden" name="attendance_choice[<?php echo esc_attr($row['id']); ?>]" value="<?php echo esc_attr($row['choice']); ?>" data-tfp-attendance-value>
                                </div>
                            </td>
                            <td><input class="tfp-course-attendance-note" type="text" name="attendance_note[<?php echo esc_attr($row['id']); ?>]" value="<?php echo esc_attr($row['note']); ?>" placeholder="<?php esc_attr_e('Add session notes for this disciple', 'tfp-dashboard'); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$student_rows): ?><tr><td colspan="3"><div class="tfp-facilitator-empty"><?php esc_html_e('No disciples are assigned to this cohort.', 'tfp-dashboard'); ?></div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="tfp-course-attendance-card__footer">
                <span><?php echo esc_html(sprintf(_n('%d disciple', '%d disciples', count($student_rows), 'tfp-dashboard'), count($student_rows))); ?></span>
                <div>
                    <button type="submit" class="tfp-dash-btn tfp-dash-btn--secondary"><?php esc_html_e('Save Attendance', 'tfp-dashboard'); ?></button>
                    <a href="<?php echo esc_url($homework_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Next: Open Homework', 'tfp-dashboard'); ?> <span aria-hidden="true">➜</span></a>
                </div>
            </footer>
        </form>
    </div>
    <?php
}



/**
 * Render the cohort/week homework review using the existing LearnDash answers
 * and facilitator review records. No separate homework data is created.
 */
function tfp_facilitator_meetings_render_session_homework($cohort, $week_number)
{
    $cohort_id = absint($cohort->ID);
    $course_id = absint(get_post_meta($cohort_id, '_related_course', true));
    $weeks = $course_id && function_exists('tfp_ld_get_weeks') ? array_values((array) tfp_ld_get_weeks($course_id)) : [];
    $week_number = max(1, absint($week_number));
    $lesson = $weeks[$week_number - 1] ?? null;

    if (!$lesson instanceof WP_Post) {
        wp_die(esc_html__('The selected course week could not be found.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    $student_ids = function_exists('tfp_facilitator_cohort_students')
        ? array_values(array_unique(array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))))
        : [];
    $questions = function_exists('tfp_week_get_homework_questions')
        ? tfp_week_get_homework_questions((int) $lesson->ID, false)
        : [];
    $students = [];
    $question_stats = [];

    foreach ($questions as $index => $question) {
        $question_id = sanitize_key((string) ($question['id'] ?? ''));
        if (!$question_id) {
            continue;
        }
        $question_stats[$question_id] = [
            'index' => (int) $index,
            'prompt' => (string) ($question['prompt'] ?? ''),
            'wrong' => 0,
            'answered' => 0,
            'percent' => null,
        ];
    }

    foreach ($student_ids as $student_id) {
        $user = get_userdata($student_id);
        if (!$user) {
            continue;
        }
        $answers = function_exists('tfp_week_get_homework_answers')
            ? (array) tfp_week_get_homework_answers($student_id, (int) $lesson->ID)
            : [];
        $review = function_exists('tfp_facilitator_homework_review_data')
            ? tfp_facilitator_homework_review_data($student_id, (int) $lesson->ID)
            : ['questions' => []];
        $student_questions = [];

        foreach ($questions as $index => $question) {
            $question_id = sanitize_key((string) ($question['id'] ?? ''));
            if (!$question_id) {
                continue;
            }
            $answer = isset($answers[$question_id]) && is_array($answers[$question_id]) ? $answers[$question_id] : [];
            $has_answer = function_exists('tfp_week_is_question_answered')
                ? tfp_week_is_question_answered($question, $answer)
                : !empty($answer);
            $answer_text = function_exists('tfp_facilitator_homework_question_answer')
                ? tfp_facilitator_homework_question_answer($question, $answer)
                : trim((string) ($answer['text'] ?? ''));
            $review_item = isset($review['questions'][$question_id]) && is_array($review['questions'][$question_id])
                ? $review['questions'][$question_id]
                : [];
            $decision = sanitize_key((string) ($review_item['decision'] ?? ''));
            $is_wrong = $decision === 'incorrect';
            $can_auto_grade = ($question['type'] ?? '') === 'multiple_choice'
                && $has_answer
                && function_exists('tfp_week_is_homework_answer_correct');
            if ($decision !== '' || $can_auto_grade) {
                if ($can_auto_grade && $decision === '') {
                    $is_wrong = !tfp_week_is_homework_answer_correct($question, $answer);
                }
                if ($has_answer && isset($question_stats[$question_id])) {
                    $question_stats[$question_id]['answered']++;
                    if ($is_wrong) {
                        $question_stats[$question_id]['wrong']++;
                    }
                }
            }
            $student_questions[$question_id] = [
                'answer' => $has_answer ? $answer_text : __('Not submitted', 'tfp-dashboard'),
                'has_answer' => (bool) $has_answer,
                'decision' => $decision,
                'feedback' => (string) ($review_item['feedback'] ?? ''),
                'mastery' => isset($review_item['mastery']) && is_array($review_item['mastery']) ? $review_item['mastery'] : [],
            ];
        }

        $students[] = [
            'id' => $student_id,
            'name' => $user->display_name ?: $user->user_email,
            'questions' => $student_questions,
        ];
    }


    // Staging-only sample preview; demo answers and students are never persisted.
    $demo_environment = wp_get_environment_type();
    $demo_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $demo_path = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    // Enable previews on staging/development/local installs, including staging folders on a production domain.
    $demo_allowed = in_array($demo_environment, ['staging', 'development', 'local'], true)
        || in_array($demo_host, ['localhost', '127.0.0.1'], true)
        || (bool) preg_match('/\.(local|test|localhost)$/i', $demo_host)
        || strpos($demo_path, '/thefollowprojectstaging') !== false;
    $demo_mode = $demo_allowed && isset($_GET['tfp_homework_demo']) && sanitize_text_field(wp_unslash($_GET['tfp_homework_demo'])) === '1';
    if ($demo_mode) {
        $questions = [
            ['id' => 'demo_hw_q1', 'prompt' => 'What does it mean to show kindness to others?', 'type' => 'long_text'],
            ['id' => 'demo_hw_q2', 'prompt' => 'How can you apply this week’s lesson in daily life?', 'type' => 'long_text'],
        ];
        $sample_students = [
            ['id' => 'demo_student_1', 'name' => 'Alex Morgan', 'answers' => ['demo_hw_q1' => 'Showing patience, listening carefully, and helping someone in need.', 'demo_hw_q2' => 'I will practise the lesson by supporting my family and community.']],
            ['id' => 'demo_student_2', 'name' => 'Ava Thompson', 'answers' => ['demo_hw_q1' => 'Treating people with respect and offering help when they need it.', 'demo_hw_q2' => 'I will take time to encourage others and respond with compassion.']],
            ['id' => 'demo_student_3', 'name' => 'Benjamin Carter', 'answers' => ['demo_hw_q1' => 'Being thoughtful and putting another person’s needs first.', 'demo_hw_q2' => 'I will use the lesson when making decisions at school and at home.']],
            ['id' => 'demo_student_4', 'name' => 'Chloe Bennett', 'answers' => ['demo_hw_q1' => 'Listening without judging and giving practical support.', 'demo_hw_q2' => 'I will look for one practical way to help someone each day.']],
        ];
        $students = [];
        $question_stats = [];
        foreach ($questions as $index => $question) {
            $question_stats[$question['id']] = ['index' => $index, 'prompt' => $question['prompt'], 'wrong' => 1, 'answered' => 4, 'percent' => 25];
        }
        foreach ($sample_students as $sample) {
            $student_questions = [];
            foreach ($questions as $question) {
                $qid = $question['id'];
                $student_questions[$qid] = [
                    'answer' => $sample['answers'][$qid],
                    'has_answer' => true,
                    'decision' => 'reviewed',
                    'feedback' => 'Good reflection. Consider adding one specific example from the lesson.',
                    'mastery' => [],
                ];
            }
            $students[] = ['id' => $sample['id'], 'name' => $sample['name'], 'questions' => $student_questions];
        }
    }

    foreach ($question_stats as &$stat) {
        $stat['percent'] = $stat['answered'] > 0 ? (int) round(($stat['wrong'] / $stat['answered']) * 100) : null;
    }
    unset($stat);

    $base_url = tfp_facilitator_meetings_url();
    $demo_url = add_query_arg([
        'tfp_meeting_cohort_id' => $cohort_id,
        'tfp_meeting_week' => $week_number,
        'tfp_meeting_session_view' => 'homework',
        'tfp_homework_demo' => $demo_mode ? '0' : '1',
    ], $base_url);
    $attendance_url = add_query_arg([
        'tfp_meeting_cohort_id' => $cohort_id,
        'tfp_meeting_week' => $week_number,
        'tfp_meeting_session_view' => 'attendance',
    ], $base_url);
    $all_meetings_url = remove_query_arg(
        ['tfp_meeting_cohort_id', 'tfp_meeting_week', 'tfp_meeting_session_view', 'tfp_meeting_student_page'],
        $base_url
    );
    $tests_url = tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'tests');
    $first_student = !empty($students) ? (string) $students[0]['id'] : '';
    $first_question = !empty($questions) ? sanitize_key((string) ($questions[0]['id'] ?? '')) : '';
    $nonce = wp_create_nonce('tfp_facilitator_student_ajax');
    $question_payload = [];
    foreach ($questions as $index => $question) {
        $question_id = sanitize_key((string) ($question['id'] ?? ''));
        if (!$question_id) {
            continue;
        }
        $correct_answer = function_exists('tfp_facilitator_homework_correct_answer') ? tfp_facilitator_homework_correct_answer($question) : '';
        $explanation = function_exists('tfp_facilitator_homework_question_explanation') ? tfp_facilitator_homework_question_explanation($question) : '';
        $scripture = function_exists('tfp_facilitator_homework_question_scripture') ? tfp_facilitator_homework_question_scripture($question) : '';

        $question_payload[] = [
            'id' => $question_id,
            'number' => $index + 1,
            'prompt' => (string) ($question['prompt'] ?? ''),
            'correct' => $correct_answer,
            'explanation' => $explanation,
            'scripture' => $scripture,
            'wrong' => $question_stats[$question_id]['wrong'] ?? 0,
            'answered' => $question_stats[$question_id]['answered'] ?? 0,
            'percent' => $question_stats[$question_id]['percent'] ?? null,
        ];
    }
    ?>
    <div class="tfp-meeting-homework-page" data-tfp-session-homework
        data-cohort-id="<?php echo esc_attr($cohort_id); ?>"
        data-lesson-id="<?php echo esc_attr((int) $lesson->ID); ?>"
        data-student-id="<?php echo esc_attr($first_student); ?>"
        data-nonce="<?php echo esc_attr($nonce); ?>"
        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <nav class="tfp-meeting-homework-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a><span>/</span>
            <a href="<?php echo esc_url($all_meetings_url); ?>"><?php esc_html_e('Meetings', 'tfp-dashboard'); ?></a><span>/</span>
            <a href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => $cohort_id, 'tfp_meeting_week' => $week_number], $base_url)); ?>"><?php echo esc_html($cohort->post_title); ?></a><span>/</span>
            <strong><?php echo esc_html(sprintf(__('Week %d Session', 'tfp-dashboard'), $week_number)); ?></strong>
        </nav>
        <header class="tfp-meeting-homework-header">
            <div><h1><?php echo esc_html($cohort->post_title); ?></h1><p><?php echo esc_html($lesson->post_title); ?></p></div>
            <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($all_meetings_url); ?>">↩ <?php esc_html_e('Back to All Meetings', 'tfp-dashboard'); ?></a>
        </header>
        <nav class="tfp-meeting-homework-tabs" aria-label="<?php esc_attr_e('Course session sections', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url($attendance_url); ?>"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></a>
            <span class="is-active" aria-current="page"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></span>
            <a href="<?php echo esc_url($tests_url); ?>"><?php esc_html_e('Tests', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'exercise')); ?>"><?php esc_html_e('Exercises', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'notes')); ?>"><?php esc_html_e('Notes', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'closed-session')); ?>"><?php esc_html_e('Closed Session', 'tfp-dashboard'); ?></a>
        </nav>

        <section class="tfp-meeting-homework-surface">
            <header class="tfp-meeting-homework-surface__head">
                <h2><?php esc_html_e('Homework', 'tfp-dashboard'); ?></h2>
                <?php if ($demo_allowed): ?><a class="tfp-meeting-homework-demo-toggle" href="<?php echo esc_url($demo_url); ?>"><?php echo esc_html($demo_mode ? 'Exit Demo Preview' : 'Load Demo Data'); ?></a><?php endif; ?>
            </header>
            <?php if ($demo_mode): ?><div class="tfp-meeting-homework-demo-notice" role="status">DEMO PREVIEW — 2 sample questions and 4 sample student submissions. Notes are read-only; no data is saved.</div><?php endif; ?>
            <?php if (!$questions): ?>
                <div class="tfp-meeting-homework-empty"><h3><?php esc_html_e('No homework content available', 'tfp-dashboard'); ?></h3><p><?php printf(esc_html__('There are no homework questions configured for Week %d.', 'tfp-dashboard'), $week_number); ?></p></div>
            <?php else: ?>
                <div class="tfp-meeting-homework-metrics">
                    <?php foreach ($question_payload as $q): ?>
                        <button type="button" class="tfp-meeting-homework-metric<?php echo $q['id'] === $first_question ? ' is-active' : ''; ?>" data-homework-question-select="<?php echo esc_attr($q['id']); ?>">
                            <small><?php echo esc_html(sprintf(__('Question %d', 'tfp-dashboard'), $q['number'])); ?></small>
                            <strong><?php echo $q['percent'] === null ? '—' : esc_html($q['percent'] . '%'); ?></strong>
                            <span><?php echo esc_html(sprintf(__('%d Students Wrong', 'tfp-dashboard'), (int) $q['wrong'])); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <nav class="tfp-meeting-homework-question-tabs" aria-label="<?php esc_attr_e('Homework questions', 'tfp-dashboard'); ?>">
                    <?php foreach ($question_payload as $q): ?>
                        <button type="button" class="<?php echo $q['id'] === $first_question ? 'is-active' : ''; ?>" data-homework-question-select="<?php echo esc_attr($q['id']); ?>">Q<?php echo esc_html($q['number']); ?></button>
                    <?php endforeach; ?>
                </nav>
                <div class="tfp-meeting-homework-content">
                    <main class="tfp-meeting-homework-reference">
                        <?php foreach ($question_payload as $q): ?>
                            <article data-homework-question-panel="<?php echo esc_attr($q['id']); ?>" <?php echo $q['id'] === $first_question ? '' : 'hidden'; ?>>
                                <h3 data-homework-prompt><?php echo esc_html($q['prompt']); ?></h3>
                                <h4><?php esc_html_e('Curriculum Reference', 'tfp-dashboard'); ?></h4>
                                <h5><?php esc_html_e('Correct Answer', 'tfp-dashboard'); ?></h5>
                                <div data-homework-correct><?php echo wp_kses_post(wpautop($q['correct'] ?: __('No reference answer was provided for this question.', 'tfp-dashboard'))); ?></div>
                                <h5><?php esc_html_e('Explanation:', 'tfp-dashboard'); ?></h5>
                                <div data-homework-explanation><?php echo wp_kses_post(wpautop($q['explanation'])); ?></div>
                                <?php if (!empty($q['scripture'])): ?><h5><?php esc_html_e('Scripture:', 'tfp-dashboard'); ?></h5><blockquote><?php echo wp_kses_post(wpautop($q['scripture'])); ?></blockquote><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </main>
                    <aside class="tfp-meeting-homework-students">
                        <p class="tfp-meeting-homework-note-hint"><?php esc_html_e('Notes are optional for Homework and Tests, but required for Exercises. Each student must have a note to close out the Exercises tab.', 'tfp-dashboard'); ?></p>
                        <section class="tfp-meeting-homework-note-card">
                            <label for="tfp-meeting-homework-feedback"><?php esc_html_e('Facilitator Notes', 'tfp-dashboard'); ?></label>
                            <textarea id="tfp-meeting-homework-feedback" data-homework-feedback placeholder="<?php esc_attr_e('Add a note about this student’s response...', 'tfp-dashboard'); ?>" <?php echo $demo_mode ? 'readonly' : ''; ?>></textarea>
                            <button type="button" data-homework-save-note <?php disabled($demo_mode); ?>><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                            <small data-homework-save-feedback role="status" aria-live="polite"></small>
                        </section>
                        <h4><?php printf(esc_html__('All Students (%d)', 'tfp-dashboard'), count($students)); ?></h4>
                        <div class="tfp-meeting-homework-student-list">
                            <?php foreach ($students as $student_index => $student): ?>
                                <button type="button" class="<?php echo $student_index === 0 ? 'is-active' : ''; ?>" data-homework-student-select="<?php echo esc_attr($student['id']); ?>">
                                    <span aria-hidden="true"></span><?php echo esc_html($student['name']); ?>
                                </button>
                            <?php endforeach; ?>
                            <?php if (!$students): ?><p><?php esc_html_e('No students are assigned to this cohort.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div>
                        <div class="tfp-meeting-homework-answer-preview" data-homework-answer-preview>
                            <?php esc_html_e('Select a student to view their submitted answer.', 'tfp-dashboard'); ?>
                        </div>
                        <div class="tfp-meeting-homework-legend"><span><?php esc_html_e('Add Notes', 'tfp-dashboard'); ?></span><span><?php esc_html_e('Notes Saved', 'tfp-dashboard'); ?></span></div>
                    </aside>
                </div>
            <?php endif; ?>
            <footer class="tfp-meeting-homework-footer">
                <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($tests_url); ?>"><?php esc_html_e('Next: Open Test', 'tfp-dashboard'); ?> →</a>
            </footer>
        </section>
        <script type="application/json" data-tfp-homework-data><?php echo wp_json_encode(['questions' => $question_payload, 'students' => $students], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    </div>
    <?php
}


/**
 * Save the facilitator's per-student, per-question note without changing
 * the submission's grading/completion state.
 */
function tfp_facilitator_meetings_save_homework_note()
{
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_send_json_error(['message' => __('Unauthorized.', 'tfp-dashboard')], 403);
    }
    check_ajax_referer('tfp_facilitator_student_ajax', 'nonce');

    $student_id = absint($_POST['student_id'] ?? 0);
    $lesson_id = absint($_POST['lesson_id'] ?? 0);
    $cohort_id = absint($_POST['cohort_id'] ?? 0);
    $question_id = sanitize_key(wp_unslash($_POST['question_id'] ?? ''));
    $feedback = sanitize_textarea_field(wp_unslash($_POST['feedback'] ?? ''));

    if (!function_exists('tfp_facilitator_homework_validate_context')
        || !tfp_facilitator_homework_validate_context($student_id, $lesson_id, $cohort_id)) {
        wp_send_json_error(['message' => __('Invalid homework review context.', 'tfp-dashboard')], 400);
    }

    $questions = function_exists('tfp_week_get_homework_questions')
        ? tfp_week_get_homework_questions($lesson_id, false)
        : [];
    $valid_question = false;
    foreach ($questions as $question) {
        if ((string) ($question['id'] ?? '') === $question_id) {
            $valid_question = true;
            break;
        }
    }
    if (!$valid_question) {
        wp_send_json_error(['message' => __('The selected homework question was not found.', 'tfp-dashboard')], 400);
    }

    $review = tfp_facilitator_homework_review_data($student_id, $lesson_id);
    $existing = isset($review['questions'][$question_id]) && is_array($review['questions'][$question_id])
        ? $review['questions'][$question_id]
        : [];
    $existing['feedback'] = $feedback;
    $existing['updated_at'] = current_time('mysql');
    $existing['reviewer_id'] = get_current_user_id();
    $review['questions'][$question_id] = $existing;
    $review['reviewer_id'] = get_current_user_id();

    update_user_meta($student_id, tfp_facilitator_homework_review_key($lesson_id), $review);
    wp_send_json_success(['message' => __('Notes saved.', 'tfp-dashboard')]);
}
add_action('wp_ajax_tfp_facilitator_meeting_save_homework_note', 'tfp_facilitator_meetings_save_homework_note');

/**
 * Render the facilitator's editable attendance screen for one cohort week.
 * Reads and writes the existing per-student attendance user-meta records.
 */
function tfp_facilitator_meetings_render_session_attendance($cohort, $week_number)
{
    $cohort_id = absint($cohort->ID);
    $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
    $weeks = $course_id && function_exists('tfp_ld_get_weeks')
        ? array_values((array) tfp_ld_get_weeks($course_id))
        : [];
    $week_number = max(1, absint($week_number));
    $lesson = $weeks[$week_number - 1] ?? null;

    if (!$lesson instanceof WP_Post) {
        wp_die(esc_html__('The selected course week could not be found.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    $student_ids = function_exists('tfp_facilitator_cohort_students')
        ? array_values(array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id)))
        : [];
    $students = [];
    foreach ($student_ids as $student_id) {
        $student = get_userdata($student_id);
        if (!$student) {
            continue;
        }
        $record = function_exists('tfp_facilitator_student_attendance')
            ? tfp_facilitator_student_attendance($student_id, $lesson->ID)
            : [];
        $students[] = [
            'id' => $student_id,
            'name' => $student->display_name ?: $student->user_email,
            'status' => strtoupper((string) ($record['status'] ?? '')),
            'note' => (string) ($record['note'] ?? ''),
        ];
    }
    usort($students, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    // Staging-only demo preview; sample records stay in memory and are never saved.
    $demo_environment = wp_get_environment_type();
    $demo_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $demo_path = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    // Enable previews on staging/development/local installs, including staging folders on a production domain.
    $demo_allowed = in_array($demo_environment, ['staging', 'development', 'local'], true)
        || in_array($demo_host, ['localhost', '127.0.0.1'], true)
        || (bool) preg_match('/\.(local|test|localhost)$/i', $demo_host)
        || strpos($demo_path, '/thefollowprojectstaging') !== false;
    $demo_mode = $demo_allowed && isset($_GET['tfp_attendance_demo']) && sanitize_text_field(wp_unslash($_GET['tfp_attendance_demo'])) === '1';
    if ($demo_mode) {
        $demo_names = ['Alex Morgan', 'Ava Thompson', 'Benjamin Carter', 'Chloe Bennett', 'Daniel Foster', 'Ella Richardson', 'Ethan Brooks', 'Grace Mitchell'];
        $demo_statuses = ['P', 'P', 'UA', 'P', 'EA', 'P', 'UA', 'P'];
        $demo_notes = ['Participated actively.', 'Arrived on time.', 'Absent without notice.', 'Engaged throughout the session.', 'Excused absence.', 'Contributed to discussion.', 'Follow-up required.', 'Completed all activities.'];
        $students = [];
        foreach ($demo_names as $index => $name) {
            $students[] = ['id' => 'demo_student_' . ($index + 1), 'name' => $name, 'status' => $demo_statuses[$index], 'note' => $demo_notes[$index]];
        }
    }
    $demo_url = add_query_arg([
        'tfp_meeting_cohort_id' => $cohort_id,
        'tfp_meeting_week' => $week_number,
        'tfp_meeting_session_view' => 'attendance',
        'tfp_attendance_demo' => $demo_mode ? '0' : '1',
    ], tfp_facilitator_meetings_url());

    $base_url = tfp_facilitator_meetings_url();
    $back_url = add_query_arg([
        'tfp_meeting_cohort_id' => $cohort_id,
        'tfp_meeting_week' => $week_number,
    ], $base_url);
    $all_meetings_url = remove_query_arg(
        ['tfp_meeting_cohort_id', 'tfp_meeting_week', 'tfp_meeting_session_view', 'tfp_meeting_student_page'],
        $base_url
    );
    $homework_url = add_query_arg([
        'tfp_meeting_cohort_id' => $cohort_id,
        'tfp_meeting_week' => $week_number,
        'tfp_meeting_session_view' => 'homework',
    ], $base_url);
    $schedule = (string) get_post_meta($cohort_id, '_schedule_text', true);
    $course_title = $course_id ? get_the_title($course_id) : __('Course', 'tfp-dashboard');
    $nonce = wp_create_nonce('tfp_facilitator_meeting_attendance');
    ?>
    <div class="tfp-meeting-attendance-page" data-tfp-session-attendance>
        <nav class="tfp-meeting-attendance-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url($all_meetings_url); ?>"><?php esc_html_e('Meetings', 'tfp-dashboard'); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url($back_url); ?>"><?php echo esc_html($cohort->post_title); ?></a>
            <span>/</span>
            <strong><?php echo esc_html(sprintf(__('Week %d Session', 'tfp-dashboard'), $week_number)); ?></strong>
        </nav>

        <header class="tfp-meeting-attendance-header">
            <div>
                <h1><?php echo esc_html($cohort->post_title); ?></h1>
                <p><?php echo esc_html($lesson->post_title); ?><?php echo $schedule ? ' • ' . esc_html($schedule) : ''; ?></p>
            </div>
            <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($all_meetings_url); ?>">
                <span aria-hidden="true">↩</span><?php esc_html_e('Back to All Meetings', 'tfp-dashboard'); ?>
            </a>
        </header>

        <nav class="tfp-meeting-attendance-tabs" aria-label="<?php esc_attr_e('Course session sections', 'tfp-dashboard'); ?>">
            <span class="is-active" aria-current="page"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></span>
            <a href="<?php echo esc_url($homework_url); ?>"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'tests')); ?>"><?php esc_html_e('Tests', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'exercise')); ?>"><?php esc_html_e('Exercises', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'notes')); ?>"><?php esc_html_e('Notes', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'closed-session')); ?>"><?php esc_html_e('Closed Session', 'tfp-dashboard'); ?></a>
        </nav>

        <section class="tfp-meeting-attendance-panel">
            <header class="tfp-meeting-attendance-panel__head">
                <h2><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></h2>
                <?php if ($demo_allowed): ?><a class="tfp-meeting-homework-demo-toggle" href="<?php echo esc_url($demo_url); ?>"><?php echo esc_html($demo_mode ? 'Exit Demo Preview' : 'Load Demo Data'); ?></a><?php endif; ?>
                <label class="tfp-meeting-attendance-search">
                    <span class="screen-reader-text"><?php esc_html_e('Search by name', 'tfp-dashboard'); ?></span>
                    <?php echo tfp_dashboard_icon('search'); ?>
                    <input type="search" placeholder="<?php esc_attr_e('Search by Name', 'tfp-dashboard'); ?>" data-tfp-attendance-search>
                </label>
            </header>
            <?php if ($demo_mode): ?><div class="tfp-meeting-homework-demo-notice" role="status">DEMO PREVIEW — 8 sample students and attendance records. Changes are disabled; no student data is saved.</div><?php endif; ?>

            <div class="tfp-meeting-attendance-table-wrap">
                <table class="tfp-meeting-attendance-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Mark Status', 'tfp-dashboard'); ?></th>
                            <th scope="col"><?php esc_html_e('Session Notes', 'tfp-dashboard'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-tfp-attendance-rows>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $status = $student['status'];
                        $present_active = $status === 'P';
                        $absent_active = in_array($status, ['EA', 'UA'], true);
                        ?>
                        <tr data-tfp-attendance-row data-student-id="<?php echo esc_attr($student['id']); ?>" data-student-name="<?php echo esc_attr(strtolower($student['name'])); ?>" data-status="<?php echo esc_attr(in_array($status, ['P', 'EA', 'UA'], true) ? $status : ''); ?>">
                            <td class="tfp-meeting-attendance-student"><?php echo esc_html($student['name']); ?></td>
                            <td>
                                <div class="tfp-meeting-attendance-toggle" role="group" aria-label="<?php echo esc_attr(sprintf(__('Attendance status for %s', 'tfp-dashboard'), $student['name'])); ?>">
                                    <button type="button" data-attendance-status="P" class="<?php echo $present_active ? 'is-active is-present' : ''; ?>" aria-pressed="<?php echo $present_active ? 'true' : 'false'; ?>"><?php esc_html_e('Present', 'tfp-dashboard'); ?></button>
                                    <button type="button" data-attendance-status="UA" class="<?php echo $absent_active ? 'is-active is-absent' : ''; ?>" aria-pressed="<?php echo $absent_active ? 'true' : 'false'; ?>"><?php esc_html_e('Absent', 'tfp-dashboard'); ?></button>
                                </div>
                            </td>
                            <td>
                                <input class="tfp-meeting-attendance-note" type="text" maxlength="2000" value="<?php echo esc_attr($student['note']); ?>" placeholder="<?php esc_attr_e('Add session notes for this disciple', 'tfp-dashboard'); ?>" data-attendance-note>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$students): ?>
                        <tr><td colspan="3"><div class="tfp-facilitator-empty"><?php esc_html_e('No disciples are assigned to this cohort.', 'tfp-dashboard'); ?></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <p class="tfp-meeting-attendance-no-results" data-tfp-attendance-empty hidden><?php esc_html_e('No disciples match your search.', 'tfp-dashboard'); ?></p>
            </div>

            <footer class="tfp-meeting-attendance-panel__footer">
                <span class="tfp-meeting-attendance-feedback" role="status" aria-live="polite" data-tfp-attendance-feedback></span>
                <div>
                    <button type="button" class="tfp-dash-btn-second tfp-meeting-attendance-save" data-tfp-attendance-save <?php disabled($demo_mode); ?>><?php esc_html_e('Save Attendance', 'tfp-dashboard'); ?></button>
                    <a href="<?php echo esc_url($homework_url); ?>" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Next: Open Homework', 'tfp-dashboard'); ?><span aria-hidden="true">→</span></a>
                </div>
            </footer>
        </section>

        <script type="application/json" data-tfp-meetings-config><?php echo wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'attendanceNonce' => $nonce,
            'attendanceAction' => 'tfp_facilitator_meeting_save_attendance',
            'cohortId' => $cohort_id,
            'lessonId' => (int) $lesson->ID,
            'demoMode' => $demo_mode,
        ]); ?></script>
    </div>
    <?php
}

/**
 * Bulk-save attendance using the same user-meta schema and audit history as
 * the existing facilitator student attendance editor.
 */
function tfp_facilitator_meetings_save_attendance()
{
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_send_json_error(['message' => __('Unauthorized.', 'tfp-dashboard')], 403);
    }
    check_ajax_referer('tfp_facilitator_meeting_attendance', 'nonce');

    $cohort_id = absint($_POST['cohort_id'] ?? 0);
    $lesson_id = absint($_POST['lesson_id'] ?? 0);
    $records_json = isset($_POST['records']) ? wp_unslash($_POST['records']) : '';
    $records = is_string($records_json) ? json_decode($records_json, true) : [];

    if (!$cohort_id || !$lesson_id || !is_array($records) || count($records) > 500) {
        wp_send_json_error(['message' => __('Invalid attendance submission.', 'tfp-dashboard')], 400);
    }

    $assigned_cohort = null;
    foreach (tfp_facilitator_meetings_cohorts(get_current_user_id()) as $cohort) {
        if (absint($cohort->ID) === $cohort_id) {
            $assigned_cohort = $cohort;
            break;
        }
    }
    if (!$assigned_cohort) {
        wp_send_json_error(['message' => __('You are not allowed to update this cohort.', 'tfp-dashboard')], 403);
    }

    $course_id = (int) get_post_meta($cohort_id, '_related_course', true);
    $weeks = $course_id && function_exists('tfp_ld_get_weeks') ? (array) tfp_ld_get_weeks($course_id) : [];
    $valid_lesson_ids = array_map('absint', wp_list_pluck($weeks, 'ID'));
    if (!in_array($lesson_id, $valid_lesson_ids, true)) {
        wp_send_json_error(['message' => __('The selected week does not belong to this cohort course.', 'tfp-dashboard')], 403);
    }

    $allowed_students = function_exists('tfp_facilitator_cohort_students')
        ? array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))
        : [];
    $allowed_lookup = array_fill_keys($allowed_students, true);
    $saved = 0;
    $updated_at = current_time('mysql');
    $author_id = get_current_user_id();

    foreach ($records as $row) {
        if (!is_array($row)) {
            continue;
        }
        $student_id = absint($row['student_id'] ?? 0);
        $status = strtoupper(sanitize_text_field($row['status'] ?? ''));
        $note = sanitize_textarea_field($row['note'] ?? '');

        if (!$student_id || empty($allowed_lookup[$student_id]) || !in_array($status, ['P', 'EA', 'UA'], true)) {
            continue;
        }

        $previous = function_exists('tfp_facilitator_student_attendance')
            ? tfp_facilitator_student_attendance($student_id, $lesson_id)
            : [];
        $previous_status = (string) ($previous['status'] ?? '');
        $previous_note = (string) ($previous['note'] ?? '');

        if ($previous_status !== $status || $previous_note !== $note) {
            $history = function_exists('tfp_facilitator_student_attendance_history')
                ? tfp_facilitator_student_attendance_history($student_id, $lesson_id)
                : [];
            $history[] = [
                'action' => $previous_status === '' ? 'created' : 'updated',
                'old_status' => $previous_status,
                'old_note' => $previous_note,
                'status' => $status,
                'note' => $note,
                'changed_at' => $updated_at,
                'author_id' => $author_id,
            ];
            update_user_meta(
                $student_id,
                function_exists('tfp_facilitator_student_attendance_history_key')
                    ? tfp_facilitator_student_attendance_history_key($lesson_id)
                    : 'tfp_facilitator_student_attendance_history_' . $lesson_id,
                $history
            );
        }

        update_user_meta(
            $student_id,
            function_exists('tfp_facilitator_student_attendance_key')
                ? tfp_facilitator_student_attendance_key($lesson_id)
                : 'tfp_facilitator_student_attendance_' . $lesson_id,
            [
                'status' => $status,
                'note' => $note,
                'updated_at' => $updated_at,
                'author_id' => $author_id,
            ]
        );
        $saved++;
    }

    wp_send_json_success([
        'saved' => $saved,
        'message' => sprintf(_n('%d attendance record saved.', '%d attendance records saved.', $saved, 'tfp-dashboard'), $saved),
    ]);
}
add_action('wp_ajax_tfp_facilitator_meeting_save_attendance', 'tfp_facilitator_meetings_save_attendance');


function tfp_facilitator_meetings_session_url($cohort_id, $week_number, $view)
{
    return add_query_arg([
        'tfp_meeting_cohort_id' => absint($cohort_id),
        'tfp_meeting_week' => absint($week_number),
        'tfp_meeting_session_view' => sanitize_key($view),
    ], tfp_facilitator_meetings_url());
}

/**
 * Facilitator view of the exercise instructions configured on a LearnDash week.
 * This deliberately reads existing lesson metadata; it does not invent submissions.
 */
/**
 * Save a required facilitator exercise log for one student in an assigned cohort.
 * The record is stored on the lesson and mirrored to the student's user meta.
 */
function tfp_facilitator_meetings_save_exercise_log()
{
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_send_json_error(['message' => __('Permission denied.', 'tfp-dashboard')], 403);
    }

    $cohort_id = absint($_POST['cohort_id'] ?? 0);
    $lesson_id = absint($_POST['lesson_id'] ?? 0);
    $student_id = absint($_POST['student_id'] ?? 0);
    $note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));

    check_ajax_referer('tfp_meeting_exercise_log_' . $cohort_id . '_' . $lesson_id, 'nonce');

    $allowed_cohorts = array_map('absint', wp_list_pluck(tf_facilitator_meetings_cohorts_safe(), 'ID'));
    $course_id = $cohort_id ? absint(get_post_meta($cohort_id, '_related_course', true)) : 0;
    $week_ids = $course_id && function_exists('tfp_ld_get_weeks')
        ? array_map('absint', wp_list_pluck((array) tfp_ld_get_weeks($course_id), 'ID'))
        : [];
    $student_ids = $cohort_id && function_exists('tfp_facilitator_cohort_students')
        ? array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))
        : [];

    if (
        !$cohort_id || !in_array($cohort_id, $allowed_cohorts, true)
        || !$lesson_id || get_post_type($lesson_id) !== 'sfwd-lessons' || !in_array($lesson_id, $week_ids, true)
        || !$student_id || !in_array($student_id, $student_ids, true)
    ) {
        wp_send_json_error(['message' => __('Invalid cohort, lesson or student.', 'tfp-dashboard')], 403);
    }

    if ($note === '') {
        wp_send_json_error(['message' => __('An exercise log is required for every student.', 'tfp-dashboard')], 400);
    }

    $record = [
        'text' => $note,
        'author_id' => get_current_user_id(),
        'updated_at' => current_time('mysql'),
        'cohort_id' => $cohort_id,
        'lesson_id' => $lesson_id,
    ];

    $meta_key = 'tfp_meeting_exercise_logs_' . $cohort_id;
    $logs = get_post_meta($lesson_id, $meta_key, true);
    if (!is_array($logs)) {
        $logs = [];
    }
    $logs[$student_id] = $record;
    update_post_meta($lesson_id, $meta_key, $logs);

    // Keep a student-scoped copy available to the student dashboard experience.
    update_user_meta($student_id, 'tfp_week_meeting_exercise_log_' . $lesson_id, $record);

    wp_send_json_success([
        'message' => __('Exercise log saved.', 'tfp-dashboard'),
        'updated_at' => $record['updated_at'],
    ]);
}
add_action('wp_ajax_tfp_facilitator_meeting_save_exercise_log', 'tfp_facilitator_meetings_save_exercise_log');


/**
 * Render the exercise instructions and required per-student exercise logs for one cohort week.
 * Content comes from the selected LearnDash lesson and assigned cohort students.
 */
function tfp_facilitator_meetings_render_session_exercise($cohort, $week_number)
{
    $cohort_id = absint($cohort->ID);
    $course_id = absint(get_post_meta($cohort_id, '_related_course', true));
    $weeks = $course_id && function_exists('tfp_ld_get_weeks')
        ? array_values((array) tfp_ld_get_weeks($course_id))
        : [];
    $week_number = max(1, absint($week_number));
    $lesson = $weeks[$week_number - 1] ?? null;

    if (!$lesson instanceof WP_Post) {
        wp_die(esc_html__('The selected course week could not be found.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    $title = trim((string) get_post_meta($lesson->ID, 'tfp_week_meeting_exercise_title', true));
    $group = trim((string) get_post_meta($lesson->ID, 'tfp_week_meeting_exercise_group_type', true));
    $format = trim((string) get_post_meta($lesson->ID, 'tfp_week_meeting_exercise_format', true));
    $description = (string) get_post_meta($lesson->ID, 'tfp_week_meeting_exercise_description', true);
    $purpose = (string) get_post_meta($lesson->ID, 'tfp_week_meeting_exercise_purpose', true);

    // Reference-based demo content is shown only on staging/development/local installs.
    // Existing configured lesson content always takes priority; demo content is never persisted.
    $demo_environment = wp_get_environment_type();
    $demo_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $demo_path = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    $demo_allowed = in_array($demo_environment, ['staging', 'development', 'local'], true)
        || in_array($demo_host, ['localhost', '127.0.0.1'], true)
        || (bool) preg_match('/\\.(local|test|localhost)$/i', $demo_host)
        || strpos($demo_path, '/thefollowprojectstaging') !== false;
    $exercise_is_empty = $title === '' && $group === '' && $format === '' && trim($description) === '' && trim($purpose) === '';
    $demo_mode = $demo_allowed && $exercise_is_empty
        && (!isset($_GET['tfp_exercise_demo']) || sanitize_text_field(wp_unslash($_GET['tfp_exercise_demo'])) !== '0');

    if ($demo_mode) {
        $title = 'Exercise #2 – Flesh vs. Holy Spirit';
        $group = 'Group/Individual';
        $format = 'Seated • Journaling • Guided Listening';
        $description = "In this exercise, the student remains seated while the facilitator directs the class or small group to journal while speaking and listening to either the voice of the flesh or the voice of the Holy Spirit.\n\nThe goal is to help participants become aware that the flesh does speak, and to begin discerning the difference between thoughts, impressions, and desires that originate from the flesh versus those that are communicated by the Holy Spirit.\n\nParticipants are guided to listen intentionally, write what they hear, and reflect on the source of each impression without judgment. This exercise helps surface internal patterns and strengthens spiritual awareness through observation and practice.";
        $purpose = "The purpose of this exercise is to develop discernment and spiritual awareness by recognizing the distinction between the flesh and the Holy Spirit.\n\nBefore something can be denied, it must first be identified. This exercise creates space for participants to notice how the flesh communicates, while also learning to recognize the voice and leading of the Holy Spirit.\n\nBelievers understand that Jesus has taken authority over all flesh, and through the Holy Spirit we are empowered to subdue the self’s voice and desires. This exercise supports that process by building awareness, humility, and trust in God’s guidance through intentional listening and journaling.";
    }

    $student_ids = function_exists('tfp_facilitator_cohort_students')
        ? array_values(array_unique(array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))))
        : [];
    $students = [];
    foreach ($student_ids as $student_id) {
        $user = get_userdata($student_id);
        if (!$user) {
            continue;
        }
        $students[] = [
            'id' => $student_id,
            'name' => $user->display_name ?: $user->user_email,
        ];
    }
    usort($students, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    $logs = get_post_meta($lesson->ID, 'tfp_meeting_exercise_logs_' . $cohort_id, true);
    if (!is_array($logs)) {
        $logs = [];
    }
    foreach ($students as &$student) {
        $entry = isset($logs[$student['id']]) && is_array($logs[$student['id']])
            ? $logs[$student['id']]
            : [];
        $student['note'] = (string) ($entry['text'] ?? '');
        $student['saved'] = $student['note'] !== '';
        $student['updated_at'] = (string) ($entry['updated_at'] ?? '');
    }
    unset($student);

    $first_student = $students[0]['id'] ?? 0;
    $nonce = wp_create_nonce('tfp_meeting_exercise_log_' . $cohort_id . '_' . (int) $lesson->ID);
    $base_url = tfp_facilitator_meetings_url();
    $all_meetings_url = remove_query_arg(
        ['tfp_meeting_cohort_id', 'tfp_meeting_week', 'tfp_meeting_session_view', 'tfp_meeting_student_page'],
        $base_url
    );
    $next_week_url = !empty($weeks[$week_number])
        ? tfp_facilitator_meetings_session_url($cohort_id, $week_number + 1, 'exercise')
        : $all_meetings_url;
    ?>
    <div class="tfp-meeting-homework-page tfp-meeting-exercise-page" data-tfp-session-exercise
        data-cohort-id="<?php echo esc_attr($cohort_id); ?>"
        data-lesson-id="<?php echo esc_attr((int) $lesson->ID); ?>"
        data-nonce="<?php echo esc_attr($nonce); ?>"
        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <nav class="tfp-meeting-homework-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a><span>/</span>
            <a href="<?php echo esc_url($all_meetings_url); ?>"><?php esc_html_e('Meetings', 'tfp-dashboard'); ?></a><span>/</span>
            <a href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => $cohort_id, 'tfp_meeting_week' => $week_number], $base_url)); ?>"><?php echo esc_html($cohort->post_title); ?></a><span>/</span>
            <strong><?php echo esc_html(sprintf(__('Week %d Session', 'tfp-dashboard'), $week_number)); ?></strong>
        </nav>
        <header class="tfp-meeting-homework-header">
            <div><h1><?php echo esc_html($cohort->post_title); ?></h1><p><?php echo esc_html($lesson->post_title); ?></p></div>
            <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($all_meetings_url); ?>">↩ <?php esc_html_e('Back to All Meetings', 'tfp-dashboard'); ?></a>
        </header>
        <nav class="tfp-meeting-homework-tabs" aria-label="<?php esc_attr_e('Course session sections', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'attendance')); ?>"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'homework')); ?>"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'tests')); ?>"><?php esc_html_e('Tests', 'tfp-dashboard'); ?></a>
            <span class="is-active" aria-current="page"><?php esc_html_e('Exercises', 'tfp-dashboard'); ?></span>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'notes')); ?>"><?php esc_html_e('Notes', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'closed-session')); ?>"><?php esc_html_e('Closed Session', 'tfp-dashboard'); ?></a>
        </nav>

        <section class="tfp-meeting-homework-surface tfp-meeting-exercise-surface">
            <header class="tfp-meeting-homework-surface__head">
                <h2><?php echo esc_html($title ?: __('Weekly Exercise', 'tfp-dashboard')); ?></h2>
                <?php if ($demo_allowed): ?>
                    <?php $demo_url = add_query_arg('tfp_exercise_demo', $demo_mode ? '0' : '1', remove_query_arg('tfp_exercise_demo')); ?>
                    <a class="tfp-meeting-homework-demo-toggle" href="<?php echo esc_url($demo_url); ?>"><?php echo esc_html($demo_mode ? 'Exit Demo Preview' : 'Load Demo Data'); ?></a>
                <?php endif; ?>
            </header>
            <div class="tfp-meeting-exercise-content">
                <main class="tfp-meeting-exercise-reference">
                    <?php if ($group || $format): ?>
                        <p class="tfp-meeting-exercise-meta"><?php echo esc_html(implode(' • ', array_filter([$group, $format]))); ?></p>
                    <?php endif; ?>
                    <?php if ($description): ?>
                        <h3><?php esc_html_e('Description', 'tfp-dashboard'); ?></h3>
                        <?php echo wp_kses_post(wpautop($description)); ?>
                    <?php else: ?>
                        <p><?php esc_html_e('No exercise description has been configured for this week.', 'tfp-dashboard'); ?></p>
                    <?php endif; ?>
                    <?php if ($purpose): ?>
                        <h3 class="tfp-meeting-exercise-purpose-title"><?php esc_html_e('Purpose', 'tfp-dashboard'); ?></h3>
                        <?php echo wp_kses_post(wpautop($purpose)); ?>
                    <?php endif; ?>
                </main>

                <aside class="tfp-meeting-exercise-logs">
                    <div class="tfp-meeting-exercise-required">
                        <strong><?php esc_html_e('Exercise Logs are Required', 'tfp-dashboard'); ?></strong>
                        <p><?php esc_html_e('A detailed observation is mandatory for every student during Exercises. All notes will post to student dashboards.', 'tfp-dashboard'); ?></p>
                    </div>

                    <?php if ($students): ?>
                        <section class="tfp-meeting-exercise-note-card">
                            <label data-exercise-selected-name for="tfp-meeting-exercise-log"><?php echo esc_html($students[0]['name']); ?></label>
                            <textarea id="tfp-meeting-exercise-log" data-exercise-note rows="6" maxlength="5000" placeholder="<?php esc_attr_e('Add a detailed observation for this student...', 'tfp-dashboard'); ?>"><?php echo esc_textarea($students[0]['note']); ?></textarea>
                            <button type="button" data-exercise-save><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                            <small data-exercise-feedback role="status" aria-live="polite"></small>
                        </section>
                    <?php else: ?>
                        <p class="tfp-meeting-exercise-empty"><?php esc_html_e('No students are assigned to this cohort.', 'tfp-dashboard'); ?></p>
                    <?php endif; ?>

                    <h4><?php printf(esc_html__('All Students (%d)', 'tfp-dashboard'), count($students)); ?></h4>
                    <div class="tfp-meeting-exercise-student-list">
                        <?php foreach ($students as $index => $student): ?>
                            <button type="button" class="<?php echo trim(($index === 0 ? 'is-active ' : '') . ($student['saved'] ? 'is-saved' : '')); ?>" data-exercise-student="<?php echo esc_attr($student['id']); ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                <span aria-hidden="true"></span><?php echo esc_html($student['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="tfp-meeting-exercise-legend"><span><?php esc_html_e('Add Notes', 'tfp-dashboard'); ?></span><span><?php esc_html_e('Notes Saved', 'tfp-dashboard'); ?></span></div>
                </aside>
            </div>
            <footer class="tfp-meeting-homework-footer">
                <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($next_week_url); ?>"><?php esc_html_e('Next: Open Exercises', 'tfp-dashboard'); ?> →</a>
            </footer>
        </section>
        <script type="application/json" data-exercise-student-data><?php echo wp_json_encode(['students' => $students, 'selected' => $first_student], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    </div>
    <?php
}

/**
 * Display existing student reflections and allow facilitators to append a weekly note.
 * Uses the existing lesson and user metadata keys used by the student week experience.
 */
function tfp_facilitator_meetings_render_session_notes($cohort, $week_number)
{
    $cohort_id = absint($cohort->ID);
    $course_id = function_exists('tfp_facilitator_cohort_course_id')
        ? absint(tfp_facilitator_cohort_course_id($cohort_id))
        : absint(get_post_meta($cohort_id, '_related_course', true));
    $weeks = $course_id && function_exists('tfp_ld_get_weeks') ? array_values((array) tfp_ld_get_weeks($course_id)) : [];
    $week_number = max(1, absint($week_number));
    $lesson = $weeks[$week_number - 1] ?? null;

    if (!$lesson instanceof WP_Post) {
        wp_die(esc_html__('The selected course week could not be found.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    $student_ids = function_exists('tfp_facilitator_cohort_students')
        ? array_values(array_unique(array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))))
        : [];
    $logs_key = 'tfp_meeting_exercise_logs_' . $cohort_id;
    $logs = get_post_meta($lesson->ID, $logs_key, true);
    $logs = is_array($logs) ? $logs : [];

    // Keep internal reflections private to facilitators and scoped to this cohort/week.
    $reflection_key = 'tfp_meeting_internal_reflections_' . $cohort_id;
    $reflections = get_post_meta($lesson->ID, $reflection_key, true);
    $reflections = is_array($reflections) ? $reflections : [];
    $reflection_one = (string) ($reflections['atmosphere'] ?? '');
    $reflection_two = (string) ($reflections['prayer_hurdle'] ?? '');

    $nonce_action = 'tfp_meeting_notes_' . $cohort_id . '_' . (int) $lesson->ID;
    $nonce = wp_create_nonce($nonce_action);
    $feedback = '';

    // Demo preview is available only on staging/development/local installs.
    // It uses display-only sample data and never writes to real student records.
    $demo_environment = wp_get_environment_type();
    $demo_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $demo_path = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    $demo_allowed = in_array($demo_environment, ['staging', 'development', 'local'], true)
        || in_array($demo_host, ['localhost', '127.0.0.1'], true)
        || (bool) preg_match('/\\.(local|test|localhost)$/i', $demo_host)
        || strpos($demo_path, '/thefollowprojectstaging') !== false;
    $demo_mode = $demo_allowed
        && isset($_GET['tfp_notes_demo'])
        && sanitize_text_field(wp_unslash($_GET['tfp_notes_demo'])) === '1';

    // All writes are restricted to this facilitator's assigned cohort and this course week.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tfp_notes_action']) && !$demo_mode) {
        $posted_nonce = sanitize_text_field(wp_unslash($_POST['tfp_notes_nonce'] ?? ''));
        if (!wp_verify_nonce($posted_nonce, $nonce_action)) {
            $feedback = __('Security check failed. Please refresh and try again.', 'tfp-dashboard');
        } else {
            $action = sanitize_key(wp_unslash($_POST['tfp_notes_action']));
            $student_id = absint($_POST['student_id'] ?? 0);
            $allowed_cohorts = array_map('absint', wp_list_pluck(tfp_facilitator_meetings_cohorts_safe(), 'ID'));
            $valid_students = array_map('absint', $student_ids);

            if (!in_array($cohort_id, $allowed_cohorts, true) || ($student_id && !in_array($student_id, $valid_students, true))) {
                $feedback = __('You are not allowed to update this session.', 'tfp-dashboard');
            } elseif ($action === 'save_reflections') {
                $reflection_one = sanitize_textarea_field(wp_unslash($_POST['reflection_atmosphere'] ?? ''));
                $reflection_two = sanitize_textarea_field(wp_unslash($_POST['reflection_prayer_hurdle'] ?? ''));
                update_post_meta($lesson->ID, $reflection_key, [
                    'atmosphere' => $reflection_one,
                    'prayer_hurdle' => $reflection_two,
                    'author_id' => get_current_user_id(),
                    'updated_at' => current_time('mysql'),
                ]);
                $feedback = __('Internal reflections saved.', 'tfp-dashboard');
            } elseif ($action === 'finish_session') {
                $missing = array_filter($student_ids, static function ($id) use ($logs) {
                    return empty(trim((string) ($logs[$id]['text'] ?? '')));
                });
                if ($missing) {
                    $feedback = sprintf(__('Cannot post session: %d mandatory exercise log(s) are missing.', 'tfp-dashboard'), count($missing));
                } else {
                    update_post_meta($lesson->ID, 'tfp_meeting_session_posted_' . $cohort_id, [
                        'posted' => 1,
                        'author_id' => get_current_user_id(),
                        'posted_at' => current_time('mysql'),
                    ]);
                    $feedback = __('Session posted successfully.', 'tfp-dashboard');
                }
            } elseif ($student_id && $action === 'save_log') {
                $note = sanitize_textarea_field(wp_unslash($_POST['exercise_note'] ?? ''));
                if ($note === '') {
                    $feedback = __('An exercise log is required for every student.', 'tfp-dashboard');
                } else {
                    $record = [
                        'text' => $note,
                        'author_id' => get_current_user_id(),
                        'updated_at' => current_time('mysql'),
                        'cohort_id' => $cohort_id,
                        'lesson_id' => (int) $lesson->ID,
                    ];
                    $logs[$student_id] = $record;
                    update_post_meta($lesson->ID, $logs_key, $logs);
                    // Preserve the existing student-dashboard mirror used by the weekly experience.
                    update_user_meta($student_id, 'tfp_week_meeting_exercise_log_' . (int) $lesson->ID, $record);
                    $feedback = __('Exercise log saved.', 'tfp-dashboard');
                }
            } elseif ($student_id && $action === 'delete_log') {
                unset($logs[$student_id]);
                update_post_meta($lesson->ID, $logs_key, $logs);
                delete_user_meta($student_id, 'tfp_week_meeting_exercise_log_' . (int) $lesson->ID);
                delete_post_meta($lesson->ID, 'tfp_meeting_session_posted_' . $cohort_id);
                $feedback = __('Exercise log deleted.', 'tfp-dashboard');
            } elseif ($student_id && $action === 'share_log') {
                $record = $logs[$student_id] ?? [];
                if (empty($record['text'])) {
                    $feedback = __('There is no exercise log to share.', 'tfp-dashboard');
                } else {
                    update_user_meta($student_id, 'tfp_week_meeting_exercise_log_' . (int) $lesson->ID, $record);
                    $feedback = __('Exercise log shared with the student dashboard.', 'tfp-dashboard');
                }
            }
        }

        // Refresh data after any write, keeping the same page and selected session.
        $logs = get_post_meta($lesson->ID, $logs_key, true);
        $logs = is_array($logs) ? $logs : [];
        $reflections = get_post_meta($lesson->ID, $reflection_key, true);
        $reflections = is_array($reflections) ? $reflections : [];
        $reflection_one = (string) ($reflections['atmosphere'] ?? $reflection_one);
        $reflection_two = (string) ($reflections['prayer_hurdle'] ?? $reflection_two);
    }

    $rows = [];
    foreach ($student_ids as $student_id) {
        $user = get_userdata($student_id);
        if (!$user) {
            continue;
        }
        $record = isset($logs[$student_id]) && is_array($logs[$student_id]) ? $logs[$student_id] : [];
        $rows[] = [
            'id' => $student_id,
            'name' => $user->display_name ?: $user->user_email,
            'note' => (string) ($record['text'] ?? ''),
            'updated_at' => (string) ($record['updated_at'] ?? ''),
        ];
    }
    usort($rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    // Replace only the rendered view with sample content while demo mode is active.
    // Sample records are not persisted and all editing/saving controls are disabled below.
    if ($demo_mode) {
        $rows = [
            ['id' => 0, 'name' => 'Sharon Stone', 'note' => 'Lorem ipsum dolor sit amet consectetur adipiscing elit. Sit amet consectetur adipiscing elit quisque faucibus ex. Adipiscing elit quisque faucibus ex sapien vitae pellentesque.', 'updated_at' => '', 'type' => 'Exercise Log'],
            ['id' => 0, 'name' => 'Barry Bromid', 'note' => '', 'updated_at' => '', 'type' => 'Exercise Log Required'],
            ['id' => 0, 'name' => 'Lena Merrick', 'note' => 'Exercise Log', 'updated_at' => '', 'type' => 'Warm-up Routine'],
            ['id' => 0, 'name' => 'Derek Holloway', 'note' => 'Sin – The Beginning', 'updated_at' => '', 'type' => 'Homework Review'],
            ['id' => 0, 'name' => 'Cassandra Voss', 'note' => 'Morning meditation enhanced focus and reduced stress levels significantly.', 'updated_at' => '', 'type' => 'Cool-down Stretches'],
            ['id' => 0, 'name' => 'Elias Montague', 'note' => 'Dietary adjustments made, focusing on increased protein and reduced sugar intake.', 'updated_at' => '', 'type' => 'Progress Notes'],
        ];
        $reflection_one = 'The group was engaged and attentive. Several students shared meaningful observations during the discussion.';
        $reflection_two = 'Continue encouraging consistent Scripture reading and support students who are finding it difficult to build a daily routine.';
    }

    $missing_count = count(array_filter($rows, static function ($row) {
        return trim($row['note']) === '';
    }));
    $posted = !$demo_mode && get_post_meta($lesson->ID, 'tfp_meeting_session_posted_' . $cohort_id, true);
    $posted = is_array($posted) && !empty($posted['posted']);
    $base_url = tfp_facilitator_meetings_url();
    ?>
    <div class="tfp-meeting-session-detail-page tfp-meeting-notes-page">
        <header class="tfp-meeting-homework-header">
            <div>
                <h1><?php echo esc_html($cohort->post_title); ?></h1>
                <p><?php echo esc_html($lesson->post_title); ?></p>
            </div>
            <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($base_url); ?>">↩ <?php esc_html_e('Back to All Meetings', 'tfp-dashboard'); ?></a>
        </header>
        <nav class="tfp-meeting-homework-tabs" aria-label="<?php esc_attr_e('Course session sections', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'attendance')); ?>"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'homework')); ?>"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'tests')); ?>"><?php esc_html_e('Tests', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'exercise')); ?>"><?php esc_html_e('Exercises', 'tfp-dashboard'); ?></a>
            <span class="is-active" aria-current="page"><?php esc_html_e('Notes', 'tfp-dashboard'); ?></span>
        </nav>

        <section class="tfp-meeting-notes-shell">
            <?php if ($demo_allowed): ?>
                <?php $demo_url = add_query_arg('tfp_notes_demo', $demo_mode ? '0' : '1', remove_query_arg('tfp_notes_demo')); ?>
                <div class="tfp-meeting-notes-demo-control"><a class="tfp-meeting-homework-demo-toggle" href="<?php echo esc_url($demo_url); ?>"><?php echo esc_html($demo_mode ? 'Exit Demo Preview' : 'Load Demo Data'); ?></a></div>
            <?php endif; ?>
            <?php if ($demo_mode): ?><p class="tfp-meeting-notes-feedback" role="status">DEMO PREVIEW — Sample observations and private reflections are displayed. Editing, saving, deleting and posting are disabled; no data is stored.</p><?php endif; ?>
            <?php if ($feedback): ?><p class="tfp-meeting-notes-feedback" role="status"><?php echo esc_html($feedback); ?></p><?php endif; ?>
            <div class="tfp-meeting-notes-alert <?php echo $missing_count ? 'is-warning' : 'is-complete'; ?>" role="status">
                <span class="tfp-meeting-notes-alert__icon" aria-hidden="true">!</span>
                <div><strong><?php esc_html_e('Action Required:', 'tfp-dashboard'); ?></strong><small><?php echo esc_html(sprintf(_n('%d Mandatory Exercise Log Missing', '%d Mandatory Exercise Logs Missing', $missing_count, 'tfp-dashboard'), $missing_count)); ?></small></div>
                <p><?php esc_html_e('All students must have an observation to close the session.', 'tfp-dashboard'); ?></p>
            </div>

            <section class="tfp-meeting-notes-review">
                <h2><?php esc_html_e('Final Observation Review', 'tfp-dashboard'); ?></h2>
                <div class="tfp-meeting-notes-table-wrap">
                    <table class="tfp-meeting-notes-table">
                        <thead><tr>
                            <th><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Type of note recorded', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Logged Observation', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr class="<?php echo $row['note'] === '' ? 'is-missing' : ''; ?>" data-notes-row>
                                <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                                <td><?php if ($row['note'] === ''): ?><span class="tfp-meeting-notes-required"><?php esc_html_e('Exercise Log Required', 'tfp-dashboard'); ?></span><?php else: ?><?php echo esc_html($row['type'] ?? __('Exercise Log', 'tfp-dashboard')); ?><?php endif; ?></td>
                                <td>
                                    <div class="tfp-meeting-notes-observation" data-notes-observation><?php echo $row['note'] !== '' ? esc_html($row['note']) : esc_html__('No observation recorded for the mandatory activation exercise.', 'tfp-dashboard'); ?></div>
                                    <form class="tfp-meeting-notes-edit" method="post" hidden>
                                        <input type="hidden" name="tfp_notes_nonce" value="<?php echo esc_attr($nonce); ?>">
                                        <input type="hidden" name="tfp_notes_action" value="save_log">
                                        <input type="hidden" name="student_id" value="<?php echo esc_attr($row['id']); ?>">
                                        <textarea name="exercise_note" maxlength="5000" required><?php echo esc_textarea($row['note']); ?></textarea>
                                        <button type="submit" class="tfp-meeting-notes-action" <?php disabled($demo_mode); ?>><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
                                    </form>
                                </td>
                                <td class="tfp-meeting-notes-actions">
                                    <button type="button" class="tfp-meeting-notes-action" data-notes-edit <?php disabled($demo_mode); ?>><?php echo $row['note'] !== '' ? esc_html__('Edit', 'tfp-dashboard') : esc_html__('Add Note', 'tfp-dashboard'); ?></button>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="4"><?php esc_html_e('No students are assigned to this cohort.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="tfp-meeting-notes-export"><button type="button" class="tfp-meeting-notes-action" data-notes-export><?php esc_html_e('Export', 'tfp-dashboard'); ?></button></div>
            </section>

            <form method="post" class="tfp-meeting-notes-reflections">
                <input type="hidden" name="tfp_notes_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="tfp_notes_action" value="save_reflections">
                <h2><?php esc_html_e('Closing Internal Reflections', 'tfp-dashboard'); ?></h2>
                <label for="tfp-notes-atmosphere"><?php esc_html_e('How was the overall atmosphere and engagement in the room today?', 'tfp-dashboard'); ?></label>
                <textarea id="tfp-notes-atmosphere" name="reflection_atmosphere" rows="4" maxlength="5000" placeholder="<?php esc_attr_e('Record hunger for Scripture, resistance, or general breakthrough...', 'tfp-dashboard'); ?>"><?php echo esc_textarea($reflection_one); ?></textarea>
                <label for="tfp-notes-prayer-hurdle"><?php esc_html_e('What is the primary prayer hurdle for this cohort right now?', 'tfp-dashboard'); ?></label>
                <textarea id="tfp-notes-prayer-hurdle" name="reflection_prayer_hurdle" rows="4" maxlength="5000" placeholder="<?php esc_attr_e('Record hunger for Scripture, resistance, or general breakthrough...', 'tfp-dashboard'); ?>"><?php echo esc_textarea($reflection_two); ?></textarea>
                <p><?php esc_html_e('These reflections are private and will not be displayed on student dashboards.', 'tfp-dashboard'); ?></p>
                <button type="submit" class="tfp-meeting-notes-action" <?php disabled($demo_mode); ?>><?php esc_html_e('Save', 'tfp-dashboard'); ?></button>
            </form>
        </section>

        <footer class="tfp-meeting-notes-footer">
            <form method="post">
                <input type="hidden" name="tfp_notes_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="tfp_notes_action" value="finish_session">
                <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary" <?php disabled($missing_count > 0 || $demo_mode); ?>>
                    <?php echo $posted ? esc_html__('Session Posted', 'tfp-dashboard') : esc_html__('Finish & Post Session', 'tfp-dashboard'); ?> ↗
                </button>
                <?php if ($missing_count): ?><small><?php echo esc_html(sprintf(__('Missing mandatory data for %d student(s).', 'tfp-dashboard'), $missing_count)); ?></small><?php elseif ($posted): ?><small><?php esc_html_e('This session has been posted.', 'tfp-dashboard'); ?></small><?php endif; ?>
            </form>
        </footer>
    </div>

    <?php
}

/** Render the facilitator's question-by-question test review for a cohort week. */
function tfp_facilitator_meetings_render_session_tests($cohort, $week_number)
{
    $cohort_id = absint($cohort->ID);
    $course_id = function_exists('tfp_facilitator_cohort_course_id') ? (int) tfp_facilitator_cohort_course_id($cohort_id) : (int) get_post_meta($cohort_id, '_related_course', true);
    $weeks = $course_id && function_exists('tfp_ld_get_weeks') ? array_values((array) tfp_ld_get_weeks($course_id)) : [];
    $lesson = $weeks[max(0, absint($week_number) - 1)] ?? null;
    if (!$lesson instanceof WP_Post) { wp_die(esc_html__('The selected course week could not be found.', 'tfp-dashboard'), '', ['response' => 404]); }

    $student_ids = function_exists('tfp_facilitator_cohort_students') ? array_values(array_unique(array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id)))) : [];
    $questions = function_exists('tfp_week_get_test_questions') ? (array) tfp_week_get_test_questions($lesson->ID, false) : [];
    // Demo preview is staging-only and uses in-memory sample data; it never writes to student records.
    $demo_environment = wp_get_environment_type();
    $demo_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $demo_path = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_PATH));
    // Enable previews on staging/development/local installs, including staging folders on a production domain.
    $demo_allowed = in_array($demo_environment, ['staging', 'development', 'local'], true)
        || in_array($demo_host, ['localhost', '127.0.0.1'], true)
        || (bool) preg_match('/\.(local|test|localhost)$/i', $demo_host)
        || strpos($demo_path, '/thefollowprojectstaging') !== false;
    // Automatically show sample test data on staging when this week has no configured questions.
    // Explicit tfp_test_demo=0 always exits the preview; tfp_test_demo=1 enables it on demand.
    $demo_mode_requested = isset($_GET['tfp_test_demo'])
        ? sanitize_text_field(wp_unslash($_GET['tfp_test_demo'])) === '1'
        : empty($questions);
    $demo_mode = $demo_allowed && $demo_mode_requested;
    $stats = [];
    foreach ($questions as $i => $q) {
        $qid = sanitize_key((string) ($q['id'] ?? ''));
        if (!$qid) continue;
        $stats[$qid] = ['id'=>$qid,'number'=>$i+1,'prompt'=>(string)($q['prompt']??''),'options'=>(array)($q['options']??[]),'correct_index'=>isset($q['correct_index'])?(int)$q['correct_index']:null,'explanation'=>(string)($q['explanation']??''),'scripture'=>(string)($q['scripture']??''),'wrong'=>0,'answered'=>0];
    }
    $students = [];
    foreach ($student_ids as $student_id) {
        $user = get_userdata($student_id);
        if (!$user) continue;
        $result = function_exists('tfp_week_get_test_result') ? tfp_week_get_test_result($student_id, $lesson->ID) : null;
        foreach ((array) ($result['results'] ?? []) as $answer) {
            $qid = sanitize_key((string)($answer['id']??''));
            if (isset($stats[$qid])) { $stats[$qid]['answered']++; if (empty($answer['is_correct'])) $stats[$qid]['wrong']++; }
        }
        $students[] = ['id'=>$student_id,'name'=>$user->display_name ?: $user->user_email];
    }
    foreach ($stats as &$item) $item['wrong_pct'] = $item['answered'] ? (int)round($item['wrong']/$item['answered']*100) : null;
    unset($item);
    if ($demo_mode) {
        // Sample questions and scores are only rendered; no fake users or grades are persisted.
        $questions = [
            ['id'=>'demo_test_q1','prompt'=>'What is the first step in responding to temptation?','options'=>['Ignore the warning signs','Recognise the temptation and seek God’s guidance','Rely only on personal strength','Avoid asking for help'],'correct_index'=>1,'explanation'=>'Recognising temptation early helps a person pause, seek wisdom, and choose a faithful response.','scripture'=>'1 Corinthians 10:13'],
            ['id'=>'demo_test_q2','prompt'=>'How does obedience demonstrate trust?','options'=>['By following only convenient instructions','By trusting God and acting on His direction','By avoiding difficult decisions','By seeking approval from others'],'correct_index'=>1,'explanation'=>'Obedience puts trust into action, including when the next step is difficult.','scripture'=>'John 14:15'],
            ['id'=>'demo_test_q3','prompt'=>'What should guide daily decisions?','options'=>['Popular opinion','Personal convenience','Wisdom grounded in Scripture','Fear of criticism'],'correct_index'=>2,'explanation'=>'Scripture encourages believers to seek wisdom and allow it to shape everyday choices.','scripture'=>'Proverbs 3:5–6'],
            ['id'=>'demo_test_q4','prompt'=>'Which response best reflects love for others?','options'=>['Listening with patience and offering support','Avoiding people who disagree','Responding immediately in anger','Putting personal interests first'],'correct_index'=>0,'explanation'=>'Practical love is reflected through patience, care, and supportive action.','scripture'=>'1 Corinthians 13:4–7'],
        ];
        $demo_counts = [[4,20],[7,20],[3,20],[5,20]];
        $stats = [];
        foreach ($questions as $i => $q) {
            $qid = sanitize_key($q['id']);
            $stats[$qid] = ['id'=>$qid,'number'=>$i+1,'prompt'=>$q['prompt'],'options'=>$q['options'],'correct_index'=>$q['correct_index'],'explanation'=>$q['explanation'],'scripture'=>$q['scripture'],'wrong'=>$demo_counts[$i][0],'answered'=>$demo_counts[$i][1]];
            $stats[$qid]['wrong_pct'] = (int) round($stats[$qid]['wrong'] / $stats[$qid]['answered'] * 100);
        }
        $demo_names = ['Alex Morgan','Ava Thompson','Benjamin Carter','Chloe Bennett','Daniel Foster','Ella Richardson','Ethan Brooks','Grace Mitchell','Henry Cooper','Isabella Reed','Jack Peterson','Liam Anderson','Mia Collins','Noah Bailey','Olivia Parker','Samuel Hayes','Sophia Turner','Theodore Ward','Victoria Price','William Scott'];
        $students = [];
        foreach ($demo_names as $i => $name) $students[] = ['id'=>'demo_student_'.($i+1),'name'=>$name];
    }
    $first = $stats ? reset($stats) : null;
    $note_key = 'tfp_meeting_test_notes_' . $cohort_id . '_' . (int)$lesson->ID;
    $notes = $demo_mode ? [] : get_post_meta($lesson->ID, $note_key, true);
    if (!is_array($notes)) $notes = [];
    $nonce = wp_create_nonce('tfp_meeting_test_note_'.$cohort_id.'_'.$lesson->ID);
    $demo_url = add_query_arg(['tfp_meeting_cohort_id'=>$cohort_id,'tfp_meeting_week'=>$week_number,'tfp_meeting_session_view'=>'tests','tfp_test_demo'=>$demo_mode?'0':'1'], tfp_facilitator_meetings_url());
    ?>
    <div class="tfp-meeting-session-detail-page tfp-meeting-test-page" data-tfp-session-tests data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-cohort-id="<?php echo esc_attr($cohort_id); ?>" data-lesson-id="<?php echo esc_attr($lesson->ID); ?>">
      <header class="tfp-meeting-homework-header"><div><h1><?php echo esc_html($cohort->post_title); ?></h1><p><?php echo esc_html($lesson->post_title); ?></p></div><a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url(tfp_facilitator_meetings_url(['tfp_meeting_cohort_id'=>$cohort_id,'tfp_meeting_week'=>$week_number])); ?>">↩ Back to All Meetings</a></header>
      <nav class="tfp-meeting-homework-tabs" aria-label="Course session sections">
        <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id,$week_number,'attendance')); ?>">Attendance</a>
        <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id,$week_number,'homework')); ?>">Homework</a>
        <a class="is-active" href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id,$week_number,'tests')); ?>">Tests</a>
        <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id,$week_number,'exercise')); ?>">Exercises</a>
        <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id,$week_number,'notes')); ?>">Notes</a>
      </nav>
      <section class="tfp-meeting-homework-surface tfp-meeting-test-surface"><header class="tfp-meeting-homework-surface__head"><h2>Tests</h2><?php if($demo_allowed): ?><a class="tfp-meeting-homework-demo-toggle" href="<?php echo esc_url($demo_url); ?>"><?php echo esc_html($demo_mode ? 'Exit Demo Preview' : 'Load Demo Data'); ?></a><?php endif; ?></header>
       <?php if($demo_mode): ?><div class="tfp-meeting-homework-demo-notice" role="status">DEMO PREVIEW — 4 sample questions, 20 sample students and sample performance. Notes are read-only; no data is saved.</div><?php endif; ?>
      <?php if (!$stats): ?><div class="tfp-meeting-test-empty"><h3>No test questions found for this week.</h3><p>Add test questions to this course week to view question-level performance.</p></div>
      <?php else: ?>
        <div class="tfp-meeting-test-stats"><?php foreach($stats as $item): ?><button type="button" class="tfp-meeting-test-stat" data-test-question="<?php echo esc_attr($item['id']); ?>" aria-pressed="<?php echo $item['id']===$first['id']?'true':'false'; ?>"><small>Question <?php echo esc_html($item['number']); ?></small><strong><?php echo esc_html($item['wrong_pct']===null?'—':$item['wrong_pct'].'%'); ?></strong><span># Students Wrong</span></button><?php endforeach; ?></div>
        <div class="tfp-meeting-test-question-tabs" role="tablist"><?php foreach($stats as $item): ?><button type="button" role="tab" data-test-tab="<?php echo esc_attr($item['id']); ?>" aria-selected="<?php echo $item['id']===$first['id']?'true':'false'; ?>">Q<?php echo esc_html($item['number']); ?></button><?php endforeach; ?></div>
        <div class="tfp-meeting-test-detail"><div class="tfp-meeting-test-reference"><?php foreach($stats as $item): ?><article data-test-panel="<?php echo esc_attr($item['id']); ?>" <?php echo $item['id']===$first['id']?'':'hidden'; ?>><h3><?php echo esc_html($item['prompt']); ?></h3><h4>Curriculum Reference</h4><?php if($item['correct_index']!==null && isset($item['options'][$item['correct_index']])): ?><h5>Correct Answer</h5><p><?php echo esc_html($item['options'][$item['correct_index']]); ?></p><?php endif; ?><?php if($item['explanation']): ?><h5>Explanation</h5><p><?php echo esc_html($item['explanation']); ?></p><?php endif; ?><?php if($item['scripture']): ?><h5>Scripture</h5><blockquote><?php echo esc_html($item['scripture']); ?></blockquote><?php endif; ?><?php if(!$item['explanation']&&!$item['scripture']): ?><p class="tfp-meeting-test-muted">No additional curriculum reference is available.</p><?php endif; ?></article><?php endforeach; ?></div>
        <aside class="tfp-meeting-test-notes"><p class="tfp-meeting-test-hint">Notes are optional for Homework and Tests, but required for Exercises. Each student must have a note to close out the Exercises tab.</p><form data-test-note-form><select aria-label="Select student" data-test-note-student><?php foreach($students as $student): ?><option value="<?php echo esc_attr($student['id']); ?>"><?php echo esc_html($student['name']); ?></option><?php endforeach; ?></select><textarea data-test-note-text rows="5" maxlength="3000" placeholder="Add a note about this student's understanding…"></textarea><button type="submit" class="tfp-dash-btn tfp-dash-btn--primary" <?php disabled($demo_mode); ?>>Save</button><p role="status" data-test-note-feedback></p></form><h4>All Students (<?php echo count($students); ?>)</h4><div class="tfp-meeting-test-student-list"><?php foreach($students as $student): ?><button type="button" data-test-student="<?php echo esc_attr($student['id']); ?>"><span></span><?php echo esc_html($student['name']); ?></button><?php endforeach; ?></div><div class="tfp-meeting-test-legend"><span>Add Notes</span><span>Notes Saved</span></div></aside></div>
      <?php endif; ?><footer class="tfp-meeting-test-footer"><a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id,$week_number,'exercise')); ?>">Next: Open Exercises →</a></footer></section>
      <script type="application/json" data-test-note-data><?php echo wp_json_encode(['notes'=>$notes,'students'=>$students,'questions'=>array_values($stats),'demoMode'=>$demo_mode]); ?></script>
    </div>
    <?php
}

/** Save a facilitator's private note for one student and test question. */
function tfp_facilitator_meetings_save_test_note()
{
    if (!is_user_logged_in() || !function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) wp_send_json_error(['message'=>'Permission denied.'],403);
    $cohort=absint($_POST['cohort_id']??0); $lesson=absint($_POST['lesson_id']??0); $student=absint($_POST['student_id']??0);
    $question=sanitize_key(wp_unslash($_POST['question_id']??'')); $note=sanitize_textarea_field(wp_unslash($_POST['note']??''));
    check_ajax_referer('tfp_meeting_test_note_'.$cohort.'_'.$lesson,'nonce');
    $allowed=array_map('absint',wp_list_pluck(tfp_facilitator_meetings_cohorts_safe(),'ID'));
    $members=function_exists('tfp_facilitator_cohort_students')?(array)tfp_facilitator_cohort_students($cohort):[];
    $course_id = $cohort ? (int) get_post_meta($cohort, '_related_course', true) : 0;
    $cohort_weeks = $course_id && function_exists('tfp_ld_get_weeks') ? array_map('absint', wp_list_pluck((array) tfp_ld_get_weeks($course_id), 'ID')) : [];
    if(!$cohort||!in_array($cohort,$allowed,true)||!$lesson||get_post_type($lesson)!=='sfwd-lessons'||!in_array($lesson,$cohort_weeks,true)||!$student||!in_array($student,array_map('absint',$members),true)) wp_send_json_error(['message'=>'Invalid cohort, lesson or student.'],403);
    $valid=false; foreach((array)tfp_week_get_test_questions($lesson,false) as $q) if(sanitize_key((string)($q['id']??''))===$question) $valid=true;
    if(!$valid) wp_send_json_error(['message'=>'Test question not found.'],404);
    $key='tfp_meeting_test_notes_'.$cohort.'_'.$lesson; $notes=get_post_meta($lesson,$key,true); if(!is_array($notes))$notes=[];
    $notes[$student][$question]=['text'=>$note,'author_id'=>get_current_user_id(),'updated_at'=>current_time('mysql')];
    update_post_meta($lesson,$key,$notes); wp_send_json_success(['message'=>'Note saved.']);
}
add_action('wp_ajax_tfp_facilitator_meeting_save_test_note','tfp_facilitator_meetings_save_test_note');



/**
 * Read-only closeout report for a posted cohort session.
 * All scorecard values are calculated from the existing cohort/week records.
 */
function tfp_facilitator_meetings_render_closed_session($cohort, $week_number)
{
    $cohort_id = absint($cohort->ID);
    $course_id = function_exists('tfp_facilitator_cohort_course_id')
        ? absint(tfp_facilitator_cohort_course_id($cohort_id))
        : absint(get_post_meta($cohort_id, '_related_course', true));
    $weeks = $course_id && function_exists('tfp_ld_get_weeks') ? array_values((array) tfp_ld_get_weeks($course_id)) : [];
    $week_number = max(1, absint($week_number));
    $lesson = $weeks[$week_number - 1] ?? null;

    if (!$lesson instanceof WP_Post) {
        wp_die(esc_html__('The selected course week could not be found.', 'tfp-dashboard'), '', ['response' => 404]);
    }

    $allowed_cohorts = array_map('absint', wp_list_pluck(tfp_facilitator_meetings_cohorts_safe(), 'ID'));
    if (!in_array($cohort_id, $allowed_cohorts, true)) {
        wp_die(esc_html__('You do not have access to this cohort.', 'tfp-dashboard'), '', ['response' => 403]);
    }

    $posted = get_post_meta($lesson->ID, 'tfp_meeting_session_posted_' . $cohort_id, true);
    $is_posted = is_array($posted) && !empty($posted['posted']);
    $student_ids = function_exists('tfp_facilitator_cohort_students')
        ? array_values(array_unique(array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))))
        : [];
    $attendance_percent = function_exists('tfp_facilitator_meetings_attendance')
        ? tfp_facilitator_meetings_attendance($student_ids, $lesson->ID)
        : null;

    $logs_key = 'tfp_meeting_exercise_logs_' . $cohort_id;
    $logs = get_post_meta($lesson->ID, $logs_key, true);
    $logs = is_array($logs) ? $logs : [];
    $reflection_key = 'tfp_meeting_internal_reflections_' . $cohort_id;
    $reflections = get_post_meta($lesson->ID, $reflection_key, true);
    $reflections = is_array($reflections) ? $reflections : [];

    $audit_rows = [];
    $progress_values = [];
    $grade_values = [];
    $notes_count = 0;
    foreach ($student_ids as $student_id) {
        $user = get_userdata($student_id);
        if (!$user) {
            continue;
        }

        $record = isset($logs[$student_id]) && is_array($logs[$student_id]) ? $logs[$student_id] : [];
        $note = trim((string) ($record['text'] ?? ''));
        if ($note !== '') {
            $notes_count++;
            $audit_rows[] = [
                'name' => $user->display_name ?: $user->user_email,
                'type' => __('Exercise Log', 'tfp-dashboard'),
                'note' => $note,
            ];
        }

        $progress = function_exists('tfp_ld_get_week_progress') ? (array) tfp_ld_get_week_progress($student_id, $lesson->ID) : [];
        $student_progress = [];
        if (array_key_exists('reading', $progress)) {
            $student_progress[] = !empty($progress['reading']) ? 100 : 0;
        }

        if (function_exists('tfp_week_homework_progress')) {
            $homework = (array) tfp_week_homework_progress($student_id, $lesson->ID);
            if (!empty($homework['total'])) {
                $student_progress[] = min(100, max(0, ((float) $homework['completed'] / (float) $homework['total']) * 100));
                $grade_values[] = min(100, max(0, ((float) $homework['completed'] / (float) $homework['total']) * 100));
            }
        }

        foreach (['quiz' => 'tfp_week_get_quiz_result', 'test' => 'tfp_week_get_test_result'] as $kind => $callback) {
            if (!function_exists($callback)) {
                continue;
            }
            $result = call_user_func($callback, $student_id, $lesson->ID);
            if (is_array($result) && isset($result['score'], $result['total']) && is_numeric($result['score']) && is_numeric($result['total']) && (float) $result['total'] > 0) {
                $percent = min(100, max(0, ((float) $result['score'] / (float) $result['total']) * 100));
                $student_progress[] = 100;
                $grade_values[] = $percent;
            }
        }

        if ($student_progress) {
            $progress_values[] = array_sum($student_progress) / count($student_progress);
        }
    }

    usort($audit_rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    $weekly_progress = $progress_values ? (int) round(array_sum($progress_values) / count($progress_values)) : null;
    $overall_grade = $grade_values ? (int) round(array_sum($grade_values) / count($grade_values)) : null;
    $course_title = $course_id ? get_the_title($course_id) : __('Course', 'tfp-dashboard');
    $base_url = tfp_facilitator_meetings_url();
    $notes_url = tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'notes');
    $back_url = remove_query_arg(['tfp_meeting_cohort_id', 'tfp_meeting_week', 'tfp_meeting_student_page'], $base_url);
    ?>
    <div class="tfp-meeting-session-detail-page tfp-meeting-closed-page">
        <nav class="tfp-meeting-closed-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span>/</span><a href="<?php echo esc_url($base_url); ?>"><?php esc_html_e('Meetings', 'tfp-dashboard'); ?></a>
            <span>/</span><a href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => $cohort_id, 'tfp_meeting_week' => $week_number], $base_url)); ?>"><?php echo esc_html($cohort->post_title); ?></a>
            <span>/</span><strong><?php echo esc_html(sprintf(__('Week %d Session', 'tfp-dashboard'), $week_number)); ?></strong>
        </nav>
        <header class="tfp-meeting-homework-header">
            <div><h1><?php echo esc_html($cohort->post_title); ?></h1><p><?php echo esc_html($course_title); ?></p></div>
            <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($back_url); ?>">↩ <?php esc_html_e('Back to All Meetings', 'tfp-dashboard'); ?></a>
        </header>
        <nav class="tfp-meeting-homework-tabs" aria-label="<?php esc_attr_e('Course session sections', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'attendance')); ?>"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'homework')); ?>"><?php esc_html_e('Homework', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'tests')); ?>"><?php esc_html_e('Tests', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'exercise')); ?>"><?php esc_html_e('Exercises', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url($notes_url); ?>"><?php esc_html_e('Notes', 'tfp-dashboard'); ?></a>
            <a href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'closed-session')); ?>"><?php esc_html_e('Closed Session', 'tfp-dashboard'); ?></a>
            <a class="is-active" aria-current="page" href="<?php echo esc_url(tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'closed-session')); ?>"><?php esc_html_e('Closed Session', 'tfp-dashboard'); ?></a>
        </nav>

        <?php if (!$is_posted): ?>
            <div class="tfp-meeting-closed-notice" role="status"><?php esc_html_e('This session has not been posted yet. Complete the mandatory exercise logs and use Finish & Post Session on the Notes page to close it.', 'tfp-dashboard'); ?> <a href="<?php echo esc_url($notes_url); ?>"><?php esc_html_e('Open Notes', 'tfp-dashboard'); ?></a></div>
        <?php else: ?>
            <main class="tfp-meeting-closed-card">
                <h2><?php esc_html_e('Session Performance Scorecard', 'tfp-dashboard'); ?></h2>
                <div class="tfp-meeting-closed-metrics">
                    <article><small><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></small><strong><?php echo $attendance_percent !== null ? esc_html($attendance_percent . '%') : '—'; ?></strong><span><?php echo esc_html(sprintf(_n('%d Student Present', '%d Students Present', count($student_ids), 'tfp-dashboard'), count($student_ids))); ?></span></article>
                    <article><small><?php esc_html_e('Weekly Progress', 'tfp-dashboard'); ?></small><strong><?php echo $weekly_progress !== null ? esc_html($weekly_progress . '%') : '—'; ?></strong><span><?php esc_html_e('Average recorded weekly activity', 'tfp-dashboard'); ?></span></article>
                    <article><small><?php esc_html_e('Overall Grade', 'tfp-dashboard'); ?></small><strong><?php echo $overall_grade !== null ? esc_html($overall_grade . '%') : '—'; ?></strong><span><?php esc_html_e('Average available assessment score', 'tfp-dashboard'); ?></span></article>
                    <article><small><?php esc_html_e('Dashboard Notes', 'tfp-dashboard'); ?></small><strong><?php echo esc_html($notes_count); ?></strong><span><?php esc_html_e('Observations Posted', 'tfp-dashboard'); ?></span></article>
                </div>

                <section class="tfp-meeting-closed-recap">
                    <h3><?php esc_html_e('Internal Session Recap', 'tfp-dashboard'); ?></h3>
                    <div class="tfp-meeting-closed-recap-grid">
                        <article><h4><?php esc_html_e('Group Engagement', 'tfp-dashboard'); ?></h4><p><?php echo $reflections['atmosphere'] !== '' ? esc_html((string) ($reflections['atmosphere'] ?? '')) : esc_html__('No internal reflection was recorded.', 'tfp-dashboard'); ?></p></article>
                        <article><h4><?php esc_html_e('Prayer Hurdle', 'tfp-dashboard'); ?></h4><p><?php echo $reflections['prayer_hurdle'] !== '' ? esc_html((string) ($reflections['prayer_hurdle'] ?? '')) : esc_html__('No prayer hurdle reflection was recorded.', 'tfp-dashboard'); ?></p></article>
                    </div>
                </section>

                <section class="tfp-meeting-closed-audit">
                    <h3><?php esc_html_e('Final Observation Audit', 'tfp-dashboard'); ?></h3>
                    <div class="tfp-meeting-closed-table-wrap">
                        <table class="tfp-meeting-closed-table" data-closed-session-report>
                            <thead><tr><th><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Type of note recorded', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Logged Observation', 'tfp-dashboard'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($audit_rows as $row): ?>
                                <tr><td><strong><?php echo esc_html($row['name']); ?></strong></td><td><?php echo esc_html($row['type']); ?></td><td><?php echo esc_html($row['note']); ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (!$audit_rows): ?><tr><td colspan="3"><?php esc_html_e('No observations are available for this session.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tfp-meeting-closed-view-notes"><a href="<?php echo esc_url($notes_url); ?>"><?php esc_html_e('View All Notes', 'tfp-dashboard'); ?></a></div>
                </section>
            </main>
            <footer class="tfp-meeting-closed-footer"><button type="button" class="tfp-dash-btn tfp-dash-btn--primary" data-closed-session-export>↓ <?php esc_html_e('Export Full Session Report', 'tfp-dashboard'); ?></button></footer>
        <?php endif; ?>
    </div>
    <?php
}


function tfp_facilitator_meetings_render_cohort_dashboard($cohort)
{
    $session_view = sanitize_key(wp_unslash($_GET['tfp_meeting_session_view'] ?? ''));
    if ($session_view === 'attendance') {
        tfp_facilitator_meetings_render_session_attendance($cohort, absint($_GET['tfp_meeting_week'] ?? 1));
        return;
    }
    if ($session_view === 'tests') { tfp_facilitator_meetings_render_session_tests($cohort, absint($_GET['tfp_meeting_week'] ?? 1)); return; }
    if ($session_view === 'homework') {
        tfp_facilitator_meetings_render_session_homework($cohort, absint($_GET['tfp_meeting_week'] ?? 1));
        return;
    }
    if ($session_view === 'exercise') {
        tfp_facilitator_meetings_render_session_exercise($cohort, absint($_GET['tfp_meeting_week'] ?? 1));
        return;
    }
    if ($session_view === 'notes') {
        tfp_facilitator_meetings_render_session_notes($cohort, absint($_GET['tfp_meeting_week'] ?? 1));
        return;
    }

    if ($session_view === 'closed-session') {
        tfp_facilitator_meetings_render_closed_session($cohort, absint($_GET['tfp_meeting_week'] ?? 1));
        return;
    }
    $cohort_id = absint($cohort->ID);
    $course_id = function_exists('tfp_facilitator_cohort_course_id') ? (int) tfp_facilitator_cohort_course_id($cohort_id) : 0;
    $weeks = $course_id && function_exists('tfp_ld_get_weeks') ? array_values((array) tfp_ld_get_weeks($course_id)) : [];
    $students = function_exists('tfp_facilitator_cohort_students') ? array_values(array_map('absint', (array) tfp_facilitator_cohort_students($cohort_id))) : [];
    $current_week = $course_id && function_exists('tfp_facilitator_cohort_week') ? (int) tfp_facilitator_cohort_week($cohort_id, $course_id) : 1;
    $week_number = isset($_GET['tfp_meeting_week']) ? absint($_GET['tfp_meeting_week']) : $current_week;
    $week_number = $weeks ? min(max(1, $week_number), count($weeks)) : 0;
    $lesson = $week_number ? ($weeks[$week_number - 1] ?? null) : null;
    $course_title = $course_id ? get_the_title($course_id) : __('Course', 'tfp-dashboard');
    $schedule = (string) get_post_meta($cohort_id, '_schedule_text', true);
    $start_date = (string) get_post_meta($cohort_id, '_start_date', true);
    $meeting_rows = function_exists('tfp_facilitator_meetings_rows') ? (array) tfp_facilitator_meetings_rows([$cohort]) : [];
    $meeting_row = null;
    foreach ($meeting_rows as $row) {
        if ((int) $row['week_number'] === $week_number) {
            $meeting_row = $row;
            break;
        }
    }
    $meeting_url = $meeting_row['meeting_url'] ?? ($lesson ? (string) get_post_meta($lesson->ID, 'tfp_week_meeting_discord_url', true) : '');
    $description = $meeting_row['description'] ?? ($lesson ? wp_strip_all_tags((string) get_post_meta($lesson->ID, 'tfp_week_meeting_description', true)) : '');
    $facilitator_name = $meeting_row['facilitator'] ?? ($lesson ? wp_strip_all_tags((string) get_post_meta($lesson->ID, 'tfp_week_facilitator_name', true)) : '');
    $meeting_notes = $meeting_row['notes'] ?? ($lesson ? wp_strip_all_tags((string) get_post_meta($lesson->ID, 'tfp_week_meeting_facilitator_notes', true)) : '');
    $meeting_date = $meeting_row ? trim($meeting_row['date_label'] . ' • ' . $meeting_row['time_label']) : __('Schedule not set', 'tfp-dashboard');
    $base_url = tfp_facilitator_meetings_url();
    $back_url = remove_query_arg(['tfp_meeting_cohort_id', 'tfp_meeting_week', 'tfp_meeting_student_page'], $base_url);


    // Concept mastery is calculated from actual LearnDash completion and saved grade records.
    $mastery = [];
    if (function_exists('tfp_facilitator_cohorts_section_groups') && function_exists('tfp_facilitator_cohorts_section_progress')) {
        foreach (tfp_facilitator_cohorts_section_groups($weeks) as $group) {
            $progress = tfp_facilitator_cohorts_section_progress($students, $group);
            $mastery[] = [
                'title' => (string) $group['name'],
                'percent' => (int) $progress['percent'],
                'grade' => (string) ($progress['avg_grade'] ?? ''),
            ];
        }
    }
    $strongest = $mastery;
    $weakest = $mastery;
    usort($strongest, static fn($a, $b) => $b['percent'] <=> $a['percent']);
    usort($weakest, static fn($a, $b) => $a['percent'] <=> $b['percent']);
    $strongest = array_slice($strongest, 0, 2);
    $weakest = array_slice($weakest, 0, 2);

    // Convert stored assessment results into the letter-grade chips used by the design.
    $result_percent = static function ($result) {
        if (!is_array($result) || !isset($result['score'], $result['total']) || !is_numeric($result['score']) || !is_numeric($result['total']) || (float) $result['total'] <= 0) {
            return null;
        }
        return (float) $result['score'] / (float) $result['total'] * 100;
    };
    $performance = [];
    foreach ($students as $student_id) {
        $student = get_userdata($student_id);
        if (!$student) {
            continue;
        }
        $progress = $lesson && function_exists('tfp_ld_get_week_progress') ? (array) tfp_ld_get_week_progress($student_id, $lesson->ID) : [];
        $homework_progress = $lesson && function_exists('tfp_week_homework_progress') ? (array) tfp_week_homework_progress($student_id, $lesson->ID) : [];
        $homework_percent = !empty($homework_progress['total']) ? ((int) $homework_progress['completed'] / (int) $homework_progress['total']) * 100 : null;
        $quiz_percent = $lesson && function_exists('tfp_week_get_quiz_result') ? $result_percent(tfp_week_get_quiz_result($student_id, $lesson->ID)) : null;
        $test_percent = $lesson && function_exists('tfp_week_get_test_result') ? $result_percent(tfp_week_get_test_result($student_id, $lesson->ID)) : null;
        $homework_status = $lesson && function_exists('tfp_facilitator_roster_work_status')
            ? tfp_facilitator_roster_work_status($student_id, $lesson->ID, 'homework')
            : ['label' => __('Not Started', 'tfp-dashboard'), 'tone' => 'neutral'];
        $quiz_status = $lesson && function_exists('tfp_facilitator_roster_work_status')
            ? tfp_facilitator_roster_work_status($student_id, $lesson->ID, 'quiz')
            : ['label' => __('Not Started', 'tfp-dashboard'), 'tone' => 'neutral'];
        $test_status = $lesson && function_exists('tfp_facilitator_roster_work_status')
            ? tfp_facilitator_roster_work_status($student_id, $lesson->ID, 'test')
            : ['label' => __('Not Started', 'tfp-dashboard'), 'tone' => 'neutral'];
        $homework_label = in_array($homework_status['tone'], ['warning', 'danger'], true)
            ? __('Needs Review', 'tfp-dashboard')
            : ($homework_percent !== null ? tfp_facilitator_meetings_letter($homework_percent) : '—');
        $quiz_label = in_array($quiz_status['tone'], ['warning', 'danger'], true)
            ? __('Needs Review', 'tfp-dashboard')
            : ($quiz_percent !== null ? tfp_facilitator_meetings_letter($quiz_percent) : '—');
        $test_label = in_array($test_status['tone'], ['warning', 'danger'], true)
            ? __('Needs Review', 'tfp-dashboard')
            : ($test_percent !== null ? tfp_facilitator_meetings_letter($test_percent) : '—');
        $performance[] = [
            'id' => $student_id,
            'name' => $student->display_name ?: $student->user_email,
            'reading' => !empty($progress['reading']) ? __('Completed', 'tfp-dashboard') : __('Not Started', 'tfp-dashboard'),
            'reading_tone' => !empty($progress['reading']) ? 'success' : 'neutral',
            'homework' => $homework_label,
            'homework_tone' => $homework_status['tone'] ?? 'neutral',
            'quiz' => $quiz_label,
            'quiz_tone' => $quiz_status['tone'] ?? 'neutral',
            'test' => $test_label,
            'test_tone' => $test_status['tone'] ?? 'neutral',
            'grade' => $lesson && function_exists('tfp_grade_get_week') ? (string) tfp_grade_get_week($student_id, $lesson->ID) : '',
        ];
    }
    usort($performance, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

    // Queue rows are generated from the same live student statuses shown in the performance grid.
    $queue_display_items = [];
    foreach ($performance as $student_row) {
        if (in_array($student_row['test_tone'], ['warning', 'danger'], true)) {
            $queue_display_items[] = [
                'title' => __('Test Review Required', 'tfp-dashboard'),
                'student' => $student_row['name'],
                'button' => __('Review Test', 'tfp-dashboard'),
                'icon' => 'action-warning',
                'button_icon' => 'unlock',
                'url' => tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'tests'),
            ];
        }
        if (in_array($student_row['homework_tone'], ['warning', 'danger'], true)) {
            $queue_display_items[] = [
                'title' => __('Grade Homework', 'tfp-dashboard'),
                'student' => $student_row['name'],
                'button' => __('Grade Now', 'tfp-dashboard'),
                'icon' => 'action-warning',
                'button_icon' => 'grades',
                'url' => add_query_arg(['tfp_homework_course' => $course_id, 'tfp_homework_cohort_filter' => $cohort_id], tfp_dashboard_get_url('tfp-dashboard-facilitator-homework')),
            ];
        }
    }
    $queue_display_items[] = [
        'title' => __('Publish Weekly Meeting Notes', 'tfp-dashboard'),
        'student' => sprintf(__('Week %d: Add facilitator notes for this session', 'tfp-dashboard'), $week_number),
        'button' => __('Add Note', 'tfp-dashboard'),
        'icon' => 'action-publish',
        'button_icon' => 'doc',
        'url' => tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'notes'),
    ];
    $student_page = max(1, absint($_GET['tfp_meeting_student_page'] ?? 1));
    $per_page = 7;
    $total_students = count($performance);
    $total_pages = max(1, (int) ceil($total_students / $per_page));
    $student_page = min($student_page, $total_pages);
    $visible_students = array_slice($performance, ($student_page - 1) * $per_page, $per_page);
    $from = $total_students ? (($student_page - 1) * $per_page) + 1 : 0;
    $to = min($student_page * $per_page, $total_students);

    $crumb_meetings = add_query_arg(['tfp_meeting_tab' => 'upcoming'], $base_url);
    ?>
    <header class="tfp-dash-pageheader tfp-meeting-cohort-header">
        <button type="button" class="tfp-dash-pageheader__mobile-toggle" data-tfp-sidebar-toggle aria-label="<?php esc_attr_e('Open facilitator menu', 'tfp-dashboard'); ?>"><?php echo tfp_dashboard_icon('toggle'); ?></button>
        <nav class="tfp-meeting-cohort-header__crumb" aria-label="<?php esc_attr_e('Breadcrumb', 'tfp-dashboard'); ?>">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home')); ?>"><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo esc_url($crumb_meetings); ?>"><?php esc_html_e('Meetings', 'tfp-dashboard'); ?></a>
            <span aria-hidden="true">/</span>
            <strong><?php echo esc_html($cohort->post_title); ?></strong>
        </nav>
        <div class="tfp-meeting-cohort-header__main">
            <div>
                <h1><?php echo esc_html($cohort->post_title); ?></h1>
                <p><?php echo esc_html(trim($course_title . ($schedule ? ' • ' . $schedule : ''))); ?></p>
            </div>
            <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($back_url); ?>"><span aria-hidden="true">↩</span><?php esc_html_e('Back to All Meetings', 'tfp-dashboard'); ?></a>
        </div>
    </header>

    <div class="tfp-meeting-cohort-page" data-tfp-meeting-cohort>
        <div class="tfp-meeting-cohort__toolbar">
            <?php
            $toolbar_lesson_title = $lesson ? trim((string) $lesson->post_title) : '';
            // Course lesson titles sometimes already include their week number.
            // Avoid rendering "Week 09: Week 09: ..." in the toolbar.
            if ($toolbar_lesson_title !== '') {
                $toolbar_lesson_title = preg_replace('/^Week\s*0*' . preg_quote((string) $week_number, '/') . '\s*[:\-–—]\s*/i', '', $toolbar_lesson_title);
            }
            ?>
            <h2><?php echo $lesson ? esc_html(sprintf(__('Week %1$02d: %2$s', 'tfp-dashboard'), $week_number, $toolbar_lesson_title)) : esc_html__('Weekly Session', 'tfp-dashboard'); ?></h2>
            <div class="tfp-meeting-cohort__controls">
                <form method="get" class="tfp-meeting-cohort__week-form">
                    <input type="hidden" name="tfp_meeting_cohort_id" value="<?php echo esc_attr($cohort_id); ?>">
                    <label class="screen-reader-text" for="tfp-meeting-week"><?php esc_html_e('Select week', 'tfp-dashboard'); ?></label>
                    <select id="tfp-meeting-week" name="tfp_meeting_week" data-tfp-meeting-week>
                        <?php foreach ($weeks as $index => $week): ?>
                            <option value="<?php echo esc_attr($index + 1); ?>" <?php selected($week_number, $index + 1); ?>><?php echo esc_html(sprintf(__('Week %1$d: %2$s', 'tfp-dashboard'), $index + 1, $week->post_title)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a class="tfp-dash-btn tfp-dash-btn--primary tfp-meeting-attendance-open" href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => $cohort_id, 'tfp_meeting_week' => $week_number, 'tfp_meeting_session_view' => 'attendance'], $base_url)); ?>"><?php esc_html_e('Attendance', 'tfp-dashboard'); ?></a>
                <button type="button" class="tfp-dash-btn tfp-dash-btn--primary" data-tfp-export-meeting-report><?php echo tfp_dashboard_icon('doc'); ?><span><?php esc_html_e('Export Report', 'tfp-dashboard'); ?></span></button>
            </div>
        </div>

        <div class="tfp-meeting-cohort__top-grid">
            <section class="tfp-dash-panel tfp-meeting-session-card">
                <div class="tfp-meeting-cohort__panel-title"><h3><?php esc_html_e('Meeting Session Flow', 'tfp-dashboard'); ?></h3></div>
                <?php if ($lesson): ?>
                    <div class="tfp-meeting-session-card__body">
                        <div class="tfp-meeting-session-card__date"><?php echo tfp_dashboard_icon('calendar'); ?><span><?php echo esc_html($meeting_date); ?></span></div>
                        <h4><?php echo esc_html($lesson->post_title); ?></h4>
                        <?php if ($description): ?><p><?php echo esc_html($description); ?></p><?php endif; ?>
                        <?php if ($facilitator_name): ?><small><?php echo esc_html($facilitator_name); ?></small><?php endif; ?>
                        <?php if ($meeting_url): ?>
                            <a class="tfp-dash-btn tfp-dash-btn--primary tfp-meeting-session-card__join" href="<?php echo esc_url($meeting_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo tfp_dashboard_icon('send'); ?><span><?php esc_html_e('Start Weekly Live Session Now', 'tfp-dashboard'); ?></span></a>
                        <?php else: ?>
                            <p class="tfp-meeting-session-card__missing"><?php esc_html_e('A live meeting link has not been added for this week yet.', 'tfp-dashboard'); ?></p>
                        <?php endif; ?>
                        <?php if ($meeting_notes): ?><details class="tfp-meeting-session-card__notes"><summary><?php esc_html_e('Facilitator meeting notes', 'tfp-dashboard'); ?></summary><p><?php echo esc_html($meeting_notes); ?></p></details><?php endif; ?>
                        <a class="tfp-dash-btn-second tfp-meeting-session-card__attendance-link" href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => $cohort_id, 'tfp_meeting_week' => $week_number, 'tfp_meeting_session_view' => 'attendance'], $base_url)); ?>"><?php esc_html_e('Manage Attendance', 'tfp-dashboard'); ?> <span aria-hidden="true">→</span></a>
                    </div>
                <?php else: ?>
                    <div class="tfp-facilitator-empty"><?php esc_html_e('No LearnDash weeks are linked to this cohort course.', 'tfp-dashboard'); ?></div>
                <?php endif; ?>
            </section>

            <section class="tfp-dash-panel tfp-meeting-queue-card">
                <div class="tfp-meeting-cohort__panel-title">
                    <div><h3><?php esc_html_e('Action Fulfillment Queue', 'tfp-dashboard'); ?></h3><p><?php esc_html_e('Tasks that need your Attention, Actions and Submission.', 'tfp-dashboard'); ?></p></div>
                    <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-facilitator-home') . '#tfp-facilitator-review-list'); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                </div>
                <div class="tfp-meeting-queue-card__list">
                    <?php foreach ($queue_display_items as $item): ?>
                        <article class="tfp-meeting-queue-item">
                            <span class="tfp-meeting-queue-item__icon"><?php echo tfp_dashboard_icon($item['icon']); ?></span>
                            <div class="tfp-meeting-queue-item__copy"><strong><?php echo esc_html($item['title']); ?></strong><small><?php echo esc_html($item['student']); ?></small></div>
                            <?php if ($demo_mode): ?>
                                <span class="tfp-dash-btn tfp-dash-btn--primary is-demo-disabled" aria-disabled="true"><?php echo tfp_dashboard_icon($item['button_icon']); ?><span><?php echo esc_html($item['button']); ?></span></span>
                            <?php else: ?>
                                <a class="tfp-dash-btn tfp-dash-btn--primary" href="<?php echo esc_url($item['url']); ?>"><?php echo tfp_dashboard_icon($item['button_icon']); ?><span><?php echo esc_html($item['button']); ?></span></a>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$queue_display_items): ?><div class="tfp-facilitator-empty"><?php esc_html_e('No action items require attention right now.', 'tfp-dashboard'); ?></div><?php endif; ?>
                </div>
            </section>

            <section class="tfp-dash-panel tfp-meeting-mastery-card">
                <div class="tfp-meeting-cohort__panel-title"><h3><?php esc_html_e('Concept Mastery', 'tfp-dashboard'); ?></h3></div>
                <?php foreach ([['title' => __('Strongest Understanding', 'tfp-dashboard'), 'items' => $strongest, 'tone' => 'strong'], ['title' => __('Most Missed Concepts', 'tfp-dashboard'), 'items' => $weakest, 'tone' => 'weak']] as $group): ?>
                    <div class="tfp-meeting-mastery-group tfp-meeting-mastery-group--<?php echo esc_attr($group['tone']); ?>">
                        <h4><?php echo esc_html($group['title']); ?></h4>
                        <?php if ($group['items']): foreach ($group['items'] as $item): ?>
                            <div class="tfp-meeting-mastery-row">
                                <div><span><?php echo esc_html($item['title']); ?></span><strong><?php echo esc_html($item['percent']); ?>%</strong></div>
                                <div class="tfp-meeting-mastery-bar"><span style="width:<?php echo esc_attr($item['percent']); ?>%"></span></div>
                            </div>
                        <?php endforeach; else: ?>
                            <p class="tfp-meeting-mastery-empty"><?php esc_html_e('No saved weekly progress is available yet.', 'tfp-dashboard'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        </div>

        <section class="tfp-dash-panel tfp-meeting-performance-card">
            <div class="tfp-meeting-cohort__panel-title"><h3><?php esc_html_e('Weekly Performance Grid', 'tfp-dashboard'); ?></h3></div>
            <div class="tfp-dash-table-wrap tfp-meeting-performance-wrap">
                <table class="tfp-dash-table tfp-meeting-performance-table" data-tfp-meeting-report-table>
                    <thead><tr>
                        <th><?php esc_html_e('Disciples', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Reading', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Homework', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Quiz', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Test', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Weekly Grade', 'tfp-dashboard'); ?></th>
                        <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($visible_students as $row):
                        $profile_url = add_query_arg('tfp_student_id', $row['id'], tfp_dashboard_get_url('tfp-dashboard-facilitator-student'));
                        $action_url = $profile_url;
                        $action_label = __('View Profile', 'tfp-dashboard');
                        if (in_array($row['homework_tone'], ['warning', 'danger'], true)) {
                            $action_url = add_query_arg(['tfp_homework_course' => $course_id, 'tfp_homework_cohort_filter' => $cohort_id], tfp_dashboard_get_url('tfp-dashboard-facilitator-homework'));
                            $action_label = __('Review Homework', 'tfp-dashboard');
                        } elseif (in_array($row['test_tone'], ['warning', 'danger'], true)) {
                            $action_url = tfp_facilitator_meetings_session_url($cohort_id, $week_number, 'tests');
                            $action_label = __('Review Test', 'tfp-dashboard');
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                            <td><span class="tfp-meeting-work-status tfp-meeting-work-status--<?php echo esc_attr($row['reading_tone']); ?>"><?php echo esc_html($row['reading']); ?></span></td>
                            <?php foreach (['homework', 'quiz', 'test'] as $key): ?><td><span class="tfp-meeting-grade tfp-meeting-grade--<?php echo esc_attr($row[$key . '_tone']); ?>"><?php echo esc_html($row[$key]); ?></span></td><?php endforeach; ?>
                            <td><span class="tfp-meeting-grade"><?php echo esc_html($row['grade'] ?: __('Upcoming', 'tfp-dashboard')); ?></span></td>
                            <td><a class="tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url($action_url); ?>"><?php echo esc_html($action_label); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$visible_students): ?><tr><td colspan="7"><div class="tfp-facilitator-empty"><?php esc_html_e('No disciples are assigned to this cohort.', 'tfp-dashboard'); ?></div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <footer class="tfp-meeting-performance-pagination">
                <span><?php echo esc_html($from . ' - ' . $to . ' of ' . $total_students . ' items'); ?></span>
                <div>
                    <?php if ($student_page > 1): ?><a href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => $cohort_id, 'tfp_meeting_week' => $week_number, 'tfp_meeting_student_page' => $student_page - 1], $base_url)); ?>"><?php esc_html_e('‹ Previous', 'tfp-dashboard'); ?></a><?php endif; ?>
                    <?php if ($student_page < $total_pages): ?><a class="tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => $cohort_id, 'tfp_meeting_week' => $week_number, 'tfp_meeting_student_page' => $student_page + 1], $base_url)); ?>"><?php esc_html_e('Next ›', 'tfp-dashboard'); ?></a><?php endif; ?>
                </div>
            </footer>
        </section>
    </div>
    <?php
}

function tfp_facilitator_meetings_render()
{
    if (!function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
        wp_safe_redirect(tfp_dashboard_get_url('tfp-dashboard-facilitator-home'));
        exit;
    }

    $cohorts = tfp_facilitator_meetings_cohorts();
    $selected_cohort_id = absint($_GET['tfp_meeting_cohort_id'] ?? 0);
    if ($selected_cohort_id) {
        $selected_cohort = null;
        foreach ($cohorts as $assigned_cohort) {
            if (absint($assigned_cohort->ID) === $selected_cohort_id) {
                $selected_cohort = $assigned_cohort;
                break;
            }
        }
        if (!$selected_cohort) {
            wp_die(esc_html__('You do not have access to this cohort.', 'tfp-dashboard'), '', ['response' => 403]);
        }
        $selected_course_id = function_exists('tfp_facilitator_cohort_course_id') ? (int) tfp_facilitator_cohort_course_id($selected_cohort_id) : 0;
        $selected_weeks = $selected_course_id && function_exists('tfp_ld_get_weeks') ? array_values((array) tfp_ld_get_weeks($selected_course_id)) : [];
        $selected_current_week = $selected_course_id && function_exists('tfp_facilitator_cohort_week') ? (int) tfp_facilitator_cohort_week($selected_cohort_id, $selected_course_id) : 1;
        $selected_week_number = isset($_GET['tfp_meeting_week']) ? absint($_GET['tfp_meeting_week']) : $selected_current_week;
        $selected_week_number = $selected_weeks ? min(max(1, $selected_week_number), count($selected_weeks)) : 0;
        if (sanitize_key($_GET['tfp_meeting_view'] ?? '') === 'attendance' && $selected_week_number > 0) {
            $selected_students = function_exists('tfp_facilitator_cohort_students') ? array_values(array_map('absint', (array) tfp_facilitator_cohort_students($selected_cohort_id))) : [];
            tfp_facilitator_meetings_render_session_attendance($selected_cohort, $selected_week_number);
        } else {
            tfp_facilitator_meetings_render_cohort_dashboard($selected_cohort);
        }
        return;
    }
    $rows = tfp_facilitator_meetings_rows($cohorts);
    $now = current_time('timestamp', true);

    $upcoming_all = array_values(array_filter($rows, static fn($row) => (int) $row['timestamp'] >= $now));
    $past_all = array_values(array_filter($rows, static fn($row) => (int) $row['timestamp'] < $now));

    $tab = sanitize_key($_GET['tfp_meeting_tab'] ?? 'upcoming');
    if (!in_array($tab, ['upcoming', 'past', 'requests'], true))
        $tab = 'upcoming';

    $search = sanitize_text_field($_GET['tfp_meeting_search'] ?? '');
    $cohort_filter = absint($_GET['tfp_meeting_cohort'] ?? 0);
    $sort = sanitize_key($_GET['tfp_meeting_sort'] ?? 'date');

    $filter_rows = static function ($items) use ($search, $cohort_filter, $sort) {
        $items = array_values(array_filter($items, static function ($row) use ($search, $cohort_filter) {
            if ($cohort_filter && (int) $row['cohort_id'] !== $cohort_filter)
                return false;
            if ($search !== '') {
                $searchable = strtolower(
                    $row['date_label'] . ' '
                    . $row['time_label'] . ' '
                    . $row['cohort_name'] . ' '
                    . $row['module']
                );

                if (strpos($searchable, strtolower($search)) === false)
                    return false;
            }
            return true;
        }));

        usort($items, static function ($a, $b) use ($sort) {
            if ($sort === 'cohort')
                return strcasecmp($a['cohort_name'], $b['cohort_name']);
            if ($sort === 'module')
                return strcasecmp($a['module'], $b['module']);
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
        if (!in_array($request['status'], ['approved', 'denied', 'acknowledged'], true)) {
            $request_pending++;
            if ($request['type'] === 'cohort_skip')
                $request_skip++;
            if ($request['type'] === 'student_absence')
                $request_absence++;
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
            <a class="<?php echo $tab === 'upcoming' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tfp_meeting_tab' => 'upcoming'], $meeting_url)); ?>"><?php esc_html_e('Upcoming Meetings', 'tfp-dashboard'); ?></a>
            <a class="<?php echo $tab === 'past' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tfp_meeting_tab' => 'past'], $meeting_url)); ?>"><?php esc_html_e('Past Meetings', 'tfp-dashboard'); ?></a>
            <a class="<?php echo $tab === 'requests' ? 'is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['tfp_meeting_tab' => 'requests'], $meeting_url)); ?>"><?php esc_html_e('Requests', 'tfp-dashboard'); ?></a>
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
                    <button type="button" class="tfp-meetings-create-btn tfp-dash-btn tfp-dash-btn--primary" data-tfp-open-request-create><span aria-hidden="true">+</span><?php esc_html_e('Create New Skip Request', 'tfp-dashboard'); ?></button>
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
                        <?php if ($request_rows):
                            foreach ($request_rows as $request): ?>
                            <tr>
                                <td><span class="tfp-meetings-request-tag tfp-meetings-request-tag--<?php echo esc_attr($request['type']); ?>"><?php echo esc_html($request['type_label']); ?></span></td>
                                <td><?php echo esc_html($request['requester']); ?></td>
                                <td class="tfp-meetings-date"><?php echo esc_html($request['date']); ?></td>
                                <td><?php echo esc_html($request['reason']); ?></td>
                                <td><span class="tfp-meetings-status tfp-meetings-status--<?php echo esc_attr($request['status']); ?>"><?php echo esc_html($request['status_label']); ?></span></td>
                                <td><button type="button" class="tfp-meetings-action-btn is-outline" data-tfp-request="<?php echo esc_attr(wp_json_encode($request)); ?>"><?php echo in_array($request['status'], ['pending', 'in_progress', 'needs_review'], true) ? esc_html__('Add', 'tfp-dashboard') : esc_html__('View', 'tfp-dashboard'); ?></button></td>
                            </tr>
                        <?php endforeach;
                        else: ?>
                            <tr><td colspan="6"><div class="tfp-facilitator-empty"><?php esc_html_e('No meeting requests found.', 'tfp-dashboard'); ?></div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <footer class="tfp-homework-pagination">
                    <span class="tfp-homework-pagination__details">
                        <?php if ($request_rows): ?>
                            <?php printf(
                                esc_html__('%1$d - %2$d of %3$d items', 'tfp-dashboard'),
                                1,
                                count($request_rows),
                                count($request_rows)
                            ); ?>
                        <?php else: ?>
                            <?php esc_html_e('0 of 0 items', 'tfp-dashboard'); ?>
                        <?php endif; ?>
                    </span>
                    <div class="tfp-homework-pagination__actions"></div>
                </footer>
            </section>
        <?php else: ?>
            <section class="tfp-meetings-panel">
                <div class="tfp-meetings-panel__head">
                    <h5><?php echo $tab === 'past' ? esc_html__('Active Cohorts', 'tfp-dashboard') : esc_html__('All Upcoming Meetings', 'tfp-dashboard'); ?></h5>
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
                            <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                            <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if ($page_rows):
                            foreach ($page_rows as $row): ?>
                            <tr>
                                <td class="tfp-meetings-date-cell"><strong><?php echo esc_html($row['date_label']); ?></strong><small><?php echo esc_html($row['time_label']); ?></small></td>
                                <td><?php echo esc_html($row['cohort_name']); ?></td>
                                <td><?php echo esc_html($row['module']); ?></td>
                                <td><span class="tfp-meetings-grade"><?php echo esc_html($row['homework_label']); ?></span></td>
                                <td><span class="tfp-meetings-grade"><?php echo esc_html($row['test_label']); ?></span></td>
                                <td><span class="tfp-meetings-attendance"><?php echo esc_html($row['attendance_label']); ?></span></td>
                                <td><span class="tfp-meetings-status tfp-meetings-status--<?php echo esc_attr($row['meeting_status']); ?>"><?php
                                echo esc_html(
                                    $row['meeting_status'] === 'needs_log'
                                        ? __('Needs Log', 'tfp-dashboard')
                                        : ($row['meeting_status'] === 'in_progress'
                                            ? __('In Progress', 'tfp-dashboard')
                                            : __('Upcoming', 'tfp-dashboard'))
                                );
                                ?></span></td>
                                <td>
                                    <div class="tfp-meetings-row-actions">
                                        <?php if ($row['meeting_status'] === 'upcoming'): ?>
                                            <button type="button" class="tfp-meetings-action-btn tfp-meetings-action-btn--skip tfp-dash-btn-small tfp-dash--outline" data-tfp-skip="<?php echo esc_attr(wp_json_encode($row)); ?>"><?php esc_html_e('Skip', 'tfp-dashboard'); ?></button>
                                        <?php endif; ?>
                                        <a class="tfp-meetings-action-btn tfp-dash-btn-small tfp-dash-btn--primary" href="<?php echo esc_url(add_query_arg(['tfp_meeting_cohort_id' => $row['cohort_id'], 'tfp_meeting_week' => $row['week_number']], $meeting_url)); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach;
                        else: ?>
                            <tr><td colspan="8"><div class="tfp-facilitator-empty"><?php echo $tab === 'past' ? esc_html__('No past meetings found.', 'tfp-dashboard') : esc_html__('No upcoming meetings found.', 'tfp-dashboard'); ?></div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <footer class="tfp-homework-pagination">
                    <span class="tfp-homework-pagination__details">
                        <?php if ($total): ?>
                            <?php printf(
                                esc_html__('%1$d - %2$d of %3$d items', 'tfp-dashboard'),
                                $start,
                                $end,
                                $total
                            ); ?>
                        <?php else: ?>
                            <?php esc_html_e('0 of 0 items', 'tfp-dashboard'); ?>
                        <?php endif; ?>
                    </span>

                    <?php
                    $base = [
                        'tfp_meeting_tab' => $tab,
                        'tfp_meeting_search' => $search,
                        'tfp_meeting_cohort' => $cohort_filter,
                        'tfp_meeting_sort' => $sort,
                    ];
                    $prev_url = add_query_arg(
                        array_merge($base, ['tfp_meeting_page' => max(1, $page - 1)]),
                        $meeting_url
                    );
                    $next_url = add_query_arg(
                        array_merge($base, ['tfp_meeting_page' => min($pages, $page + 1)]),
                        $meeting_url
                    );
                    ?>

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
                    <label><?php esc_html_e('Affected Date', 'tfp-dashboard'); ?><input type="datetime-local" name="date" data-tfp-request-date required></label>
                    <label><?php esc_html_e('Reason', 'tfp-dashboard'); ?><textarea name="reason" rows="4" required></textarea></label>
                    <button type="submit" class="tfp-meetings-create-btn tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Create Request', 'tfp-dashboard'); ?></button>
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
