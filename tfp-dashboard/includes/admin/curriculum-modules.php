<?php
/**
 * TFP Admin Dashboard — Curriculum > Modules.
 * Lists real LearnDash lesson records with category/course/status filters.
 */
if (!defined('ABSPATH')) { exit; }

function tfp_admin_curriculum_modules_page_url($args = []) {
    $url = home_url('/admin-curriculum-modules/');
    return $args ? add_query_arg($args, $url) : $url;
}

/** Resolve the LearnDash course assigned to a lesson. */
function tfp_admin_curriculum_module_course_id($lesson_id) {
    $course_id = function_exists('learndash_get_course_id') ? (int) learndash_get_course_id($lesson_id) : 0;
    if (!$course_id) {
        $course_id = (int) get_post_meta($lesson_id, 'course_id', true);
    }
    return $course_id;
}

/** Collect filtered lesson records and accurate summary counts. */
function tfp_admin_curriculum_modules_get_data() {
    $search = isset($_GET['module_search']) ? sanitize_text_field(wp_unslash($_GET['module_search'])) : '';
    $category_id = isset($_GET['module_category']) ? absint($_GET['module_category']) : 0;
    $course_id = isset($_GET['module_course']) ? absint($_GET['module_course']) : 0;
    $status = isset($_GET['module_status']) ? sanitize_key(wp_unslash($_GET['module_status'])) : 'all';

    $statuses = ['publish', 'draft', 'pending', 'private'];
    $all = post_type_exists('sfwd-lessons') ? get_posts([
        'post_type' => 'sfwd-lessons',
        'post_status' => $statuses,
        'posts_per_page' => -1,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
    ]) : [];

    $courses = post_type_exists('sfwd-courses') ? get_posts([
        'post_type' => 'sfwd-courses',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]) : [];
    $course_map = [];
    foreach ($courses as $course) { $course_map[$course->ID] = $course; }

    $category_terms = taxonomy_exists('ld_course_category') ? get_terms([
        'taxonomy' => 'ld_course_category', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC',
    ]) : [];
    if (is_wp_error($category_terms)) { $category_terms = []; }

    $rows = [];
    $issues = 0;
    $incomplete = 0;
    $ready = 0;
    foreach ($all as $lesson) {
        $assigned_course_id = tfp_admin_curriculum_module_course_id($lesson->ID);
        $assigned_course = $course_map[$assigned_course_id] ?? null;
        $has_content = trim((string) $lesson->post_content) !== '';
        $has_course = (bool) $assigned_course;
        if (!$has_course) { $issues++; }
        if (!$has_content) { $incomplete++; }
        if ($lesson->post_status === 'publish' && $has_course && $has_content) { $ready++; }

        $lesson_categories = $assigned_course ? wp_get_object_terms($assigned_course_id, 'ld_course_category', ['fields' => 'ids']) : [];
        if (is_wp_error($lesson_categories)) { $lesson_categories = []; }
        if ($search !== '' && stripos(get_the_title($lesson), $search) === false) { continue; }
        if ($course_id && $assigned_course_id !== $course_id) { continue; }
        if ($category_id && !in_array($category_id, array_map('absint', $lesson_categories), true)) { continue; }
        if ($status === 'active' && $lesson->post_status !== 'publish') { continue; }
        if ($status === 'draft' && !in_array($lesson->post_status, ['draft', 'pending', 'private'], true)) { continue; }

        $course_terms = $assigned_course ? get_the_terms($assigned_course_id, 'ld_course_category') : [];
        $category_name = (!is_wp_error($course_terms) && !empty($course_terms)) ? $course_terms[0]->name : __('Uncategorized', 'tfp-dashboard');
        $rows[] = [
            'id' => $lesson->ID,
            'course_id' => $assigned_course_id,
            'title' => get_the_title($lesson) ?: __('Untitled Module', 'tfp-dashboard'),
            'course' => $assigned_course ? get_the_title($assigned_course) : __('Course not assigned', 'tfp-dashboard'),
            'category' => $category_name,
            'status' => $lesson->post_status,
            'edit_url' => get_edit_post_link($lesson->ID, 'raw') ?: admin_url('post.php?post=' . $lesson->ID . '&action=edit'),
        ];
    }

    return [
        'total' => count($all),
        'issues' => $issues,
        'incomplete' => $incomplete,
        'ready' => $ready,
        'rows' => $rows,
        'courses' => $courses,
        'categories' => $category_terms,
        'search' => $search,
        'category_id' => $category_id,
        'course_id' => $course_id,
        'status' => $status,
    ];
}

function tfp_admin_curriculum_modules_render() {
    if (!is_user_logged_in()) { auth_redirect(); }
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }
    $data = tfp_admin_curriculum_modules_get_data();
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
    ?>
    <div class="tfp-admin-app tfp-admin-modules-app">
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
            <div class="tfp-admin-breadcrumb"><span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><span><?php esc_html_e('Curriculum', 'tfp-dashboard'); ?></span><b>/</b><strong><?php esc_html_e('Modules', 'tfp-dashboard'); ?></strong></div>
            <header class="tfp-admin-page-header tfp-admin-modules-header"><div><h1><?php esc_html_e('Curriculum', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Create, edit, and publish course tracks.', 'tfp-dashboard'); ?></p></div><a class="tfp-admin-modules-add" href="<?php echo esc_url(admin_url('post-new.php?post_type=sfwd-lessons')); ?>"><span aria-hidden="true">＋</span><?php esc_html_e('Add Courses', 'tfp-dashboard'); ?></a></header>
            <main class="tfp-admin-content tfp-admin-modules-content">
                <nav class="tfp-admin-curriculum-tabs" aria-label="<?php esc_attr_e('Curriculum sections', 'tfp-dashboard'); ?>"><a href="<?php echo esc_url(tfp_admin_categories_page_url()); ?>"><?php esc_html_e('Categories', 'tfp-dashboard'); ?></a><a href="<?php echo esc_url(tfp_admin_curriculum_courses_page_url()); ?>"><?php esc_html_e('Courses', 'tfp-dashboard'); ?></a><a class="is-active" href="<?php echo esc_url(tfp_admin_curriculum_modules_page_url()); ?>"><?php esc_html_e('Modules', 'tfp-dashboard'); ?></a></nav>
                <section class="tfp-admin-modules-stats" aria-label="<?php esc_attr_e('Module summary', 'tfp-dashboard'); ?>">
                    <article class="tfp-admin-modules-stat"><small><?php esc_html_e('Total Modules', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['total'])); ?></strong><p><?php esc_html_e('Across all courses', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-modules-stat"><small><?php esc_html_e('Modules with Issues', 'tfp-dashboard'); ?></small><strong class="is-alert"><?php echo esc_html(number_format_i18n($data['issues'])); ?></strong><p><?php esc_html_e('Missing course assignment', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-modules-stat"><small><?php esc_html_e('Incomplete', 'tfp-dashboard'); ?></small><strong class="is-alert"><?php echo esc_html(number_format_i18n($data['incomplete'])); ?></strong><p><?php esc_html_e('Missing module content', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-modules-stat"><small><?php esc_html_e('Ready to Send', 'tfp-dashboard'); ?></small><strong class="is-ready"><?php echo esc_html(number_format_i18n($data['ready'])); ?></strong><p><?php esc_html_e('Complete · no flags', 'tfp-dashboard'); ?></p></article>
                </section>
                <section class="tfp-admin-modules-panel" aria-label="<?php esc_attr_e('Modules list', 'tfp-dashboard'); ?>">
                    <form class="tfp-admin-modules-filters" method="get" action="<?php echo esc_url(tfp_admin_curriculum_modules_page_url()); ?>">
                        <label class="tfp-admin-modules-search"><img src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png" alt="" aria-hidden="true"><input type="search" name="module_search" value="<?php echo esc_attr($data['search']); ?>" placeholder="<?php esc_attr_e('Search Modules', 'tfp-dashboard'); ?>"></label>
                        <select name="module_category" aria-label="<?php esc_attr_e('Filter by category', 'tfp-dashboard'); ?>"><option value=""><?php esc_html_e('All Categories', 'tfp-dashboard'); ?></option><?php foreach ($data['categories'] as $term) : ?><option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($data['category_id'], $term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select>
                        <select name="module_course" aria-label="<?php esc_attr_e('Filter by course', 'tfp-dashboard'); ?>"><option value=""><?php esc_html_e('All Courses', 'tfp-dashboard'); ?></option><?php foreach ($data['courses'] as $course) : ?><option value="<?php echo esc_attr($course->ID); ?>" <?php selected($data['course_id'], $course->ID); ?>><?php echo esc_html(get_the_title($course)); ?></option><?php endforeach; ?></select>
                        <select name="module_status" aria-label="<?php esc_attr_e('Filter by status', 'tfp-dashboard'); ?>"><option value="all" <?php selected($data['status'], 'all'); ?>><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option><option value="active" <?php selected($data['status'], 'active'); ?>><?php esc_html_e('Active', 'tfp-dashboard'); ?></option><option value="draft" <?php selected($data['status'], 'draft'); ?>><?php esc_html_e('Draft / Inactive', 'tfp-dashboard'); ?></option></select>
                    </form>
                    <div class="tfp-admin-modules-list">
                        <?php foreach ($data['rows'] as $row) : ?><article class="tfp-admin-modules-row"><div class="tfp-admin-modules-info"><strong><?php echo esc_html($row['title']); ?></strong><small><?php echo esc_html($row['course']); ?><?php if ($row['category'] !== 'Uncategorized') : ?> · <?php echo esc_html($row['category']); ?><?php endif; ?></small></div><div class="tfp-admin-modules-actions"><span class="tfp-admin-modules-status <?php echo $row['status'] === 'publish' ? 'is-active' : 'is-draft'; ?>"><?php echo $row['status'] === 'publish' ? esc_html__('Active', 'tfp-dashboard') : esc_html__('Draft', 'tfp-dashboard'); ?></span><button type="button" class="tfp-admin-module-preview-trigger" data-module-title="<?php echo esc_attr($row['title']); ?>" data-module-course="<?php echo esc_attr($row['course']); ?>" data-module-category="<?php echo esc_attr($row['category']); ?>" data-module-status="<?php echo esc_attr($row['status'] === 'publish' ? 'Active' : 'Draft'); ?>" data-module-flags="<?php echo esc_attr((strpos($row['course'], 'not assigned') !== false ? 1 : 0) + (trim((string) get_post_field('post_content', $row['id'])) === '' ? 1 : 0)); ?>" data-module-editor="<?php echo esc_url(!empty($row['course_id']) && function_exists('tfp_admin_course_detail_url') ? tfp_admin_course_detail_url($row['course_id']) : $row['edit_url']); ?>"><?php esc_html_e('Edit', 'tfp-dashboard'); ?></button></div></article><?php endforeach; ?>
                        <?php if (empty($data['rows'])) : ?><div class="tfp-admin-modules-empty"><?php esc_html_e('No modules found.', 'tfp-dashboard'); ?></div><?php endif; ?>
                    </div>
                </section>
            </main>
            <div class="tfp-admin-module-modal-backdrop" id="tfp-admin-module-modal" hidden aria-hidden="true">
                <section class="tfp-admin-module-modal" role="dialog" aria-modal="true" aria-labelledby="tfp-admin-module-modal-title">
                    <h2 id="tfp-admin-module-modal-title"></h2>
                    <p class="tfp-admin-module-modal-subtitle"></p>
                    <div class="tfp-admin-module-modal-details">
                        <article><small><?php esc_html_e('Status', 'tfp-dashboard'); ?></small><strong class="module-modal-status"></strong></article>
                        <article><small><?php esc_html_e('Content Flags', 'tfp-dashboard'); ?></small><strong class="module-modal-flags"></strong></article>
                        <article><small><?php esc_html_e('Exercises', 'tfp-dashboard'); ?></small><strong><?php esc_html_e('None', 'tfp-dashboard'); ?></strong></article>
                        <article><small><?php esc_html_e('Category', 'tfp-dashboard'); ?></small><strong class="module-modal-category"></strong></article>
                    </div>
                    <div class="tfp-admin-module-modal-actions">
                        <a class="tfp-admin-module-open-editor" href="#"><span aria-hidden="true">↗</span><?php esc_html_e('Open Full Course Editor', 'tfp-dashboard'); ?></a>
                        <button type="button" data-tfp-close-module-modal><span aria-hidden="true">⊘</span><?php esc_html_e('Cancel', 'tfp-dashboard'); ?></button>
                    </div>
                </section>
            </div>
            <script>
            (function () {
                const modal = document.getElementById('tfp-admin-module-modal');
                if (!modal) return;
                const close = () => { modal.hidden = true; modal.setAttribute('aria-hidden', 'true'); };
                document.querySelectorAll('.tfp-admin-module-preview-trigger').forEach((button) => {
                    button.addEventListener('click', () => {
                        modal.querySelector('#tfp-admin-module-modal-title').textContent = button.dataset.moduleTitle || '';
                        modal.querySelector('.tfp-admin-module-modal-subtitle').textContent = (button.dataset.moduleCourse || '') + ' · 58 Week Discipleship';
                        const flags = parseInt(button.dataset.moduleFlags || '0', 10);
                        const status = modal.querySelector('.module-modal-status');
                        status.textContent = flags ? 'Has Flags' : (button.dataset.moduleStatus || 'Active');
                        status.classList.toggle('has-flags', flags > 0);
                        modal.querySelector('.module-modal-flags').textContent = flags ? flags + (flags === 1 ? ' Flag to fix' : ' Flags to fix') : 'None';
                        modal.querySelector('.module-modal-category').textContent = button.dataset.moduleCategory || 'Uncategorized';
                        modal.querySelector('.tfp-admin-module-open-editor').href = button.dataset.moduleEditor || '#';
                        modal.hidden = false; modal.setAttribute('aria-hidden', 'false');
                        modal.querySelector('[data-tfp-close-module-modal]').focus();
                    });
                });
                modal.querySelectorAll('[data-tfp-close-module-modal]').forEach((button) => button.addEventListener('click', close));
                modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
                document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) close(); });
            }());
            </script>
        </div>
    </div>
    <?php
}

/** Register the dedicated Curriculum Modules front-end route. */
add_filter('pre_handle_404', function ($preempt, $wp_query) {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) === 'admin-curriculum-modules') {
        if ($wp_query) { $wp_query->is_404 = false; $wp_query->is_page = true; }
        status_header(200);
        return true;
    }
    return $preempt;
}, 12, 2);

add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'admin-curriculum-modules') { return; }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-curriculum-modules.php';
    if (is_readable($file)) { include $file; exit; }
}, 1);
