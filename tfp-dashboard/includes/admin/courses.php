<?php
/**
 * TFP Admin Dashboard — Courses overview.
 * Uses LearnDash course records and the shared Admin Console shell.
 */
if (!defined('ABSPATH')) {
    exit;
}

function tfp_admin_courses_page_url() {
    return home_url('/admin-courses/');
}

function tfp_admin_courses_count_steps($value) {
    if (!is_array($value)) {
        return empty($value) ? 0 : 1;
    }
    $count = 0;
    foreach ($value as $child) {
        $count += tfp_admin_courses_count_steps($child);
    }
    return $count;
}

function tfp_admin_courses_get_course_steps_count($course_id) {
    if (function_exists('learndash_get_course_steps')) {
        $steps = learndash_get_course_steps($course_id);
        return tfp_admin_courses_count_steps($steps);
    }
    $lessons = get_posts([
        'post_type' => 'sfwd-lessons',
        'post_status' => ['publish', 'private', 'draft'],
        'numberposts' => -1,
        'meta_query' => [[
            'key' => 'course_id',
            'value' => (string) $course_id,
            'compare' => '=',
        ]],
        'fields' => 'ids',
    ]);
    return count($lessons);
}

function tfp_admin_courses_get_weeks($course_id) {
    $keys = ['tfp_total_weeks', '_tfp_total_weeks', 'course_weeks', '_course_weeks'];
    foreach ($keys as $key) {
        $value = absint(get_post_meta($course_id, $key, true));
        if ($value > 0) {
            return $value;
        }
    }
    return '—';
}

function tfp_admin_courses_get_data() {
    $status_filter = isset($_GET['course_view']) ? sanitize_key(wp_unslash($_GET['course_view'])) : 'all';
    $week_filter = isset($_GET['course_week']) ? sanitize_text_field(wp_unslash($_GET['course_week'])) : '';
    $selected_course = isset($_GET['course_id']) ? absint($_GET['course_id']) : 0;
    $search = isset($_GET['course_search']) ? sanitize_text_field(wp_unslash($_GET['course_search'])) : '';

    $counts = post_type_exists('sfwd-courses') ? wp_count_posts('sfwd-courses') : (object) [];
    $published = (int) ($counts->publish ?? 0);
    $draft = (int) ($counts->draft ?? 0) + (int) ($counts->pending ?? 0) + (int) ($counts->private ?? 0) + (int) ($counts->future ?? 0);
    $total = $published + $draft;

    $query_args = [
        'post_type' => 'sfwd-courses',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => 100,
        'orderby' => 'modified',
        'order' => 'DESC',
    ];
    if ($search !== '') {
        $query_args['s'] = $search;
    }
    if ($status_filter === 'published') {
        $query_args['post_status'] = ['publish'];
    } elseif ($status_filter === 'draft') {
        $query_args['post_status'] = ['draft', 'pending', 'private'];
    }
    if ($selected_course) {
        $query_args['post__in'] = [$selected_course];
    }
    $selected_category = isset($_GET['course_category']) ? absint($_GET['course_category']) : 0;
    if ($selected_category && taxonomy_exists('ld_course_category')) {
        $query_args['tax_query'] = [[
            'taxonomy' => 'ld_course_category',
            'field' => 'term_id',
            'terms' => $selected_category,
        ]];
    }
    $courses = post_type_exists('sfwd-courses') ? get_posts($query_args) : [];

    if ($week_filter !== '') {
        $courses = array_values(array_filter($courses, static function ($course) use ($week_filter) {
            return (string) tfp_admin_courses_get_weeks($course->ID) === $week_filter;
        }));
    }

    $all_courses = post_type_exists('sfwd-courses') ? get_posts([
        'post_type' => 'sfwd-courses',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => 100,
        'orderby' => 'modified',
        'order' => 'DESC',
    ]) : [];

    $alerts = 0;
    $activity = [];
    $course_rows = [];
    $total_steps = 0;
    foreach ($all_courses as $course) {
        $steps = tfp_admin_courses_get_course_steps_count($course->ID);
        $weeks = tfp_admin_courses_get_weeks($course->ID);
        $total_steps += $steps;
        if ($steps < 1) {
            $alerts++;
        }
        $activity[] = [
            'title' => get_the_title($course->ID),
            'event' => $course->post_status === 'publish' ? __('Course updated', 'tfp-dashboard') : sprintf(__('Course %s', 'tfp-dashboard'), ucfirst($course->post_status)),
            'date' => get_post_modified_time('M j, g:i A', false, $course->ID, true),
            'timestamp' => get_post_modified_time('U', false, $course->ID, true),
            'url' => function_exists('tfp_admin_course_detail_url') ? tfp_admin_course_detail_url($course->ID) : get_edit_post_link($course->ID, 'raw'),
        ];
        $course_rows[$course->ID] = [
            'title' => get_the_title($course->ID),
            'category' => (function () use ($course) {
                $terms = get_the_terms($course->ID, 'ld_course_category');
                return (!is_wp_error($terms) && !empty($terms)) ? $terms[0]->name : __('Uncategorized', 'tfp-dashboard');
            })(),
            'status' => $course->post_status,
            'weeks' => $weeks,
            'steps' => $steps,
            'completion' => '—',
            'date' => get_post_modified_time('M j, g:i A', false, $course->ID, true),
            'url' => function_exists('tfp_admin_course_detail_url') ? tfp_admin_course_detail_url($course->ID) : get_edit_post_link($course->ID, 'raw'),
        ];
    }
    usort($activity, static function ($a, $b) {
        return (int) $b['timestamp'] <=> (int) $a['timestamp'];
    });

    $filtered_rows = [];
    foreach ($courses as $course) {
        if (isset($course_rows[$course->ID])) {
            $filtered_rows[] = $course_rows[$course->ID];
        }
    }

    $weeks = [];
    foreach ($all_courses as $course) {
        $week = tfp_admin_courses_get_weeks($course->ID);
        if ($week !== '—') {
            $weeks[(string) $week] = (string) $week;
        }
    }
    ksort($weeks, SORT_NUMERIC);

    $content_status = $total > 0 ? ($alerts > 0 ? 'Attention' : 'Good') : 'Unknown';
    $content_class = $alerts > 0 ? 'is-attention' : ($total > 0 ? 'is-good' : 'is-unknown');
    $assessment_status = 'Unknown';
    $assessment_class = 'is-unknown';
    $updates_7d = 0;
    $week_ago = current_time('timestamp') - WEEK_IN_SECONDS;
    foreach ($activity as $item) {
        if ((int) $item['timestamp'] >= $week_ago) {
            $updates_7d++;
        }
    }

    return [
        'total' => $total,
        'published' => $published,
        'draft' => $draft,
        'alerts' => $alerts,
        'courses' => $all_courses,
        'rows' => $filtered_rows,
        'activity' => array_slice($activity, 0, 8),
        'weeks' => $weeks,
        'content_status' => $content_status,
        'content_class' => $content_class,
        'assessment_status' => $assessment_status,
        'assessment_class' => $assessment_class,
        'updates_7d' => $updates_7d,
        'total_steps' => $total_steps,
        'status_filter' => $status_filter,
        'week_filter' => $week_filter,
        'selected_course' => $selected_course,
        'search' => $search,
    ];
}

function tfp_admin_courses_render() {
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
        if (($item['slug'] ?? '') === 'curriculum') {
            $item['url'] = function_exists('tfp_admin_categories_page_url') ? tfp_admin_categories_page_url() : home_url('/admin-curriculum-categories/');
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
                <span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><span><?php esc_html_e('Curriculum', 'tfp-dashboard'); ?></span><b>/</b><strong><?php esc_html_e('Courses', 'tfp-dashboard'); ?></strong>
            </div>

            <header class="tfp-admin-page-header tfp-admin-courses-header">
                <div>
                    <h1><?php esc_html_e('Curriculum', 'tfp-dashboard'); ?></h1>
                    <p><?php esc_html_e('Create, edit, and publish course tracks.', 'tfp-dashboard'); ?></p>
                </div>
                <a class="tfp-admin-courses-add" href="<?php echo esc_url($add_course_url); ?>"><span aria-hidden="true">＋</span><?php esc_html_e('Add Courses', 'tfp-dashboard'); ?></a>
            </header>

            <main class="tfp-admin-content tfp-admin-courses-content">
                <nav class="tfp-admin-curriculum-tabs" aria-label="<?php esc_attr_e('Curriculum sections', 'tfp-dashboard'); ?>">
                    <a href="<?php echo esc_url(tfp_admin_categories_page_url()); ?>"><?php esc_html_e('Categories', 'tfp-dashboard'); ?></a>
                    <a class="is-active" href="<?php echo esc_url(tfp_admin_courses_page_url()); ?>"><?php esc_html_e('Courses', 'tfp-dashboard'); ?></a>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=sfwd-lessons')); ?>"><?php esc_html_e('Modules', 'tfp-dashboard'); ?></a>
                </nav>

                <section class="tfp-admin-courses-stat-grid tfp-admin-courses-curriculum-stats" aria-label="<?php esc_attr_e('Course summary', 'tfp-dashboard'); ?>">
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Total Course', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['total'])); ?></strong><p><?php esc_html_e('Across all Categories', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Active Course', 'tfp-dashboard'); ?></small><strong class="is-active-count"><?php echo esc_html(number_format_i18n($data['published'])); ?></strong><p><?php esc_html_e('Total Active Courses', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-courses-stat"><small><?php esc_html_e('Drafts/Inactive', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['draft'])); ?></strong><p><?php esc_html_e('Total Inactive Courses', 'tfp-dashboard'); ?></p></article>
                </section>

                <section class="tfp-admin-courses-list-panel" aria-label="<?php esc_attr_e('Courses list', 'tfp-dashboard'); ?>">
                    <form class="tfp-admin-courses-curriculum-filters" method="get" action="<?php echo esc_url(tfp_admin_courses_page_url()); ?>">
                        <label class="tfp-admin-courses-search">
                            <span class="dashicons dashicons-search" aria-hidden="true"></span>
                            <input type="search" name="course_search" value="<?php echo esc_attr($data['search']); ?>" placeholder="<?php esc_attr_e('Search Courses', 'tfp-dashboard'); ?>">
                        </label>
                        <select name="course_category" aria-label="<?php esc_attr_e('Filter by category', 'tfp-dashboard'); ?>">
                            <option value=""><?php esc_html_e('All Categories', 'tfp-dashboard'); ?></option>
                            <?php
                            $course_categories = taxonomy_exists('ld_course_category') ? get_terms(['taxonomy' => 'ld_course_category', 'hide_empty' => false]) : [];
                            if (!is_wp_error($course_categories)) :
                                foreach ($course_categories as $term) :
                                    $selected_category = isset($_GET['course_category']) ? absint($_GET['course_category']) : 0;
                            ?>
                                <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($selected_category, $term->term_id); ?>><?php echo esc_html($term->name); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                        <select name="course_view" aria-label="<?php esc_attr_e('Filter by status', 'tfp-dashboard'); ?>">
                            <option value="all" <?php selected($data['status_filter'], 'all'); ?>><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option>
                            <option value="published" <?php selected($data['status_filter'], 'published'); ?>><?php esc_html_e('Active', 'tfp-dashboard'); ?></option>
                            <option value="draft" <?php selected($data['status_filter'], 'draft'); ?>><?php esc_html_e('Draft / Inactive', 'tfp-dashboard'); ?></option>
                        </select>
                    </form>

                    <div class="tfp-admin-courses-curriculum-list">
                        <?php foreach ($data['rows'] as $row) : ?>
                            <article class="tfp-admin-courses-curriculum-row">
                                <div class="tfp-admin-courses-curriculum-info">
                                    <strong><?php echo esc_html($row['title']); ?></strong>
                                    <small><?php echo esc_html($row['category']); ?> · <?php echo esc_html(number_format_i18n($row['steps'])); ?> <?php esc_html_e('Modules', 'tfp-dashboard'); ?></small>
                                </div>
                                <div class="tfp-admin-courses-curriculum-actions">
                                    <span class="tfp-admin-courses-curriculum-status <?php echo $row['status'] === 'publish' ? 'is-active' : 'is-draft'; ?>"><?php echo $row['status'] === 'publish' ? esc_html__('Active', 'tfp-dashboard') : esc_html__('Draft', 'tfp-dashboard'); ?></span>
                                    <a href="<?php echo esc_url($row['url']); ?>"><?php esc_html_e('Edit', 'tfp-dashboard'); ?></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($data['rows'])) : ?>
                            <div class="tfp-admin-courses-curriculum-empty"><?php esc_html_e('No courses found.', 'tfp-dashboard'); ?></div>
                        <?php endif; ?>
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
    if (basename($path) === 'admin-courses') {
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
    if (basename($path) !== 'admin-courses') {
        return;
    }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-courses.php';
    if (is_readable($file)) {
        include $file;
        exit;
    }
}, 1);
