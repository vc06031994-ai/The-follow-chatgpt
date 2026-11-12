<?php
/**
 * Admin Course Detail dashboard.
 * Renders the selected LearnDash course and its ordered week (lesson) mapping.
 */
if (!defined('ABSPATH')) {
    exit;
}

function tfp_admin_course_detail_url($course_id) {
    return home_url('/admin-course/' . absint($course_id) . '/');
}

function tfp_admin_course_detail_route_id() {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (!preg_match('~(?:^|/)admin-course/([^/]+)/?$~', $path, $matches)) {
        return 0;
    }

    $route = sanitize_title(rawurldecode($matches[1]));
    if (ctype_digit($route)) {
        return absint($route); // Preserve compatibility with previously shared numeric URLs.
    }

    $course = get_page_by_path($route, OBJECT, 'sfwd-courses');
    return ($course instanceof WP_Post) ? (int) $course->ID : 0;
}

function tfp_admin_course_detail_week_items($lesson_id) {
    $items = [];
    if (get_post_meta($lesson_id, 'tfp_week_video_url', true)) {
        $items[] = 'Video';
    }
    $lesson = get_post($lesson_id);
    if ($lesson && (trim(wp_strip_all_tags($lesson->post_content)) !== '' || trim((string) $lesson->post_excerpt) !== '')) {
        $items[] = 'Reading';
    }
    $homework = function_exists('tfp_week_get_homework_questions') ? tfp_week_get_homework_questions($lesson_id, false) : [];
    if (!empty($homework)) {
        $items[] = 'Hmwk';
    }
    $quiz = function_exists('tfp_week_get_quiz_questions') ? tfp_week_get_quiz_questions($lesson_id, false) : [];
    if (!empty($quiz)) {
        $items[] = 'Quiz';
    }
    $test = function_exists('tfp_week_get_test_questions') ? tfp_week_get_test_questions($lesson_id, false) : [];
    if (!empty($test)) {
        $items[] = 'Test';
    }
    if (get_post_meta($lesson_id, 'tfp_week_meeting_date', true) || get_post_meta($lesson_id, 'tfp_week_meeting_discord_url', true)) {
        $items[] = 'Meeting';
    }
    return $items;
}

function tfp_admin_course_detail_get_data($course_id) {
    $course = get_post(absint($course_id));
    if (!$course || $course->post_type !== 'sfwd-courses') {
        return null;
    }

    $weeks = function_exists('tfp_ld_get_weeks') ? (array) tfp_ld_get_weeks($course_id) : [];
    $cohorts = function_exists('tfp_cohorts_for_course') ? (array) tfp_cohorts_for_course($course_id) : [];
    $week_rows = [];
    $alerts = 0;
    $modules_mapped = 0;

    foreach ($weeks as $index => $week) {
        $items = tfp_admin_course_detail_week_items($week->ID);
        $modules_mapped = count($weeks);
        $is_published = $week->post_status === 'publish';
        if (!$is_published || empty($items)) {
            $alerts++;
        }
        $week_rows[] = [
            'number' => $index + 1,
            'id' => (int) $week->ID,
            'title' => get_the_title($week->ID),
            'status' => $is_published ? 'Published' : 'Attention',
            'status_class' => $is_published ? 'is-published' : 'is-attention',
            'items' => $items,
            'gate' => 'Strict',
            'edit_url' => get_edit_post_link($week->ID, 'raw'),
        ];
    }

    $course_type = '';
    foreach (['tfp_course_type', '_tfp_course_type', 'course_type'] as $key) {
        $course_type = trim((string) get_post_meta($course_id, $key, true));
        if ($course_type !== '') {
            break;
        }
    }

    $attendance_policy = '';
    foreach (['tfp_attendance_policy', '_tfp_attendance_policy', 'tfp_default_attendance_policy'] as $key) {
        $attendance_policy = trim((string) get_post_meta($course_id, $key, true));
        if ($attendance_policy !== '') {
            break;
        }
    }
    if ($attendance_policy === '') {
        $attendance_policy = 'Strict';
    }

    $first_week_id = !empty($weeks) ? (int) $weeks[0]->ID : 0;
    $quiz_defaults = '—';
    if ($first_week_id && function_exists('tfp_week_get_quiz_max_attempts') && function_exists('tfp_week_get_quiz_pass_percentage')) {
        $quiz_defaults = sprintf(
            __('%1$s attempts, %2$s%% pass', 'tfp-dashboard'),
            tfp_week_get_quiz_max_attempts($first_week_id),
            tfp_week_get_quiz_pass_percentage($first_week_id)
        );
    }
    $test_defaults = '—';
    if ($first_week_id && function_exists('tfp_week_get_test_max_attempts') && function_exists('tfp_week_get_test_pass_percentage')) {
        $test_defaults = sprintf(
            __('%1$s attempts, %2$s%% pass', 'tfp-dashboard'),
            tfp_week_get_test_max_attempts($first_week_id),
            tfp_week_get_test_pass_percentage($first_week_id)
        );
    }

    return [
        'course' => $course,
        'weeks' => $weeks,
        'week_rows' => $week_rows,
        'total_weeks' => count($weeks),
        'modules_mapped' => $modules_mapped,
        'active_cohorts' => count(array_filter($cohorts, static function ($cohort) {
            return $cohort->post_status === 'publish';
        })),
        'alerts' => $alerts,
        'course_type' => $course_type !== '' ? $course_type : '—',
        'attendance_policy' => $attendance_policy,
        'quiz_defaults' => $quiz_defaults,
        'test_defaults' => $test_defaults,
        'created' => get_the_date('M j, g:i A', $course_id),
        'updated' => get_post_modified_time('M j, g:i A', false, $course_id, true),
        'status' => ucfirst($course->post_status),
    ];
}

function tfp_admin_course_detail_render() {
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    $course_id = tfp_admin_course_detail_route_id();
    $data = tfp_admin_course_detail_get_data($course_id);
    if (!$data) {
        wp_die(esc_html__('The requested course could not be found.', 'tfp-dashboard'), esc_html__('Course not found', 'tfp-dashboard'), ['response' => 404]);
    }

    $user = wp_get_current_user();
    $name = $user->display_name ?: __('Administrator', 'tfp-dashboard');
    $avatar = get_avatar_url($user->ID, ['size' => 96]);
    $nav = function_exists('tfp_admin_dashboard_nav_items') ? tfp_admin_dashboard_nav_items() : [];
    foreach ($nav as &$item) {
        unset($item['active']);
        if (($item['slug'] ?? '') === 'courses') {
            $item['url'] = function_exists('tfp_admin_courses_page_url') ? tfp_admin_courses_page_url() : home_url('/admin-courses/');
            $item['active'] = true;
        }
    }
    unset($item);
    $course_title = get_the_title($data['course']);
    if (!$course_title) {
        $course_title = (string) $data['course']->post_title;
    }
    $cohorts_url = admin_url('edit.php?post_type=tfp_cohort');
    $page_number = isset($_GET['week_page']) ? max(1, absint($_GET['week_page'])) : 1;
    $per_page = 6;
    $total_rows = count($data['week_rows']);
    $total_pages = max(1, (int) ceil($total_rows / $per_page));
    $page_number = min($page_number, $total_pages);
    $visible_rows = array_slice($data['week_rows'], ($page_number - 1) * $per_page, $per_page);
    $first_item = $total_rows ? (($page_number - 1) * $per_page) + 1 : 0;
    $last_item = min($page_number * $per_page, $total_rows);
    $tabs = ['Overview' => '', 'Plan' => 'plan', 'Rules' => 'rules', 'Cohorts' => 'cohorts', 'Insights' => 'insights', 'Access' => 'access', 'Health' => 'health'];
    ?>
    <div class="tfp-admin-app tfp-admin-course-detail-app">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top">
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg>
                </button>
            </div>
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>">
                    <img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </a>
            </div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?>
                    <a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span>
                        <span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom">
                <div class="tfp-admin-profile">
                    <img src="<?php echo esc_url($avatar); ?>" alt="">
                    <span><strong><?php echo esc_html($name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span>
                </div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                    <span aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span>
                </a>
            </div>
        </aside>

        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar">
                <button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button>
                <span><?php esc_html_e('Admin Console', 'tfp-dashboard'); ?></span>
            </div>
            <div class="tfp-admin-breadcrumb">
                <a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a>
                <b>/</b><a href="<?php echo esc_url(tfp_admin_courses_page_url()); ?>"><?php esc_html_e('Courses', 'tfp-dashboard'); ?></a>
                <b>/</b><strong class="tfp-admin-breadcrumb-current"><?php echo esc_html($course_title); ?></strong>
            </div>

            <header class="tfp-admin-page-header tfp-admin-course-detail-header">
                <div><h1><?php echo esc_html($course_title); ?></h1><p><?php esc_html_e('Course details and week mapping', 'tfp-dashboard'); ?></p></div>
                <a class="tfp-admin-course-view-cohorts" href="<?php echo esc_url($cohorts_url); ?>"><span aria-hidden="true">◉</span><?php esc_html_e('View Cohorts', 'tfp-dashboard'); ?></a>
            </header>

            <nav class="tfp-admin-course-tabs" aria-label="<?php esc_attr_e('Course sections', 'tfp-dashboard'); ?>">
                <?php foreach ($tabs as $label => $tab) : ?>
                    <a class="<?php echo $tab === '' ? 'is-active' : 'is-inactive'; ?>" href="<?php echo esc_url($tab === '' ? tfp_admin_course_detail_url($course_id) : add_query_arg('course_tab', $tab, tfp_admin_course_detail_url($course_id))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <main class="tfp-admin-content tfp-admin-course-detail-content">
                <section class="tfp-admin-course-detail-stats">
                    <article><small><?php esc_html_e('Total Weeks', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['total_weeks'])); ?></strong><p><?php esc_html_e('Number of weeks in course', 'tfp-dashboard'); ?></p></article>
                    <article><small><?php esc_html_e('Modules Mapped', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['modules_mapped'])); ?></strong><p><?php esc_html_e('Total mapped items in course weeks', 'tfp-dashboard'); ?></p></article>
                    <article><small><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['active_cohorts'])); ?></strong><p><?php esc_html_e('Live cohorts running courses', 'tfp-dashboard'); ?></p></article>
                    <article><small><?php esc_html_e('Course Alerts', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['alerts'])); ?></strong><p><?php esc_html_e('Issues needing attention in courses', 'tfp-dashboard'); ?></p></article>
                </section>

                <section class="tfp-admin-course-detail-panels">
                    <article class="tfp-admin-panel tfp-admin-course-summary">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Course Summary', 'tfp-dashboard'); ?></h2></div>
                        <dl>
                            <div><dt><?php esc_html_e('Status', 'tfp-dashboard'); ?></dt><dd><span class="tfp-admin-course-pill <?php echo $data['course']->post_status === 'publish' ? 'is-published' : 'is-attention'; ?>"><?php echo esc_html($data['status']); ?></span></dd></div>
                            <div><dt><?php esc_html_e('Course Type', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['course_type']); ?></dd></div>
                            <div><dt><?php esc_html_e('Created', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['created']); ?></dd></div>
                            <div><dt><?php esc_html_e('Last Updated', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['updated']); ?></dd></div>
                            <div><dt><?php esc_html_e('Default Attendance Policy', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['attendance_policy']); ?></dd></div>
                            <div><dt><?php esc_html_e('Quiz Defaults', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['quiz_defaults']); ?></dd></div>
                            <div><dt><?php esc_html_e('Test Defaults', 'tfp-dashboard'); ?></dt><dd><?php echo esc_html($data['test_defaults']); ?></dd></div>
                        </dl>
                    </article>

                    <article class="tfp-admin-panel tfp-admin-course-week-health">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('User System Health', 'tfp-dashboard'); ?></h2><a href="#tfp-course-weeks"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-course-week-health-list">
                            <?php foreach (array_slice($data['week_rows'], 0, 12) as $row) : ?>
                                <div class="tfp-admin-course-week-health-row">
                                    <strong><?php echo esc_html(sprintf(__('Week %s', 'tfp-dashboard'), $row['number'])); ?></strong>
                                    <span><?php echo esc_html($row['title']); ?></span>
                                    <em class="tfp-admin-course-pill <?php echo esc_attr($row['status_class']); ?>"><?php echo esc_html($row['status']); ?></em>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($data['week_rows'])) : ?><p class="tfp-admin-course-no-weeks"><?php esc_html_e('No weeks are mapped to this course yet.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div>
                    </article>
                </section>

                <section class="tfp-admin-panel tfp-admin-course-weeks" id="tfp-course-weeks">
                    <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Weeks', 'tfp-dashboard'); ?></h2></div>
                    <div class="tfp-admin-course-weeks-scroll">
                        <table>
                            <thead><tr><th><?php esc_html_e('Week', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Module', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Required Items', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Gate', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($visible_rows as $row) : ?>
                                    <tr>
                                        <td><?php echo esc_html(sprintf(__('Week %s', 'tfp-dashboard'), $row['number'])); ?></td>
                                        <td><?php echo esc_html($row['title']); ?></td>
                                        <td><div class="tfp-admin-course-required-items"><?php foreach ($row['items'] as $item) : ?><span><?php echo esc_html($item); ?></span><?php endforeach; ?><?php if (empty($row['items'])) : ?><span class="is-empty">—</span><?php endif; ?></div></td>
                                        <td><span class="tfp-admin-course-pill is-strict"><?php echo esc_html($row['gate']); ?></span></td>
                                        <td><a class="tfp-admin-course-week-view" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($visible_rows)) : ?><tr><td colspan="5" class="tfp-admin-course-no-weeks"><?php esc_html_e('No weeks are mapped to this course yet.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="tfp-admin-course-weeks-footer">
                        <span><?php echo esc_html(sprintf(__('%1$s - %2$s of %3$s weeks', 'tfp-dashboard'), number_format_i18n($first_item), number_format_i18n($last_item), number_format_i18n($total_rows))); ?></span>
                        <div class="tfp-admin-course-pagination-actions">
                            <?php if ($page_number > 1) : ?>
                                <a class="is-previous" href="<?php echo esc_url(add_query_arg('week_page', $page_number - 1, tfp_admin_course_detail_url($course_id)) . '#tfp-course-weeks'); ?>"><span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></a>
                            <?php else : ?>
                                <span class="is-previous is-disabled"><span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span>
                            <?php endif; ?>
                            <?php if ($page_number < $total_pages) : ?>
                                <a class="is-next" href="<?php echo esc_url(add_query_arg('week_page', $page_number + 1, tfp_admin_course_detail_url($course_id)) . '#tfp-course-weeks'); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span></a>
                            <?php else : ?>
                                <span class="is-next is-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <?php
}

add_filter('pre_handle_404', function ($preempt, $wp_query) {
    if (tfp_admin_course_detail_route_id()) {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }
    return $preempt;
}, 13, 2);

add_action('template_redirect', function () {
    if (!tfp_admin_course_detail_route_id()) {
        return;
    }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-course-detail.php';
    if (is_readable($file)) {
        include $file;
        exit;
    }
}, 2);
