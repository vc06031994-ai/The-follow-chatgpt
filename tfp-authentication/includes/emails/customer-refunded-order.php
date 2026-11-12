<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style type="text/css">
@import url('https://fonts.googleapis.com/css2?family=Lora:wght@400;700&display=swap');
@import url('https://stijndv.com/fonts/Eudoxus-Sans.css');
@media only screen and (max-width:620px) {
 .tfp-refund-shell{width:100%!important;max-width:100%!important}
 .tfp-refund-pad{padding-left:20px!important;padding-right:20px!important}
 .tfp-refund-nav td{padding-left:9px!important;padding-right:9px!important;font-size:14px!important}
 .tfp-refund-logo{width:190px!important;height:auto!important;max-width:80%!important}
 .tfp-refund-product-image{width:72px!important;height:72px!important}
 .tfp-refund-product-image-cell{width:78px!important}
 .tfp-refund-product-price{width:68px!important;font-size:12px!important}
 .tfp-refund-footer{padding-left:20px!important;padding-right:20px!important}
 .tfp-refund-footer-title{font-size:21px!important}
 .tfp-refund-social-icons{width:260px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background:#ffffff;">
<?php
/**
 * TFP Refund Order email.
 *
 * Uses the native WooCommerce customer-refunded-order notification.
 * Refund line items and amounts are resolved from the actual refund/order.
 */
defined('ABSPATH') || exit;

$order = isset($order) && $order instanceof WC_Order ? $order : null;
if (!$order) {
    return;
}

$my_account_url = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('myaccount')
    : home_url('/my-account/');

$shop_url = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('shop')
    : home_url('/shop/');

$view_in_browser_url = apply_filters('tfp_refund_order_email_view_in_browser_url', home_url('/'));
$logo_url = defined('TFP_AUTH_URL') ? trailingslashit(TFP_AUTH_URL) . 'assets/images/MAIN_LOGO.png' : '';
$blogname = get_bloginfo('name');
$customer_first_name = $order->get_billing_first_name();
$customer_name = $customer_first_name ?: $order->get_formatted_billing_full_name();
$customer_email = $order->get_billing_email();
$order_date = $order->get_date_created()
    ? wc_format_datetime($order->get_date_created())
    : current_time('F j, Y');

$refund_objects = method_exists($order, 'get_refunds') ? $order->get_refunds() : [];
$latest_refund = !empty($refund_objects) ? reset($refund_objects) : null;
$refund_date = ($latest_refund && $latest_refund->get_date_created())
    ? wc_format_datetime($latest_refund->get_date_created())
    : current_time('F j, Y');

$refund_amount_value = isset($refund_amount) && is_numeric($refund_amount)
    ? (float) $refund_amount
    : (($latest_refund && method_exists($latest_refund, 'get_amount'))
        ? abs((float) $latest_refund->get_amount())
        : abs((float) $order->get_total_refunded()));

if (isset($tfp_refund_data) && is_array($tfp_refund_data)) {
    $refund_amount_value = isset($tfp_refund_data['amount']) ? (float) $tfp_refund_data['amount'] : $refund_amount_value;
    $refund_date = !empty($tfp_refund_data['date']) ? (string) $tfp_refund_data['date'] : $refund_date;
}
$display_refund_amount = wc_price($refund_amount_value, ['currency' => $order->get_currency()]);
$payment_title = $order->get_payment_method_title();
$payment_title = $payment_title ?: __('Original payment method', 'woocommerce');

$refunded_items = [];
if (isset($tfp_refund_data['items']) && is_array($tfp_refund_data['items'])) {
    $refunded_items = $tfp_refund_data['items'];
} elseif ($latest_refund && $latest_refund instanceof WC_Order_Refund) {
    foreach ($latest_refund->get_items('line_item') as $refund_item) {
        $product = $refund_item->get_product();
        $product_id = $refund_item->get_product_id();
        $quantity = abs((int) $refund_item->get_quantity());
        $refunded_items[] = [
            'name' => $refund_item->get_name(),
            'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
            'quantity' => max(1, $quantity),
            'total' => abs((float) $refund_item->get_total()),
        ];
    }
}

if (empty($refunded_items)) {
    foreach ($order->get_items('line_item') as $order_item_id => $order_item) {
        $quantity = method_exists($order, 'get_qty_refunded_for_item')
            ? abs((int) $order->get_qty_refunded_for_item($order_item_id))
            : 0;
        $item_total = method_exists($order, 'get_total_refunded_for_item')
            ? abs((float) $order->get_total_refunded_for_item($order_item_id))
            : 0;
        if ($quantity > 0 || $item_total > 0) {
            $product = $order_item->get_product();
            $refunded_items[] = [
                'name' => $order_item->get_name(),
                'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
                'quantity' => max(1, $quantity),
                'total' => $item_total,
            ];
        }
    }
}

if (empty($refunded_items)) {
    foreach ($order->get_items('line_item') as $order_item) {
        $product = $order_item->get_product();
        $refunded_items[] = [
            'name' => $order_item->get_name(),
            'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
            'quantity' => (int) $order_item->get_quantity(),
            'total' => (float) $order_item->get_total(),
        ];
        break;
    }
}

$refund_status = !empty($tfp_refund_data['status'])
    ? (string) $tfp_refund_data['status']
    : __('Refund Processed', 'woocommerce');
$refund_note = !empty($tfp_refund_data['note'])
    ? (string) $tfp_refund_data['note']
    : __('Please allow 5–10 business days for your bank to post the credit.', 'woocommerce');
$waiting_rumble_url = 'https://rumble.com/user/thefollowproject';
$waiting_community_url = 'https://www.skool.com/the-follow-project-2107';
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background:#ffffff;">
<tr><td align="center" style="padding:4px;background:#f5f5f5;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="tfp-refund-shell" style="width:600px;max-width:100%;margin:0 auto;background:#ffffff;border:1px solid #eeeeee;">
<tr><td style="height:42px;background:#00666e;padding:0 40px;text-align:right;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:12px;font-weight:700;line-height:16px;color:#ffffff;"><a href="<?php echo esc_url($view_in_browser_url); ?>" style="color:#ffffff;text-decoration:none;"><?php esc_html_e('View in browser →', 'woocommerce'); ?></a></td></tr>
<tr><td style="height:150px;background:#fbfbfb;text-align:center;padding:0 40px;">
<img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($blogname); ?>" class="tfp-refund-logo" width="212" height="50" style="display:block;width:212px;height:50px;max-width:212px;margin:20px auto 24px;border:0;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" class="tfp-refund-nav"><tr>
<td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;"><a href="<?php echo esc_url($shop_url); ?>" style="color:#1c1b1b;text-decoration:none;">Shop</a></td>
<td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;"><a href="<?php echo esc_url(home_url('/about-us/')); ?>" style="color:#1c1b1b;text-decoration:none;">About Us</a></td>
<td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;"><a href="<?php echo esc_url(home_url('/updates/')); ?>" style="color:#1c1b1b;text-decoration:none;">Updates</a></td>
<td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;"><a href="<?php echo esc_url(home_url('/contact/')); ?>" style="color:#1c1b1b;text-decoration:none;">Contacts</a></td>
</tr></table></td></tr>
<tr><td class="tfp-refund-pad" style="padding:32px 40px 24px;background:#ffffff;">
<div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:16px;font-weight:700;line-height:21px;color:#bd9817;"><?php echo esc_html(sprintf(__('Your Order #%s Refund Processed', 'woocommerce'), $order->get_order_number())); ?></div>
<div style="padding-top:7px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:28px;font-weight:700;line-height:42px;color:#151411;"><?php esc_html_e('Your Refund Has Been Issued.', 'woocommerce'); ?></div>
<div style="padding-top:6px;font-family:'Lora',Georgia,serif;font-size:14px;line-height:25px;color:#2a2c2d;"><?php echo esc_html(sprintf(__('%s, your refund for Order #%s has been issued to your original payment method.', 'woocommerce'), $customer_name, $order->get_order_number())); ?></div>
<div style="padding-top:8px;font-family:'Lora',Georgia,serif;font-size:12px;line-height:20px;color:#777777;"><?php echo esc_html(sprintf(__('Order date: %1$s  |  Refund date: %2$s', 'woocommerce'), $order_date, $refund_date)); ?></div>
</td></tr>
<tr><td style="padding:0 40px 24px;"><div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div></td></tr>
<tr><td class="tfp-refund-pad" style="padding:20px 40px 20px;background:#ffffff;">
<div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:16px;font-weight:700;line-height:21px;color:#bd9817;"><?php esc_html_e('Refund Amount', 'woocommerce'); ?></div>
<div style="padding-top:8px;padding-bottom:12px;font-family:'Lora',Georgia,serif;font-size:14px;line-height:25px;color:#2a2c2d;"><strong style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:20px;color:#151411;"><?php echo wp_kses_post($display_refund_amount); ?></strong> — <?php echo esc_html($refund_note); ?></div>
</td></tr>
<tr><td style="padding:0 40px 24px;"><div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div></td></tr>
<tr><td class="tfp-refund-pad" style="padding:0 40px 24px;background:#ffffff;">
<div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:16px;font-weight:700;line-height:21px;color:#bd9817;"><?php esc_html_e('Refund Details', 'woocommerce'); ?></div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin-top:12px;border-collapse:collapse;">
<tr><td style="padding:5px 0;font-family:'Lora',Georgia,serif;font-size:13px;line-height:20px;color:#2a2c2d;"><?php esc_html_e('Refund status', 'woocommerce'); ?></td><td align="right" style="padding:5px 0;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;font-weight:700;color:#00666e;"><?php echo esc_html($refund_status); ?></td></tr>
<tr><td style="padding:5px 0;font-family:'Lora',Georgia,serif;font-size:13px;line-height:20px;color:#2a2c2d;"><?php esc_html_e('Payment method', 'woocommerce'); ?></td><td align="right" style="padding:5px 0;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;font-weight:700;color:#151411;"><?php echo esc_html($payment_title); ?></td></tr>
<tr><td style="padding:5px 0;font-family:'Lora',Georgia,serif;font-size:13px;line-height:20px;color:#2a2c2d;"><?php esc_html_e('Customer', 'woocommerce'); ?></td><td align="right" style="padding:5px 0;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;color:#151411;"><?php echo esc_html($customer_email); ?></td></tr>
</table>
</td></tr>
<tr><td style="padding:0 40px 24px;"><div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div></td></tr>
<tr><td class="tfp-refund-pad" style="padding:0 40px 28px;background:#ffffff;">
<div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:16px;font-weight:700;line-height:21px;color:#bd9817;"><?php esc_html_e('Refunded Item(s)', 'woocommerce'); ?></div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin-top:10px;table-layout:fixed;border-collapse:collapse;">
<?php foreach ($refunded_items as $refunded_item):
    $item_name = isset($refunded_item['name']) ? (string) $refunded_item['name'] : __('Refunded product', 'woocommerce');
    $item_image = isset($refunded_item['image']) ? (string) $refunded_item['image'] : '';
    $item_quantity = isset($refunded_item['quantity']) ? absint($refunded_item['quantity']) : 1;
    $item_total_value = isset($refunded_item['total']) ? abs((float) $refunded_item['total']) : 0;
    $item_total_html = wc_price($item_total_value, ['currency' => $order->get_currency()]);
?>
<tr>
<td width="90" valign="top" class="tfp-refund-product-image-cell" style="width:90px;padding:10px 12px 14px 0;border-bottom:1px solid #dddddd;">
<?php if ($item_image): ?><img src="<?php echo esc_url($item_image); ?>" alt="<?php echo esc_attr($item_name); ?>" width="90" height="90" class="tfp-refund-product-image" style="display:block;width:90px;height:90px;object-fit:cover;background:#d4c4a8;border:0;"><?php else: ?><div style="width:90px;height:90px;background:#f0ece5;font-family:'Lora',Georgia,serif;font-size:10px;line-height:90px;color:#6b7280;text-align:center;"><?php esc_html_e('Product', 'woocommerce'); ?></div><?php endif; ?>
</td>
<td valign="top" style="padding:17px 8px 14px 0;border-bottom:1px solid #dddddd;">
<div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:14px;font-weight:700;line-height:20px;color:#151411;"><?php echo esc_html($item_name); ?></div>
<div style="padding-top:5px;font-family:'Lora',Georgia,serif;font-size:12px;line-height:18px;color:#777777;"><?php echo esc_html(sprintf(__('Refunded quantity: %d', 'woocommerce'), max(1, $item_quantity))); ?></div>
</td>
<td width="82" align="right" valign="top" class="tfp-refund-product-price" style="width:82px;padding:16px 0 14px;border-bottom:1px solid #dddddd;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#151411;white-space:nowrap;"><?php echo wp_kses_post($item_total_html); ?></td>
</tr>
<?php endforeach; ?>
</table>
</td></tr>
<tr><td class="tfp-refund-pad" style="padding:0 40px 26px;background:#ffffff;text-align:center;">
<div style="font-family:'Lora',Georgia,serif;font-size:14px;line-height:21px;color:#2a2c2d;"><?php esc_html_e('Questions? Email us at', 'woocommerce'); ?> <a href="mailto:care@thefollowproject.com" style="color:#00666e;text-decoration:underline;">care@thefollowproject.com</a></div>
</td></tr>
<tr><td class="tfp-refund-footer" style="padding:32px 40px 28px;background:#00666e;text-align:center;">
<div class="tfp-refund-footer-title" style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:24px;font-weight:700;line-height:32px;color:#ffffff;"><?php esc_html_e('FIND US ON SOCIALS', 'woocommerce'); ?></div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="300" class="tfp-refund-social-icons" style="width:300px;max-width:100%;margin:20px auto 0;border-collapse:collapse;"><tr>
<td width="25%" align="center"><a href="https://www.youtube.com/@TheFollowProject"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-youtube.png'); ?>" alt="YouTube" width="44" height="44" style="display:block;width:44px;height:44px;border:0;margin:0 auto;"></a></td>
<td width="25%" align="center"><a href="https://www.instagram.com/thefollowproject.us"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-instagram.png'); ?>" alt="Instagram" width="44" height="44" style="display:block;width:44px;height:44px;border:0;margin:0 auto;"></a></td>
<td width="25%" align="center"><a href="https://www.tiktok.com/@thefollowprojectofficial"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-tiktok.png'); ?>" alt="TikTok" width="44" height="44" style="display:block;width:44px;height:44px;border:0;margin:0 auto;"></a></td>
<td width="25%" align="center"><a href="https://www.pinterest.com/thefollowproject/"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-pinterest.png'); ?>" alt="Pinterest" width="44" height="44" style="display:block;width:44px;height:44px;border:0;margin:0 auto;"></a></td>
</tr></table>
<div style="padding-top:20px;font-family:'Lora',Georgia,serif;font-size:14px;line-height:23px;color:#ffffff;"><?php esc_html_e('If you have any questions, reply to this email or contact', 'woocommerce'); ?><br><a href="mailto:care@thefollowproject.com" style="color:#ffffff;text-decoration:none;">care@thefollowproject.com</a></div>
</td></tr>
<tr><td style="padding:22px 40px;background:#ffffff;text-align:center;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:10px;line-height:15px;color:#333333;">
<?php esc_html_e('No longer want to receive these emails?', 'woocommerce'); ?>
<a href="<?php echo esc_url($my_account_url); ?>" style="color:#333333;text-decoration:underline;"><?php esc_html_e('Unsubscribe', 'woocommerce'); ?></a>
&nbsp;|&nbsp; <?php echo esc_html($blogname); ?> | Faith-Based Ministries
</td></tr>
</table></td></tr></table>
</body>
</html>