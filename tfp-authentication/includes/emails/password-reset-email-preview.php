<?php
/** Admin-only visual preview for the TFP password reset email. */
defined('ABSPATH') || exit;
add_action('admin_menu', function () {
    add_submenu_page('woocommerce', __('Password Reset Email Preview', 'tfp-authentication'), __('Password Reset Email Preview', 'tfp-authentication'), 'manage_woocommerce', 'tfp-password-reset-email-preview', 'tfp_render_password_reset_email_preview');
});
function tfp_render_password_reset_email_preview() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to view this preview.', 'tfp-authentication'));
    }
    $user = wp_get_current_user();
    if (!$user || !$user->exists()) {
        $user = (object) ['display_name' => 'Customer', 'user_login' => 'customer', 'user_email' => 'customer@example.com'];
    }
    $reset_url = add_query_arg(['key' => 'PREVIEW-RESET-KEY', 'login' => rawurlencode($user->user_login)], function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/'));
    $blogname = get_bloginfo('name');
    echo '<div class="wrap"><h1>' . esc_html__('Password Reset Email Preview', 'tfp-authentication') . '</h1><p>' . esc_html__('Visual preview only. The sample reset link is not valid and no password is changed.', 'tfp-authentication') . '</p><div style="background:#eef1f4;padding:24px 12px;border:1px solid #dcdcde;">';
    include TFP_AUTH_PATH . 'includes/emails/customer-password-reset.php';
    echo '</div></div>';
}