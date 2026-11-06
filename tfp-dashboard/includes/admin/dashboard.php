<?php
/**
 * TFP Admin Dashboard — responsive front-end administration console.
 * Strictly adheres to Administrator user role and design consistency
 * with existing Student and Faculty dashboards.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/users.php';

/** Check whether a plugin is active without relying on admin-only includes. */
function tfp_admin_dashboard_plugin_active($plugin_file) {
    $active = (array) get_option('active_plugins', []);
    if (in_array($plugin_file, $active, true)) {
        return true;
    }
    $network = (array) get_site_option('active_sitewide_plugins', []);
    return isset($network[$plugin_file]);
}

/** Count published posts for an existing post type. */
function tfp_admin_dashboard_count_posts($post_type, $status = 'publish') {
    if (!post_type_exists($post_type)) {
        return 0;
    }
    $counts = wp_count_posts($post_type);
    return isset($counts->{$status}) ? (int) $counts->{$status} : 0;
}

/** Return SVG icon for Admin navigation matching the reference design. */
function tfp_admin_dashboard_nav_icon($slug) {
    switch ($slug) {
        case 'dashboard':
            return '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="5.5" height="5.5" rx="1"/><rect x="10.5" y="2" width="5.5" height="5.5" rx="1"/><rect x="2" y="10.5" width="5.5" height="5.5" rx="1"/><rect x="10.5" y="10.5" width="5.5" height="5.5" rx="1"/></svg>';
        case 'users':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 2 15.5V17"/><circle cx="8" cy="6" r="3.5"/><path d="M18 17v-1.2a3 3 0 0 0-2.2-2.9"/><path d="M13.5 2.6a3.5 3.5 0 0 1 0 6.8"/></svg>';
        case 'courses':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="16" height="12" rx="2"/><path d="M6 18h8"/><path d="M10 15v3"/></svg>';
        case 'curriculum':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2l8 4.5-8 4.5-8-4.5L10 2z"/><path d="M2 11l8 4.5 8-4.5"/><path d="M2 15.5l8 4.5 8-4.5"/></svg>';
        case 'cohorts':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="3"/><circle cx="14" cy="7" r="2.5"/><path d="M2 17c0-2.8 2.2-4.5 5-4.5s5 1.7 5 4.5"/><path d="M13 12.6c1.8.3 3.5 1.5 3.5 4.4"/></svg>';
        case 'facilitators':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="6" r="3.5"/><path d="M3.5 17c0-3.2 2.9-5.5 6.5-5.5s6.5 2.3 6.5 5.5"/><circle cx="16" cy="15" r="2.5"/></svg>';
        case 'payments':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="16" height="12" rx="2"/><path d="M2 8.5h16"/><path d="M5.5 12.5h3"/></svg>';
        case 'documents':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 3h7l5 5v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M11 3v5h5"/></svg>';
        case 'communication':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18.5 2.5L9 11.5"/><path d="M18.5 2.5L12.5 18l-3.5-6.5-6.5-3.5 16-5.5z"/></svg>';
        case 'verification':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2l7 3v5c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V5l7-3z"/><path d="M7 10.5l2 2 4.5-4.5"/></svg>';
        case 'calendar':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="14" rx="2"/><path d="M6 2v4"/><path d="M14 2v4"/><path d="M2.5 8.5h15"/><circle cx="7" cy="12" r=".7"/><circle cx="10" cy="12" r=".7"/><circle cx="13" cy="12" r=".7"/><circle cx="7" cy="15" r=".7"/><circle cx="10" cy="15" r=".7"/></svg>';
        case 'reports':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17V3"/><path d="M3 17h14"/><rect x="6" y="9" width="2.5" height="5" rx=".5"/><rect x="10.5" y="5" width="2.5" height="9" rx=".5"/><rect x="15" y="7" width="2.5" height="7" rx=".5"/></svg>';
        case 'settings':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h8M15 5h2M11 3v4"/><path d="M3 10h3M10 10h7M6 8v4"/><path d="M3 15h11M17 15h0M14 13v4"/></svg>';
        case 'profile':
            return '<svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="8"/><circle cx="10" cy="8" r="3"/><path d="M5.5 15.5a6 6 0 0 1 9 0"/></svg>';
        default:
            return '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="9" r="6"/></svg>';
    }
}

/** Get permalink to the Admin Dashboard page. */
function tfp_admin_dashboard_page_url() {
    $current_id = get_queried_object_id();
    if ($current_id && is_page($current_id)) {
        return remove_query_arg(['view', 'tfp_admin_page', 'user_search'], get_permalink($current_id));
    }
    $pages = get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_key' => '_wp_page_template',
        'meta_value' => 'tfp-dashboard-admin-home',
        'fields' => 'ids',
    ]);
    if (!empty($pages)) {
        return get_permalink($pages[0]);
    }
    $page = get_page_by_path('admin-dashboard');
    if ($page) {
        return get_permalink($page->ID);
    }
    if (isset($_SERVER['REQUEST_URI'])) {
        $clean_uri = strtok($_SERVER['REQUEST_URI'], '?');
        return home_url($clean_uri);
    }
    return home_url('/tfp-admin-dashboard');
}

/** Build standard navigation items for Admin Dashboard matching the reference design. */
function tfp_admin_dashboard_nav_items() {
    $dashboard_url = tfp_admin_dashboard_page_url();
    // Sidebar Users opens the Users landing page; its View All opens User Overview.
    $users_url = function_exists('tfp_admin_users_home_url')
        ? tfp_admin_users_home_url()
        : home_url('/admin-users/');

    return [
        ['slug' => 'dashboard', 'label' => __('Dashboard', 'tfp-dashboard'), 'url' => $dashboard_url, 'active' => true],
        ['slug' => 'users', 'label' => __('Users', 'tfp-dashboard'), 'url' => $users_url, 'active' => false],
        ['slug' => 'courses', 'label' => __('Courses', 'tfp-dashboard'), 'url' => admin_url('edit.php?post_type=sfwd-courses')],
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

/** Resolve the complete live admin dashboard data set. */
function tfp_admin_dashboard_get_data() {
    $user_counts = count_users();
    $roles = (array) ($user_counts['avail_roles'] ?? []);
    $student_roles = ['subscriber', 'student'];
    $students = count(get_users([
        'role__in' => $student_roles,
        'meta_key' => 'tfp_cohort_choice',
        'meta_compare' => 'EXISTS',
        'fields' => 'ID',
        'number' => -1,
    ]));

    $cohort_posts = get_posts([
        'post_type' => 'tfp_cohort',
        'post_status' => ['publish', 'private'],
        'posts_per_page' => 9,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    $recent_users = get_users([
        'number' => 9,
        'orderby' => 'registered',
        'order' => 'DESC',
    ]);

    $orders = [];
    $payment_total = 0.0;
    $failed_orders = 0;
    $pending_orders = 0;
    if (function_exists('wc_get_orders')) {
        $orders = wc_get_orders([
            'limit' => 9,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array_map(static function ($status) { return preg_replace('/^wc-/', '', $status); }, array_keys(wc_get_order_statuses())),
        ]);
        $page = 1;
        do {
            $sales_batch = wc_get_orders([
                'status' => ['processing', 'completed'],
                'limit' => 100,
                'page' => $page,
                'paginate' => true,
                'return' => 'objects',
            ]);
            $batch_orders = (is_object($sales_batch) && isset($sales_batch->orders)) ? $sales_batch->orders : [];
            foreach ($batch_orders as $sales_order) {
                if ($sales_order instanceof WC_Order) {
                    $payment_total += (float) $sales_order->get_total();
                }
            }
            $max_pages = (is_object($sales_batch) && isset($sales_batch->max_num_pages)) ? (int) $sales_batch->max_num_pages : 0;
            $page++;
        } while ($page <= $max_pages);
        $failed_orders = function_exists('wc_orders_count') ? (int) wc_orders_count('failed') : 0;
        $pending_orders = function_exists('wc_orders_count') ? (int) wc_orders_count('pending') : 0;
    }

    $pending_aid = [];
    $pending_aid_count = 0;
    if (post_type_exists('tfp_financial_aid')) {
        $pending_aid_query = new WP_Query([
            'post_type' => 'tfp_financial_aid',
            'post_status' => ['publish', 'private', 'pending'],
            'posts_per_page' => 7,
            'orderby' => 'date',
            'order' => 'ASC',
            'meta_query' => [[
                'key' => '_tfp_status',
                'value' => 'pending',
                'compare' => '=',
            ]],
        ]);
        $pending_aid = $pending_aid_query->posts;
        $pending_aid_count = (int) $pending_aid_query->found_posts;
    }

    $checks = [];
    $checks[] = [
        'type' => 'user',
        'label' => __('User Accounts', 'tfp-dashboard'),
        'detail' => sprintf(_n('%s registered account', '%s registered accounts', (int) ($user_counts['total_users'] ?? 0), 'tfp-dashboard'), number_format_i18n((int) ($user_counts['total_users'] ?? 0))),
        'status' => __('Good', 'tfp-dashboard'),
        'class' => 'is-good',
    ];
    $checks[] = [
        'type' => 'email',
        'label' => __('Email Configuration', 'tfp-dashboard'),
        'detail' => __('Delivery provider is not tested by this dashboard.', 'tfp-dashboard'),
        'status' => __('Not Tested', 'tfp-dashboard'),
        'class' => 'is-degraded',
    ];
    $checks[] = [
        'type' => 'billing',
        'label' => __('Billing', 'tfp-dashboard'),
        'detail' => function_exists('wc_get_orders') ? sprintf(__('WooCommerce active · %s failed orders', 'tfp-dashboard'), number_format_i18n($failed_orders)) : __('WooCommerce is not active.', 'tfp-dashboard'),
        'status' => function_exists('wc_get_orders') ? ($failed_orders > 0 ? __('Review', 'tfp-dashboard') : __('Good', 'tfp-dashboard')) : __('Unavailable', 'tfp-dashboard'),
        'class' => function_exists('wc_get_orders') ? ($failed_orders > 0 ? 'is-degraded' : 'is-good') : 'is-down',
    ];
    $checks[] = [
        'type' => 'documents',
        'label' => __('Documents', 'tfp-dashboard'),
        'detail' => wp_upload_dir()['error'] ? __('WordPress uploads directory reports an error.', 'tfp-dashboard') : __('WordPress uploads directory is available.', 'tfp-dashboard'),
        'status' => wp_upload_dir()['error'] ? __('Review', 'tfp-dashboard') : __('Good', 'tfp-dashboard'),
        'class' => wp_upload_dir()['error'] ? 'is-degraded' : 'is-good',
    ];
    $checks[] = [
        'type' => 'passwords',
        'label' => __('Password Security', 'tfp-dashboard'),
        'detail' => __('WordPress authentication is enabled.', 'tfp-dashboard'),
        'status' => __('Good', 'tfp-dashboard'),
        'class' => 'is-good',
    ];

    $all_cohorts_count = tfp_admin_dashboard_count_posts('tfp_cohort', 'publish');
    $system_alerts = $failed_orders + $pending_orders + $pending_aid_count;

    return [
        'students' => $students,
        'payments' => $payment_total,
        'cohort_count' => count($cohort_posts),
        'all_cohorts' => $all_cohorts_count,
        'alerts' => $system_alerts,
        'users' => $recent_users,
        'orders' => $orders,
        'cohorts' => $cohort_posts,
        'approvals' => $pending_aid,
        'approval_count' => $pending_aid_count,
        'checks' => $checks,
        'failed_orders' => $failed_orders,
    ];
}

/** Format a date timestamp in consistent dashboard format. */
function tfp_admin_dashboard_date($date, $format = 'Y-m-d, H:i') {
    if (!$date) {
        return '—';
    }
    $timestamp = is_numeric($date) ? (int) $date : strtotime($date);
    return $timestamp ? wp_date($format, $timestamp) : '—';
}

/** Enqueue Admin Dashboard assets. */
function tfp_admin_dashboard_enqueue_assets() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $dash_url = defined('TFP_DASH_URL') ? TFP_DASH_URL : plugin_dir_url(dirname(dirname(__FILE__)));
    $dash_ver = defined('TFP_DASH_VERSION') ? TFP_DASH_VERSION : '1.0.0';
    $css_file = dirname(__DIR__, 2) . '/assets/css/admin-dashboard.css';
    $js_file = dirname(__DIR__, 2) . '/assets/js/admin-dashboard.js';
    $css_ver = file_exists($css_file) ? (string) filemtime($css_file) : $dash_ver;
    $js_ver = file_exists($js_file) ? (string) filemtime($js_file) : $dash_ver;

    wp_enqueue_style('dashicons');
    wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
    wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
    wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
    wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], $css_ver);
    wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], $js_ver, true);
}

/** Render the complete custom Admin Dashboard view. */
function tfp_admin_dashboard_render() {
    tfp_admin_dashboard_enqueue_assets();
    // Strictly verify Administrator user access.
    if (!is_user_logged_in()) {
        auth_redirect();
    }
    if (!current_user_can('manage_options') && !current_user_can('administrator')) {
        wp_die(
            esc_html__('You do not have permission to access this page.', 'tfp-dashboard'),
            esc_html__('Access denied', 'tfp-dashboard'),
            ['response' => 403]
        );
    }

    $data = tfp_admin_dashboard_get_data();
    $user = wp_get_current_user();
    $name = $user->display_name ?: __('Administrator', 'tfp-dashboard');
    $first_name = get_user_meta($user->ID, 'first_name', true);
    if (!$first_name) {
        $first_name = preg_split('/\s+/', trim($name))[0];
    }
    $avatar = get_avatar_url($user->ID, ['size' => 96]);
    $nav = tfp_admin_dashboard_nav_items();
    $status_disrupted = $data['alerts'] > 0;
    ?>
    <div class="tfp-admin-app">
        <!-- Sidebar Navigation -->
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-sidebar-top">
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="14" height="14" rx="2"></rect><path d="M6 2v14"></path></svg>
                </button>
            </div>
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(tfp_admin_dashboard_page_url()); ?>">
                    <img src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
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
                    <span>
                        <strong><?php echo esc_html($name); ?></strong>
                        <small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small>
                    </span>
                </div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">
                    <span aria-hidden="true">✕</span>
                    <?php esc_html_e('Logout', 'tfp-dashboard'); ?>
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar">
                <button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button>
                <span><?php esc_html_e('Admin Console', 'tfp-dashboard'); ?></span>
            </div>

            <!-- Breadcrumb matching reference image -->
            <div class="tfp-admin-breadcrumb">
                <span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span>
                <b>/</b>
                <strong><?php esc_html_e('Console', 'tfp-dashboard'); ?></strong>
            </div>

            <!-- Page Header with Welcome & Operational Status Badge -->
            <header class="tfp-admin-page-header">
                <div>
                    <h1><?php echo esc_html(sprintf(__('Welcome, %s', 'tfp-dashboard'), $first_name)); ?></h1>
                    <p><?php esc_html_e('Current operational status and critical pathway metrics for the project.', 'tfp-dashboard'); ?></p>
                </div>
                <div class="tfp-admin-operational-status <?php echo $status_disrupted ? 'is-disrupted' : 'is-operational'; ?>">
                    <span class="tfp-admin-status-dot"></span>
                    <?php echo $status_disrupted ? esc_html__('STATUS: DISRUPTED', 'tfp-dashboard') : esc_html__('STATUS: OPERATIONAL', 'tfp-dashboard'); ?>
                </div>
            </header>

            <main class="tfp-admin-content">
                <!-- 4 Top Stat Metric Cards matching reference image -->
                <section class="tfp-admin-stat-grid" aria-label="<?php esc_attr_e('Platform metrics', 'tfp-dashboard'); ?>">
                    <article class="tfp-admin-stat-card">
                        <small><?php esc_html_e('Total Students Enrolled', 'tfp-dashboard'); ?></small>
                        <div class="tfp-admin-stat-value">
                            <strong><?php echo esc_html(number_format_i18n($data['students'])); ?></strong>
                            
                        </div>
                        <p><?php esc_html_e('Active students in cohorts', 'tfp-dashboard'); ?></p>
                    </article>
                    <article class="tfp-admin-stat-card">
                        <small><?php esc_html_e('Total Payments Collected', 'tfp-dashboard'); ?></small>
                        <div class="tfp-admin-stat-value">
                            <strong><?php echo wp_kses_post(function_exists('wc_price') ? wc_price($data['payments']) : number_format_i18n($data['payments'], 2)); ?></strong>
                            
                        </div>
                        <p><?php esc_html_e('Gross collected (excl. refunds)', 'tfp-dashboard'); ?></p>
                    </article>
                    <article class="tfp-admin-stat-card">
                        <small><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></small>
                        <div class="tfp-admin-stat-value">
                            <strong><?php echo esc_html($data['all_cohorts']); ?></strong>
                            
                        </div>
                        <p><?php esc_html_e('Cohorts with status Active', 'tfp-dashboard'); ?></p>
                    </article>
                    <article class="tfp-admin-stat-card">
                        <small><?php esc_html_e('System Alerts', 'tfp-dashboard'); ?></small>
                        <div class="tfp-admin-stat-value">
                            <strong><?php echo esc_html($data['alerts']); ?></strong>
                        </div>
                        <p><?php esc_html_e('Items needing review', 'tfp-dashboard'); ?></p>
                    </article>
                </section>

                <!-- Middle 3-Column Grid matching reference image -->
                <section class="tfp-admin-top-grid">
                    <!-- Column 1: Recent User Activity -->
                    <article class="tfp-admin-panel tfp-admin-activity">
                        <div class="tfp-admin-panel-heading">
                            <div>
                                <h2><?php esc_html_e('Recent User Activity', 'tfp-dashboard'); ?></h2>
                            </div>
                            <a href="<?php echo esc_url(tfp_admin_users_home_url()); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                        </div>
                        <div class="tfp-admin-activity-list">
                            <?php
                            $activity_rows = [];
                            if ($data['users']) {
                                foreach ($data['users'] as $person) {
                                    $user_roles = (array) $person->roles;
                                    $role_label = $user_roles ? translate_user_role(reset($user_roles)) : __('Student', 'tfp-dashboard');
                                    $activity_rows[] = [
                                        'event' => $person->display_name ?: $person->user_login,
                                        'date' => tfp_admin_dashboard_date($person->user_registered, 'Y-m-d, H:i'),
                                        'who' => $role_label,
                                        'what' => get_user_meta($person->ID, 'tfp_cohort_choice', true) ? get_the_title(absint(get_user_meta($person->ID, 'tfp_cohort_choice', true))) : __('No cohort assigned', 'tfp-dashboard'),
                                        'link' => get_edit_user_link($person->ID),
                                    ];
                                }
                            }
                            foreach ($activity_rows as $row) :
                            ?>
                                <a class="tfp-admin-activity-row" href="<?php echo esc_url($row['link']); ?>">
                                    <div class="tfp-admin-activity-col1">
                                        <strong><?php echo esc_html($row['event']); ?></strong>
                                        <time><?php echo esc_html($row['date']); ?></time>
                                    </div>
                                    <div class="tfp-admin-activity-col2">
                                        <span><?php echo esc_html($row['who']); ?></span>
                                    </div>
                                    <div class="tfp-admin-activity-col3">
                                        <span><?php echo esc_html($row['what']); ?></span>
                                    </div>
                                    <b class="tfp-admin-chevron" aria-hidden="true">›</b>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($activity_rows)) : ?>
                                <p class="tfp-admin-empty-state"><?php esc_html_e('No recent user activity found.', 'tfp-dashboard'); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>

                    <!-- Column 2: Recent Payments -->
                    <article class="tfp-admin-panel tfp-admin-payments">
                        <div class="tfp-admin-panel-heading">
                            <div>
                                <h2><?php esc_html_e('Recent Payments', 'tfp-dashboard'); ?></h2>
                            </div>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                        </div>
                        <div class="tfp-admin-payment-list">
                            <?php
                            $payment_rows = [];
                            if ($data['orders']) {
                                foreach ($data['orders'] as $order) {
                                    $customer = $order->get_formatted_billing_full_name() ?: __('Guest customer', 'tfp-dashboard');
                                    $order_date = $order->get_date_created();
                                    $payment_rows[] = [
                                        'name' => $customer,
                                        'date' => $order_date ? $order_date->date_i18n('Y-m-d, H:i') : '—',
                                        'amount' => wp_strip_all_tags(wc_price((float) $order->get_total(), ['currency' => $order->get_currency()])),
                                        'link' => $order->get_edit_order_url(),
                                    ];
                                }
                            }
                            foreach ($payment_rows as $row) :
                            ?>
                                <a class="tfp-admin-payment-row" href="<?php echo esc_url($row['link']); ?>">
                                    <div class="tfp-admin-payment-info">
                                        <strong><?php echo esc_html($row['name']); ?></strong>
                                        <small><?php echo esc_html($row['date']); ?></small>
                                    </div>
                                    <div class="tfp-admin-payment-meta">
                                        <b><?php echo esc_html($row['amount']); ?></b>
                                        <i class="tfp-admin-chevron" aria-hidden="true">›</i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($payment_rows)) : ?>
                                <p class="tfp-admin-empty-state"><?php esc_html_e('No payment records found.', 'tfp-dashboard'); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>

                    <!-- Column 3: System Health -->
                    <article class="tfp-admin-panel tfp-admin-health">
                        <div class="tfp-admin-panel-heading">
                            <div>
                                <h2><?php esc_html_e('System Health', 'tfp-dashboard'); ?></h2>
                            </div>
                            <a href="<?php echo esc_url(admin_url('site-health.php')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                        </div>
                        <div class="tfp-admin-health-list">
                            <?php foreach ($data['checks'] as $check) : ?>
                                <div class="tfp-admin-health-row">
                                    <div class="tfp-admin-health-avatar tfp-admin-health-avatar--<?php echo esc_attr($check['type']); ?>">
                                        <?php if ($check['type'] === 'user') : ?>
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><circle cx="10" cy="7" r="4"/><path d="M4 17c0-3 2.7-5 6-5s6 2 6 5"/></svg>
                                        <?php elseif ($check['type'] === 'email') : ?>
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm1 2v.4l6 3.6 6-3.6V6H4zm12 8V8.2l-5.5 3.3a1 1 0 0 1-1 0L4 8.2V14h12z"/></svg>
                                        <?php elseif ($check['type'] === 'billing') : ?>
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><rect x="2" y="4" width="16" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M2 8.5h16" stroke="currentColor" stroke-width="2"/></svg>
                                        <?php elseif ($check['type'] === 'documents') : ?>
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3h7l5 5v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/></svg>
                                        <?php else : ?>
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><rect x="4" y="9" width="12" height="8" rx="1.5"/><path d="M6.5 9V6a3.5 3.5 0 0 1 7 0v3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tfp-admin-health-info">
                                        <strong><?php echo esc_html($check['label']); ?></strong>
                                        <small><?php echo esc_html($check['detail']); ?></small>
                                    </div>
                                    <span class="tfp-admin-health-badge <?php echo esc_attr($check['class']); ?>">
                                        <?php echo esc_html($check['status']); ?>
                                    </span>
                                    <i class="tfp-admin-chevron" aria-hidden="true">›</i>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <!-- Bottom 2-Column Grid matching reference image -->
                <section class="tfp-admin-bottom-grid">
                    <!-- Column 1: Approval Queue -->
                    <article class="tfp-admin-panel tfp-admin-approvals">
                        <div class="tfp-admin-panel-heading">
                            <div>
                                <h2><?php esc_html_e('Approval Que', 'tfp-dashboard'); ?></h2>
                                <p><?php esc_html_e('Requests that need a decision.', 'tfp-dashboard'); ?></p>
                            </div>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=tfp_financial_aid')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                        </div>
                        <div class="tfp-admin-approval-list">
                            <?php
                            $approval_rows = [];
                            if ($data['approvals']) {
                                foreach ($data['approvals'] as $app) {
                                    $student_id = (int) get_post_meta($app->ID, '_tfp_student_id', true);
                                    $student = $student_id ? get_userdata($student_id) : false;
                                    $app_title = $student ? $student->display_name . ', Skip week request' : get_the_title($app);
                                    $approval_rows[] = [
                                        'title' => $app_title,
                                        'meta' => get_post_meta($app->ID, '_tfp_week_label', true) ?: get_the_date('', $app),
                                        'url' => get_edit_post_link($app->ID),
                                    ];
                                }
                            }
                            foreach ($approval_rows as $item) :
                            ?>
                                <div class="tfp-admin-approval-row">
                                    <div class="tfp-admin-approval-info">
                                        <strong><?php echo esc_html($item['title']); ?></strong>
                                        <small><?php echo esc_html($item['meta']); ?></small>
                                    </div>
                                    <!-- Action Button strictly matches Student and Faculty .tfp-grade-btn.tfp-grade-btn--view -->
                                    <a class="tfp-grade-btn tfp-grade-btn--view" href="<?php echo esc_url($item['url']); ?>">
                                        <?php esc_html_e('Review', 'tfp-dashboard'); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($approval_rows)) : ?>
                                <p class="tfp-admin-empty-state"><?php esc_html_e('No pending approvals.', 'tfp-dashboard'); ?></p>
                            <?php endif; ?>
                        </div>
                    </article>

                    <!-- Column 2: Active Cohort Overview Table -->
                    <article class="tfp-admin-panel tfp-admin-cohorts">
                        <div class="tfp-admin-panel-heading">
                            <div>
                                <h2><?php esc_html_e('Active CohortOverview', 'tfp-dashboard'); ?></h2>
                                <p><?php esc_html_e('Current cohorts, their progress, and the facilitators guiding them.', 'tfp-dashboard'); ?></p>
                            </div>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=tfp_cohort')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a>
                        </div>

                        <!-- Table strictly wraps in .tfp-dash-table-wrap and uses .tfp-dash-table -->
                        <div class="tfp-dash-table-wrap tfp-admin-table-wrap">
                            <table class="tfp-dash-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Cohort', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th>
                                        <th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $cohort_list = [];
                                    foreach ($data['cohorts'] as $cohort_post) {
                                        $facilitator_id = function_exists('tfp_cohort_get_facilitator_user_id')
                                            ? absint(tfp_cohort_get_facilitator_user_id($cohort_post->ID))
                                            : absint(get_post_meta($cohort_post->ID, 'tfp_facilitator_id', true));
                                        $facilitator = $facilitator_id ? get_userdata($facilitator_id) : false;
                                        $stored_status = get_post_meta($cohort_post->ID, 'tfp_status', true);
                                        if ($stored_status === '') {
                                            $stored_status = get_post_meta($cohort_post->ID, '_tfp_status', true);
                                        }
                                        $status_label = $stored_status !== '' ? sanitize_text_field((string) $stored_status) : get_post_status_object($cohort_post->post_status)->label;
                                        $status_type = sanitize_html_class(strtolower(str_replace([' ', '_'], '-', $status_label)));
                                        $current_week = get_post_meta($cohort_post->ID, 'tfp_current_week', true);
                                        $total_weeks = get_post_meta($cohort_post->ID, 'tfp_total_weeks', true);
                                        $week_label = ($current_week !== '' && $total_weeks !== '')
                                            ? sprintf(__('Week %1$s of %2$s', 'tfp-dashboard'), absint($current_week), absint($total_weeks))
                                            : '—';
                                        $cohort_list[] = [
                                            'cohort' => get_the_title($cohort_post),
                                            'week' => $week_label,
                                            'fac' => $facilitator ? $facilitator->display_name : '—',
                                            'status' => $status_label,
                                            'type' => $status_type,
                                            'url' => get_edit_post_link($cohort_post->ID),
                                        ];
                                    }

                                    foreach ($cohort_list as $row) :
                                    ?>
                                        <tr>
                                            <td>
                                                <strong class="tfp-dash-table__primary"><?php echo esc_html($row['cohort']); ?></strong>
                                                <small class="tfp-dash-table__muted"><?php echo esc_html($row['week']); ?></small>
                                            </td>
                                            <td><?php echo esc_html($row['fac']); ?></td>
                                            <td>
                                                <span class="tfp-admin-status-pill tfp-admin-status-pill--<?php echo esc_attr($row['type']); ?>">
                                                    <?php echo esc_html($row['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <!-- Action Button strictly matches Student and Faculty .tfp-grade-btn.tfp-grade-btn--view -->
                                                <a class="tfp-grade-btn tfp-grade-btn--view" href="<?php echo esc_url($row['url']); ?>">
                                                    <?php esc_html_e('View', 'tfp-dashboard'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($cohort_list)) : ?>
                                        <tr><td colspan="4" class="tfp-admin-empty-state"><?php esc_html_e('No cohorts found.', 'tfp-dashboard'); ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </div>
    <?php
}

/** Load Admin Dashboard assets whenever the admin dashboard is accessed. */
add_action('wp_enqueue_scripts', function () {
    $should_enqueue = false;
    if (function_exists('tfp_dashboard_current_template_slug') && tfp_dashboard_current_template_slug() === 'tfp-dashboard-admin-home') {
        $should_enqueue = true;
    } elseif (is_page()) {
        $page_id = get_the_ID();
        $tmpl = get_page_template_slug($page_id);
        if ($tmpl === 'tfp-dashboard-admin-home' || strpos((string) $tmpl, 'template-admin-home') !== false) {
            $should_enqueue = true;
        }
        $slug = get_post_field('post_name', $page_id);
        if (strpos((string) $slug, 'admin') !== false) {
            $should_enqueue = true;
        }
    } elseif (isset($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], 'admin') !== false)) {
        $should_enqueue = true;
    }

    if ($should_enqueue) {
        tfp_admin_dashboard_enqueue_assets();
    }
}, 5);
