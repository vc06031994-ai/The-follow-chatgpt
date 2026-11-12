<?php
/**
 * TFP Admin Dashboard — User Overview.
 * Dynamic user directory with role, status, system and keyword filters.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Resolve the dedicated System Health page URL. */
function tfp_admin_system_health_page_url() {
    return home_url('/admin-system-health/');
}

/** Route the dedicated System Health page without requiring a WordPress page record. */
add_filter('pre_handle_404', function ($preempt, $wp_query) {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) === 'admin-system-health') {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }
    return $preempt;
}, 11, 2);

add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'admin-system-health') {
        return;
    }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-system-health.php';
    if (isset($_GET['refresh_health'])) {
        check_admin_referer('tfp_refresh_system_health');
        wp_safe_redirect(remove_query_arg(['refresh_health', '_wpnonce']));
        exit;
    }
    if (is_readable($file)) {
        include $file;
        exit;
    }
}, 3);

/** Register the Admin Users page template. */
add_filter('theme_page_templates', function ($templates) {
    $templates['tfp-dashboard-admin-users'] = __('TFP Admin Dashboard — Users', 'tfp-dashboard');
    return $templates;
});

/** Load the custom template when it is assigned to a WordPress page. */
add_filter('template_include', function ($template) {
    if (is_page() && get_page_template_slug(get_queried_object_id()) === 'tfp-dashboard-admin-users') {
        $file = TFP_DASH_PATH . 'templates/template-admin-users.php';
        if (is_readable($file)) {
            return $file;
        }
    }
    return $template;
}, 99);

/** Support the existing virtual Admin Users routes. */
add_filter('pre_handle_404', function ($preempt, $wp_query) {
    $request_path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $last_segment = basename($request_path);
    if (in_array($last_segment, ['admin-users', 'tfp-admin-users', 'user-overview'], true) ||
        (isset($_GET['tfp_admin_page']) && sanitize_key(wp_unslash($_GET['tfp_admin_page'])) === 'users')) {
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        status_header(200);
        return true;
    }
    return $preempt;
}, 10, 2);

add_action('template_redirect', function () {
    global $wp_query;
    $request_path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $last_segment = basename($request_path);
    $is_virtual_users = in_array($last_segment, ['user-overview', 'tfp-admin-users'], true) ||
        (isset($_GET['tfp_admin_page']) && sanitize_key(wp_unslash($_GET['tfp_admin_page'])) === 'users');

    if ($is_virtual_users) {
        status_header(200);
        if ($wp_query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
        }
        $file = TFP_DASH_PATH . 'templates/template-admin-users.php';
        if (is_readable($file)) {
            include $file;
            exit;
        }
    }
}, 2);

/** Route the separate Users landing page. */
add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (basename($path) !== 'admin-users') {
        return;
    }
    status_header(200);
    nocache_headers();
    $file = TFP_DASH_PATH . 'templates/template-admin-users-home.php';
    if (is_readable($file)) {
        include $file;
        exit;
    }
}, 1);

/** Resolve the Users landing page URL. */
function tfp_admin_users_home_url() {
    $page = get_page_by_path('admin-users', OBJECT, 'page');
    if ($page instanceof WP_Post && $page->post_status === 'publish') {
        return get_permalink($page->ID);
    }
    return home_url('/admin-users/');
}

/** Resolve the Admin Users page URL. */
function tfp_admin_users_page_url() {
    // Prefer the actual WordPress page with the requested user-overview slug.
    $overview_page = get_page_by_path('user-overview', OBJECT, 'page');
    if ($overview_page instanceof WP_Post && $overview_page->post_status === 'publish') {
        return get_permalink($overview_page->ID);
    }

    // Backward compatibility for sites that assigned the dedicated page template.
    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_key' => '_wp_page_template',
        'meta_value' => 'tfp-dashboard-admin-users',
        'fields' => 'ids',
    ]);
    if (!empty($pages)) {
        return get_permalink($pages[0]);
    }

    // Use the explicit user-overview route, never the generic Dashboard ?view=users route.
    return home_url('/user-overview/');
}

/** Shared Admin navigation with Users active. */
function tfp_admin_users_nav_items() {
    $dashboard_url = function_exists('tfp_admin_dashboard_page_url')
        ? tfp_admin_dashboard_page_url()
        : home_url('/tfp-admin-dashboard/');
    return [
        ['slug' => 'dashboard', 'label' => __('Dashboard', 'tfp-dashboard'), 'url' => $dashboard_url],
        ['slug' => 'users', 'label' => __('Users', 'tfp-dashboard'), 'url' => tfp_admin_users_home_url(), 'active' => true],
        ['slug' => 'courses', 'label' => __('Courses', 'tfp-dashboard'), 'url' => function_exists('tfp_admin_courses_page_url') ? tfp_admin_courses_page_url() : home_url('/admin-courses/')],
        ['slug' => 'curriculum', 'label' => __('Curriculum', 'tfp-dashboard'), 'url' => admin_url('edit.php?post_type=sfwd-lessons')],
        ['slug' => 'cohorts', 'label' => __('Cohorts', 'tfp-dashboard'), 'url' => admin_url('edit.php?post_type=tfp_cohort')],
        ['slug' => 'facilitators', 'label' => __('Facilitators', 'tfp-dashboard'), 'url' => admin_url('users.php?role=facilitator')],
        ['slug' => 'payments', 'label' => __('Payments', 'tfp-dashboard'), 'url' => admin_url('edit.php?post_type=shop_order')],
        ['slug' => 'documents', 'label' => __('Documents', 'tfp-dashboard'), 'url' => admin_url('upload.php')],
        ['slug' => 'communication', 'label' => __('Communication', 'tfp-dashboard'), 'url' => admin_url('edit.php?post_type=tfp_ticket')],
        ['slug' => 'verification', 'label' => __('Verification', 'tfp-dashboard'), 'url' => admin_url('edit.php?post_type=tfp_financial_aid')],
        ['slug' => 'calendar', 'label' => __('Calendar', 'tfp-dashboard'), 'url' => admin_url('admin.php?page=tfp-attendance-history')],
        ['slug' => 'reports', 'label' => __('Reports', 'tfp-dashboard'), 'url' => admin_url('admin.php?page=tfp-weekly-submissions')],
        ['slug' => 'settings', 'label' => __('Settings', 'tfp-dashboard'), 'url' => admin_url('options-general.php')],
        ['slug' => 'profile', 'label' => __('Profile', 'tfp-dashboard'), 'url' => admin_url('profile.php')],
    ];
}

/** Return dynamic user directory data for the current filter state. */
function tfp_admin_users_get_data() {
    $per_page = 4;
    $current_page = max(1, absint($_GET['users_page'] ?? 1));
    $role = isset($_GET['user_role']) ? sanitize_key(wp_unslash($_GET['user_role'])) : '';
    $status = isset($_GET['user_status']) ? sanitize_key(wp_unslash($_GET['user_status'])) : '';
    $system = isset($_GET['user_system']) ? sanitize_text_field(wp_unslash($_GET['user_system'])) : '';
    $search = isset($_GET['user_search']) ? sanitize_text_field(wp_unslash($_GET['user_search'])) : '';

    $args = [
        'number' => -1,
        'orderby' => 'registered',
        'order' => 'DESC',
        'fields' => 'all',
    ];
    if ($role !== '') {
        $args['role'] = $role;
    }
    $meta_query = ['relation' => 'AND'];
    if ($status === 'deactivated') {
        $meta_query[] = ['key' => '_tfp_deactivated', 'value' => '1', 'compare' => '='];
    } elseif ($status === 'active') {
        $meta_query[] = [
            'relation' => 'OR',
            ['key' => '_tfp_deactivated', 'compare' => 'NOT EXISTS'],
            ['key' => '_tfp_deactivated', 'value' => ['', '0'], 'compare' => 'IN'],
        ];
    }
    if ($system !== '') {
        $meta_query[] = ['key' => 'tfp_system', 'value' => $system, 'compare' => '='];
    }
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    $users = get_users($args);
    if ($search !== '') {
        $needle = strtolower($search);
        $matching_cohorts = get_posts([
            'post_type' => 'tfp_cohort',
            'post_status' => ['publish', 'private', 'draft'],
            'posts_per_page' => -1,
            's' => $search,
            'fields' => 'ids',
        ]);
        $users = array_values(array_filter($users, function ($person) use ($needle, $matching_cohorts) {
            $name_match = strpos(strtolower($person->display_name . ' ' . $person->user_email . ' ' . $person->user_login), $needle) !== false;
            $cohort_id = absint(get_user_meta($person->ID, 'tfp_cohort_choice', true));
            return $name_match || ($cohort_id && in_array($cohort_id, $matching_cohorts, true));
        }));
    }

    // Derive available system filter values from actual user metadata.
    $systems = [];
    foreach (get_users(['fields' => 'ID', 'number' => -1]) as $user_id) {
        $system_value = get_user_meta($user_id, 'tfp_system', true);
        if (is_string($system_value) && $system_value !== '') {
            $systems[$system_value] = $system_value;
        }
    }
    natcasesort($systems);

    $total = count($users);
    $total_pages = max(1, (int) ceil($total / $per_page));
    $current_page = min($current_page, $total_pages);
    $page_users = array_slice($users, ($current_page - 1) * $per_page, $per_page);

    $rows = [];
    foreach ($page_users as $person) {
        $roles = (array) $person->roles;
        $primary_role = !empty($roles) ? reset($roles) : '';
        $role_label = $primary_role ? translate_user_role(wp_roles()->roles[$primary_role]['name'] ?? ucfirst($primary_role)) : __('Unassigned', 'tfp-dashboard');
        if ($primary_role === 'administrator') {
            $role_label = __('Admin', 'tfp-dashboard');
        } elseif ($primary_role === 'subscriber') {
            $role_label = __('Student', 'tfp-dashboard');
        }

        $cohort_id = absint(get_user_meta($person->ID, 'tfp_cohort_choice', true));
        $cohort_name = $cohort_id ? get_the_title($cohort_id) : '—';
        $deactivated = (bool) get_user_meta($person->ID, '_tfp_deactivated', true);
        $last_login = get_user_meta($person->ID, '_tfp_last_login', true);
        $last_activity = $last_login ? wp_date('M j, g:ia', strtotime($last_login)) : '—';
        $flags = get_user_meta($person->ID, 'tfp_flags', true);
        if ($flags === '' || $flags === false) {
            $flags = get_user_meta($person->ID, '_tfp_flags', true);
        }
        $flag_count = is_array($flags) ? count($flags) : absint($flags);

        $rows[] = [
            'id' => $person->ID,
            'name' => $person->display_name ?: $person->user_login,
            'role' => $role_label,
            'status' => $deactivated ? __('Deactivated', 'tfp-dashboard') : __('Active', 'tfp-dashboard'),
            'status_class' => $deactivated ? 'is-deactivated' : 'is-active',
            'cohort' => $cohort_name ?: '—',
            'flags' => $flag_count,
            'last_activity' => $last_activity,
            'url' => get_edit_user_link($person->ID),
        ];
    }

    $roles = wp_roles()->roles;
    $role_options = [];
    foreach ($roles as $slug => $details) {
        $role_options[$slug] = translate_user_role($details['name']);
    }

    return [
        'rows' => $rows,
        'total' => $total,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'per_page' => $per_page,
        'role' => $role,
        'status' => $status,
        'system' => $system,
        'search' => $search,
        'roles' => $role_options,
        'systems' => $systems,
    ];
}

/** Render the separate Users landing page with a live user preview. */
function tfp_admin_users_home_render() {
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }
    if (function_exists('tfp_admin_dashboard_enqueue_assets')) {
        tfp_admin_dashboard_enqueue_assets();
    }

    $current_user = wp_get_current_user();
    $all_users_url = tfp_admin_users_page_url();
    $nav = tfp_admin_users_nav_items();
    $total_users = count_users();
    $total = (int) ($total_users['total_users'] ?? 0);
    $student_ids = get_users([
        'role__in' => ['subscriber', 'student'],
        'meta_key' => 'tfp_cohort_choice',
        'meta_compare' => 'EXISTS',
        'fields' => 'ID',
        'number' => -1,
    ]);
    $student_count = count($student_ids);
    $facilitator_ids = get_users([
        'role' => 'facilitator',
        'meta_query' => [
            'relation' => 'OR',
            ['key' => '_tfp_deactivated', 'compare' => 'NOT EXISTS'],
            ['key' => '_tfp_deactivated', 'value' => ['', '0'], 'compare' => 'IN'],
        ],
        'fields' => 'ID',
        'number' => -1,
    ]);
    $facilitator_count = count($facilitator_ids);
    $active_count = 0;
    $recent_users = get_users(['number' => 4, 'orderby' => 'registered', 'order' => 'DESC']);
    $all_user_ids = get_users(['fields' => 'ID', 'number' => -1]);
    foreach ($all_user_ids as $user_id) {
        $last_login = get_user_meta($user_id, '_tfp_last_login', true);
        if ($last_login && strtotime($last_login) >= strtotime('-30 days') && !get_user_meta($user_id, '_tfp_deactivated', true)) {
            $active_count++;
        }
    }
    $search_value = isset($_GET['user_search']) ? sanitize_text_field(wp_unslash($_GET['user_search'])) : '';
    $activity_users = get_users(['number' => 8, 'orderby' => 'registered', 'order' => 'DESC']);
    $payments = [];
    if (function_exists('wc_get_orders')) {
        $payments = wc_get_orders(['limit' => 5, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects']);
    }

    $upload_info = wp_upload_dir();
    $active_plugins = (array) get_option('active_plugins', []);
    $discord_plugin_detected = false;
    $attendance_plugin_detected = post_type_exists('tfp_attendance');
    foreach ($active_plugins as $plugin_file) {
        if (stripos($plugin_file, 'discord') !== false) {
            $discord_plugin_detected = true;
        }
        if (stripos($plugin_file, 'attendance') !== false) {
            $attendance_plugin_detected = true;
        }
    }
    $documents_ready = empty($upload_info['error']) && !empty($upload_info['basedir']) && wp_is_writable($upload_info['basedir']);
    $systems = [
        ['label' => __('Billing', 'tfp-dashboard'), 'detail' => function_exists('wc_get_orders') ? __('WooCommerce order service is available', 'tfp-dashboard') : __('WooCommerce is not active', 'tfp-dashboard'), 'good' => function_exists('wc_get_orders')],
        ['label' => __('Authentication', 'tfp-dashboard'), 'detail' => function_exists('wp_check_password') ? __('WordPress authentication functions are available', 'tfp-dashboard') : __('WordPress authentication service is unavailable', 'tfp-dashboard'), 'good' => function_exists('wp_check_password')],
        ['label' => __('Discord Sync', 'tfp-dashboard'), 'detail' => $discord_plugin_detected ? __('Discord integration plugin detected; live connection has not been tested', 'tfp-dashboard') : __('No Discord integration plugin detected', 'tfp-dashboard'), 'good' => false],
        ['label' => __('Documents', 'tfp-dashboard'), 'detail' => $documents_ready ? __('WordPress uploads directory is writable', 'tfp-dashboard') : __('WordPress uploads directory is unavailable or not writable', 'tfp-dashboard'), 'good' => $documents_ready],
        ['label' => __('Attendance', 'tfp-dashboard'), 'detail' => $attendance_plugin_detected ? __('Attendance module detected', 'tfp-dashboard') : __('Attendance module is not detected', 'tfp-dashboard'), 'good' => $attendance_plugin_detected],
        ['label' => __('Password', 'tfp-dashboard'), 'detail' => function_exists('wp_check_password') ? __('WordPress password verification is available', 'tfp-dashboard') : __('Password verification is unavailable', 'tfp-dashboard'), 'good' => function_exists('wp_check_password')],
    ];
    ?>
    <div class="tfp-admin-app tfp-admin-users-home">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top">
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg>
                </button>
            </div>
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>">
                    <img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </a>
            </div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?>
                    <a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo function_exists('tfp_admin_dashboard_nav_icon') ? tfp_admin_dashboard_nav_icon($item['slug']) : '•'; ?></span>
                        <span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom">
                <div class="tfp-admin-profile"><img src="<?php echo esc_url(get_avatar_url($current_user->ID, ['size' => 96])); ?>" alt=""><span><strong><?php echo esc_html($current_user->display_name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span></div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span class="tfp-admin-logout-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h4"/><path d="M12 6l4 4-4 4"/><path d="M16 10H7"/></svg></span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a>
            </div>
        </aside>
        <div class="tfp-admin-main">
            <div class="tfp-admin-breadcrumb"><a href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a><b>/</b><strong><?php esc_html_e('Users', 'tfp-dashboard'); ?></strong></div>
            <header class="tfp-admin-page-header tfp-admin-users-home-header">
                <div><h1><?php esc_html_e('Users', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Manage students, facilitators, and administrators', 'tfp-dashboard'); ?></p></div>
                <div class="tfp-admin-users-home-actions">
                    <form class="tfp-admin-users-home-search" method="get" action="<?php echo esc_url($all_users_url); ?>">
                        <span class="tfp-admin-search-png" aria-hidden="true"><img src="<?php echo esc_url('https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png'); ?>" alt=""></span>
                        <input type="search" name="user_search" value="<?php echo esc_attr($search_value); ?>" placeholder="<?php esc_attr_e('Search Users...', 'tfp-dashboard'); ?>">
                    </form>
                    <a class="tfp-admin-add-user-btn" href="<?php echo esc_url(admin_url('user-new.php')); ?>">＋ <?php esc_html_e('Add User', 'tfp-dashboard'); ?></a>
                </div>
            </header>
            <main class="tfp-admin-content tfp-admin-users-home-content">
                <section class="tfp-admin-stat-grid tfp-admin-users-home-stats" aria-label="<?php esc_attr_e('User statistics', 'tfp-dashboard'); ?>">
                    <article class="tfp-admin-stat-card"><small><?php esc_html_e('Total Users', 'tfp-dashboard'); ?></small><div class="tfp-admin-stat-value"><strong><?php echo esc_html(number_format_i18n($total)); ?></strong></div><p><?php esc_html_e('Students | Facilitators | Admins', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-stat-card"><small><?php esc_html_e('Active Users', 'tfp-dashboard'); ?></small><div class="tfp-admin-stat-value"><strong><?php echo esc_html(number_format_i18n($active_count)); ?></strong></div><p><?php esc_html_e('Last login within 30 days', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-stat-card"><small><?php esc_html_e('Students Enrolled', 'tfp-dashboard'); ?></small><div class="tfp-admin-stat-value"><strong><?php echo esc_html(number_format_i18n($student_count)); ?></strong></div><p><?php esc_html_e('Student accounts', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-stat-card"><small><?php esc_html_e('Facilitators Active', 'tfp-dashboard'); ?></small><div class="tfp-admin-stat-value"><strong><?php echo esc_html(number_format_i18n($facilitator_count)); ?></strong></div><p><?php esc_html_e('Facilitator accounts', 'tfp-dashboard'); ?></p></article>
                </section>

                <section class="tfp-admin-users-home-panels">
                    <article class="tfp-admin-panel tfp-admin-users-activity">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Recent User Activity', 'tfp-dashboard'); ?></h2><a href="<?php echo esc_url(tfp_admin_user_activity_url()); ?>"><?php esc_html_e('Activity', 'tfp-dashboard'); ?></a><a href="<?php echo esc_url($all_users_url); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-activity-list">
                            <?php if ($activity_users) : foreach ($activity_users as $person) :
                                $cohort_id = absint(get_user_meta($person->ID, 'tfp_cohort_choice', true));
                                $cohort = $cohort_id ? get_the_title($cohort_id) : __('No cohort assigned', 'tfp-dashboard');
                                $activity_date = $person->user_registered ? wp_date('Y-m-d H:i', strtotime($person->user_registered)) : '—';
                            ?>
                                <a class="tfp-admin-activity-row" href="<?php echo esc_url(get_edit_user_link($person->ID)); ?>">
                                    <span class="tfp-admin-activity-col1"><strong><?php echo esc_html($person->display_name ?: $person->user_login); ?></strong><time><?php echo esc_html($activity_date); ?></time></span>
                                    <span class="tfp-admin-activity-col2"><span><?php echo esc_html($cohort); ?></span></span>
                                    <i class="tfp-admin-chevron" aria-hidden="true">›</i>
                                </a>
                            <?php endforeach; else : ?><p class="tfp-admin-users-empty"><?php esc_html_e('No user activity available.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div>
                    </article>

                    <article class="tfp-admin-panel tfp-admin-users-health">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('User System Health', 'tfp-dashboard'); ?></h2><a href="<?php echo esc_url(tfp_admin_system_health_page_url()); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-health-list">
                            <?php foreach ($systems as $system) :
                                $health_icon_paths = [
                                    'Billing' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18M7 15h3"></path>',
                                    'Authentication' => '<path d="M12 3 20 6v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6l8-3Z"></path><path d="m9 12 2 2 4-4"></path>',
                                    'Discord Sync' => '<path d="M8 7c2.5-1.2 5.5-1.2 8 0l2 9c-2 1.5-4 2-6 2s-4-.5-6-2l2-9Z"></path><path d="M9 8 7 6m8 2 2-2M9.5 13h.01M14.5 13h.01M9 16c2 1 4 1 6 0"></path>',
                                    'Documents' => '<path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"></path><path d="M14 3v5h5M9 13h6M9 17h6"></path>',
                                    'Attendance' => '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18M8 15l2 2 5-5"></path>',
                                    'Password' => '<rect x="4" y="10" width="16" height="11" rx="2"></rect><path d="M8 10V7a4 4 0 1 1 8 0v3M12 14v3"></path>',
                                ][$system['label']] ?? '<circle cx="12" cy="12" r="8"></circle>';
                            ?>
                                <div class="tfp-admin-health-row">
                                    <span class="tfp-admin-health-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false" xmlns="http://www.w3.org/2000/svg"><?php echo $health_icon_paths; ?></svg></span>
                                    <span class="tfp-admin-health-info"><strong><?php echo esc_html($system['label']); ?></strong><small><?php echo esc_html($system['detail']); ?></small></span>
                                    <span class="tfp-admin-health-status <?php echo $system['good'] ? 'is-good' : 'is-attention'; ?>"><?php echo $system['good'] ? esc_html__('Good', 'tfp-dashboard') : esc_html__('Degraded', 'tfp-dashboard'); ?></span>
                                    <i class="tfp-admin-chevron" aria-hidden="true">›</i>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="tfp-admin-panel tfp-admin-users-payments">
                        <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('Recent Payments', 'tfp-dashboard'); ?></h2><a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-payment-list">
                            <?php if ($payments) : foreach ($payments as $order) :
                                $billing_name = trim($order->get_formatted_billing_full_name());
                                $order_status = $order->get_status();
                                $payment_label = in_array($order_status, ['failed', 'cancelled'], true) ? __('Fail', 'tfp-dashboard') : (in_array($order_status, ['blocked'], true) ? __('Blocked', 'tfp-dashboard') : (in_array($order_status, ['on-hold', 'pending'], true) ? __('Attention', 'tfp-dashboard') : __('Good', 'tfp-dashboard')));
                                $payment_class = strtolower($payment_label);
                            ?>
                                <a class="tfp-admin-payment-row" href="<?php echo esc_url($order->get_edit_order_url()); ?>">
                                    <span class="tfp-admin-payment-info"><strong><?php echo esc_html($billing_name ?: __('Customer', 'tfp-dashboard')); ?></strong><small><?php echo esc_html(sprintf(__('Order #%s · %s %s', 'tfp-dashboard'), $order->get_order_number(), number_format_i18n((float) $order->get_total(), 2) . ' ' . $order->get_currency(), '')); ?></small></span>
                                    <span class="tfp-admin-payment-status is-<?php echo esc_attr($payment_class); ?>"><?php echo esc_html($payment_label); ?></span>
                                    <i class="tfp-admin-chevron" aria-hidden="true">›</i>
                                </a>
                            <?php endforeach; else : ?><p class="tfp-admin-users-empty"><?php esc_html_e('No payment records available.', 'tfp-dashboard'); ?></p><?php endif; ?>
                        </div>
                    </article>
                </section>

                <section class="tfp-admin-panel tfp-admin-overview-table-panel tfp-admin-users-home-table">
                    <div class="tfp-admin-panel-heading"><h2><?php esc_html_e('User Overview', 'tfp-dashboard'); ?></h2><a href="<?php echo esc_url($all_users_url); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                    <div class="tfp-admin-overview-table-wrap">
                        <table class="tfp-admin-overview-table">
                            <thead><tr><th><?php esc_html_e('User', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Role', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Cohort', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Flags', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Last Activity', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                            <tbody>
                            <?php if ($recent_users) : foreach ($recent_users as $person) :
                                $roles = (array) $person->roles;
                                $role_slug = $roles ? reset($roles) : '';
                                $role_label = $role_slug === 'administrator' ? __('Admin', 'tfp-dashboard') : ($role_slug === 'subscriber' ? __('Student', 'tfp-dashboard') : ($role_slug ? translate_user_role(wp_roles()->roles[$role_slug]['name'] ?? ucfirst($role_slug)) : __('Unassigned', 'tfp-dashboard')));
                                $cohort_id = absint(get_user_meta($person->ID, 'tfp_cohort_choice', true));
                                $cohort = $cohort_id ? get_the_title($cohort_id) : '—';
                                $deactivated = (bool) get_user_meta($person->ID, '_tfp_deactivated', true);
                                $flags = get_user_meta($person->ID, 'tfp_flags', true);
                                if ($flags === '' || $flags === false) { $flags = get_user_meta($person->ID, '_tfp_flags', true); }
                                $flag_count = is_array($flags) ? count($flags) : absint($flags);
                                $last_login = get_user_meta($person->ID, '_tfp_last_login', true);
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($person->display_name ?: $person->user_login); ?></strong></td>
                                    <td><?php echo esc_html($role_label); ?></td>
                                    <td><span class="tfp-admin-user-status <?php echo $deactivated ? 'is-deactivated' : 'is-active'; ?>"><?php echo $deactivated ? esc_html__('Deactivated', 'tfp-dashboard') : esc_html__('Active', 'tfp-dashboard'); ?></span></td>
                                    <td><?php echo esc_html($cohort ?: '—'); ?></td>
                                    <td><span class="tfp-admin-overview-flags"><svg class="tfp-admin-flag-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3 2v12" fill="none" stroke="#171717" stroke-width="1.2"/><path d="M3.8 2.5 13.5 5.1 3.8 7.8Z" fill="#c51f30"/></svg><?php echo esc_html(number_format_i18n($flag_count)); ?></span></td>
                                    <td><?php echo esc_html($last_login ? wp_date('M j, g:ia', strtotime($last_login)) : '—'); ?></td>
                                    <td><a class="tfp-admin-overview-view" href="<?php echo esc_url(get_edit_user_link($person->ID)); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                </tr>
                            <?php endforeach; else : ?><tr><td colspan="7" class="tfp-admin-overview-empty"><?php esc_html_e('No users found.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </div>
    </div>
    <?php
}

/** Render the responsive User Overview screen. */
function tfp_admin_users_render() {
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__('You do not have permission to access this page.', 'tfp-dashboard'),
            esc_html__('Access denied', 'tfp-dashboard'),
            ['response' => 403]
        );
    }

    if (function_exists('tfp_admin_dashboard_enqueue_assets')) {
        tfp_admin_dashboard_enqueue_assets();
    }

    $data = tfp_admin_users_get_data();
    $user = wp_get_current_user();
    $name = $user->display_name ?: __('Administrator', 'tfp-dashboard');
    $avatar = get_avatar_url($user->ID, ['size' => 96]);
    $nav = tfp_admin_users_nav_items();
    $base_url = remove_query_arg(['users_page', 'user_role', 'user_status', 'user_system', 'user_search']);
    ?>
    <div class="tfp-admin-app tfp-admin-users-overview">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top">
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg>
                </button>
            </div>
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>">
                    <img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </a>
            </div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?>
                    <a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo function_exists('tfp_admin_dashboard_nav_icon') ? tfp_admin_dashboard_nav_icon($item['slug']) : '•'; ?></span>
                        <span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom">
                <div class="tfp-admin-profile">
                    <img src="<?php echo esc_url($avatar); ?>" alt="">
                    <span><strong><?php echo esc_html($name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span>
                </div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span class="tfp-admin-logout-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h4"/><path d="M12 6l4 4-4 4"/><path d="M16 10H7"/></svg></span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a>
            </div>
        </aside>

        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar">
                <button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button>
                <span><?php esc_html_e('Admin / Users / Overview', 'tfp-dashboard'); ?></span>
            </div>

            <div class="tfp-admin-breadcrumb">
                <a href="<?php echo esc_url(function_exists('tfp_admin_dashboard_page_url') ? tfp_admin_dashboard_page_url() : home_url('/tfp-admin-dashboard/')); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a>
                <b>/</b><a href="<?php echo esc_url(tfp_admin_users_page_url()); ?>"><?php esc_html_e('Users', 'tfp-dashboard'); ?></a>
                <b>/</b><strong><?php esc_html_e('Overview', 'tfp-dashboard'); ?></strong>
            </div>

            <header class="tfp-admin-page-header tfp-admin-users-overview-header">
                <div>
                    <h1><?php esc_html_e('User Overview', 'tfp-dashboard'); ?></h1>
                    <p><?php esc_html_e('Full directory management and account status for all project users.', 'tfp-dashboard'); ?></p>
                </div>
                <a class="tfp-admin-add-user-btn" href="<?php echo esc_url(admin_url('user-new.php')); ?>"><span aria-hidden="true">＋</span><?php esc_html_e('Add User', 'tfp-dashboard'); ?></a>
            </header>

            <main class="tfp-admin-content tfp-admin-users-overview-content">
                <form class="tfp-admin-users-filters" method="get" action="<?php echo esc_url($base_url); ?>">
                    <?php if (isset($_GET['view'])) : ?><input type="hidden" name="view" value="users"><?php endif; ?>
                    <label class="screen-reader-text" for="tfp-user-role"><?php esc_html_e('Filter by role', 'tfp-dashboard'); ?></label>
                    <select id="tfp-user-role" name="user_role">
                        <option value=""><?php esc_html_e('All Roles', 'tfp-dashboard'); ?></option>
                        <?php foreach ($data['roles'] as $slug => $label) : ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($data['role'], $slug); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="screen-reader-text" for="tfp-user-status"><?php esc_html_e('Filter by status', 'tfp-dashboard'); ?></label>
                    <select id="tfp-user-status" name="user_status">
                        <option value=""><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option>
                        <option value="active" <?php selected($data['status'], 'active'); ?>><?php esc_html_e('Active', 'tfp-dashboard'); ?></option>
                        <option value="deactivated" <?php selected($data['status'], 'deactivated'); ?>><?php esc_html_e('Deactivated', 'tfp-dashboard'); ?></option>
                    </select>

                    <label class="screen-reader-text" for="tfp-user-system"><?php esc_html_e('Filter by system', 'tfp-dashboard'); ?></label>
                    <select id="tfp-user-system" name="user_system">
                        <option value=""><?php esc_html_e('All Systems', 'tfp-dashboard'); ?></option>
                        <?php foreach ($data['systems'] as $system_name) : ?>
                            <option value="<?php echo esc_attr($system_name); ?>" <?php selected($data['system'], $system_name); ?>><?php echo esc_html($system_name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="tfp-admin-overview-search">
                        <span class="tfp-admin-search-png" aria-hidden="true"><img src="<?php echo esc_url('https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png'); ?>" alt=""></span>
                        <input type="search" name="user_search" value="<?php echo esc_attr($data['search']); ?>" placeholder="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>">
                    </label>
                    <button class="tfp-admin-filter-submit" type="submit"><?php esc_html_e('Apply filters', 'tfp-dashboard'); ?></button>
                </form>

                <section class="tfp-admin-panel tfp-admin-overview-table-panel" aria-labelledby="tfp-user-overview-title">
                    <div class="tfp-admin-panel-heading">
                        <h2 id="tfp-user-overview-title"><?php esc_html_e('User Overview', 'tfp-dashboard'); ?></h2>
                        <a href="<?php echo esc_url(tfp_admin_users_page_url()); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                    </div>
                    <div class="tfp-admin-overview-table-wrap">
                        <table class="tfp-admin-overview-table">
                            <thead><tr>
                                <th><?php esc_html_e('User', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Role', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Cohort', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Flags', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Last Activity', 'tfp-dashboard'); ?></th>
                                <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php if (!empty($data['rows'])) : ?>
                                <?php foreach ($data['rows'] as $row) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                                        <td><?php echo esc_html($row['role']); ?></td>
                                        <td><span class="tfp-admin-overview-status <?php echo esc_attr($row['status_class']); ?>"><?php echo esc_html($row['status']); ?></span></td>
                                        <td><?php echo esc_html($row['cohort']); ?></td>
                                        <td><span class="tfp-admin-overview-flags"><svg class="tfp-admin-flag-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M3 2v12" fill="none" stroke="#171717" stroke-width="1.2"/><path d="M3.8 2.5 13.5 5.1 3.8 7.8Z" fill="#c51f30"/></svg><?php echo esc_html(number_format_i18n($row['flags'])); ?></span></td>
                                        <td><?php echo esc_html($row['last_activity']); ?></td>
                                        <td><a class="tfp-admin-overview-view" href="<?php echo esc_url($row['url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="7" class="tfp-admin-overview-empty"><?php esc_html_e('No users found for the selected filters.', 'tfp-dashboard'); ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <footer class="tfp-admin-overview-pagination">
                        <span><?php
                            $from = $data['total'] ? (($data['current_page'] - 1) * $data['per_page']) + 1 : 0;
                            $to = min($data['current_page'] * $data['per_page'], $data['total']);
                            printf(
                                esc_html__('%1$s - %2$s of %3$s users', 'tfp-dashboard'),
                                number_format_i18n($from),
                                number_format_i18n($to),
                                number_format_i18n($data['total'])
                            );
                        ?></span>
                        <nav aria-label="<?php esc_attr_e('User directory pagination', 'tfp-dashboard'); ?>">
                            <?php if ($data['current_page'] > 1) : ?>
                                <a class="tfp-admin-overview-prev" href="<?php echo esc_url(add_query_arg('users_page', $data['current_page'] - 1)); ?>">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></a>
                            <?php else : ?>
                                <span class="tfp-admin-overview-prev is-disabled">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></span>
                            <?php endif; ?>
                            <?php if ($data['current_page'] < $data['total_pages']) : ?>
                                <a class="tfp-admin-overview-next" href="<?php echo esc_url(add_query_arg('users_page', $data['current_page'] + 1)); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</a>
                            <?php else : ?>
                                <span class="tfp-admin-overview-next is-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</span>
                            <?php endif; ?>
                        </nav>
                    </footer>
                </section>
            </main>
        </div>
    </div>
    <?php
}


/**
 * Build verifiable per-user health rows from live WordPress and WooCommerce data.
 * Only report issues supported by stored account/order state; never invent integration state.
 */
function tfp_admin_system_health_get_rows() {
    $users = get_users([
        'number' => -1,
        'orderby' => 'display_name',
        'order' => 'ASC',
        'fields' => 'all',
    ]);
    $rows = [];

    foreach ($users as $person) {
        $roles = (array) $person->roles;
        $role_slug = !empty($roles) ? reset($roles) : '';
        $role_label = $role_slug ? translate_user_role(wp_roles()->roles[$role_slug]['name'] ?? ucfirst($role_slug)) : __('Unassigned', 'tfp-dashboard');
        if ($role_slug === 'administrator') {
            $role_label = __('Admin', 'tfp-dashboard');
        } elseif ($role_slug === 'subscriber' || $role_slug === 'student') {
            $role_label = __('Student', 'tfp-dashboard');
        }

        $last_check = wp_date(get_option('date_format') . ', ' . get_option('time_format'));

        // Account access is based on the project's existing deactivation flag.
        $deactivated = (bool) get_user_meta($person->ID, '_tfp_deactivated', true);
        $rows[] = [
            'user_id' => $person->ID,
            'user' => $person->display_name ?: $person->user_login,
            'role' => $role_label,
            'system' => __('Access', 'tfp-dashboard'),
            'status' => $deactivated ? 'attention' : 'good',
            'issue' => $deactivated ? __('Account paused by admin', 'tfp-dashboard') : __('No access restriction recorded', 'tfp-dashboard'),
            'last_check' => $last_check,
            'url' => get_edit_user_link($person->ID),
        ];

        // WooCommerce order statuses are the source of truth for billing alerts.
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'customer_id' => $person->ID,
                'status' => ['pending', 'failed', 'on-hold'],
                'limit' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'objects',
            ]);
            foreach ($orders as $order) {
                $status = $order->get_status();
                $issue = $status === 'failed'
                    ? __('Payment failed on last attempt', 'tfp-dashboard')
                    : ($status === 'on-hold'
                        ? __('Payment is on hold', 'tfp-dashboard')
                        : __('Payment is awaiting completion', 'tfp-dashboard'));
                $date = $order->get_date_modified() ?: $order->get_date_created();
                $rows[] = [
                    'user_id' => $person->ID,
                    'user' => $person->display_name ?: $person->user_login,
                    'role' => $role_label,
                    'system' => __('Billing', 'tfp-dashboard'),
                    'status' => 'attention',
                    'issue' => $issue,
                    'last_check' => $date ? $date->date_i18n(get_option('date_format') . ', ' . get_option('time_format')) : __('Not recorded', 'tfp-dashboard'),
                    'url' => function_exists('wc_get_endpoint_url') ? admin_url('post.php?post=' . absint($order->get_id()) . '&action=edit') : get_edit_user_link($person->ID),
                ];
            }
        }

        // Existing project flag metadata is included only when it contains a real flag.
        $flags = get_user_meta($person->ID, 'tfp_flags', true);
        if ($flags === '' || $flags === false) {
            $flags = get_user_meta($person->ID, '_tfp_flags', true);
        }
        $flag_count = is_array($flags) ? count($flags) : absint($flags);
        if ($flag_count > 0) {
            $rows[] = [
                'user_id' => $person->ID,
                'user' => $person->display_name ?: $person->user_login,
                'role' => $role_label,
                'system' => __('Compliance', 'tfp-dashboard'),
                'status' => 'attention',
                'issue' => sprintf(_n('%s account flag requires review', '%s account flags require review', $flag_count, 'tfp-dashboard'), number_format_i18n($flag_count)),
                'last_check' => $last_check,
                'url' => get_edit_user_link($person->ID),
            ];
        }
    }

    return $rows;
}

/** Render the responsive System Health directory. */
function tfp_admin_system_health_render() {
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
    }

    if (function_exists('tfp_admin_dashboard_enqueue_assets')) {
        tfp_admin_dashboard_enqueue_assets();
    }
    $css_path = TFP_DASH_PATH . 'assets/css/admin-system-health.css';
    wp_enqueue_style('tfp-admin-system-health', TFP_DASH_URL . 'assets/css/admin-system-health.css', ['tfp-admin-dashboard'], file_exists($css_path) ? (string) filemtime($css_path) : TFP_DASH_VERSION);

    $page = max(1, absint($_GET['health_page'] ?? 1));
    $role_filter = isset($_GET['health_role']) ? sanitize_key(wp_unslash($_GET['health_role'])) : '';
    $status_filter = isset($_GET['health_status']) ? sanitize_key(wp_unslash($_GET['health_status'])) : '';
    $system_filter = isset($_GET['health_system']) ? sanitize_text_field(wp_unslash($_GET['health_system'])) : '';
    $search = isset($_GET['health_search']) ? sanitize_text_field(wp_unslash($_GET['health_search'])) : '';

    $all_rows = tfp_admin_system_health_get_rows();
    $role_options = [];
    foreach (wp_roles()->roles as $slug => $details) {
        $role_options[$slug] = translate_user_role($details['name']);
    }
    $systems = [];
    foreach ($all_rows as $row) {
        $systems[$row['system']] = $row['system'];
    }
    natcasesort($systems);

    $filtered = array_values(array_filter($all_rows, function ($row) use ($role_filter, $status_filter, $system_filter, $search) {
        if ($role_filter !== '' && sanitize_key($row['role']) !== $role_filter) {
            $role_slug = $role_filter === 'subscriber' ? 'student' : $role_filter;
            $user = get_userdata($row['user_id']);
            if (!$user || !in_array($role_slug, (array) $user->roles, true)) {
                return false;
            }
        }
        if ($status_filter !== '' && $row['status'] !== $status_filter) {
            return false;
        }
        if ($system_filter !== '' && $row['system'] !== $system_filter) {
            return false;
        }
        if ($search !== '' && stripos($row['user'], $search) === false) {
            $cohort_id = absint(get_user_meta($row['user_id'], 'tfp_cohort_choice', true));
            $cohort = $cohort_id ? get_the_title($cohort_id) : '';
            if (!$cohort || stripos($cohort, $search) === false) {
                return false;
            }
        }
        return true;
    }));

    $per_page = 20;
    $total = count($filtered);
    $total_pages = max(1, (int) ceil($total / $per_page));
    $page = min($page, $total_pages);
    $page_rows = array_slice($filtered, ($page - 1) * $per_page, $per_page);
    $current_user = wp_get_current_user();
    $nav = tfp_admin_users_nav_items();
    $base_url = remove_query_arg(['health_page', 'health_role', 'health_status', 'health_system', 'health_search', 'refresh_health', '_wpnonce']);
    ?>
    <div class="tfp-admin-app tfp-admin-system-health">
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top">
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg>
                </button>
            </div>
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>">
                    <img class="tfp-admin-logo-expanded" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <img class="tfp-admin-logo-collapsed" src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-collapsed.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </a>
            </div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?>
                    <a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo function_exists('tfp_admin_dashboard_nav_icon') ? tfp_admin_dashboard_nav_icon($item['slug']) : '•'; ?></span>
                        <span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom">
                <div class="tfp-admin-profile"><img src="<?php echo esc_url(get_avatar_url($current_user->ID, ['size' => 96])); ?>" alt=""><span><strong><?php echo esc_html($current_user->display_name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span></div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span class="tfp-admin-logout-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h4"/><path d="M12 6l4 4-4 4"/><path d="M16 10H7"/></svg></span><span class="tfp-admin-logout-label"><?php esc_html_e('Logout', 'tfp-dashboard'); ?></span></a>
            </div>
        </aside>
        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar"><button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button><span><?php esc_html_e('Admin / Users / System Health', 'tfp-dashboard'); ?></span></div>
            <div class="tfp-admin-breadcrumb">
                <a href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>"><?php esc_html_e('Admin', 'tfp-dashboard'); ?></a><b>/</b>
                <a href="<?php echo esc_url(tfp_admin_users_home_url()); ?>"><?php esc_html_e('Users', 'tfp-dashboard'); ?></a><b>/</b>
                <strong><?php esc_html_e('System Health', 'tfp-dashboard'); ?></strong>
            </div>
            <header class="tfp-admin-page-header tfp-admin-system-health-header">
                <div><h1><?php esc_html_e('Users System Health', 'tfp-dashboard'); ?></h1><p><?php esc_html_e('Connection status and system-level requirements for users', 'tfp-dashboard'); ?></p></div>
                <a class="tfp-admin-health-refresh" href="<?php echo esc_url(wp_nonce_url(add_query_arg('refresh_health', '1'), 'tfp_refresh_system_health')); ?>"><span aria-hidden="true">↻</span><?php esc_html_e('Refresh All Systems', 'tfp-dashboard'); ?></a>
            </header>
            <main class="tfp-admin-content tfp-admin-system-health-content">
                <form class="tfp-admin-system-health-filters" method="get" action="<?php echo esc_url($base_url); ?>">
                    <label class="screen-reader-text" for="tfp-health-role"><?php esc_html_e('Filter by role', 'tfp-dashboard'); ?></label>
                    <select id="tfp-health-role" name="health_role"><option value=""><?php esc_html_e('All Roles', 'tfp-dashboard'); ?></option>
                        <?php foreach ($role_options as $slug => $label) : ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($role_filter, $slug); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
                    </select>
                    <label class="screen-reader-text" for="tfp-health-status"><?php esc_html_e('Filter by status', 'tfp-dashboard'); ?></label>
                    <select id="tfp-health-status" name="health_status"><option value=""><?php esc_html_e('All Statuses', 'tfp-dashboard'); ?></option>
                        <option value="attention" <?php selected($status_filter, 'attention'); ?>><?php esc_html_e('Attention', 'tfp-dashboard'); ?></option>
                        <option value="good" <?php selected($status_filter, 'good'); ?>><?php esc_html_e('Good', 'tfp-dashboard'); ?></option>
                    </select>
                    <label class="screen-reader-text" for="tfp-health-system"><?php esc_html_e('Filter by system', 'tfp-dashboard'); ?></label>
                    <select id="tfp-health-system" name="health_system"><option value=""><?php esc_html_e('All Systems', 'tfp-dashboard'); ?></option>
                        <?php foreach ($systems as $system) : ?><option value="<?php echo esc_attr($system); ?>" <?php selected($system_filter, $system); ?>><?php echo esc_html($system); ?></option><?php endforeach; ?>
                    </select>
                    <label class="tfp-admin-health-search"><span class="tfp-admin-search-png" aria-hidden="true"><img src="<?php echo esc_url('https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Note_Search-2.png'); ?>" alt=""></span><input type="search" name="health_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search Name or Cohort', 'tfp-dashboard'); ?>"></label>
                    <button class="tfp-admin-filter-submit" type="submit"><?php esc_html_e('Apply filters', 'tfp-dashboard'); ?></button>
                </form>
                <section class="tfp-admin-panel tfp-admin-overview-table-panel tfp-admin-system-health-table-panel" aria-label="<?php esc_attr_e('User system health records', 'tfp-dashboard'); ?>">
                    <div class="tfp-admin-overview-table-wrap">
                        <table class="tfp-admin-overview-table">
                            <thead><tr><th><?php esc_html_e('User', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Role', 'tfp-dashboard'); ?></th><th><?php esc_html_e('System', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Issue', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Last Check', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead>
                            <tbody>
                            <?php if ($page_rows) : foreach ($page_rows as $row) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($row['user']); ?></strong></td><td><?php echo esc_html($row['role']); ?></td><td><?php echo esc_html($row['system']); ?></td>
                                    <td><span class="tfp-admin-overview-status<?php echo $row['status'] === 'good' ? ' is-active' : ''; ?>"><?php echo $row['status'] === 'attention' ? esc_html__('Attention', 'tfp-dashboard') : esc_html__('Good', 'tfp-dashboard'); ?></span></td>
                                    <td><?php echo esc_html($row['issue']); ?></td><td><?php echo esc_html($row['last_check']); ?></td>
                                    <td><a class="tfp-admin-overview-view" href="<?php echo esc_url($row['url']); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="7" class="tfp-admin-system-health-empty"><?php esc_html_e('No matching system health records found.', 'tfp-dashboard'); ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <footer class="tfp-admin-overview-pagination tfp-admin-system-health-pagination">
                        <span><?php $from = $total ? (($page - 1) * $per_page) + 1 : 0; $to = min($page * $per_page, $total); printf(esc_html__('%1$s - %2$s of %3$s records', 'tfp-dashboard'), number_format_i18n($from), number_format_i18n($to), number_format_i18n($total)); ?></span>
                        <nav aria-label="<?php esc_attr_e('System health pagination', 'tfp-dashboard'); ?>">
                            <?php if ($page > 1) : ?><a class="tfp-admin-overview-prev tfp-admin-system-health-prev" href="<?php echo esc_url(add_query_arg('health_page', $page - 1)); ?>">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></a><?php else : ?><span class="tfp-admin-system-health-prev is-disabled">‹ <span><?php esc_html_e('Previous', 'tfp-dashboard'); ?></span></span><?php endif; ?>
                            <?php if ($page < $total_pages) : ?><a class="tfp-admin-overview-next tfp-admin-system-health-next" href="<?php echo esc_url(add_query_arg('health_page', $page + 1)); ?>"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</a><?php else : ?><span class="tfp-admin-system-health-next is-disabled"><?php esc_html_e('Next', 'tfp-dashboard'); ?> ›</span><?php endif; ?>
                        </nav>
                    </footer>
                </section>
            </main>
        </div>
    </div>
    <?php
}
