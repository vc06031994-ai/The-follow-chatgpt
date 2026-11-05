<?php
/**
 * TFP Admin Dashboard — Home page template.
 * Assign this template to a normal WordPress Page from Page Attributes.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Only administrators can access the Admin Dashboard.
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

// Hide the WordPress toolbar on this full-screen dashboard to match the Figma canvas.
show_admin_bar(false);
status_header(200);
nocache_headers();

if (!function_exists('tfp_admin_dashboard_render')) {
    require_once dirname(__DIR__) . '/includes/admin/dashboard.php';
}
if (!function_exists('tfp_admin_users_render')) {
    require_once dirname(__DIR__) . '/includes/admin/users.php';
}

// Resolve dashboard URL and version constants safely
$dash_url = defined('TFP_DASH_URL') ? TFP_DASH_URL : plugin_dir_url(dirname(__DIR__));
$dash_ver = defined('TFP_DASH_VERSION') ? TFP_DASH_VERSION : '1.0.0';

// Ensure assets are registered and enqueued before wp_head()
wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], $dash_ver);
wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], $dash_ver, true);

if (function_exists('tfp_admin_dashboard_enqueue_assets')) {
    tfp_admin_dashboard_enqueue_assets();
}

$admin_view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : (isset($_GET['tfp_admin_page']) ? sanitize_text_field(wp_unslash($_GET['tfp_admin_page'])) : '');
$body_classes = 'tfp-admin-dashboard-view' . ($admin_view === 'users' ? ' tfp-admin-users-view' : '');
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <!-- Direct style injection ensures CSS always loads even if WP slug/cache bypasses wp_head enqueue -->
    <link rel="stylesheet" id="tfp-direct-dash-base-css" href="<?php echo esc_url($dash_url . 'assets/css/core.css?ver=' . $dash_ver); ?>">
    <link rel="stylesheet" id="tfp-direct-dash-core-css" href="<?php echo esc_url($dash_url . 'assets/css/dashboard.css?ver=' . $dash_ver); ?>">
    <link rel="stylesheet" id="tfp-direct-dash-comp-css" href="<?php echo esc_url($dash_url . 'assets/css/components.css?ver=' . $dash_ver); ?>">
    <link rel="stylesheet" id="tfp-direct-admin-dash-css" href="<?php echo esc_url($dash_url . 'assets/css/admin-dashboard.css?ver=' . $dash_ver); ?>">
</head>
<body <?php body_class($body_classes); ?>>
<?php
wp_body_open();
if ($admin_view === 'users' && function_exists('tfp_admin_users_render')) {
    tfp_admin_users_render();
} elseif (function_exists('tfp_admin_dashboard_render')) {
    tfp_admin_dashboard_render();
} else {
    echo '<main style="padding:40px;font:16px sans-serif">' . esc_html__('Admin Dashboard renderer is not loaded. Please check the TFP Dashboard plugin files.', 'tfp-dashboard') . '</main>';
}
wp_footer();
?>
<script src="<?php echo esc_url($dash_url . 'assets/js/admin-dashboard.js?ver=' . $dash_ver); ?>"></script>
</body>
</html>
