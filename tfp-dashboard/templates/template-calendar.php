<?php
/**
 * Loaded via template_include for pages using "TFP Dashboard — Calendar".
 */

if (!defined('ABSPATH'))
    exit;

tfp_dashboard_require_login();

// Ensure core assets are enqueued
if (!wp_style_is('tfp-dashboard-core', 'enqueued')) {
    wp_enqueue_style('tfp-dashboard-core', TFP_DASH_URL . 'assets/css/dashboard.css', [], TFP_DASH_VERSION);
    wp_enqueue_script('tfp-dashboard-core', TFP_DASH_URL . 'assets/js/dashboard.js', [], TFP_DASH_VERSION, true);
}

tfp_dashboard_shell_start('calendar');
tfp_dashboard_render_calendar_content();
tfp_dashboard_shell_end();
