<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep e-commerce-only customers/readers separate from the Discipleship UI
 * on the custom My Account page.
 *
 * Enrolled Disciples and staff retain the original account experience. Pure
 * e-commerce customers/readers are sent to the standalone payment-method
 * page, while program users continue using payment-details.
 */
function tfp_auth_get_customer_payment_method_url()
{
    // Prefer the dedicated WordPress page containing the customer billing
    // shortcode, so the page can be changed from wp-admin without code edits.
    if (function_exists('tfp_get_customer_billing_page_url')) {
        $page_url = (string) tfp_get_customer_billing_page_url();
        if ($page_url !== '') {
            return $page_url;
        }
    }

    // Reliable fallback for the dedicated My Account endpoint/page.
    $my_account_url = function_exists('wc_get_page_permalink')
        ? (string) wc_get_page_permalink('myaccount')
        : '';

    if ($my_account_url !== '') {
        return trailingslashit($my_account_url) . 'payment-method/';
    }

    return '';
}

function tfp_auth_render_customer_account_with_routing()
{
    $html = tfp_auth_render_custom_my_account_shortcode();

    if (!is_user_logged_in()) {
        return $html;
    }

    $user_id = get_current_user_id();
    $has_program_access = function_exists('tfp_dashboard_user_has_full_access')
        ? tfp_dashboard_user_has_full_access($user_id)
        : false;

    // Enrolled Disciples and staff retain the original Discipleship account UI
    // and the /payment-details/ destination.
    if ($has_program_access) {
        return $html;
    }

    // Remove the program-dashboard banner for e-commerce-only customers.
    $html = preg_replace(
        '~<div class="tfp-account-disciple-banner".*?(?=<div class="tfp-account-grid">)~s',
        '',
        $html
    );

    // Ensure the account badge reflects the e-commerce-only experience.
    $html = str_replace('tfp-account-badge--disciple', 'tfp-account-badge--reader', $html);
    $html = preg_replace(
        '~(<div class="tfp-myaccount-badge tfp-account-badge--reader">)\s*Disciple\s*(</div>)~i',
        '$1Reader$2',
        $html
    );

    $customer_url = tfp_auth_get_customer_payment_method_url();
    $old_url = function_exists('tfp_dashboard_get_url')
        ? (string) tfp_dashboard_get_url('tfp-dashboard-payment-details')
        : '';

    if ($customer_url !== '') {
        // Replace the exact dashboard URL when it is available.
        if ($old_url !== '' && $old_url !== $customer_url) {
            $html = str_replace(esc_url($old_url), esc_url($customer_url), $html);
            $html = str_replace($old_url, $customer_url, $html);
        }

        // Also replace the endpoint path directly. This handles account
        // templates that build the payment-details URL independently of the
        // dashboard URL helper or encode it differently.
        $html = preg_replace_callback(
            '~href=((["\']))([^"\']*?/payment-details(?:/|[?#][^"\']*)?)(\1)~i',
            function ($matches) use ($customer_url) {
                return 'href=' . $matches[1] . esc_url($customer_url) . $matches[4];
            },
            $html
        );
    }

    return $html;
}

add_action('init', function () {
    if (shortcode_exists('tfp_my_account')) {
        remove_shortcode('tfp_my_account');
    }

    add_shortcode('tfp_my_account', 'tfp_auth_render_customer_account_with_routing');
}, 30);
