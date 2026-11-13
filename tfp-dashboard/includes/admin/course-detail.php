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
    // Use concise display headings for the two named foundation courses.
    $course_heading_title = $course_title;
    $course_id = (int) $data['course']->ID;
    if (1039 === $course_id) {
        $course_heading_title = __('12 Week Foundation', 'tfp-dashboard');
    } elseif (1659 === $course_id) {
        $course_heading_title = __('58 Week Discipleship', 'tfp-dashboard');
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

    // The Plan tab has its own 15-row pagination, independent of the Overview tab.
    $plan_per_page = 15;
    $plan_page_number = isset($_GET['plan_page']) ? max(1, absint($_GET['plan_page'])) : 1;
    $plan_total_pages = max(1, (int) ceil($total_rows / $plan_per_page));
    $plan_page_number = min($plan_page_number, $plan_total_pages);
    $plan_visible_rows = array_slice($data['week_rows'], ($plan_page_number - 1) * $plan_per_page, $plan_per_page);
    $plan_first_item = $total_rows ? (($plan_page_number - 1) * $plan_per_page) + 1 : 0;
    $plan_last_item = min($plan_page_number * $plan_per_page, $total_rows);
    $tabs = ['Overview' => '', 'Plan' => 'plan', 'Rules' => 'rules', 'Cohorts' => 'cohorts', 'Insights' => 'insights', 'Access' => 'access', 'Health' => 'health'];
    $active_tab = isset($_GET['course_tab']) ? sanitize_key(wp_unslash($_GET['course_tab'])) : '';
    $is_plan_tab = ($active_tab === 'plan');
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
                <b>/</b><strong class="tfp-admin-breadcrumb-current"><?php echo esc_html($course_heading_title); ?></strong>
                <?php if ($is_plan_tab) : ?><b>/</b><strong class="tfp-admin-breadcrumb-current"><?php esc_html_e('Plan', 'tfp-dashboard'); ?></strong><?php endif; ?>
            </div>

            <header class="tfp-admin-page-header tfp-admin-course-detail-header">
                <div><h1><?php echo esc_html($course_heading_title); ?></h1><p><?php echo $is_plan_tab ? esc_html__('Course Plan & Week Mapping', 'tfp-dashboard') : esc_html__('Course details and week mapping', 'tfp-dashboard'); ?></p></div>
                <div class="tfp-admin-course-header-actions">
                    <?php if ($is_plan_tab) : ?>
                        <a class="tfp-admin-course-edit" href="<?php echo esc_url(get_edit_post_link($course_id, 'raw')); ?>"><span aria-hidden="true">✎</span><?php esc_html_e('Edit Course', 'tfp-dashboard'); ?></a>
                    <?php endif; ?>
                    <a class="tfp-admin-course-view-cohorts" href="<?php echo esc_url($cohorts_url); ?>"><span aria-hidden="true">◉</span><?php esc_html_e('View Cohorts', 'tfp-dashboard'); ?></a>
                </div>
            </header>

            <nav class="tfp-admin-course-tabs" aria-label="<?php esc_attr_e('Course sections', 'tfp-dashboard'); ?>">
                <?php foreach ($tabs as $label => $tab) : ?>
                    <a class="<?php echo $active_tab === $tab ? 'is-active' : 'is-inactive'; ?>" href="<?php echo esc_url($tab === '' ? tfp_admin_course_detail_url($course_id) : add_query_arg('course_tab', $tab, tfp_admin_course_detail_url($course_id))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <main class="tfp-admin-content tfp-admin-course-detail-content">
                <?php if ($is_plan_tab) : ?>
                    <section class="tfp-admin-panel tfp-admin-course-plan" id="tfp-course-plan">
                        <div class="tfp-admin-course-plan-toolbar">
                            <h2><?php esc_html_e('Course Plan', 'tfp-dashboard'); ?></h2>
                            <div class="tfp-admin-course-plan-actions">
                                <a href="<?php echo esc_url(get_edit_post_link($course_id, 'raw')); ?>" class="tfp-admin-course-plan-action"><span aria-hidden="true">⇧</span><?php esc_html_e('Import', 'tfp-dashboard'); ?></a>
                                <a href="<?php echo esc_url(add_query_arg(['action' => 'tfp_course_plan_export', 'course_id' => $course_id, '_wpnonce' => wp_create_nonce('tfp_course_plan_export_' . $course_id)], admin_url('admin-post.php'))); ?>" class="tfp-admin-course-plan-action"><span aria-hidden="true">⇩</span><?php esc_html_e('Export', 'tfp-dashboard'); ?></a>
                                <a href="#tfp-course-plan-sync-log" class="tfp-admin-course-plan-sync"><span aria-hidden="true">···</span><?php esc_html_e('Sync Log', 'tfp-dashboard'); ?></a>
                            </div>
                        </div>
                        <div class="tfp-admin-course-plan-scroll">
                            <table>
                                <thead><tr><th><?php esc_html_e('Week', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Module Name', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Gate Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Exercise ID', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Content Summary', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Attention', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($plan_visible_rows as $row) :
                                    $lesson_id = (int) $row['id'];
                                    $video_count = get_post_meta($lesson_id, 'tfp_week_video_url', true) ? 1 : 0;
                                    $lesson_post = get_post($lesson_id);
                                    $reading_count = ($lesson_post && (trim(wp_strip_all_tags($lesson_post->post_content)) !== '' || trim((string) $lesson_post->post_excerpt) !== '')) ? 1 : 0;
                                    $quiz_questions = function_exists('tfp_week_get_quiz_questions') ? (array) tfp_week_get_quiz_questions($lesson_id, false) : [];
                                    $test_questions = function_exists('tfp_week_get_test_questions') ? (array) tfp_week_get_test_questions($lesson_id, false) : [];
                                    $question_count = count($quiz_questions) + count($test_questions);
                                    $exercise_id = '';
                                    foreach (['tfp_week_exercise_id', '_tfp_week_exercise_id', 'exercise_id'] as $exercise_key) {
                                        $exercise_id = trim((string) get_post_meta($lesson_id, $exercise_key, true));
                                        if ($exercise_id !== '') break;
                                    }
                                    $needs_attention = ($row['status'] !== 'Published' || empty($row['items']));
                                    $summary_parts = [];
                                    if ($video_count) $summary_parts[] = sprintf(_n('%s Video', '%s Videos', $video_count, 'tfp-dashboard'), number_format_i18n($video_count));
                                    if ($reading_count) $summary_parts[] = sprintf(_n('%s Reading', '%s Readings', $reading_count, 'tfp-dashboard'), number_format_i18n($reading_count));
                                    if ($question_count) $summary_parts[] = sprintf(_n('%s Question', '%s Questions', $question_count, 'tfp-dashboard'), number_format_i18n($question_count));
                                    if (empty($summary_parts)) $summary_parts[] = __('No mapped content', 'tfp-dashboard');
                                ?>
                                    <tr>
                                        <td><?php echo esc_html(sprintf(__('Week %s', 'tfp-dashboard'), $row['number'])); ?></td>
                                        <td><?php echo esc_html($row['title']); ?></td>
                                        <td><span class="tfp-admin-course-pill is-strict"><?php esc_html_e('Strict', 'tfp-dashboard'); ?></span></td>
                                        <td><?php echo esc_html($exercise_id !== '' ? $exercise_id : '—'); ?></td>
                                        <td><?php echo esc_html(implode(' | ', $summary_parts)); ?></td>
                                        <td><span class="tfp-admin-course-plan-attention <?php echo $needs_attention ? 'is-attention' : 'is-good'; ?>"><?php echo $needs_attention ? esc_html__('Attention', 'tfp-dashboard') : esc_html__('Good', 'tfp-dashboard'); ?></span></td>
                                        <td><a class="tfp-admin-course-week-view" href="<?php echo esc_url($row['edit_url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($data['week_rows'])) : ?><tr><td colspan="7" class="tfp-admin-course-no-weeks"><?php esc_html_e('No weeks are mapped to this course yet.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="tfp-admin-course-plan-footer">
                            <span><?php echo esc_html(sprintf(__('%1$s - %2$s of %3$s items', 'tfp-dashboard'), number_format_i18n($plan_first_item), number_format_i18n($plan_last_item), number_format_i18n($total_rows))); ?></span>
                            <div class="tfp-admin-course-pagination-actions">
                                <?php if ($plan_page_number > 1) : ?>
                                    <a class="is-previous" href="<?php echo esc_url(add_query_arg(['course_tab' => 'plan', 'plan_page' => $plan_page_number - 1], tfp_admin_course_detail_url($course_id)) . '#tfp-course-plan'); ?>"><span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></a>
                                <?php else : ?>
                                    <span class="is-previous is-disabled"><span aria-hidden="true">‹</span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span>
                                <?php endif; ?>
                                <?php if ($plan_page_number < $plan_total_pages) : ?>
                                    <a class="is-next" href="<?php echo esc_url(add_query_arg(['course_tab' => 'plan', 'plan_page' => $plan_page_number + 1], tfp_admin_course_detail_url($course_id)) . '#tfp-course-plan'); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span></a>
                                <?php else : ?>
                                    <span class="is-next is-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?><span aria-hidden="true">›</span></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="tfp-admin-course-plan-sync-note" id="tfp-course-plan-sync-log"><?php esc_html_e('Sync log is not available yet.', 'tfp-dashboard'); ?></div>
                    </section>
                <?php else : ?>
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
                <?php endif; ?>
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


/**
 * Export the mapped course plan as CSV.
 */
function tfp_admin_course_plan_export() {
    if (!is_user_logged_in() || (!current_user_can('manage_options') && !current_user_can('administrator'))) {
        wp_die(esc_html__('You do not have permission to export this course plan.', 'tfp-dashboard'), '', ['response' => 403]);
    }
    $course_id = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    check_admin_referer('tfp_course_plan_export_' . $course_id);
    $data = tfp_admin_course_detail_get_data($course_id);
    if (!$data) {
        wp_die(esc_html__('The requested course could not be found.', 'tfp-dashboard'), '', ['response' => 404]);
    }
    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="course-plan-' . $course_id . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Week', 'Module Name', 'Gate Status', 'Exercise ID', 'Content Summary', 'Attention']);
    foreach ($data['week_rows'] as $row) {
        $lesson_id = (int) $row['id'];
        $video_count = get_post_meta($lesson_id, 'tfp_week_video_url', true) ? 1 : 0;
        $lesson_post = get_post($lesson_id);
        $reading_count = ($lesson_post && (trim(wp_strip_all_tags($lesson_post->post_content)) !== '' || trim((string) $lesson_post->post_excerpt) !== '')) ? 1 : 0;
        $quiz_questions = function_exists('tfp_week_get_quiz_questions') ? (array) tfp_week_get_quiz_questions($lesson_id, false) : [];
        $test_questions = function_exists('tfp_week_get_test_questions') ? (array) tfp_week_get_test_questions($lesson_id, false) : [];
        $question_count = count($quiz_questions) + count($test_questions);
        $exercise_id = '';
        foreach (['tfp_week_exercise_id', '_tfp_week_exercise_id', 'exercise_id'] as $exercise_key) {
            $exercise_id = trim((string) get_post_meta($lesson_id, $exercise_key, true));
            if ($exercise_id !== '') break;
        }
        $summary = [];
        if ($video_count) $summary[] = $video_count . ' Video';
        if ($reading_count) $summary[] = $reading_count . ' Reading';
        if ($question_count) $summary[] = $question_count . ' Question';
        if (!$summary) $summary[] = 'No mapped content';
        $attention = ($row['status'] !== 'Published' || empty($row['items'])) ? 'Attention' : 'Good';
        fputcsv($output, ['Week ' . $row['number'], $row['title'], 'Strict', $exercise_id, implode(' | ', $summary), $attention]);
    }
    fclose($output);
    exit;
}
add_action('admin_post_tfp_course_plan_export', 'tfp_admin_course_plan_export');
