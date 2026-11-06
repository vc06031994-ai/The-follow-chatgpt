<?php
/**
 * TFP Admin Dashboard — Users landing page.
 * This is separate from the full User Overview directory.
 */
if (!defined('ABSPATH')) {
    exit;
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

show_admin_bar(false);
status_header(200);
nocache_headers();

if (!function_exists('tfp_admin_users_home_render')) {
    require_once dirname(__DIR__) . '/includes/admin/users.php';
}

$dash_url = defined('TFP_DASH_URL') ? TFP_DASH_URL : plugin_dir_url(dirname(__DIR__));
$dash_ver = defined('TFP_DASH_VERSION') ? TFP_DASH_VERSION : '1.0.0';
$admin_css_path = dirname(__DIR__) . '/assets/css/admin-dashboard.css';
$admin_css_ver = file_exists($admin_css_path) ? (string) filemtime($admin_css_path) : $dash_ver;

wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', $dash_url . 'assets/css/core.css', [], $dash_ver);
wp_enqueue_style('tfp-dashboard-core', $dash_url . 'assets/css/dashboard.css', ['tfp-dashboard-base'], $dash_ver);
wp_enqueue_style('tfp-dashboard-components', $dash_url . 'assets/css/components.css', ['tfp-dashboard-core'], $dash_ver);
wp_enqueue_style('tfp-admin-dashboard', $dash_url . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], $dash_ver);
wp_enqueue_script('tfp-admin-dashboard', $dash_url . 'assets/js/admin-dashboard.js', [], $dash_ver, true);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <link rel="stylesheet" href="<?php echo esc_url($dash_url . 'assets/css/core.css?ver=' . $dash_ver); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($dash_url . 'assets/css/dashboard.css?ver=' . $dash_ver); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($dash_url . 'assets/css/components.css?ver=' . $dash_ver); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($dash_url . 'assets/css/admin-dashboard.css?ver=' . $admin_css_ver); ?>">
</head>
<body <?php body_class('tfp-admin-dashboard-view tfp-admin-users-home-view'); ?>>
<?php
wp_body_open();
if (function_exists('tfp_admin_users_home_render')) {
    tfp_admin_users_home_render();
} else {
    echo '<main style="padding:40px">' . esc_html__('Users page renderer is unavailable.', 'tfp-dashboard') . '</main>';
}
wp_footer();
?>
<script src="<?php echo esc_url($dash_url . 'assets/js/admin-dashboard.js?ver=' . $dash_ver); ?>"></script>
</body>
</html>
