<?php
/**
 * Loaded via template_include for pages using "TFP Dashboard — Grades".
 */

if (!defined('ABSPATH'))
    exit;

tfp_dashboard_require_login();

// Ensure core and grades assets are enqueued
if (!wp_style_is('tfp-dashboard-grades', 'enqueued')) {
    wp_enqueue_style('tfp-dashboard-core', TFP_DASH_URL . 'assets/css/dashboard.css', [], TFP_DASH_VERSION);
    wp_enqueue_style('tfp-dashboard-grades', TFP_DASH_URL . 'assets/css/grades.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
    wp_enqueue_script('tfp-dashboard-core', TFP_DASH_URL . 'assets/js/dashboard.js', [], TFP_DASH_VERSION, true);
    wp_enqueue_script('tfp-dashboard-grades', TFP_DASH_URL . 'assets/js/grades.js', [], TFP_DASH_VERSION, true);
}

tfp_dashboard_shell_start('grades');
tfp_dashboard_render_grades_content();
tfp_dashboard_shell_end();
