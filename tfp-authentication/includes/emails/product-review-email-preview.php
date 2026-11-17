<?php
/** Admin-only Product Review email preview; no order is persisted. */
defined('ABSPATH') || exit;
add_action('admin_menu', function () {
 add_submenu_page('woocommerce', __('TFP Product Review Preview','tfp-authentication'), __('Product Review Preview','tfp-authentication'), 'manage_woocommerce', 'tfp-product-review-email-preview', 'tfp_render_product_review_email_preview');
});
function tfp_render_product_review_email_preview() {
 if (!current_user_can('manage_woocommerce')) wp_die(esc_html__('Permission denied.','tfp-authentication'));
 $products = function_exists('wc_get_products') ? wc_get_products(['status'=>'publish','limit'=>3,'orderby'=>'date','order'=>'DESC']) : [];
 echo '<div class="wrap"><h1>TFP Product Review Email Preview</h1><p>Sample in-memory order only. No order is saved. Test emails are sent only to the address entered below.</p>';
 tfp_render_email_preview_test_form('product-review');
 if (!$products || !class_exists('WC_Order')) { echo '<p>WooCommerce and at least one published product are required.</p></div>'; return; }
 $order = new WC_Order(); $order->set_created_via('tfp-product-review-preview'); $order->set_date_created(gmdate('Y-m-d H:i:s')); $order->set_currency(get_woocommerce_currency()); $order->set_billing_first_name('Jane'); $order->set_billing_last_name('Doe'); $order->set_billing_email('jane.doe@example.com'); $order->add_product($products[0],1); $order->calculate_totals(false);
 $product=$products[0]; $recommendations=array_slice($products,1,2); $sent_to_admin=false; $plain_text=false; $email=null;
 echo '<div style="max-width:640px;margin:18px auto;background:#eef1f4;padding:16px 8px;border:1px solid #dcdcde">'; include TFP_AUTH_PATH.'includes/emails/customer-product-review.php'; echo '</div></div>';
}