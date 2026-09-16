<?php
defined('ABSPATH') || exit;

echo esc_html(wp_strip_all_tags($email_heading));
echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo sprintf(esc_html__('Hi %s,', 'woocommerce'), esc_html($user_display_name)) . "\n\n";

if (!empty($password_generated) && !empty($set_password_url)) {
    echo sprintf(esc_html__('Thanks for creating an account on %s. Here’s a copy of your user details.', 'woocommerce'), esc_html($blogname)) . "\n\n";
    echo "----------------------------------------\n\n";
    echo sprintf(esc_html__('Username: %s.', 'woocommerce'), esc_html($user_login)) . "\n\n";
    echo esc_html__('To set your password, visit the following address:', 'woocommerce') . "\n\n";
    echo esc_url($set_password_url) . "\n\n";
    echo esc_html__('My account:', 'woocommerce') . " " . esc_url($set_password_url) . "\n\n";
} else {
    echo sprintf(
        esc_html__('Thanks for creating an account on %1$s. Your username is %2$s. You can access your account area to view orders, change your password, and more at: %3$s', 'woocommerce'),
        esc_html($blogname),
        esc_html($user_login),
        esc_url(wc_get_page_permalink('myaccount'))
    ) . "\n\n";
}

if ($additional_content) {
    echo wp_kses_post(wp_strip_all_tags(wptexturize($additional_content))) . "\n\n";
}

echo "----------------------------------------\n\n";
echo wp_kses_post(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
