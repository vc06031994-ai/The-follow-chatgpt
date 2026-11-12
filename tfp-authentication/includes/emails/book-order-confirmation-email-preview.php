<?php
/**
 * Admin-only preview for the dedicated TFP Book Order Confirmation email.
 * Builds an in-memory WooCommerce order and never saves it.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __('Book Order Confirmation Preview', 'tfp-authentication'),
        __('Book Order Confirmation Preview', 'tfp-authentication'),
        'manage_woocommerce',
        'tfp-book-order-confirmation-preview',
        'tfp_render_book_order_confirmation_preview'
    );
});

function tfp_build_book_order_confirmation_preview_order() {
    if (!class_exists('WC_Order') || !function_exists('wc_get_products')) {
        return null;
    }

    $order = new WC_Order();
    $order->set_created_via('tfp-book-order-confirmation-preview');
    $order->set_date_created(gmdate('Y-m-d H:i:s'));
    $order->set_currency(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD');
    $order->set_billing_first_name('Jane');
    $order->set_billing_last_name('Doe');
    $order->set_billing_address_1('123 Main Street');
    $order->set_billing_city('Nashville');
    $order->set_billing_state('TN');
    $order->set_billing_postcode('37201');
    $order->set_billing_country('US');
    $order->set_billing_email('preview@example.com');
    $order->set_shipping_first_name('Jane');
    $order->set_shipping_last_name('Doe');
    $order->set_shipping_address_1('123 Main Street');
    $order->set_shipping_city('Nashville');
    $order->set_shipping_state('TN');
    $order->set_shipping_postcode('37201');
    $order->set_shipping_country('US');

    $products = wc_get_products([
        'status' => 'publish',
        'limit' => 2,
        'category' => ['books'],
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    foreach ($products as $product) {
        if ($product instanceof WC_Product) {
            $order->add_product($product, 1);
        }
    }

    if (!$order->get_items()) {
        return null;
    }

    $order->set_shipping_total(5);
    $order->calculate_totals(false);
    return $order;
}

function tfp_render_book_order_confirmation_preview() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to view this preview.', 'tfp-authentication'));
    }

    $order = tfp_build_book_order_confirmation_preview_order();
    $template = TFP_AUTH_PATH . 'includes/emails/customer-book-order-confirmation.php';

    echo '<div class="wrap"><h1>' . esc_html__('TFP Book Order Confirmation Preview', 'tfp-authentication') . '</h1>';
    echo '<p>' . esc_html__('This preview uses an in-memory sample order with published Books. No order is saved. Test emails are sent only to the address entered below.', 'tfp-authentication') . '</p>';
    tfp_render_email_preview_test_form('book-order-confirmation');

    if (!$order) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('No published products in the Books category were found for preview.', 'tfp-authentication') . '</p></div></div>';
        return;
    }

    $sent_to_admin = false;
    $plain_text = false;
    $email = null;
    $blogname = get_bloginfo('name');

    echo '<div style="max-width:640px;background:#eef1f4;padding:24px 12px;border:1px solid #dcdcde;">';
    include $template;
    echo '</div></div>';
}
