<?php
/**
 * TFP Admin Dashboard — Courses page.
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!is_user_logged_in()) {
    auth_redirect();
}
if (!current_user_can('manage_options') && !current_user_can('administrator')) {
    wp_die(esc_html__('You do not have permission to access this page.', 'tfp-dashboard'), esc_html__('Access denied', 'tfp-dashboard'), ['response' => 403]);
}

show_admin_bar(false);
status_header(200);
nocache_headers();

if (!function_exists('tfp_admin_courses_render')) {
    require_once dirname(__DIR__) . '/includes/admin/courses.php';
}

$dash_url = defined('TFP_DASH_URL') ? TFP_DASH_URL : plugin_dir_url(dirname(__DIR__));
$dash_ver = defined('TFP_DASH_VERSION') ? TFP_DASH_VERSION : '1.0.0';
$admin_css = dirname(__DIR__) . '/assets/css/admin-dashboard.css';
$admin_js = dirname(__DIR__) . '/assets/js/admin-dashboard.js';
$courses_css = dirname(__DIR__) . '/assets/css/admin-courses.css';
$courses_js = dirname(__DIR__) . '/assets/js/admin-courses.js';

wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], file_exists($admin_css) ? (string) filemtime($admin_css) : $dash_ver);
wp_enqueue_style('tfp-admin-courses', $dash_url . 'assets/css/admin-courses.css', ['tfp-admin-dashboard'], file_exists($courses_css) ? (string) filemtime($courses_css) : $dash_ver);
wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], file_exists($admin_js) ? (string) filemtime($admin_js) : $dash_ver, true);
wp_enqueue_script('tfp-admin-courses', $dash_url . 'assets/js/admin-courses.js', ['tfp-admin-dashboard'], file_exists($courses_js) ? (string) filemtime($courses_js) : $dash_ver, true);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('tfp-admin-dashboard-view tfp-admin-courses-view'); ?>>
<?php
wp_body_open();
tfp_admin_courses_render();
wp_footer();
?>
</body>
</html>
