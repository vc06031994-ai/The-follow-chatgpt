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
if (!current_user_can('manage_options')) {
    wp_die(
        esc_html__('You do not have permission to access this page.', 'tfp-dashboard'),
        esc_html__('Access denied', 'tfp-dashboard'),
        ['response' => 403]
    );
}

status_header(200);
nocache_headers();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('tfp-admin-dashboard-view'); ?>>
<?php
wp_body_open();
if (function_exists('tfp_admin_dashboard_render')) {
    tfp_admin_dashboard_render();
} else {
    echo '<main style="padding:40px;font:16px sans-serif">' . esc_html__('Admin Dashboard renderer is not loaded. Please check the TFP Dashboard plugin files.', 'tfp-dashboard') . '</main>';
}
wp_footer();
?>
</body>
</html>
