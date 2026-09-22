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
    $is_program_user = function_exists('tfp_auth_is_program_user')
        ? tfp_auth_is_program_user($user_id)
        : false;

    // Registered program users, including students who have not paid yet,
    // retain the Discipleship account UI and /payment-details/ destination.
    // Pure e-commerce customers/readers use the standalone /payment-method/ page.
    if ($is_program_user) {
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

    add_shortcode('tfp_my_account', function () {

        /*
         * Forgot Password request page.
         *
         * This handles:
         * /my-account/lost-password/
         */
        $is_lost_password_request = false;

        if (
            function_exists('is_wc_endpoint_url')
            && is_wc_endpoint_url('lost-password')
        ) {
            $is_lost_password_request = true;
        }

        /*
         * Fallback check for custom/staging URLs where WooCommerce
         * endpoint detection may not work.
         */
        if (!$is_lost_password_request) {

            $request_uri = isset($_SERVER['REQUEST_URI'])
                ? wp_unslash($_SERVER['REQUEST_URI'])
                : '';

            if (strpos($request_uri, '/lost-password/') !== false) {
                $is_lost_password_request = true;
            }
        }

        /*
         * Only treat it as the reset request page when there is
         * NO actual reset key yet.
         */
        $has_reset_key = !empty($_GET['key'])
            && (
                !empty($_GET['id'])
                || !empty($_GET['login'])
            );

        if (
            $is_lost_password_request
            && !$has_reset_key
            && function_exists(
                'tfp_auth_render_password_reset_request_screen'
            )
        ) {
            return tfp_auth_render_password_reset_request_screen();
        }

        /*
         * Existing password reset link.
         */
        if (
            $has_reset_key
            && function_exists(
                'tfp_auth_render_password_reset_screen'
            )
        ) {
            return tfp_auth_render_password_reset_screen();
        }

        /*
         * Password reset success screen.
         */
        $is_reset_success = isset($_GET['tfp_password_reset'])
            && 'success' === sanitize_key(
                wp_unslash($_GET['tfp_password_reset'])
            );

        if (
            $is_reset_success
            && function_exists(
                'tfp_auth_password_reset_enqueue_styles'
            )
        ) {

            tfp_auth_password_reset_enqueue_styles();

            return '<div class="tfp-password-reset-wrapper">
                <div class="tfp-password-reset-card">

                    <h2 class="tfp-password-reset-title">' .
                    esc_html__(
                        'Password Updated',
                        'tfp-authentication'
                    ) .
                    '</h2>

                    <p class="tfp-password-reset-description">' .
                    esc_html__(
                        'Your password has been set successfully. You can now log in with your new password.',
                        'tfp-authentication'
                    ) .
                    '</p>

                </div>
            </div>';
        }

        /*
         * Normal Customer / Student account rendering.
         */
        return tfp_auth_render_customer_account_with_routing();

    });

}, 30);