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

function tfp_admin_cohort_detail_render() {
    if (!is_user_logged_in()) { auth_redirect(); }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }
    $id = tfp_admin_cohort_detail_request_id();
    $data = tfp_admin_cohort_detail_data($id);
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
        <div class="tfp-admin-breadcrumb"><a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a><b>/</b><a href="<?php echo esc_url(tfp_admin_cohorts_url()); ?>"><?php esc_html_e('Cohorts', 'tfp-dashboard'); ?></a><b>/</b><strong><?php echo esc_html(get_the_title($id)); ?></strong><?php if ($tab === 'roster') : ?><b>/</b><strong><?php esc_html_e('Roster', 'tfp-dashboard'); ?></strong><?php endif; ?></div>
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
                      <tr><td colspan="6" class="tfp-admin-roster-empty"><?php esc_html_e('No students match your search.', 'tfp-dashboard'); ?></td></tr>
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
            <section class="tfp-admin-cohort-data-panel"><h2><?php esc_html_e('Cohort Schedule', 'tfp-dashboard'); ?></h2><dl><div><dt><?php esc_html_e('Start Date', 'tfp-dashboard'); ?></dt><dd><?php echo $data['start_date'] && strtotime($data['start_date']) ? esc_html(date_i18n(get_option('date_format'), strtotime($data['start_date']))) : '—'; ?></dd></div><div><dt><?php esc_html_e('Meeting', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['schedule'] ?: '—'); ?></dd></div><div><dt><?php esc_html_e('Meeting Room', 'tfp-dashboard'); ?></dt><dd><?php echo $data['meeting_url'] ? '<a href="' . esc_url($data['meeting_url']) . '" target="_blank" rel="noopener">' . esc_html($data['meeting_url']) . '</a>' : '—'; ?></dd></div></dl></section>
          <?php elseif ($tab === 'settings') : ?>
            <section class="tfp-admin-cohort-data-panel"><h2><?php esc_html_e('Cohort Settings', 'tfp-dashboard'); ?></h2><dl><div><dt><?php esc_html_e('Course', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['course'] ? $data['course']->post_title : '—'); ?></dd></div><div><dt><?php esc_html_e('Capacity', 'tfp-dashboard'); ?></dt><dd><?php echo $data['capacity'] ? esc_html(number_format_i18n($data['capacity'])) : esc_html__('Unlimited', 'tfp-dashboard'); ?></dd></div><div><dt><?php esc_html_e('Status', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['status']); ?></dd></div></dl><a class="tfp-admin-cohort-primary" href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit Cohort Settings', 'tfp-dashboard'); ?></a></section>
          <?php else : ?>
            <section class="tfp-admin-cohort-data-panel"><h2><?php esc_html_e('Recent Activity', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Cohort created', 'tfp-dashboard'); ?> · <?php echo esc_html(get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $id)); ?></p><p><?php esc_html_e('Last updated', 'tfp-dashboard'); ?> · <?php echo esc_html(get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $id)); ?></p></section>
          <?php endif; ?>
        </main>
      </div>
    </div>
    <?php
}
