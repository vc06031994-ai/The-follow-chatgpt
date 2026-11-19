<?php
/**
 * TFP Admin Dashboard — Curriculum > Courses page.
 * Separate route and renderer; shares the existing LearnDash course data helpers.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** URL for the Courses tab inside Curriculum. */
function tfp_admin_curriculum_courses_page_url() {
    return home_url('/admin-curriculum-courses/');
}

function tfp_admin_curriculum_courses_render() {
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    $data = tfp_admin_courses_get_data();
    $user = wp_get_current_user();
    $name = $user->display_name ?: __('Administrator', 'tfp-dashboard');
    $avatar = get_avatar_url($user->ID, ['size' => 96]);
    $nav = function_exists('tfp_admin_dashboard_nav_items') ? tfp_admin_dashboard_nav_items() : [];
    foreach ($nav as &$item) {
        unset($item['active']);
        if (($item['slug'] ?? '') === 'courses') {
            $item['url'] = tfp_admin_curriculum_courses_page_url();
            $item['active'] = true;
        }
    }
    unset($item);
    $add_course_url = admin_url('post-new.php?post_type=sfwd-courses');
    ?>
    <div class="tfp-admin-app tfp-admin-courses-app">
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
                <span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><strong><?php esc_html_e('Courses', 'tfp-dashboard'); ?></strong>
            </div>

            <header class="tfp-admin-page-header tfp-admin-courses-header">
                <div>
                    <h1><?php esc_html_e('Courses', 'tfp-dashboard'); ?></h1>
                    <p><?php esc_html_e('Manage course templates, map modules to weeks, and monitor curriculum health.', 'tfp-dashboard'); ?></p>
                </div>
                <a class="tfp-admin-courses-add" href="<?php echo esc_url($add_course_url); ?>"><span aria-hidden="true">＋</span><?php esc_html_e('Add Course', 'tfp-dashboard'); ?></a>
            </header>

            <main class="tfp-admin-content tfp-admin-courses-content">

                <form class="tfp-admin-courses-filters" method="get" action="<?php echo esc_url(tfp_admin_curriculum_courses_page_url()); ?>">
                    <label class="screen-reader-text" for="tfp-course-select"><?php esc_html_e('Select course', 'tfp-dashboard'); ?></label>
                    <select id="tfp-course-select" name="course_id">
                        <option value="0"><?php esc_html_e('Select Course', 'tfp-dashboard'); ?></option>
                        <?php foreach ($data['courses'] as $course) : ?>
                            <option value="<?php echo esc_attr($course->ID); ?>" <?php selected($data['selected_course'], $course->ID); ?>><?php echo esc_html(get_the_title($course)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="screen-reader-text" for="tfp-course-view"><?php esc_html_e('Select view', 'tfp-dashboard'); ?></label>
                    <select id="tfp-course-view" name="course_view">
                        <option value="all" <?php selected($data['status_filter'], 'all'); ?>><?php esc_html_e('Select View', 'tfp-dashboard'); ?></option>
                        <option value="published" <?php selected($data['status_filter'], 'published'); ?>><?php esc_html_e('Published', 'tfp-dashboard'); ?></option>
                        <option value="draft" <?php selected($data['status_filter'], 'draft'); ?>><?php esc_html_e('Draft / Paused', 'tfp-dashboard'); ?></option>
                    </select>
                    <label class="screen-reader-text" for="tfp-course-week"><?php esc_html_e('Select week', 'tfp-dashboard'); ?></label>
                    <select id="tfp-course-week" name="course_week">
                        <option value=""><?php esc_html_e('Select Week', 'tfp-dashboard'); ?></option>
                        <?php foreach ($data['weeks'] as $week) : ?>
                            <option value="<?php echo esc_attr($week); ?>" <?php selected($data['week_filter'], $week); ?>><?php echo esc_html(sprintf(__('Week %s', 'tfp-dashboard'), $week)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="tfp-admin-courses-search">
                        <img class="tfp-admin-courses-search-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png" alt="" aria-hidden="true" width="16" height="16">
                        <input type="search" name="course_search" value="<?php echo esc_attr($data['search']); ?>" placeholder="<?php esc_attr_e('Search Courses', 'tfp-dashboard'); ?>">
                    </label>
                </form>

                <section class="tfp-admin-courses-stat-grid" aria-label="<?php esc_attr_e('Course metrics', 'tfp-dashboard'); ?>">
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Total Courses', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['total'])); ?></strong><p><?php esc_html_e('Templates in the system', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Published', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['published'])); ?></strong><p><?php esc_html_e('Available to learners', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Draft / Paused', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['draft'])); ?></strong><p><?php esc_html_e('Not currently active', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Course Alerts', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['alerts'])); ?></strong><p><?php esc_html_e('Needs Attention', 'tfp-dashboard'); ?></p></article>
                </section>

                <section class="tfp-admin-courses-panels">
                    <article class="tfp-admin-panel tfp-admin-courses-activity">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Recent Course Activity', 'tfp-dashboard'); ?></h2><a href="<?php echo esc_url(admin_url('edit.php?post_type=sfwd-courses')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-courses-activity-list">
                            <?php foreach ($data['activity'] as $event) : ?>
                                <a class="tfp-admin-courses-activity-row" href="<?php echo esc_url($event['url']); ?>">
                                    <span><strong><?php echo esc_html($event['title']); ?></strong><small><?php echo esc_html($event['event']); ?></small></span>
                                    <time><?php echo esc_html($event['date']); ?></time>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($data['activity'])) : ?><p class="tfp-admin-empty-state"><?php esc_html_e('No course activity found.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div>
                    </article>

                    <article class="tfp-admin-panel tfp-admin-courses-health">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Course Health', 'tfp-dashboard'); ?></h2><a href="<?php echo esc_url(admin_url('edit.php?post_type=sfwd-courses')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-courses-health-list">
                            <div class="tfp-admin-courses-health-row"><img class="tfp-admin-courses-health-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Camera.png" alt="" aria-hidden="true" width="30" height="30"><div><strong><?php esc_html_e('Content Health', 'tfp-dashboard'); ?></strong><small><?php echo esc_html(sprintf(_n('%s course without mapped content', '%s courses without mapped content', $data['alerts'], 'tfp-dashboard'), number_format_i18n($data['alerts']))); ?></small></div><em class="<?php echo esc_attr($data['content_class']); ?>"><?php echo esc_html($data['content_status']); ?></em><b>›</b></div>
                            <div class="tfp-admin-courses-health-row"><img class="tfp-admin-courses-health-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/File_Check.png" alt="" aria-hidden="true" width="30" height="30"><div><strong><?php esc_html_e('Assessment Health', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Assessment mapping is not yet reported in this overview', 'tfp-dashboard'); ?></small></div><em class="<?php echo esc_attr($data['assessment_class']); ?>"><?php echo esc_html($data['assessment_status']); ?></em><b>›</b></div>
                            <div class="tfp-admin-courses-health-row"><img class="tfp-admin-courses-health-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Settings.png" alt="" aria-hidden="true" width="30" height="30"><div><strong><?php esc_html_e('Gating & Rules', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Course access follows published and draft states', 'tfp-dashboard'); ?></small></div><em class="is-good"><?php esc_html_e('Good', 'tfp-dashboard'); ?></em><b>›</b></div>
                            <div class="tfp-admin-courses-health-row"><img class="tfp-admin-courses-health-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Link.png" alt="" aria-hidden="true" width="30" height="30"><div><strong><?php esc_html_e('Cohort Sync', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('No verified course-to-cohort sync source', 'tfp-dashboard'); ?></small></div><em class="is-unknown"><?php esc_html_e('Unknown', 'tfp-dashboard'); ?></em><b>›</b></div>
                            <div class="tfp-admin-courses-health-row"><img class="tfp-admin-courses-health-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/File_Add.png" alt="" aria-hidden="true" width="30" height="30"><div><strong><?php esc_html_e('Content Updates', 'tfp-dashboard'); ?></strong><small><?php echo esc_html(sprintf(_n('%s update in the last 7 days', '%s updates in the last 7 days', $data['updates_7d'], 'tfp-dashboard'), number_format_i18n($data['updates_7d']))); ?></small></div><em class="is-good"><?php esc_html_e('Good', 'tfp-dashboard'); ?></em><b>›</b></div>
                            <div class="tfp-admin-courses-health-row"><img class="tfp-admin-courses-health-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Edit.png" alt="" aria-hidden="true" width="30" height="30"><div><strong><?php esc_html_e('Metadata & Tags', 'tfp-dashboard'); ?></strong><small><?php esc_html_e('Review course descriptions and featured images in the course editor', 'tfp-dashboard'); ?></small></div><em class="is-unknown"><?php esc_html_e('Unknown', 'tfp-dashboard'); ?></em><b>›</b></div>
                        </div>
                    </article>
                </section>

                <section class="tfp-admin-panel tfp-admin-courses-overview">
                    <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Course Overview', 'tfp-dashboard'); ?></h2></div>
                    <div class="tfp-admin-courses-table-wrap">
                        <table class="tfp-admin-courses-table">
                            <thead><tr><th><?php esc_html_e('Course', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Weeks', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Modules Mapped', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Completion Rate', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Last Activity', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($data['rows'] as $row) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($row['title']); ?></strong></td>
                                        <td><span class="tfp-admin-courses-status is-<?php echo esc_attr($row['status']); ?>"><?php echo esc_html(ucfirst($row['status'])); ?></span></td>
                                        <td><?php echo esc_html($row['weeks']); ?></td>
                                        <td><?php echo esc_html(number_format_i18n($row['steps'])); ?></td>
                                        <td><?php echo esc_html($row['completion']); ?></td>
                                        <td><?php echo esc_html($row['date']); ?></td>
                                        <td><a class="tfp-admin-courses-view" href="<?php echo esc_url($row['url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($data['rows'])) : ?><tr><td colspan="7" class="tfp-admin-courses-empty"><?php esc_html_e('No courses match the selected filters.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <?php
}

/** Make /admin-courses/ a virtual dashboard route, consistent with the Users page. */
add_filter('pre_handle_404', function ($preempt, $wp_query) {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) === 'admin-curriculum-courses') {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }
    return $preempt;
}, 12, 2);

add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'admin-curriculum-courses') {
        return;
    }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-curriculum-courses.php';
    if (is_readable($file)) {
        include $file;
        exit;
    }
}, 1);
