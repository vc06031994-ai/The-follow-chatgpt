<?php
/**
 * TFP Admin Dashboard — User System Health page.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!is_user_logged_in()) {
    auth_redirect();
}
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
}
show_admin_bar(false);
status_header(200);
nocache_headers();

$dash_url = defined('TFP_DASH_URL') ? TFP_DASH_URL : plugin_dir_url(dirname(__DIR__));
$dash_ver = defined('TFP_DASH_VERSION') ? TFP_DASH_VERSION : '1.0.0';
$admin_css_path = dirname(__DIR__) . '/assets/css/admin-dashboard.css';
$admin_js_path = dirname(__DIR__) . '/assets/js/admin-dashboard.js';
$health_css_path = dirname(__DIR__) . '/assets/css/admin-system-health.css';
$admin_css_ver = file_exists($admin_css_path) ? (string) filemtime($admin_css_path) : $dash_ver;
$admin_js_ver = file_exists($admin_js_path) ? (string) filemtime($admin_js_path) : $dash_ver;
$health_css_ver = file_exists($health_css_path) ? (string) filemtime($health_css_path) : $dash_ver;

wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], $admin_css_ver);
wp_enqueue_style('tfp-admin-system-health', $dash_url . 'assets/css/admin-system-health.css', ['tfp-admin-dashboard'], $health_css_ver);
wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], $admin_js_ver, true);
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
if (function_exists('tfp_admin_system_health_render')) {
    tfp_admin_system_health_render();
} else {
    echo '<main style="padding:40px;font:16px sans-serif">' . esc_html__('System Health renderer is not loaded. Please check the TFP Dashboard plugin files.', 'tfp-dashboard') . '</main>';
}
wp_footer();
?>
</body>
</html>
