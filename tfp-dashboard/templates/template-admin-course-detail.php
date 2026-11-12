<?php
/**
 * TFP Admin Dashboard — individual Course Detail.
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
if (!defined('DONOTCACHEPAGE')) {
    define('DONOTCACHEPAGE', true);
}
status_header(200);
nocache_headers();

$dash_url = TFP_DASH_URL;
$dash_ver = TFP_DASH_VERSION;
$admin_css = TFP_DASH_PATH . 'assets/css/admin-dashboard.css';
$detail_css = TFP_DASH_PATH . 'assets/css/admin-course-detail.css';
$admin_js = TFP_DASH_PATH . 'assets/js/admin-dashboard.js';

wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], file_exists($admin_css) ? (string) filemtime($admin_css) : $dash_ver);
wp_enqueue_style('tfp-admin-course-detail', $dash_url . 'assets/css/admin-course-detail.css', ['tfp-admin-dashboard'], file_exists($detail_css) ? (string) filemtime($detail_css) : $dash_ver);
wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], file_exists($admin_js) ? (string) filemtime($admin_js) : $dash_ver, true);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('tfp-admin-dashboard-view tfp-admin-course-detail-view'); ?>>
<?php
wp_body_open();
tfp_admin_course_detail_render();
wp_footer();
?>
</body>
</html>
