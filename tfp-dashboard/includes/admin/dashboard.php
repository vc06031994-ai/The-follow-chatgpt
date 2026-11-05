<?php
/**
 * TFP Admin Dashboard — Overview screen.
 * Displays live WordPress, LearnDash, WooCommerce and TFP cohort totals.
 */
if (!defined('ABSPATH')) {
    exit;
}

/** Register the custom overview screen for site administrators. */
add_action('admin_menu', function () {
    add_menu_page(
        __('TFP Admin Dashboard', 'tfp-dashboard'),
        __('TFP Dashboard', 'tfp-dashboard'),
        'manage_options',
        'tfp-admin-dashboard',
        'tfp_admin_dashboard_render',
        'dashicons-dashboard',
        3
    );
});

/** Load styles only on the TFP dashboard screen. */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_tfp-admin-dashboard') {
        return;
    }

    wp_enqueue_style(
        'tfp-admin-dashboard',
        TFP_DASH_URL . 'assets/css/admin-dashboard.css',
        [],
        TFP_DASH_VERSION
    );
});

/** Return a published post count without assuming the post type exists. */
function tfp_admin_dashboard_post_count($post_type) {
    if (!post_type_exists($post_type)) {
        return 0;
    }

    $counts = wp_count_posts($post_type);
    return isset($counts->publish) ? (int) $counts->publish : 0;
}

/** Render the live overview page. */
function tfp_admin_dashboard_render() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'));
    }

    $user_counts = count_users();
    $roles = isset($user_counts['avail_roles']) ? $user_counts['avail_roles'] : [];
    $facilitators = (int) ($roles['facilitator'] ?? 0) + (int) ($roles['group_leader'] ?? 0);
    $administrators = (int) ($roles['administrator'] ?? 0);
    $students = max(0, (int) $user_counts['total_users'] - $facilitators - $administrators);

    $cohorts = tfp_admin_dashboard_post_count('tfp_cohort');
    $courses = tfp_admin_dashboard_post_count('sfwd-courses');
    $orders = function_exists('wc_get_orders')
        ? (int) wc_orders_count('completed') + (int) wc_orders_count('processing')
        : 0;

    $cards = [
        [
            'label' => __('Total Students', 'tfp-dashboard'),
            'value' => number_format_i18n($students),
            'icon'  => 'dashicons-groups',
            'url'   => admin_url('users.php'),
        ],
        [
            'label' => __('Facilitators', 'tfp-dashboard'),
            'value' => number_format_i18n($facilitators),
            'icon'  => 'dashicons-businessperson',
            'url'   => admin_url('users.php?role=facilitator'),
        ],
        [
            'label' => __('Active Cohorts', 'tfp-dashboard'),
            'value' => number_format_i18n($cohorts),
            'icon'  => 'dashicons-networking',
            'url'   => admin_url('edit.php?post_type=tfp_cohort'),
        ],
        [
            'label' => __('LearnDash Courses', 'tfp-dashboard'),
            'value' => number_format_i18n($courses),
            'icon'  => 'dashicons-welcome-learn-more',
            'url'   => admin_url('edit.php?post_type=sfwd-courses'),
        ],
        [
            'label' => __('Paid Orders', 'tfp-dashboard'),
            'value' => number_format_i18n($orders),
            'icon'  => 'dashicons-cart',
            'url'   => admin_url('edit.php?post_type=shop_order'),
        ],
    ];
    ?>
    <div class="wrap tfp-admin-wrap">
        <header class="tfp-admin-header">
            <div>
                <p class="tfp-admin-eyebrow"><?php esc_html_e('THE FOLLOW PROJECT', 'tfp-dashboard'); ?></p>
                <h1><?php esc_html_e('Admin Dashboard', 'tfp-dashboard'); ?></h1>
                <p><?php esc_html_e('Platform overview and management at a glance.', 'tfp-dashboard'); ?></p>
            </div>
            <div class="tfp-admin-header-date"><?php echo esc_html(wp_date(get_option('date_format'))); ?></div>
        </header>

        <section class="tfp-admin-metrics" aria-label="<?php esc_attr_e('Platform statistics', 'tfp-dashboard'); ?>">
            <?php foreach ($cards as $card) : ?>
                <a class="tfp-admin-metric" href="<?php echo esc_url($card['url']); ?>">
                    <span class="tfp-admin-metric-icon"><span class="dashicons <?php echo esc_attr($card['icon']); ?>"></span></span>
                    <span class="tfp-admin-metric-label"><?php echo esc_html($card['label']); ?></span>
                    <strong><?php echo esc_html($card['value']); ?></strong>
                    <span class="tfp-admin-metric-link"><?php esc_html_e('View details', 'tfp-dashboard'); ?> <span aria-hidden="true">→</span></span>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="tfp-admin-panel">
            <div class="tfp-admin-panel-heading">
                <div>
                    <h2><?php esc_html_e('Quick Management', 'tfp-dashboard'); ?></h2>
                    <p><?php esc_html_e('Open an existing management area.', 'tfp-dashboard'); ?></p>
                </div>
            </div>
            <div class="tfp-admin-quicklinks">
                <a href="<?php echo esc_url(admin_url('users.php')); ?>"><span class="dashicons dashicons-admin-users"></span><span><?php esc_html_e('Manage Users', 'tfp-dashboard'); ?></span><span class="tfp-admin-arrow">→</span></a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=tfp_cohort')); ?>"><span class="dashicons dashicons-groups"></span><span><?php esc_html_e('Manage Cohorts', 'tfp-dashboard'); ?></span><span class="tfp-admin-arrow">→</span></a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=sfwd-courses')); ?>"><span class="dashicons dashicons-welcome-learn-more"></span><span><?php esc_html_e('Manage Courses', 'tfp-dashboard'); ?></span><span class="tfp-admin-arrow">→</span></a>
                <?php if (function_exists('wc_get_orders')) : ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order')); ?>"><span class="dashicons dashicons-cart"></span><span><?php esc_html_e('Manage Orders', 'tfp-dashboard'); ?></span><span class="tfp-admin-arrow">→</span></a>
                <?php endif; ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=tfp_financial_aid')); ?>"><span class="dashicons dashicons-clipboard"></span><span><?php esc_html_e('Financial Aid', 'tfp-dashboard'); ?></span><span class="tfp-admin-arrow">→</span></a>
            </div>
        </section>
    </div>
    <?php
}


/**
 * Register the public-facing URL alias for the admin dashboard.
 * The page itself remains restricted to site administrators.
 */
add_action('init', function () {
    add_rewrite_rule('^tfp-admin-dashboard/?$', 'index.php?tfp_admin_dashboard=1', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'tfp_admin_dashboard';
    return $vars;
});

/** Enqueue dashboard assets on the frontend route only. */
add_action('wp_enqueue_scripts', function () {
    if ((int) get_query_var('tfp_admin_dashboard') !== 1) {
        return;
    }

    wp_enqueue_style('dashicons');
    wp_enqueue_style(
        'tfp-admin-dashboard-frontend',
        TFP_DASH_URL . 'assets/css/admin-dashboard.css',
        [],
        TFP_DASH_VERSION
    );

    // Remove theme content width constraints for this dedicated dashboard view.
    wp_add_inline_style(
        'tfp-admin-dashboard-frontend',
        '.tfp-admin-dashboard-page{max-width:1440px;margin:0 auto;padding:28px 24px 48px;box-sizing:border-box}.tfp-admin-dashboard-page .tfp-admin-wrap{margin:0}@media(max-width:600px){.tfp-admin-dashboard-page{padding:18px 14px 32px}}'
    );
});

/** Render the pretty URL while preserving administrator-only access. */
add_action('template_redirect', function () {
    if ((int) get_query_var('tfp_admin_dashboard') !== 1) {
        return;
    }

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

    nocache_headers();
    get_header();
    echo '<main class="tfp-admin-dashboard-page">';
    tfp_admin_dashboard_render();
    echo '</main>';
    get_footer();
    exit;
}, 1);
