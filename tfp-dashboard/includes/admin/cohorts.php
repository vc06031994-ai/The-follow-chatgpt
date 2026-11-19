<?php
/** Admin Cohorts directory — reads the existing tfp_cohort CPT and metadata. */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_cohorts_url() { return home_url('/admin-cohorts/'); }

add_filter('pre_handle_404', function ($preempt, $query) {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) === 'admin-cohorts') {
        if ($query) { $query->is_404 = false; $query->is_page = true; }
        status_header(200);
        return true;
    }
    return $preempt;
}, 12, 2);

add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'admin-cohorts') { return; }
    status_header(200);
    nocache_headers();
    $template = TFP_DASH_PATH . 'templates/template-admin-cohorts.php';
    if (is_readable($template)) { include $template; exit; }
}, 1);

function tfp_admin_cohorts_get_records() {
    $search = isset($_GET['cohort_search']) ? sanitize_text_field(wp_unslash($_GET['cohort_search'])) : '';
    $course_id = isset($_GET['cohort_course']) ? absint($_GET['cohort_course']) : 0;
    $status = isset($_GET['cohort_status']) ? sanitize_key(wp_unslash($_GET['cohort_status'])) : '';
    $post_status = $status === 'active' ? ['publish'] : ($status === 'draft' ? ['draft'] : ['publish', 'draft']);
    $args = ['post_type' => 'tfp_cohort', 'post_status' => $post_status, 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 's' => $search];
    if ($course_id) {
        $args['meta_query'] = [['key' => '_related_course', 'value' => $course_id, 'compare' => '=', 'type' => 'NUMERIC']];
    }
    $records = [];
    foreach (get_posts($args) as $cohort) {
        $related_course = absint(get_post_meta($cohort->ID, '_related_course', true));
        $facilitator_id = function_exists('tfp_cohort_get_facilitator_user_id') ? absint(tfp_cohort_get_facilitator_user_id($cohort->ID)) : 0;
        $facilitator_user = $facilitator_id ? get_userdata($facilitator_id) : false;
        $students = function_exists('tfp_cohort_seats_taken') ? tfp_cohort_seats_taken($cohort->ID) : count(get_users(['meta_key' => 'tfp_cohort_choice', 'meta_value' => $cohort->ID, 'fields' => 'ID', 'number' => -1]));
        $seat_capacity = function_exists('tfp_cohort_seats_total') ? tfp_cohort_seats_total($cohort->ID) : 0;
        $records[] = [
            'id' => $cohort->ID, 'name' => get_the_title($cohort),
            'course' => $related_course ? get_the_title($related_course) : __('Course not assigned', 'tfp-dashboard'),
            'course_id' => $related_course,
            'facilitator' => $facilitator_user ? $facilitator_user->display_name : (get_post_meta($cohort->ID, '_facilitator', true) ?: '—'),
            'students' => (int) $students, 'capacity' => (int) $seat_capacity,
            'date' => (string) get_post_meta($cohort->ID, '_start_date', true),
            'meeting' => (string) get_post_meta($cohort->ID, '_schedule_text', true),
            'status' => $cohort->post_status === 'publish' ? 'active' : 'draft',
            'edit_url' => get_edit_post_link($cohort->ID, 'raw'),
            'course_url' => $related_course ? get_edit_post_link($related_course, 'raw') : '',
        ];
    }
    return ['records' => $records, 'search' => $search, 'course' => $course_id, 'status' => $status];
}

function tfp_admin_cohorts_render() {
    if (!is_user_logged_in()) { auth_redirect(); }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }
    $data = tfp_admin_cohorts_get_records();

    // Show non-persistent sample rows only when the real directory is empty and no filters are active.
    // These examples are for layout review only and are never written to the database.
    if (empty($data['records']) && empty($data['search']) && empty($data['course']) && empty($data['status'])) {
        $demo_rows = [
            ['id' => 0, 'name' => '58W: Spring 2026 – Tue A', 'course' => '58 Week Discipleship', 'course_id' => 0, 'facilitator' => 'John Doe', 'students' => 20, 'capacity' => 20, 'date' => '2026-04-18', 'meeting' => 'Mon, 7:00 PM PST', 'status' => 'active', 'edit_url' => '', 'course_url' => '', 'is_demo' => true],
            ['id' => 0, 'name' => '58W: Spring 2026 – Tue B', 'course' => '58 Week Discipleship', 'course_id' => 0, 'facilitator' => 'Jane Smith', 'students' => 16, 'capacity' => 20, 'date' => '2026-04-18', 'meeting' => 'Tue, 6:30 PM PST', 'status' => 'active', 'edit_url' => '', 'course_url' => '', 'is_demo' => true],
            ['id' => 0, 'name' => 'FND: Fall 2026 – Tue A', 'course' => '12 Week Foundation Course', 'course_id' => 0, 'facilitator' => 'Michael Brown', 'students' => 18, 'capacity' => 20, 'date' => '2026-09-08', 'meeting' => 'Tue, 7:00 PM PST', 'status' => 'active', 'edit_url' => '', 'course_url' => '', 'is_demo' => true],
            ['id' => 0, 'name' => 'FND: Spring 2026 – Tue A', 'course' => '12 Week Foundation Course', 'course_id' => 0, 'facilitator' => 'Sarah Wilson', 'students' => 12, 'capacity' => 20, 'date' => '2026-04-21', 'meeting' => 'Thu, 6:00 PM PST', 'status' => 'active', 'edit_url' => '', 'course_url' => '', 'is_demo' => true],
            ['id' => 0, 'name' => '58W: Summer 2026 – Thu A', 'course' => '58 Week Discipleship', 'course_id' => 0, 'facilitator' => 'John Doe', 'students' => 8, 'capacity' => 20, 'date' => '2026-06-04', 'meeting' => 'Thu, 7:00 PM PST', 'status' => 'draft', 'edit_url' => '', 'course_url' => '', 'is_demo' => true],
            ['id' => 0, 'name' => 'FND: Winter 2026 – Mon A', 'course' => '12 Week Foundation Course', 'course_id' => 0, 'facilitator' => 'Jane Smith', 'students' => 0, 'capacity' => 20, 'date' => '2026-12-07', 'meeting' => 'Mon, 6:00 PM PST', 'status' => 'draft', 'edit_url' => '', 'course_url' => '', 'is_demo' => true],
            ['id' => 0, 'name' => 'FND: Winter 2026 – Wed A', 'course' => '12 Week Foundation Course', 'course_id' => 0, 'facilitator' => 'Michael Brown', 'students' => 0, 'capacity' => 20, 'date' => '2026-12-09', 'meeting' => 'Wed, 7:00 PM PST', 'status' => 'draft', 'edit_url' => '', 'course_url' => '', 'is_demo' => true],
            ['id' => 0, 'name' => '58W: Winter 2026 – Sat A', 'course' => '58 Week Discipleship', 'course_id' => 0, 'facilitator' => 'Sarah Wilson', 'students' => 0, 'capacity' => 20, 'date' => '2026-12-12', 'meeting' => 'Sat, 10:00 AM PST', 'status' => 'draft', 'edit_url' => '', 'course_url' => '', 'is_demo' => true],
        ];
        $data['records'] = $demo_rows;
        $data['is_demo'] = true;
    }

    $user = wp_get_current_user();
    $courses = get_posts(['post_type' => 'sfwd-courses', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    $active = array_values(array_filter($data['records'], function ($r) { return $r['status'] === 'active'; }));
    $drafts = array_values(array_filter($data['records'], function ($r) { return $r['status'] === 'draft'; }));
    $nav = function_exists('tfp_admin_dashboard_nav_items') ? tfp_admin_dashboard_nav_items() : [];
    foreach ($nav as &$item) {
        $item['active'] = ($item['slug'] ?? '') === 'cohorts';
        if (($item['slug'] ?? '') === 'cohorts') { $item['url'] = tfp_admin_cohorts_url(); }
    }
    unset($item);
    ?>
    <div class="tfp-admin-app tfp-admin-cohorts-app">
      <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
        <div class="tfp-admin-sidebar-top"><button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"/><path d="M6 2v14"/></svg></button></div>
        <div class="tfp-admin-brand-row"><a class="tfp-admin-brand" href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt=""></a></div>
        <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
          <?php foreach ($nav as $item) : ?><a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>"><span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo function_exists('tfp_admin_dashboard_nav_icon') ? tfp_admin_dashboard_nav_icon($item['slug']) : ''; ?></span><span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span></a><?php endforeach; ?>
        </nav>
        <div class="tfp-admin-sidebar-bottom"><div class="tfp-admin-profile"><img src="<?php echo esc_url(get_avatar_url($user->ID, ['size' => 96])); ?>" alt=""><span><strong><?php echo esc_html($user->display_name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span></div><a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span class="tfp-admin-logout-icon" aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a></div>
      </aside>
      <div class="tfp-admin-main">
        <div class="tfp-admin-breadcrumb"><a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a><b>/</b><strong><?php esc_html_e('Cohorts', 'tfp-dashboard'); ?></strong></div>
        <header class="tfp-admin-page-header tfp-admin-cohorts-header"><div><h1><?php esc_html_e('Cohorts', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Manage active cohort groups, facilitators, and progress.', 'tfp-dashboard'); ?></p></div><button class="tfp-admin-add-user-btn tfp-admin-cohorts-add" type="button" data-tfp-open-cohort-modal><span aria-hidden="true">＋</span><?php esc_html_e('Add Cohort', 'tfp-dashboard'); ?></button></header>
        <main class="tfp-admin-content tfp-admin-cohorts-content">
          <form class="tfp-admin-cohorts-filters" method="get" action="<?php echo esc_url(tfp_admin_cohorts_url()); ?>">
            <label class="screen-reader-text" for="tfp-cohort-course"><?php esc_html_e('Filter by course', 'tfp-dashboard'); ?></label><select id="tfp-cohort-course" name="cohort_course"><option value="0"><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option><?php foreach ($courses as $course) : ?><option value="<?php echo esc_attr($course->ID); ?>" <?php selected($data['course'], $course->ID); ?>><?php echo esc_html($course->post_title); ?></option><?php endforeach; ?></select>
            <label class="screen-reader-text" for="tfp-cohort-status"><?php esc_html_e('Filter by status', 'tfp-dashboard'); ?></label><select id="tfp-cohort-status" name="cohort_status"><option value=""><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option><option value="active" <?php selected($data['status'], 'active'); ?>><?php esc_html_e('Active', 'tfp-dashboard'); ?></option><option value="draft" <?php selected($data['status'], 'draft'); ?>><?php esc_html_e('Draft', 'tfp-dashboard'); ?></option></select>
            <label class="tfp-admin-cohorts-search"><span class="tfp-admin-search-png" aria-hidden="true"><img src="<?php echo esc_url('https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png'); ?>" alt=""></span><input type="search" name="cohort_search" value="<?php echo esc_attr($data['search']); ?>" placeholder="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>"></label><button class="tfp-admin-cohorts-filter-submit" type="submit"><?php esc_html_e('Apply', 'tfp-dashboard'); ?></button>
          </form>
          <?php
          $table = function ($heading, $rows, $draft = false) { ?>
            <section class="tfp-admin-cohorts-panel"><h2><?php echo esc_html($heading); ?></h2><div class="tfp-admin-cohorts-table-wrap tfp-dash-table-wrap"><table class="tfp-admin-cohorts-table tfp-dash-table">
              <colgroup><col class="tfp-col-name"><col class="tfp-col-facilitator"><col class="tfp-col-students"><col class="tfp-col-date"><col class="tfp-col-meeting"><col class="tfp-col-status"><col class="tfp-col-actions"></colgroup>
              <thead><tr><th><?php esc_html_e('Cohort Name', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Students', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Start Date', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Meeting', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><span class="screen-reader-text"><?php esc_html_e('Actions', 'tfp-dashboard'); ?></span></th></tr></thead><tbody>
              <?php if ($rows) : foreach ($rows as $row) : ?><tr>
                <td data-label="<?php esc_attr_e('Cohort Name', 'tfp-dashboard'); ?>"><strong><?php echo esc_html($row['name']); ?></strong><small><?php echo esc_html($row['course']); ?></small></td>
                <td data-label="<?php esc_attr_e('Facilitator', 'tfp-dashboard'); ?>"><?php echo esc_html($row['facilitator']); ?></td><td data-label="<?php esc_attr_e('Students', 'tfp-dashboard'); ?>"><?php echo esc_html(number_format_i18n($row['students']) . ($row['capacity'] > 0 ? '/' . number_format_i18n($row['capacity']) : '')); ?></td>
                <td data-label="<?php esc_attr_e('Start Date', 'tfp-dashboard'); ?>"><?php echo $row['date'] && strtotime($row['date']) ? esc_html(date_i18n('M j', strtotime($row['date']))) : '—'; ?></td><td data-label="<?php esc_attr_e('Meeting', 'tfp-dashboard'); ?>"><?php echo esc_html($row['meeting'] ?: '—'); ?></td>
                <td data-label="<?php esc_attr_e('Status', 'tfp-dashboard'); ?>"><span class="tfp-admin-cohort-status <?php echo $draft ? 'is-draft' : 'is-active'; ?>"><?php echo $draft ? esc_html__('Draft', 'tfp-dashboard') : esc_html__('Active', 'tfp-dashboard'); ?></span></td>
                <td data-label="<?php esc_attr_e('Actions', 'tfp-dashboard'); ?>" class="tfp-admin-cohort-actions"><?php if (!empty($row['is_demo'])) : ?><span class="tfp-admin-cohort-btn is-primary is-disabled" aria-disabled="true"><?php echo $draft ? esc_html__('Edit', 'tfp-dashboard') : esc_html__('View', 'tfp-dashboard'); ?></span><?php if (!$draft) : ?><span class="tfp-admin-cohort-btn is-outline is-disabled" aria-disabled="true"><?php esc_html_e('View', 'tfp-dashboard'); ?></span><?php endif; ?><?php elseif ($draft) : ?><a class="tfp-admin-cohort-btn is-outline" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('Edit', 'tfp-dashboard'); ?></a><?php else : ?><a class="tfp-admin-cohort-btn is-primary" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a><?php if ($row['course_url']) : ?><a class="tfp-admin-cohort-btn is-outline" href="<?php echo esc_url($row['course_url']); ?>" title="<?php esc_attr_e('Open linked course editor', 'tfp-dashboard'); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a><?php endif; ?><?php endif; ?></td>
              </tr><?php endforeach; else : ?><tr><td colspan="7" class="tfp-admin-cohorts-empty"><?php esc_html_e('No cohorts found.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
            </tbody></table></div></section>
          <?php };
          $table(__('Active Cohorts', 'tfp-dashboard'), $active);
          $table(__('Draft Cohorts', 'tfp-dashboard'), $drafts, true);
          ?>
        </main>
      </div>
    </div>
    <?php
}


/**
 * Create a cohort from the Admin Cohorts modal.
 * Uses the existing tfp_cohort post type and established metadata keys.
 */
add_action('wp_ajax_tfp_admin_create_cohort', 'tfp_admin_create_cohort_ajax');
function tfp_admin_create_cohort_ajax() {
    check_ajax_referer('tfp_admin_create_cohort', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to create cohorts.', 'tfp-dashboard')], 403);
    }

    $course_id = absint($_POST['course_id'] ?? 0);
    $course = $course_id ? get_post($course_id) : null;
    if (!$course || $course->post_type !== 'sfwd-courses' || $course->post_status !== 'publish') {
        wp_send_json_error(['message' => __('Please select a valid course.', 'tfp-dashboard')], 400);
    }

    $start_date = sanitize_text_field(wp_unslash($_POST['start_date'] ?? ''));
    $date = DateTime::createFromFormat('!Y-m-d', $start_date);
    if (!$date || $date->format('Y-m-d') !== $start_date) {
        wp_send_json_error(['message' => __('Please select a valid start date.', 'tfp-dashboard')], 400);
    }

    $day = sanitize_text_field(wp_unslash($_POST['meeting_day'] ?? ''));
    $allowed_days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    if (!in_array($day, $allowed_days, true)) {
        wp_send_json_error(['message' => __('Please select a meeting day.', 'tfp-dashboard')], 400);
    }

    $time = sanitize_text_field(wp_unslash($_POST['meeting_time'] ?? ''));
    if (!preg_match('/^(0?[1-9]|1[0-2]):[0-5][0-9]$/', $time)) {
        wp_send_json_error(['message' => __('Please enter a valid meeting time.', 'tfp-dashboard')], 400);
    }

    $ampm = strtoupper(sanitize_text_field(wp_unslash($_POST['meeting_ampm'] ?? 'AM')));
    if (!in_array($ampm, ['AM','PM'], true)) { $ampm = 'AM'; }

    $zone = sanitize_text_field(wp_unslash($_POST['meeting_zone'] ?? 'PST'));
    $allowed_zones = ['PST','MST','CST','EST','UTC'];
    if (!in_array($zone, $allowed_zones, true)) { $zone = 'PST'; }

    $facilitator_id = absint($_POST['facilitator_id'] ?? 0);
    if ($facilitator_id) {
        $facilitator = get_userdata($facilitator_id);
        if (!$facilitator) { $facilitator_id = 0; }
    }

    $capacity = absint($_POST['capacity'] ?? 0);
    if ($capacity < 1) {
        wp_send_json_error(['message' => __('Please enter a cohort capacity greater than zero.', 'tfp-dashboard')], 400);
    }

    $discord_room = sanitize_text_field(wp_unslash($_POST['discord_room'] ?? ''));
    $month = (int) $date->format('n');
    $semester = ($month >= 3 && $month <= 5) ? 'Spring' : (($month >= 6 && $month <= 8) ? 'Summer' : (($month >= 9 && $month <= 11) ? 'Fall' : 'Winter'));
    $group_number = 1;
    $title = sprintf('%s — %s %s — %s — Group %d', get_the_title($course_id), $semester, $date->format('Y'), $day, $group_number);

    $post_id = wp_insert_post([
        'post_type' => 'tfp_cohort',
        'post_title' => $title,
        'post_status' => 'publish',
        'post_author' => get_current_user_id(),
    ], true);

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => $post_id->get_error_message()], 500);
    }

    $schedule = sprintf('%s %s %s %s', $day, $time, $ampm, $zone);
    update_post_meta($post_id, '_related_course', $course_id);
    update_post_meta($post_id, '_start_date', $start_date);
    update_post_meta($post_id, '_schedule_text', $schedule);
    update_post_meta($post_id, '_seats_total', $capacity);
    update_post_meta($post_id, '_facilitator_user_id', $facilitator_id);
    update_post_meta($post_id, '_facilitator', $facilitator_id ? get_userdata($facilitator_id)->display_name : '');
    update_post_meta($post_id, '_discord_room', $discord_room);
    if ($discord_room && filter_var($discord_room, FILTER_VALIDATE_URL)) {
        update_post_meta($post_id, '_meeting_url', esc_url_raw($discord_room));
    }

    wp_send_json_success([
        'message' => __('Cohort created successfully.', 'tfp-dashboard'),
        'redirect' => tfp_admin_cohorts_url(),
        'id' => $post_id,
    ]);
}
