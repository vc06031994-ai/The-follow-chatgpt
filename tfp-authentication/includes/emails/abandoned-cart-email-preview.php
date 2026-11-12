<?php
/**
 * Admin-only visual preview for the TFP Abandoned Cart email.
 * Uses published Books as sample cart lines; it does not create or send a cart.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __('Abandoned Cart Email Preview', 'tfp-authentication'),
        __('Abandoned Cart Email Preview', 'tfp-authentication'),
        'manage_woocommerce',
        'tfp-abandoned-cart-email-preview',
        'tfp_render_abandoned_cart_email_preview'
    );
});

function tfp_render_abandoned_cart_email_preview() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to view this preview.', 'tfp-authentication'));
    }

    $products = function_exists('wc_get_products') ? wc_get_products([
        'status' => 'publish',
        'limit' => 4,
        'category' => ['books'],
        'orderby' => 'date',
        'order' => 'DESC',
    ]) : [];

    $cart_items = [];
    foreach (array_slice($products, 0, 2) as $product) {
        $cart_items[] = ['product' => $product, 'quantity' => 2];
    }

    // Sample URL is only for visual preview and does not restore a real cart.
    $cart_url = add_query_arg('tfp_cart_preview', '1', function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'));
    $customer_name = 'Customer';
    $blogname = get_bloginfo('name');

    echo '<div class="wrap"><h1>' . esc_html__('Abandoned Cart Email Preview', 'tfp-authentication') . '</h1>';
    echo '<p>' . esc_html__('This preview uses published Books as sample cart products. The Return to Your Cart button is a sample link only; it does not restore a real customer cart.', 'tfp-authentication') . '</p>';
    tfp_render_email_preview_test_form('abandoned-cart');
    echo '<div style="background:#eef1f4;padding:24px 12px;border:1px solid #dcdcde;">';
    include TFP_AUTH_PATH . 'includes/emails/customer-abandoned-cart.php';
    echo '</div></div>';
}