<?php
/**
 * Plain-text companion for the TFP custom WooCommerce new-account email.
 */
defined('ABSPATH') || exit;

$my_account_url = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('myaccount')
    : home_url('/');

$shop_url = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('shop')
    : home_url('/');

$account_url = !empty($password_generated) && !empty($set_password_url)
    ? $set_password_url
    : $my_account_url;

echo esc_html__('Welcome to The Follow Project.', 'woocommerce');
echo "\n\n";
echo esc_html__("You're all set.", 'woocommerce') . "\n\n";
echo esc_html__('Your account is ready. Track your orders and access your study resources all in one place.', 'woocommerce') . "\n\n";

if (!empty($password_generated) && !empty($set_password_url)) {
    echo esc_html__('Your new account was created during checkout. Use the secure link below to set your password and access your account:', 'woocommerce') . "\n";
    echo esc_url($set_password_url) . "\n\n";
}

echo esc_html__('Browse Books:', 'woocommerce') . "\n";
echo esc_url($shop_url) . "\n\n";

echo esc_html__('My Account:', 'woocommerce') . "\n";
echo esc_url($account_url) . "\n\n";

echo esc_html__('Join Community:', 'woocommerce') . "\n";
echo esc_url(home_url('/updates/')) . "\n\n";

echo esc_html__('Start Your Journey', 'woocommerce') . "\n";
echo esc_html__('Begin with one of our most-read titles.', 'woocommerce') . "\n\n";

if ($additional_content) {
    echo wp_kses_post(wp_strip_all_tags(wptexturize($additional_content))) . "\n\n";
}

echo esc_html__('If you have any questions, reply to this email or contact care@thefollowproject.com.', 'woocommerce') . "\n\n";
echo esc_html__('The Follow Project | Faith-Based Ministries', 'woocommerce');
