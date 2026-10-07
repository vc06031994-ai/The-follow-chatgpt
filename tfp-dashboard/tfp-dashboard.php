<?php
/**
 * Plugin Name: TFP Dashboard
 * Description: Custom-coded student dashboard (Home, Grades, Communication, Documents, Calendar, Profile) for The Follow Project. Reuses helper functions from the TFP Authentication plugin. Not built with Elementor — fully custom templates for app-like behaviour and pixel-perfect design control.
 * Version: 1.2.10
 * Author: The Follow Project
 * Text Domain: tfp-dashboard
 */

if (!defined('ABSPATH'))
    exit;

define('TFP_DASH_VERSION', '1.2.13');
define('TFP_DASH_PATH', plugin_dir_path(__FILE__));
define('TFP_DASH_URL', plugin_dir_url(__FILE__));

/**
 * Activation: create the chat messages table.
 */
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table = $wpdb->prefix . 'tfp_ticket_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ticket_id BIGINT UNSIGNED NOT NULL,
        sender_id BIGINT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY ticket_id (ticket_id),
        KEY created_at (created_at)
    ) {$charset_collate};";
