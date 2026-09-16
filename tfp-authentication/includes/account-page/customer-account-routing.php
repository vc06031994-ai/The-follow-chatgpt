<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keep e-commerce-only customers/readers separate from the Discipleship UI
 * on the custom My Account page.
 *
 * Enrolled Disciples and staff retain the original account experience. Pure
 * e-commerce customers/readers get the standalone billing page link instead
 * of being sent into the Discipleship payment-details route.
 */
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

    // Enrolled Disciples and staff retain the original Discipleship account UI.
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

    // Replace the Discipleship payment-details URL wherever it appears in the
    // rendered account markup. This is intentionally not limited to one exact
    // href string because esc_url() may encode query-string characters.
    $old_url = function_exists('tfp_dashboard_get_url')
        ? tfp_dashboard_get_url('tfp-dashboard-payment-details')
        : '';
    $customer_url = function_exists('tfp_get_customer_billing_page_url')
        ? tfp_get_customer_billing_page_url()
        : '';

    if ($old_url && $customer_url && $old_url !== $customer_url) {
        $html = str_replace(esc_url($old_url), esc_url($customer_url), $html);
        $html = str_replace($old_url, $customer_url, $html);
    }

    return $html;
}

add_action('init', function () {
    if (shortcode_exists('tfp_my_account')) {
        remove_shortcode('tfp_my_account');
    }

    add_shortcode('tfp_my_account', 'tfp_auth_render_customer_account_with_routing');
}, 30);
