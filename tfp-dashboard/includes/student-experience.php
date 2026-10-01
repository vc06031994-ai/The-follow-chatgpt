<?php
/**
 * Student calendar + document functionality for TFP Dashboard.
 */
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------
 * Content types
 * ------------------------------------------------------------------------- */
add_action('init', function () {
    register_post_type('tfp_off_day', [
        'label' => __('Off Days', 'tfp-dashboard'),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);

    register_post_type('tfp_skip_request', [
        'label' => __('Skip Requests', 'tfp-dashboard'),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-no-alt',
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);

    register_post_type('tfp_student_document', [
        'label' => __('Student Documents', 'tfp-dashboard'),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-media-document',
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
});

function tfp_student_document_statuses() {
    return [
        'pending' => __('Pending Signature', 'tfp-dashboard'),
        'signed' => __('Signed', 'tfp-dashboard'),
        'active' => __('Active', 'tfp-dashboard'),
        'expired' => __('Expired', 'tfp-dashboard'),
        'cancelled' => __('Cancelled', 'tfp-dashboard'),
    ];
}

function tfp_dashboard_user_skip_requests($user_id) {
    $posts = get_posts([
        'post_type' => 'tfp_skip_request',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [[
            'key' => '_user_id',
            'value' => (int) $user_id,
            'type' => 'NUMERIC',
        ]],
        'meta_key' => '_date',
        'orderby' => 'meta_value',
        'order' => 'DESC',
    ]);
    $rows = [];
    foreach ($posts as $post) {
        $rows[] = [
            'id' => $post->ID,
            'date' => get_post_meta($post->ID, '_date', true),
            'reason' => get_post_meta($post->ID, '_reason', true),
            'status' => get_post_meta($post->ID, '_status', true) ?: 'pending',
            'cohort_id' => (int) get_post_meta($post->ID, '_cohort_id', true),
        ];
    }
    return $rows;
}

function tfp_skip_request_statuses() {
    return [
        'pending' => __('Pending', 'tfp-dashboard'),
        'approved' => __('Approved', 'tfp-dashboard'),
        'rejected' => __('Rejected', 'tfp-dashboard'),
        'cancelled' => __('Cancelled', 'tfp-dashboard'),
    ];
}

/* -------------------------------------------------------------------------
 * Admin fields
 * ------------------------------------------------------------------------- */
add_action('add_meta_boxes', function () {
    add_meta_box('tfp_off_day_details', __('Off Day Details', 'tfp-dashboard'), 'tfp_off_day_meta_box', 'tfp_off_day', 'normal', 'high');
    add_meta_box('tfp_skip_request_details', __('Skip Request', 'tfp-dashboard'), 'tfp_skip_request_meta_box', 'tfp_skip_request', 'normal', 'high');
    add_meta_box('tfp_student_document_details', __('Student Document', 'tfp-dashboard'), 'tfp_student_document_meta_box', 'tfp_student_document', 'normal', 'high');
});

function tfp_off_day_meta_box($post) {
    wp_nonce_field('tfp_off_day_save', 'tfp_off_day_nonce');
    $date = get_post_meta($post->ID, '_date', true);
    echo '<p><label><strong>' . esc_html__('Date', 'tfp-dashboard') . '</strong><br><input type="date" name="tfp_off_day_date" value="' . esc_attr($date) . '"></label></p>';
    echo '<p>' . esc_html__('The post title is used as the holiday/off-day name.', 'tfp-dashboard') . '</p>';
}

function tfp_skip_request_meta_box($post) {
    wp_nonce_field('tfp_skip_request_save', 'tfp_skip_request_nonce');
    $user_id = (int) get_post_meta($post->ID, '_user_id', true);
    $date = get_post_meta($post->ID, '_date', true);
    $cohort_id = (int) get_post_meta($post->ID, '_cohort_id', true);
    $reason = (string) get_post_meta($post->ID, '_reason', true);
    $notes = (string) get_post_meta($post->ID, '_notes', true);
    $status = (string) get_post_meta($post->ID, '_status', true) ?: 'pending';
    echo '<p><label><strong>User ID</strong><br><input type="number" name="tfp_skip_user_id" value="' . esc_attr($user_id) . '" min="1"></label></p>';
    echo '<p><label><strong>Date</strong><br><input type="date" name="tfp_skip_date" value="' . esc_attr($date) . '"></label></p>';
    echo '<p><label><strong>Cohort ID</strong><br><input type="number" name="tfp_skip_cohort_id" value="' . esc_attr($cohort_id) . '" min="0"></label></p>';
    echo '<p><label><strong>Reason</strong><br><input type="text" class="widefat" name="tfp_skip_reason" value="' . esc_attr($reason) . '"></label></p>';
    echo '<p><label><strong>Notes</strong><br><textarea class="widefat" name="tfp_skip_notes" rows="4">' . esc_textarea($notes) . '</textarea></label></p>';
    echo '<p><label><strong>Status</strong><br><select name="tfp_skip_status">';
    foreach (tfp_skip_request_statuses() as $key => $label) {
        echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label></p>';
}

function tfp_student_document_meta_box($post) {
    wp_nonce_field('tfp_student_document_save', 'tfp_student_document_nonce');

    $user_id = (int) get_post_meta($post->ID, '_user_id', true);
    $type = (string) get_post_meta($post->ID, '_type', true) ?: 'Agreement';
    $date = (string) get_post_meta($post->ID, '_date_issued', true);
    $status = (string) get_post_meta($post->ID, '_status', true) ?: 'pending';
    $url = (string) get_post_meta($post->ID, '_document_url', true);

    $students = get_users([
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => ['ID', 'display_name', 'user_email'],
    ]);

    echo '<p><label><strong>' . esc_html__('Student', 'tfp-dashboard') . '</strong><br>';
    echo '<select name="tfp_doc_user_id" class="widefat">';
    echo '<option value="">' . esc_html__('Select Student', 'tfp-dashboard') . '</option>';

    foreach ($students as $student) {
        $label = $student->display_name;
        if (empty($label)) {
            $label = $student->user_email;
        }

        echo '<option value="' . esc_attr($student->ID) . '" ' . selected($user_id, $student->ID, false) . '>' . esc_html($label) . '</option>';
    }

    echo '</select></label></p>';

    echo '<p><label><strong>Type</strong><br><input type="text" name="tfp_doc_type" value="' . esc_attr($type) . '" class="regular-text"></label></p>';
    echo '<p><label><strong>Date issued</strong><br><input type="date" name="tfp_doc_date" value="' . esc_attr($date) . '"></label></p>';
    echo '<p><label><strong>Status</strong><br><select name="tfp_doc_status">';
    foreach (tfp_student_document_statuses() as $key => $label) {
        echo '<option value="' . esc_attr($key) . '" ' . selected($status, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label></p>';
    echo '<p><label><strong>Document URL</strong><br><input type="url" name="tfp_doc_url" value="' . esc_attr($url) . '" class="widefat" placeholder="https://..."></label></p>';
    echo '<p>' . esc_html__('Assign this document to a student. The URL may point to a PDF or secure document viewer.', 'tfp-dashboard') . '</p>';
}

add_action('save_post_tfp_off_day', function ($post_id) {
    if (!isset($_POST['tfp_off_day_nonce']) || !wp_verify_nonce($_POST['tfp_off_day_nonce'], 'tfp_off_day_save') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) return;
    update_post_meta($post_id, '_date', sanitize_text_field($_POST['tfp_off_day_date'] ?? ''));
});

add_action('save_post_tfp_skip_request', function ($post_id) {
    if (!isset($_POST['tfp_skip_request_nonce']) || !wp_verify_nonce($_POST['tfp_skip_request_nonce'], 'tfp_skip_request_save') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) return;
    update_post_meta($post_id, '_user_id', absint($_POST['tfp_skip_user_id'] ?? 0));
    update_post_meta($post_id, '_date', sanitize_text_field($_POST['tfp_skip_date'] ?? ''));
    update_post_meta($post_id, '_cohort_id', absint($_POST['tfp_skip_cohort_id'] ?? 0));
    update_post_meta($post_id, '_reason', sanitize_text_field($_POST['tfp_skip_reason'] ?? ''));
    update_post_meta($post_id, '_notes', sanitize_textarea_field($_POST['tfp_skip_notes'] ?? ''));
    $status = sanitize_key($_POST['tfp_skip_status'] ?? 'pending');
    update_post_meta($post_id, '_status', array_key_exists($status, tfp_skip_request_statuses()) ? $status : 'pending');
});

add_action('save_post_tfp_student_document', function ($post_id) {
    if (!isset($_POST['tfp_student_document_nonce']) || !wp_verify_nonce($_POST['tfp_student_document_nonce'], 'tfp_student_document_save') || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) return;

    $user_id = absint($_POST['tfp_doc_user_id'] ?? 0);
    $type = sanitize_text_field($_POST['tfp_doc_type'] ?? 'Agreement');
    $date = sanitize_text_field($_POST['tfp_doc_date'] ?? '');
    $status = sanitize_key($_POST['tfp_doc_status'] ?? 'pending');

    update_post_meta($post_id, '_user_id', $user_id);
    update_post_meta($post_id, '_type', $type);
    update_post_meta($post_id, '_date_issued', $date);
    update_post_meta($post_id, '_status', array_key_exists($status, tfp_student_document_statuses()) ? $status : 'pending');
    update_post_meta($post_id, '_document_url', esc_url_raw($_POST['tfp_doc_url'] ?? ''));
});

/* -------------------------------------------------------------------------
 * Cohort meeting helpers
 * ------------------------------------------------------------------------- */
function tfp_calendar_parse_schedule($schedule) {
    $schedule = trim((string) $schedule);
    $weekdays = [
        'sunday' => 0, 'sun' => 0,
        'monday' => 1, 'mon' => 1,
        'tuesday' => 2, 'tue' => 2, 'tues' => 2,
        'wednesday' => 3, 'wed' => 3,
        'thursday' => 4, 'thu' => 4, 'thur' => 4, 'thurs' => 4,
        'friday' => 5, 'fri' => 5,
        'saturday' => 6, 'sat' => 6,
    ];
    $weekday = null;
    foreach ($weekdays as $name => $num) {
        if (preg_match('/\\b' . preg_quote($name, '/') . '\\b/i', $schedule)) {
            $weekday = $num;
            break;
        }
    }
    $time = '00:00';
    if (preg_match('/(\\d{1,2})(?::(\\d{2}))?\\s*(am|pm)/i', $schedule, $m)) {
        $hour = (int) $m[1];
        $minute = isset($m[2]) ? (int) $m[2] : 0;
        $ampm = strtolower($m[3]);
        if ($ampm === 'pm' && $hour < 12) $hour += 12;
        if ($ampm === 'am' && $hour === 12) $hour = 0;
        $time = sprintf('%02d:%02d', $hour, $minute);
    }
    return ['weekday' => $weekday, 'time' => $time];
}

function tfp_calendar_student_cohort($user_id) {
    $cohort_id = (int) get_user_meta($user_id, 'tfp_cohort_choice', true);
    if (!$cohort_id || !function_exists('tfp_cohort_get')) return null;
    $cohort = tfp_cohort_get($cohort_id);
    if (!$cohort) return null;
    if (function_exists('tfp_billing_user_has_paid') && !tfp_billing_user_has_paid($user_id)) return null;
    return $cohort;
}

function tfp_calendar_off_days($from, $to) {
    $posts = get_posts([
        'post_type' => 'tfp_off_day',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [[
            'key' => '_date',
            'value' => [$from, $to],
            'compare' => 'BETWEEN',
            'type' => 'DATE',
        ]],
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_key' => '_date',
    ]);
    $out = [];
    foreach ($posts as $p) {
        $date = get_post_meta($p->ID, '_date', true);
        if ($date) $out[$date] = ['id' => $p->ID, 'name' => get_the_title($p->ID), 'date' => $date];
    }
    return $out;
}

function tfp_calendar_meetings_for_month($user_id, $ym) {
    $cohort = tfp_calendar_student_cohort($user_id);
    if (!$cohort) return [];
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $ym . '-01', wp_timezone());
    if (!$first) return [];
    $last = $first->modify('last day of this month');
    $schedule = tfp_calendar_parse_schedule($cohort['schedule']);
    if ($schedule['weekday'] === null || empty($cohort['start_date'])) return [];
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $cohort['start_date'], wp_timezone());
    if (!$start) return [];
    $cursor = $start;
    while ((int) $cursor->format('w') !== (int) $schedule['weekday']) $cursor = $cursor->modify('+1 day');
    if ($cursor < $first) {
        $diff = (int) floor(($first->getTimestamp() - $cursor->getTimestamp()) / WEEK_IN_SECONDS);
        if ($diff > 0) $cursor = $cursor->modify('+' . $diff . ' weeks');
        while ($cursor < $first) $cursor = $cursor->modify('+1 week');
    }
    $meetings = [];
    while ($cursor <= $last) {
        if ($cursor >= $first) {
            $date = $cursor->format('Y-m-d');
            $time = $schedule['time'];
            $meetings[$date][] = [
                'id' => $cohort['id'] . '-' . $date,
                'cohort_id' => $cohort['id'],
                'cohort' => $cohort['name'],
                'date' => $date,
                'start_time' => $time,
                'start_label' => date_i18n(get_option('time_format'), strtotime($date . ' ' . $time)),
                'schedule' => $cohort['schedule'],
                'facilitator' => $cohort['facilitator'],
                'meeting_url' => (string) get_post_meta($cohort['id'], '_meeting_url', true),
            ];
        }
        $cursor = $cursor->modify('+1 week');
    }
    return $meetings;
}

function tfp_calendar_next_meetings($user_id, $limit = 3) {
    $today = new DateTimeImmutable('today', wp_timezone());
    $out = [];
    for ($i = 0; $i < 4 && count($out) < $limit; $i++) {
        $month = $today->modify('+' . $i . ' month')->format('Y-m');
        $items = tfp_calendar_meetings_for_month($user_id, $month);
        foreach ($items as $date => $meetings) {
            foreach ($meetings as $meeting) {
                if ($date >= $today->format('Y-m-d')) {
                    $out[] = $meeting;
                    if (count($out) >= $limit) break 2;
                }
            }
        }
    }
    return $out;
}

function tfp_calendar_selected_day_data($user_id, $date) {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
    if (!$dt) return ['date' => $date, 'meetings' => [], 'off_day' => null, 'skip' => null];
    $ym = $dt->format('Y-m');
    $all = tfp_calendar_meetings_for_month($user_id, $ym);
    $off = tfp_calendar_off_days($date, $date);
    $skip = get_posts([
        'post_type' => 'tfp_skip_request',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => [
            ['key' => '_user_id', 'value' => $user_id, 'type' => 'NUMERIC'],
            ['key' => '_date', 'value' => $date],
        ],
    ]);
    $skip_data = null;
    if ($skip) {
        $skip_data = [
            'id' => $skip[0]->ID,
            'status' => get_post_meta($skip[0]->ID, '_status', true) ?: 'pending',
            'reason' => get_post_meta($skip[0]->ID, '_reason', true),
        ];
    }
    return [
        'date' => $date,
        'meetings' => $all[$date] ?? [],
        'off_day' => $off[$date] ?? null,
        'skip' => $skip_data,
    ];
}

/* -------------------------------------------------------------------------
 * Document helpers
 * ------------------------------------------------------------------------- */
function tfp_dashboard_user_documents($user_id, $type = '') {
    $args = [
        'post_type' => 'tfp_student_document',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => [['key' => '_user_id', 'value' => (int) $user_id, 'type' => 'NUMERIC']],
        'orderby' => 'meta_value',
        'meta_key' => '_date_issued',
        'order' => 'DESC',
    ];
    if ($type) $args['meta_query'][] = ['key' => '_type', 'value' => $type];
    $posts = get_posts($args);
    return array_map(function ($p) {
        return [
            'id' => $p->ID,
            'title' => get_the_title($p->ID),
            'type' => get_post_meta($p->ID, '_type', true) ?: 'Agreement',
            'date' => get_post_meta($p->ID, '_date_issued', true),
            'status' => get_post_meta($p->ID, '_status', true) ?: 'pending',
            'url' => get_post_meta($p->ID, '_document_url', true),
            'signed_at' => get_post_meta($p->ID, '_signed_at', true),
        ];
    }, $posts);
}

function tfp_dashboard_user_receipts($user_id) {
    if (!function_exists('wc_get_orders')) return [];
    $orders = wc_get_orders(['customer_id' => $user_id, 'limit' => -1, 'status' => array_keys(wc_get_order_statuses()), 'orderby' => 'date', 'order' => 'DESC']);
    $out = [];
    foreach ($orders as $order) {
        if (!$order instanceof WC_Order) continue;
        $out[] = [
            'id' => $order->get_id(),
            'title' => sprintf(__('Receipt #%s', 'tfp-dashboard'), $order->get_order_number()),
            'type' => __('Receipt', 'tfp-dashboard'),
            'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d') : '',
            'status' => wc_get_order_status_name($order->get_status()),
            'url' => $order->get_view_order_url(),
            'amount' => $order->get_formatted_order_total(),
        ];
    }
    return $out;
}

function tfp_dashboard_user_certificates($user_id) {
    $out = [];
    if (!function_exists('tfp_ld_get_program_course_id') || !function_exists('learndash_course_completed')) return $out;
    $course_id = (int) tfp_ld_get_program_course_id();
    if (!$course_id || !learndash_course_completed($user_id, $course_id)) return $out;
    $out[] = [
        'id' => $course_id,
        'title' => get_the_title($course_id) . ' ' . __('Certificate', 'tfp-dashboard'),
        'type' => __('Certificate', 'tfp-dashboard'),
        'date' => current_time('Y-m-d'),
        'status' => 'active',
        'url' => function_exists('learndash_get_course_certificate_link') ? learndash_get_course_certificate_link($course_id, $user_id) : '',
    ];
    return $out;
}

function tfp_dashboard_user_grades($user_id) {
    $course_id = function_exists('tfp_ld_get_program_course_id') ? (int) tfp_ld_get_program_course_id() : 0;
    $rows = [];
    if (!$course_id || !function_exists('tfp_ld_get_weeks')) return $rows;
    foreach (tfp_ld_get_weeks($course_id) as $i => $week) {
        $grade = function_exists('tfp_grade_get_week') ? tfp_grade_get_week($user_id, $week->ID) : '';
        $rows[] = [
            'title' => sprintf(__('Week %d — %s', 'tfp-dashboard'), $i + 1, $week->post_title),
            'type' => __('Grade', 'tfp-dashboard'),
            'date' => get_the_date('Y-m-d', $week),
            'status' => $grade ?: 'Not graded',
            'url' => '',
        ];
    }
    return $rows;
}

/* -------------------------------------------------------------------------
 * AJAX
 * ------------------------------------------------------------------------- */
add_action('wp_ajax_tfp_calendar_day', function () {
    check_ajax_referer('tfp_calendar_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => __('Please log in.', 'tfp-dashboard')], 401);
    $date = sanitize_text_field($_POST['date'] ?? '');
    wp_send_json_success(tfp_calendar_selected_day_data(get_current_user_id(), $date));
});

add_action('wp_ajax_tfp_skip_request', function () {
    check_ajax_referer('tfp_calendar_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => __('Please log in.', 'tfp-dashboard')], 401);
    $user_id = get_current_user_id();
    $date = sanitize_text_field($_POST['date'] ?? '');
    $cohort_id = absint($_POST['cohort_id'] ?? 0);
    $reason = sanitize_text_field($_POST['reason'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) || !$cohort_id || !$reason) wp_send_json_error(['message' => __('Date, cohort and reason are required.', 'tfp-dashboard')], 400);
    $cohort = tfp_calendar_student_cohort($user_id);
    if (!$cohort || (int) $cohort['id'] !== $cohort_id) wp_send_json_error(['message' => __('That cohort is not available for your account.','tfp-dashboard')], 403);
    $existing = get_posts(['post_type'=>'tfp_skip_request','post_status'=>'publish','posts_per_page'=>1,'meta_query'=>[['key'=>'_user_id','value'=>$user_id,'type'=>'NUMERIC'],['key'=>'_date','value'=>$date],['key'=>'_status','value'=>['pending','approved'],'compare'=>'IN']]]);
    if ($existing) wp_send_json_error(['message'=>__('A skip request already exists for this date.','tfp-dashboard')],409);
    $id = wp_insert_post(['post_type'=>'tfp_skip_request','post_status'=>'publish','post_title'=>sprintf(__('Skip Request — %s — %s','tfp-dashboard'),wp_get_current_user()->display_name,$date)]);
    if (!$id || is_wp_error($id)) wp_send_json_error(['message'=>__('Unable to create the request.','tfp-dashboard')],500);
    update_post_meta($id,'_user_id',$user_id);
    update_post_meta($id,'_date',$date);
    update_post_meta($id,'_cohort_id',$cohort_id);
    update_post_meta($id,'_reason',$reason);
    update_post_meta($id,'_notes',$notes);
    update_post_meta($id,'_status','pending');
    wp_send_json_success(['message'=>__('Skip request submitted.','tfp-dashboard'),'id'=>$id]);
});

add_action('wp_ajax_tfp_sign_document', function () {
    check_ajax_referer('tfp_documents_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>__('Please log in.','tfp-dashboard')],401);
    $id = absint($_POST['document_id'] ?? 0);
    $signature = sanitize_text_field($_POST['signature'] ?? '');
    $post = get_post($id);
    $user_id = get_current_user_id();
    if (!$post || $post->post_type !== 'tfp_student_document' || (int)get_post_meta($id,'_user_id',true) !== $user_id) wp_send_json_error(['message'=>__('Document not found.','tfp-dashboard')],404);
    $status = get_post_meta($id,'_status',true);
    if (!in_array($status,['pending','active'],true)) wp_send_json_error(['message'=>__('This document cannot be signed.','tfp-dashboard')],400);
    if (strlen($signature) < 2) wp_send_json_error(['message'=>__('Please enter your full name as your signature.','tfp-dashboard')],400);
    update_post_meta($id,'_status','signed');
    update_post_meta($id,'_signature',$signature);
    update_post_meta($id,'_signed_at',current_time('mysql'));
    update_post_meta($id,'_signed_ip',sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''));
    wp_send_json_success(['message'=>__('Document signed successfully.','tfp-dashboard'),'status'=>'signed']);
});
