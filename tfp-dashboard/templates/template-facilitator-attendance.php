<?php
if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();
if (!function_exists('tfp_dashboard_user_is_staff') || !tfp_dashboard_user_is_staff()) {
    wp_safe_redirect(tfp_dashboard_get_url('tfp-dashboard-facilitator-home'));
    exit;
}

tfp_dashboard_shell_start('facilitator-attendance');
tfp_dashboard_render_facilitator_attendance_content();
tfp_dashboard_shell_end();
