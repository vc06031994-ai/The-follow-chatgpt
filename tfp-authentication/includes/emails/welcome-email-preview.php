<?php
/**
 * TFP Welcome Email browser preview.
 *
 * Admin-only preview for the WooCommerce new-account welcome email.
 * This does not send an email and does not create an order/customer.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __('TFP Welcome Email Preview', 'tfp-authentication'),
        __('Welcome Email Preview', 'tfp-authentication'),
        'manage_woocommerce',
        'tfp-welcome-email-preview',
        'tfp_render_welcome_email_preview'
    );
});

function tfp_render_welcome_email_preview() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to view this preview.', 'tfp-authentication'));
    }

    $password_generated = true;
    $user_display_name = 'Test Customer';
    $user_login = 'testcustomer';
    $blogname = get_bloginfo('name');
    $email_heading = __('Welcome to The Follow Project', 'woocommerce');
    $additional_content = '';
    $set_password_url = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('myaccount')
        : home_url('/my-account/');

    // The live email template only relies on these values and WooCommerce helpers.
    $email = null;

    $template = TFP_AUTH_PATH . 'includes/emails/customer-new-account.php';

    if (!file_exists($template)) {
        echo '<div class="wrap"><h1>' . esc_html__('TFP Welcome Email Preview', 'tfp-authentication') . '</h1>';
        echo '<div class="notice notice-error"><p>' . esc_html__('The welcome email template could not be found.', 'tfp-authentication') . '</p></div></div>';
        return;
    }

    ?>
    <div class="wrap" style="max-width:700px;">
        <h1><?php esc_html_e('TFP Welcome Email Preview', 'tfp-authentication'); ?></h1>
        <p style="margin-bottom:16px;">
            <?php esc_html_e('This is a browser preview of the current WooCommerce new-account welcome email. It does not send an email or create an order.', 'tfp-authentication'); ?>
        </p>

        <div style="background:#eef1f4;padding:28px 12px;border:1px solid #dcdcde;">
            <?php include $template; ?>
        </div>
    </div>
    <?php
}
