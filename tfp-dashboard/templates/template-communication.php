<?php
/**
 * Loaded via template_include for pages using "TFP Dashboard — Communication".
 */

if (!defined('ABSPATH')) exit;

tfp_dashboard_require_login();

// Ensure core and communication assets are enqueued
if (!wp_style_is('tfp-dashboard-communication', 'enqueued')) {
    wp_enqueue_style('tfp-dashboard-core', TFP_DASH_URL . 'assets/css/dashboard.css', [], TFP_DASH_VERSION);
    wp_enqueue_style('tfp-dashboard-communication', TFP_DASH_URL . 'assets/css/communication.css', ['tfp-dashboard-core'], TFP_DASH_VERSION);
    wp_enqueue_script('tfp-dashboard-core', TFP_DASH_URL . 'assets/js/dashboard.js', [], TFP_DASH_VERSION, true);
    wp_enqueue_script('tfp-dashboard-communication', TFP_DASH_URL . 'assets/js/communication.js', [], TFP_DASH_VERSION, true);

    wp_localize_script('tfp-dashboard-communication', 'tfpChatSettings', [
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('tfp_chat_nonce'),
        'currentUser'  => get_current_user_id(),
        'pollInterval' => 4000,
    ]);
}

tfp_dashboard_shell_start('communication');
tfp_dashboard_render_communication_content();
tfp_dashboard_shell_end();
