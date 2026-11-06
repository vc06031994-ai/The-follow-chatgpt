<?php
/**
 * TFP Admin Dashboard — Users page template.
 * Assign this template to a WordPress Page from Page Attributes.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Only administrators can access the Admin Users page.
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

// Resolve dashboard URL and version constants safely
$dash_url = defined('TFP_DASH_URL') ? TFP_DASH_URL : plugin_dir_url(dirname(__DIR__));
$dash_ver = defined('TFP_DASH_VERSION') ? TFP_DASH_VERSION : '1.0.0';
$admin_css_path = dirname(__DIR__) . '/assets/css/admin-dashboard.css';
$admin_js_path = dirname(__DIR__) . '/assets/js/admin-dashboard.js';
$admin_css_ver = file_exists($admin_css_path) ? (string) filemtime($admin_css_path) : $dash_ver;
$admin_js_ver = file_exists($admin_js_path) ? (string) filemtime($admin_js_path) : $dash_ver;

// Ensure assets are registered and enqueued before wp_head()
wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], $admin_css_ver);
wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], $admin_js_ver, true);

if (function_exists('tfp_admin_dashboard_enqueue_assets')) {
    tfp_admin_dashboard_enqueue_assets();
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('tfp-admin-dashboard-view tfp-admin-users-view'); ?>>
<?php
wp_body_open();
if (function_exists('tfp_admin_users_render')) {
    tfp_admin_users_render();
} else {
    echo '<main style="padding:40px;font:16px sans-serif">' . esc_html__('Admin Users renderer is not loaded. Please check the TFP Dashboard plugin files.', 'tfp-dashboard') . '</main>';
}
wp_footer();
?>
</body>
</html>

