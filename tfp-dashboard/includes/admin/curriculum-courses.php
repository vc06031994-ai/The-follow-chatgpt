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
    if (!is_user_logged_in()) { auth_redirect(); }
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
        if (($item['slug'] ?? '') === 'curriculum') {
            $item['url'] = tfp_admin_categories_page_url();
            $item['active'] = true;
        }
    }
    unset($item);
    $add_course_url = admin_url('post-new.php?post_type=sfwd-courses');
    ?>
    <div class="tfp-admin-app tfp-admin-courses-app">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top"><button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle><svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg></button></div>
            <div class="tfp-admin-brand-row"><a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>"><img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"><img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"></a></div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?><a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>"><span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo tfp_admin_dashboard_nav_icon($item['slug']); ?></span><span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span></a><?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom"><div class="tfp-admin-profile"><img src="<?php echo esc_url($avatar); ?>" alt=""><span><strong><?php echo esc_html($name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span></div><a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span aria-hidden="true">×</span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a></div>
        </aside>
        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar"><button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button><span><?php esc_html_e('Admin Console', 'tfp-dashboard'); ?></span></div>
            <div class="tfp-admin-breadcrumb"><span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><span><?php esc_html_e('Curriculum', 'tfp-dashboard'); ?></span><b>/</b><strong><?php esc_html_e('Courses', 'tfp-dashboard'); ?></strong></div>
            <header class="tfp-admin-page-header tfp-admin-courses-header"><div><h1><?php esc_html_e('Curriculum', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Create, edit, and publish course tracks.', 'tfp-dashboard'); ?></p></div><a class="tfp-admin-courses-add" href="<?php echo esc_url($add_course_url); ?>"><span aria-hidden="true">＋</span><?php esc_html_e('Add Courses', 'tfp-dashboard'); ?></a></header>
            <main class="tfp-admin-content tfp-admin-courses-content">
                <nav class="tfp-admin-curriculum-tabs" aria-label="<?php esc_attr_e('Curriculum sections', 'tfp-dashboard'); ?>"><a href="<?php echo esc_url(tfp_admin_categories_page_url()); ?>"><?php esc_html_e('Categories', 'tfp-dashboard'); ?></a><a class="is-active" href="<?php echo esc_url(tfp_admin_curriculum_courses_page_url()); ?>"><?php esc_html_e('Courses', 'tfp-dashboard'); ?></a><a href="<?php echo esc_url(admin_url('edit.php?post_type=sfwd-lessons')); ?>"><?php esc_html_e('Modules', 'tfp-dashboard'); ?></a></nav>
                <section class="tfp-admin-courses-stat-grid tfp-admin-courses-curriculum-stats" aria-label="<?php esc_attr_e('Course summary', 'tfp-dashboard'); ?>">
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Total Course', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['total'])); ?></strong><p><?php esc_html_e('Across all Categories', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Active Course', 'tfp-dashboard'); ?></small><strong class="is-active-count"><?php echo esc_html(number_format_i18n($data['published'])); ?></strong><p><?php esc_html_e('Total Active Courses', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Drafts/Inactive', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['draft'])); ?></strong><p><?php esc_html_e('Total Inactive Courses', 'tfp-dashboard'); ?></p></article>
                </section>
                <section class="tfp-admin-courses-list-panel" aria-label="<?php esc_attr_e('Courses list', 'tfp-dashboard'); ?>">
                    <form class="tfp-admin-courses-curriculum-filters" method="get" action="<?php echo esc_url(tfp_admin_curriculum_courses_page_url()); ?>">
                        <label class="tfp-admin-courses-search"><img class="tfp-admin-courses-search-icon" src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png" alt="" aria-hidden="true"><input type="search" name="course_search" value="<?php echo esc_attr($data['search']); ?>" placeholder="<?php esc_attr_e('Search Courses', 'tfp-dashboard'); ?>"></label>
                        <select name="course_category" aria-label="<?php esc_attr_e('Filter by category', 'tfp-dashboard'); ?>"><option value=""><?php esc_html_e('All Categories', 'tfp-dashboard'); ?></option>
                            <?php $course_categories = taxonomy_exists('ld_course_category') ? get_terms(['taxonomy' => 'ld_course_category', 'hide_empty' => false]) : []; $selected_category = isset($_GET['course_category']) ? absint($_GET['course_category']) : 0; if (!is_wp_error($course_categories)) : foreach ($course_categories as $term) : ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($selected_category, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                        <select name="course_view" aria-label="<?php esc_attr_e('Filter by status', 'tfp-dashboard'); ?>"><option value="all" <?php selected($data['status_filter'], 'all'); ?>><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option><option value="published" <?php selected($data['status_filter'], 'published'); ?>><?php esc_html_e('Active', 'tfp-dashboard'); ?></option><option value="draft" <?php selected($data['status_filter'], 'draft'); ?>><?php esc_html_e('Draft / Inactive', 'tfp-dashboard'); ?></option></select>
                    </form>
                    <div class="tfp-admin-courses-curriculum-list">
                        <?php foreach ($data['rows'] as $row) : ?><article class="tfp-admin-courses-curriculum-row"><div class="tfp-admin-courses-curriculum-info"><strong><?php echo esc_html($row['title']); ?></strong><small><?php echo esc_html($row['category'] ?? __('Uncategorized', 'tfp-dashboard')); ?> · <?php echo esc_html(number_format_i18n($row['steps'])); ?> <?php esc_html_e('Modules', 'tfp-dashboard'); ?></small></div><div class="tfp-admin-courses-curriculum-actions"><span class="tfp-admin-courses-curriculum-status <?php echo $row['status'] === 'publish' ? 'is-active' : 'is-draft'; ?>"><?php echo $row['status'] === 'publish' ? esc_html__('Active', 'tfp-dashboard') : esc_html__('Draft', 'tfp-dashboard'); ?></span><a href="<?php echo esc_url($row['url']); ?>"><?php esc_html_e('Edit', 'tfp-dashboard'); ?></a></div></article><?php endforeach; ?>
                        <?php if (empty($data['rows'])) : ?><div class="tfp-admin-courses-curriculum-empty"><?php esc_html_e('No courses found.', 'tfp-dashboard'); ?></div><?php endif; ?>
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
