<?php
/**
 * Plugin Name: TFP Customer Billing Shortcode
 * Description: Adds the [tfp-dash-billing-detail] shortcode for a standalone customer payment-method page.
 * Version: 1.0.2
 * Author: The Follow Project
 * Text Domain: tfp-dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Find the published WordPress Page that contains the standalone billing
 * shortcode. The page can be created/renamed from wp-admin without a
 * hardcoded slug in the plugin.
 */
function tfp_get_customer_billing_page_url()
{
    static $url = null;

    if ($url !== null) {
        return $url;
    }

    $url = '';
    $pages = get_pages([
        'post_status' => 'publish',
    ]);

    foreach ($pages as $page) {
        if (has_shortcode((string) $page->post_content, 'tfp-dash-billing-detail')) {
            $url = (string) get_permalink($page->ID);
            break;
        }
    }

    return $url;
}

/**
 * Register the standalone customer billing shortcode.
 *
 * This file is intentionally independent from the dashboard page templates.
 * It reuses the existing TFP billing helpers and AJAX endpoints.
 */
add_shortcode('tfp-dash-billing-detail', 'tfp_render_customer_billing_shortcode');

function tfp_render_customer_billing_shortcode($atts = [])
{
    if (!is_user_logged_in()) {
        return '<p class="tfp-customer-billing__notice">' . esc_html__('Please log in to manage your payment method.', 'tfp-dashboard') . '</p>';
    }

    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $name = function_exists('tfp_dashboard_user_name') ? tfp_dashboard_user_name() : $user->display_name;
    $card = function_exists('tfp_billing_get_default_card_summary') ? tfp_billing_get_default_card_summary($user_id) : null;
    $stripe_ready = function_exists('tfp_stripe_is_configured') && tfp_stripe_is_configured();

    $raw_brand = $card && !empty($card['brand']) ? strtolower((string) $card['brand']) : 'generic';
    $brand_map = ['visa', 'mastercard', 'amex', 'discover'];
    $card_brand = in_array($raw_brand, $brand_map, true) ? $raw_brand : 'generic';
    $saved_name = (string) get_user_meta($user_id, '_tfp_cardholder_name', true);
    $card_name = $saved_name ?: ($name ?: __('Card Holder', 'tfp-dashboard'));
    $last4 = $card && !empty($card['last4']) ? (string) $card['last4'] : '4242';
    $expiry = $card && !empty($card['expiry']) ? (string) $card['expiry'] : '12 / 2030';

    ob_start();
    ?>
    <section class="tfp-customer-billing" aria-labelledby="tfp-customer-billing-title">
        <div class="tfp-customer-billing__intro">
            <h2 id="tfp-customer-billing-title"><?php printf(esc_html__('Hi, %s — Update payment method', 'tfp-dashboard'), esc_html($name)); ?></h2>
            <p><?php esc_html_e('Your card details are securely collected by Stripe. We never see or store your full card number.', 'tfp-dashboard'); ?></p>
        </div>

        <div class="tfp-customer-billing__grid">
            <div class="tfp-customer-billing__preview">
                <div class="tfp-billing-visual-card-wrap">
                    <div class="tfp-billing-visual-card is-<?php echo esc_attr($card_brand); ?>" data-tfp-visual-card data-card-brand="<?php echo esc_attr($card_brand); ?>">
                        <div class="tfp-billing-visual-card__top">
                            <span class="tfp-billing-visual-card__type" data-tfp-visual-card-type><?php echo esc_html($card_brand === 'generic' ? 'Payment' : 'Credit'); ?></span>
                            <span class="tfp-billing-visual-card__brand" data-tfp-visual-card-brand><?php echo esc_html($card_brand === 'generic' ? 'CARD' : strtoupper($card_brand)); ?></span>
                        </div>
                        <div class="tfp-billing-visual-card__chip" aria-hidden="true"></div>
                        <div class="tfp-billing-visual-card__number" data-tfp-visual-card-number><?php echo esc_html('•••• •••• •••• ' . $last4); ?></div>
                        <div class="tfp-billing-visual-card__bottom">
                            <span class="tfp-billing-visual-card__name" data-tfp-visual-card-name><?php echo esc_html($card_name); ?></span>
                            <span class="tfp-billing-visual-card__expiry" data-tfp-visual-card-expiry><?php echo esc_html($expiry); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tfp-customer-billing__form-wrap">
                <?php if ($card) : ?>
                    <div class="tfp-customer-billing__saved-card">
                        <span><?php esc_html_e('Current payment method', 'tfp-dashboard'); ?></span>
                        <strong data-tfp-card-brand><?php printf('%s ending in %s', esc_html($card['brand']), esc_html($card['last4'])); ?></strong>
                        <small data-tfp-card-expiry><?php echo esc_html($card['expiry']); ?></small>
                    </div>
                <?php else : ?>
                    <p class="tfp-customer-billing__no-card" data-tfp-no-card><?php esc_html_e('No payment method saved yet.', 'tfp-dashboard'); ?></p>
                <?php endif; ?>

                <?php if ($stripe_ready) : ?>
                    <form class="tfp-customer-billing__form" data-tfp-billing-form novalidate>
                        <label><span><?php esc_html_e('Card Number', 'tfp-dashboard'); ?></span><div class="tfp-stripe-card-element" data-tfp-stripe-card-number></div></label>
                        <label><span><?php esc_html_e('Expiration', 'tfp-dashboard'); ?></span><div class="tfp-stripe-card-element" data-tfp-stripe-card-expiry></div></label>
                        <label><span><?php esc_html_e('CVC', 'tfp-dashboard'); ?></span><div class="tfp-stripe-card-element" data-tfp-stripe-card-cvc></div></label>
                        <label><span><?php esc_html_e('Name on Card', 'tfp-dashboard'); ?></span><input type="text" data-tfp-cardholder-name value="<?php echo esc_attr($saved_name ?: $name); ?>" autocomplete="cc-name" placeholder="<?php esc_attr_e('Name as it appears on card', 'tfp-dashboard'); ?>"></label>
                        <label class="tfp-customer-billing__consent"><input type="checkbox" data-tfp-billing-consent required><span><?php esc_html_e('I authorize The Follow Project to process my payment securely and agree to the Terms & Refund Policy.', 'tfp-dashboard'); ?></span></label>
                        <button type="submit" class="tfp-customer-billing__submit tfp-dash-btn tfp-dash-btn--primary" data-tfp-billing-submit><?php esc_html_e('Save Payment Method', 'tfp-dashboard'); ?></button>
                        <p class="tfp-dash-form__status" data-tfp-billing-status role="status"></p>
                    </form>
                <?php else : ?>
                    <p class="tfp-dash-form__status" data-state="error"><?php esc_html_e('Secure card updates are not configured yet. Please contact support.', 'tfp-dashboard'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) {
        return;
    }

    $post = get_post();
    if (!$post || !has_shortcode((string) $post->post_content, 'tfp-dash-billing-detail')) {
        return;
    }

    wp_enqueue_style('tfp-dashboard-core', plugins_url('assets/css/dashboard.css', __FILE__), [], '1.2.5');
    wp_enqueue_style('tfp-customer-billing', plugins_url('assets/css/customer-billing.css', __FILE__), ['tfp-dashboard-core'], '1.0.2');
    wp_enqueue_style('tfp-dashboard-forms', plugins_url('assets/css/forms.css', __FILE__), ['tfp-dashboard-core'], '1.2.5');

    if (function_exists('tfp_stripe_is_configured') && tfp_stripe_is_configured()) {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('tfp-dashboard-billing', plugins_url('assets/js/billing.js', __FILE__), ['stripe-js'], '1.0.2', true);
        wp_localize_script('tfp-dashboard-billing', 'tfpDashboardBilling', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tfp_billing_nonce'),
            'publishableKey' => function_exists('tfp_stripe_get_publishable_key') ? tfp_stripe_get_publishable_key() : '',
        ]);
    }
});
