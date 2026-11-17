<?php
/**
 * Admin-only browser preview for the TFP Refund Order email.
 * Uses an in-memory WooCommerce order and sample refund details only.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __('TFP Refund Order Preview', 'tfp-authentication'),
        __('Refund Order Preview', 'tfp-authentication'),
        'manage_woocommerce',
        'tfp-refund-order-email-preview',
        'tfp_render_refund_order_email_preview'
    );
});

/** Create a disposable order with sample customer and product information. */
function tfp_build_refund_order_preview_order() {
    if (!class_exists('WC_Order') || !function_exists('wc_get_products')) {
        return null;
    }

    $order = new WC_Order();
    $order->set_created_via('tfp-refund-order-preview');
    $order->set_date_created(gmdate('Y-m-d H:i:s'));
    $order->set_currency(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD');
    $order->set_billing_first_name('Jane');
    $order->set_billing_last_name('Doe');
    $order->set_billing_email('jane.doe@example.com');
    $order->set_billing_phone('+1 615 555 0142');
    $order->set_billing_address_1('123 Main Street');
    $order->set_billing_city('Nashville');
    $order->set_billing_state('TN');
    $order->set_billing_postcode('37201');
    $order->set_billing_country('US');
    $order->set_payment_method_title('Visa ending in 4242');

    $products = wc_get_products([
        'status' => 'publish',
        'limit' => 2,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    foreach ($products as $product) {
        if ($product instanceof WC_Product) {
            $order->add_product($product, 1);
            break;
        }
    }

    if (!$order->get_items('line_item')) {
        return null;
    }

    $order->calculate_totals(false);
    return $order;
}

function tfp_render_refund_order_email_preview() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to view this preview.', 'tfp-authentication'));
    }

    $order = tfp_build_refund_order_preview_order();
    $template = TFP_AUTH_PATH . 'includes/emails/customer-refunded-order.php';

    echo '<div class="wrap"><h1>' . esc_html__('TFP Refund Order Email Preview', 'tfp-authentication') . '</h1>';
    echo '<p>' . esc_html__('This preview uses sample refund details and a temporary in-memory order. No order or refund is created.', 'tfp-authentication') . '</p>';
    tfp_render_email_preview_test_form('refunded-order');

    if (!$order || !is_file($template)) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('A preview cannot be generated. Check WooCommerce and make sure at least one published product exists.', 'tfp-authentication') . '</p></div></div>';
        return;
    }

    $sample_items = $order->get_items('line_item');
    $sample_item = !empty($sample_items) ? reset($sample_items) : null;
    $product = $sample_item ? $sample_item->get_product() : null;
    $tfp_refund_data = [
        'amount' => 24.99,
        'date' => current_time('F j, Y'),
        'status' => __('Refund Processed', 'woocommerce'),
        'note' => __('Please allow 5–10 business days for your bank to post the credit.', 'woocommerce'),
        'items' => [[
            'name' => $sample_item ? $sample_item->get_name() : __('Sample Product', 'woocommerce'),
            'image' => ($product && $product->get_image_id()) ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
            'quantity' => 1,
            'total' => 24.99,
        ]],
    ];
    $refund_amount = 24.99;
    $sent_to_admin = false;
    $plain_text = false;
    $email = null;
    $blogname = get_bloginfo('name');

    echo '<div style="max-width:640px;margin:18px auto;background:#eef1f4;padding:16px 8px;border:1px solid #dcdcde;">';
    include $template;
    echo '</div></div>';
}
