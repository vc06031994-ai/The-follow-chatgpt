<?php
/**
 * TFP Admin Dashboard — responsive front-end administration console.
 * All dashboard values are read from existing WordPress/TFP/WooCommerce data.
 */
if (!defined('ABSPATH')) {
    exit;
}

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

/** Resolve the real admin dashboard data set. */
function tfp_admin_dashboard_get_data() {
    $user_counts = count_users();
    $roles = (array) ($user_counts['avail_roles'] ?? []);
    $staff_roles = ['administrator', 'editor', 'author', 'contributor', 'facilitator', 'group_leader', 'shop_manager'];
    $staff = 0;
    foreach ($staff_roles as $role) {
        $staff += (int) ($roles[$role] ?? 0);
    }
    $students = max(0, (int) ($user_counts['total_users'] ?? 0) - $staff);

    $cohort_posts = get_posts([
        'post_type' => 'tfp_cohort',
        'post_status' => 'publish',
        'posts_per_page' => 8,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $recent_users = get_users([
        'number' => 9,
        'orderby' => 'registered',
        'order' => 'DESC',
    ]);

    $orders = [];
    $payment_total = 0;
    $failed_orders = 0;
    if (function_exists('wc_get_orders')) {
        $orders = wc_get_orders([
            'limit' => 9,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['processing', 'completed'],
        ]);
        $payment_total = (float) wc_get_total_sales();
        $failed_orders = (int) wc_orders_count('failed');
    }

    $pending_aid = [];
    $pending_aid_count = 0;
    if (post_type_exists('tfp_financial_aid')) {
        // Financial-aid workflow status is stored in post meta, not WP post_status.
        $pending_aid_query = new WP_Query([
            'post_type' => 'tfp_financial_aid',
            'post_status' => 'any',
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

    $checks = [
        [
            'label' => __('WordPress Core', 'tfp-dashboard'),
            'detail' => get_bloginfo('version'),
            'ok' => true,
        ],
        [
            'label' => __('WooCommerce', 'tfp-dashboard'),
            'detail' => function_exists('WC') ? __('Connected', 'tfp-dashboard') : __('Not active', 'tfp-dashboard'),
            'ok' => function_exists('WC'),
        ],
        [
            'label' => __('LearnDash Courses', 'tfp-dashboard'),
            'detail' => post_type_exists('sfwd-courses') ? __('Available', 'tfp-dashboard') : __('Not available', 'tfp-dashboard'),
            'ok' => post_type_exists('sfwd-courses'),
        ],
        [
            'label' => __('TFP Cohort Records', 'tfp-dashboard'),
            'detail' => post_type_exists('tfp_cohort') ? __('Connected', 'tfp-dashboard') : __('Not available', 'tfp-dashboard'),
            'ok' => post_type_exists('tfp_cohort'),
        ],
        [
            'label' => __('Scheduled Tasks', 'tfp-dashboard'),
            'detail' => (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? __('WP-Cron disabled', 'tfp-dashboard') : __('Enabled', 'tfp-dashboard'),
            'ok' => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
        ],
    ];

    // Pending applications are workflow items, not system outages.
    $system_alerts = $failed_orders;
    foreach ($checks as $check) {
        if (!$check['ok']) {
            $system_alerts++;
        }
    }

    return [
        'students' => $students,
        'payments' => $payment_total,
        'cohort_count' => count($cohort_posts),
        'all_cohorts' => tfp_admin_dashboard_count_posts('tfp_cohort'),
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

/** Build a safe URL to an existing WordPress management screen. */
function tfp_admin_dashboard_nav_items() {
    return [
        ['label' => __('Dashboard', 'tfp-dashboard'), 'icon' => '▦', 'url' => (get_permalink(get_queried_object_id()) ?: admin_url('index.php')), 'active' => true],
        ['label' => __('Users', 'tfp-dashboard'), 'icon' => '♙', 'url' => admin_url('users.php')],
        ['label' => __('Courses', 'tfp-dashboard'), 'icon' => '▣', 'url' => admin_url('edit.php?post_type=sfwd-courses')],
        ['label' => __('Curriculum', 'tfp-dashboard'), 'icon' => '◈', 'url' => admin_url('edit.php?post_type=sfwd-lessons')],
        ['label' => __('Cohorts', 'tfp-dashboard'), 'icon' => '♧', 'url' => admin_url('edit.php?post_type=tfp_cohort')],
        ['label' => __('Facilitators', 'tfp-dashboard'), 'icon' => '♧', 'url' => admin_url('users.php?role=facilitator')],
        ['label' => __('Payments', 'tfp-dashboard'), 'icon' => '▤', 'url' => admin_url('edit.php?post_type=shop_order')],
        ['label' => __('Documents', 'tfp-dashboard'), 'icon' => '▣', 'url' => admin_url('upload.php')],
        ['label' => __('Communication', 'tfp-dashboard'), 'icon' => '➤', 'url' => admin_url('edit.php?post_type=tfp_ticket')],
        ['label' => __('Verification', 'tfp-dashboard'), 'icon' => '◇', 'url' => admin_url('edit.php?post_type=tfp_financial_aid')],
        ['label' => __('Calendar', 'tfp-dashboard'), 'icon' => '▦', 'url' => admin_url('admin.php?page=tfp-attendance-history')],
        ['label' => __('Reports', 'tfp-dashboard'), 'icon' => '▥', 'url' => admin_url('admin.php?page=tfp-weekly-submissions')],
        ['label' => __('Settings', 'tfp-dashboard'), 'icon' => '⚙', 'url' => admin_url('options-general.php')],
        ['label' => __('Profile', 'tfp-dashboard'), 'icon' => '◎', 'url' => admin_url('profile.php')],
    ];
}

/** Format a user's registration date in the site's configured timezone. */
function tfp_admin_dashboard_date($date, $format = '') {
    if (!$date) {
        return '—';
    }
    $timestamp = is_numeric($date) ? (int) $date : strtotime($date);
    return $timestamp ? wp_date($format ?: get_option('date_format') . ' · ' . get_option('time_format'), $timestamp) : '—';
}

/** Render the complete custom dashboard application view. */
function tfp_admin_dashboard_render() {
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
        <aside class="tfp-admin-sidebar" id="tfp-admin-sidebar">
            <div class="tfp-admin-brand-row">
                <a class="tfp-admin-brand" href="<?php echo esc_url(home_url('/tfp-admin-dashboard')); ?>">
                    <img src="<?php echo esc_url(TFP_DASH_URL . 'assets/images/logo-expanded.svg'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                </a>
                <button class="tfp-admin-sidebar-toggle" type="button" aria-label="<?php esc_attr_e('Toggle navigation', 'tfp-dashboard'); ?>" data-tfp-admin-toggle>▥</button>
            </div>
            <nav class="tfp-admin-nav" aria-label="<?php esc_attr_e('Admin navigation', 'tfp-dashboard'); ?>">
                <?php foreach ($nav as $item) : ?>
                    <a class="tfp-admin-nav-link<?php echo !empty($item['active']) ? ' is-active' : ''; ?>" href="<?php echo esc_url($item['url']); ?>">
                        <span class="tfp-admin-nav-icon" aria-hidden="true"><?php echo esc_html($item['icon']); ?></span>
                        <span class="tfp-admin-nav-label"><?php echo esc_html($item['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="tfp-admin-sidebar-bottom">
                <div class="tfp-admin-profile">
                    <img src="<?php echo esc_url($avatar); ?>" alt="">
                    <span><strong><?php echo esc_html($name); ?></strong><small><?php esc_html_e('Administrator', 'tfp-dashboard'); ?></small></span>
                </div>
                <a class="tfp-admin-logout" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><span aria-hidden="true">×</span><?php esc_html_e('Logout', 'tfp-dashboard'); ?></a>
            </div>
        </aside>

        <div class="tfp-admin-main">
            <div class="tfp-admin-mobilebar">
                <button type="button" data-tfp-admin-toggle aria-label="<?php esc_attr_e('Open menu', 'tfp-dashboard'); ?>">☰</button>
                <span><?php esc_html_e('Admin Console', 'tfp-dashboard'); ?></span>
            </div>
            <div class="tfp-admin-breadcrumb"><span><?php esc_html_e('Admin', 'tfp-dashboard'); ?></span><b>/</b><strong><?php esc_html_e('Console', 'tfp-dashboard'); ?></strong></div>
            <header class="tfp-admin-page-header">
                <div>
                    <h1><?php echo esc_html(sprintf(__('Welcome, %s', 'tfp-dashboard'), $first_name)); ?></h1>
                    <p><?php esc_html_e('Current operational status and critical pathway metrics for the project.', 'tfp-dashboard'); ?></p>
                </div>
                <div class="tfp-admin-operational-status <?php echo $status_disrupted ? 'is-disrupted' : 'is-operational'; ?>">
                    <span></span><?php echo $status_disrupted ? esc_html__('STATUS: DISRUPTED', 'tfp-dashboard') : esc_html__('STATUS: OPERATIONAL', 'tfp-dashboard'); ?>
                </div>
            </header>

            <main class="tfp-admin-content">
                <section class="tfp-admin-stat-grid" aria-label="<?php esc_attr_e('Platform metrics', 'tfp-dashboard'); ?>">
                    <article class="tfp-admin-stat-card"><small><?php esc_html_e('Total Students Enrolled', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['students'])); ?></strong><p><?php esc_html_e('Registered non-staff accounts', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-stat-card"><small><?php esc_html_e('Total Payments Collected', 'tfp-dashboard'); ?></small><strong><?php echo function_exists('wc_price') ? wp_kses_post(wc_price($data['payments'])) : esc_html(number_format_i18n($data['payments'], 2)); ?></strong><p><?php esc_html_e('WooCommerce lifetime sales', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-stat-card"><small><?php esc_html_e('Active Cohorts', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['all_cohorts'])); ?></strong><p><?php esc_html_e('Published cohort records', 'tfp-dashboard'); ?></p></article>
                    <article class="tfp-admin-stat-card"><small><?php esc_html_e('System Alerts', 'tfp-dashboard'); ?></small><strong><?php echo esc_html(number_format_i18n($data['alerts'])); ?></strong><p><?php esc_html_e('Items requiring attention', 'tfp-dashboard'); ?></p></article>
                </section>

                <section class="tfp-admin-top-grid">
                    <article class="tfp-admin-panel tfp-admin-activity">
                        <div class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Recent User Activity', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Latest account registrations', 'tfp-dashboard'); ?></p></div><a href="<?php echo esc_url(admin_url('users.php')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <?php if ($data['users']) : ?>
                            <div class="tfp-admin-activity-list">
                                <?php foreach ($data['users'] as $person) : ?>
                                    <a class="tfp-admin-activity-row" href="<?php echo esc_url(get_edit_user_link($person->ID)); ?>">
                                        <span class="tfp-admin-activity-primary"><strong><?php echo esc_html($person->display_name); ?></strong><small><?php echo esc_html(implode(', ', array_map('translate_user_role', (array) $person->roles))); ?></small></span>
                                        <span class="tfp-admin-activity-secondary"><?php echo esc_html__('Account registered', 'tfp-dashboard'); ?></span>
                                        <time><?php echo esc_html(tfp_admin_dashboard_date($person->user_registered, 'M j, Y · g:i a')); ?></time>
                                        <b aria-hidden="true">›</b>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?><p class="tfp-admin-empty"><?php esc_html_e('No registered users found.', 'tfp-dashboard'); ?></p><?php endif; ?>
                    </article>

                    <article class="tfp-admin-panel tfp-admin-payments">
                        <div class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Recent Payments', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Latest WooCommerce orders', 'tfp-dashboard'); ?></p></div><a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <?php if ($data['orders']) : ?>
                            <div class="tfp-admin-payment-list">
                                <?php foreach ($data['orders'] as $order) :
                                    $customer = $order->get_formatted_billing_full_name();
                                    if (!$customer) { $customer = __('Guest customer', 'tfp-dashboard'); }
                                    $order_date = $order->get_date_created();
                                    ?>
                                    <a class="tfp-admin-payment-row" href="<?php echo esc_url($order->get_edit_order_url()); ?>">
                                        <span><strong><?php echo esc_html($customer); ?></strong><small><?php echo esc_html($order_date ? $order_date->date_i18n('M j, Y · g:i a') : '—'); ?></small></span>
                                        <b><?php echo wp_kses_post($order->get_formatted_order_total()); ?></b><i aria-hidden="true">›</i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?><p class="tfp-admin-empty"><?php esc_html_e('WooCommerce is inactive or no orders are available.', 'tfp-dashboard'); ?></p><?php endif; ?>
                    </article>

                    <article class="tfp-admin-panel tfp-admin-health">
                        <div class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('System Health', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Live integration checks', 'tfp-dashboard'); ?></p></div><a href="<?php echo esc_url(admin_url('site-health.php')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-health-list">
                            <?php foreach ($data['checks'] as $check) : ?>
                                <div class="tfp-admin-health-row"><span class="tfp-admin-health-icon <?php echo $check['ok'] ? 'is-good' : 'is-bad'; ?>"><?php echo $check['ok'] ? '✓' : '!'; ?></span><span><strong><?php echo esc_html($check['label']); ?></strong><small><?php echo esc_html($check['detail']); ?></small></span><b class="<?php echo $check['ok'] ? 'is-good' : 'is-bad'; ?>"><?php echo $check['ok'] ? esc_html__('Good', 'tfp-dashboard') : esc_html__('Degraded', 'tfp-dashboard'); ?></b></div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </section>

                <section class="tfp-admin-bottom-grid">
                    <article class="tfp-admin-panel tfp-admin-approvals">
                        <div class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Approval Queue', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Financial aid requests waiting for a decision.', 'tfp-dashboard'); ?></p></div><a href="<?php echo esc_url(admin_url('edit.php?post_type=tfp_financial_aid')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <?php if ($data['approvals']) : ?>
                            <div class="tfp-admin-approval-list">
                                <?php foreach ($data['approvals'] as $application) :
                                    $student_id = (int) get_post_meta($application->ID, '_tfp_student_id', true);
                                    $student = $student_id ? get_userdata($student_id) : false;
                                    ?>
                                    <div class="tfp-admin-approval-row"><span><strong><?php echo esc_html($student ? $student->display_name : get_the_title($application)); ?></strong><small><?php echo esc_html(sprintf(__('Submitted %s', 'tfp-dashboard'), tfp_admin_dashboard_date($application->post_date, 'M j, Y'))); ?></small></span><a href="<?php echo esc_url(get_edit_post_link($application->ID)); ?>"><?php esc_html_e('Review', 'tfp-dashboard'); ?></a></div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?><p class="tfp-admin-empty"><?php esc_html_e('No pending applications. The approval queue is clear.', 'tfp-dashboard'); ?></p><?php endif; ?>
                    </article>

                    <article class="tfp-admin-panel tfp-admin-cohorts">
                        <div class="tfp-admin-panel-heading"><div><h2><?php esc_html_e('Active Cohort Overview', 'tfp-dashboard'); ?></h2><p><?php esc_html_e('Current cohorts and their assigned facilitators.', 'tfp-dashboard'); ?></p></div><a href="<?php echo esc_url(admin_url('edit.php?post_type=tfp_cohort')); ?>"><?php esc_html_e('View All', 'tfp-dashboard'); ?></a></div>
                        <div class="tfp-admin-table-wrap">
                            <table class="tfp-admin-table"><thead><tr><th><?php esc_html_e('Cohort', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Facilitator', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Status', 'tfp-dashboard'); ?></th><th><?php esc_html_e('Action', 'tfp-dashboard'); ?></th></tr></thead><tbody>
                                <?php if ($data['cohorts']) : foreach ($data['cohorts'] as $cohort) :
                                    $facilitator_id = function_exists('tfp_cohort_get_facilitator_user_id') ? tfp_cohort_get_facilitator_user_id($cohort->ID) : 0;
                                    $facilitator = $facilitator_id ? get_userdata($facilitator_id) : false;
                                    $start = get_post_meta($cohort->ID, '_start_date', true);
                                    $cohort_status = $start && strtotime($start) > current_time('timestamp') ? __('Scheduled', 'tfp-dashboard') : __('Active', 'tfp-dashboard');
                                    ?>
                                    <tr><td><strong><?php echo esc_html(get_the_title($cohort)); ?></strong><small><?php echo esc_html($start ? tfp_admin_dashboard_date($start, 'M j, Y') : __('Start date not set', 'tfp-dashboard')); ?></small></td><td><?php echo esc_html($facilitator ? $facilitator->display_name : __('Unassigned', 'tfp-dashboard')); ?></td><td><span class="tfp-admin-status-pill <?php echo $cohort_status === __('Active', 'tfp-dashboard') ? 'is-active' : 'is-scheduled'; ?>"><?php echo esc_html($cohort_status); ?></span></td><td><a class="tfp-admin-view-btn" href="<?php echo esc_url(get_edit_post_link($cohort->ID)); ?>"><?php esc_html_e('View', 'tfp-dashboard'); ?></a></td></tr>
                                <?php endforeach; else : ?><tr><td colspan="4" class="tfp-admin-empty"><?php esc_html_e('No published cohorts found.', 'tfp-dashboard'); ?></td></tr><?php endif; ?>
                            </tbody></table>
                        </div>
                    </article>
                </section>
            </main>
        </div>
    </div>
    <?php
}


/** Load Admin Dashboard assets only when its page template is active. */
add_action('wp_enqueue_scripts', function () {
    if (!function_exists('tfp_dashboard_current_template_slug') || tfp_dashboard_current_template_slug() !== 'tfp-dashboard-admin-home') {
        return;
    }

    wp_enqueue_style('dashicons');
    wp_enqueue_style('tfp-admin-dashboard', TFP_DASH_URL . 'assets/css/admin-dashboard.css', [], TFP_DASH_VERSION);
    wp_enqueue_script('tfp-admin-dashboard', TFP_DASH_URL . 'assets/js/admin-dashboard.js', [], TFP_DASH_VERSION, true);
});
