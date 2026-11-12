<?php
/** TFP Admin User Activity page template. */
if (!defined('ABSPATH')) { exit; }
if (!is_user_logged_in()) { auth_redirect(); }
if (!current_user_can('manage_options')) { wp_die(esc_html__('Access denied','tfp-dashboard'), '', ['response'=>403]); }
show_admin_bar(false);
wp_enqueue_style('dashicons');
wp_enqueue_style('tfp-dashboard-base', TFP_DASH_URL . 'assets/css/core.css', [], TFP_DASH_VERSION);
wp_enqueue_style('tfp-dashboard-core', TFP_DASH_URL . 'assets/css/dashboard.css', ['tfp-dashboard-base'], TFP_DASH_VERSION);
wp_enqueue_style('tfp-dashboard-components', TFP_DASH_URL . 'assets/css/components.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
wp_enqueue_style('tfp-admin-dashboard', TFP_DASH_URL . 'assets/css/admin-dashboard.css', ['tfp-dashboard-components'], TFP_DASH_VERSION);
wp_enqueue_style('tfp-admin-user-activity', TFP_DASH_URL . 'assets/css/admin-user-activity.css', ['tfp-admin-dashboard'], (string) filemtime(TFP_DASH_PATH . 'assets/css/admin-user-activity.css'));
wp_enqueue_script('tfp-admin-dashboard', TFP_DASH_URL . 'assets/js/admin-dashboard.js', [], TFP_DASH_VERSION, true);
?><!doctype html>
<html <?php language_attributes(); ?>><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('tfp-admin-dashboard-view tfp-admin-user-activity-view'); ?>><?php wp_body_open(); tfp_admin_user_activity_render(); wp_footer(); ?></body></html>
