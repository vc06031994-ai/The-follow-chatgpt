<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style type="text/css">
@import url('https://fonts.googleapis.com/css2?family=Lora:wght@400;700&display=swap');
@import url('https://stijndv.com/fonts/Eudoxus-Sans.css');
</style>
</head>
<body style="margin:0;padding:0;background:#ffffff;">
<?php
/**
 * TFP custom WooCommerce shipping-confirmation email.
 *
 * Design source: TFP Shipping Confirmation (Figma node 3454:31243).
 */
defined('ABSPATH') || exit;

$order = isset($order) && $order instanceof WC_Order ? $order : null;
if (!$order) {
    return;
}

$my_account_url = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('myaccount')
    : home_url('/');

$shop_url = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('shop')
    : home_url('/');

$view_in_browser_url = apply_filters(
    'tfp_shipping_confirmation_email_view_in_browser_url',
    home_url('/')
);

$logo_url = defined('TFP_AUTH_URL')
    ? trailingslashit(TFP_AUTH_URL) . 'assets/images/MAIN_LOGO.png'
    : '';

$blogname = get_bloginfo('name');

$book_registration_url = 'https://thefollowproject.com/thefollowprojectstaging/newsletter/';

$shipping_address = $order->get_formatted_shipping_address();
if (empty($shipping_address)) {
    $shipping_address = $order->get_formatted_billing_address();
}

$order_date = $order->get_date_created()
    ? wc_format_datetime($order->get_date_created())
    : current_time('F j, Y');

$items = array_values($order->get_items('line_item'));

$total_item_count = 0;
foreach ($items as $item) {
    $total_item_count += (int) $item->get_quantity();
}

$display_total = wc_price($order->get_total(), ['currency' => $order->get_currency()]);
$display_subtotal = wc_price($order->get_subtotal(), ['currency' => $order->get_currency()]);
$shipping_total = (float) $order->get_shipping_total();
$display_shipping = $shipping_total > 0
    ? wc_price($shipping_total, ['currency' => $order->get_currency()])
    : __('Calculated at next step', 'woocommerce');
$display_tax = wc_price($order->get_total_tax(), ['currency' => $order->get_currency()]);

$registerable_items = [];
foreach ($items as $item) {
    $product = $item->get_product();
    if ($product) {
        $registerable_items[] = [
            'title' => $product->get_name(),
            'url' => $book_registration_url,
        ];
    }
}

$waiting_rumble_url = 'https://rumble.com/user/thefollowproject';
$waiting_community_url = 'https://www.skool.com/the-follow-project-2107';

/**
 * Resolve shipment tracking data from WooCommerce order meta.
 *
 * Printful and other fulfillment integrations can store these values using
 * different meta keys, so common keys are checked and a filter is provided
 * for integrations that expose their tracking data through another source.
 */
$tracking_number = '';
$tracking_carrier = '';
$tracking_url = '';

$tracking_meta_keys = [
    'tracking_number',
    '_tracking_number',
    'shipment_tracking_number',
    '_shipment_tracking_number',
    'carrier',
    '_carrier',
    'shipping_carrier',
    '_shipping_carrier',
    'tracking_url',
    '_tracking_url',
    'shipment_tracking_url',
    '_shipment_tracking_url',
    'tracking_link',
    '_tracking_link',
    '_printful_tracking_number',
    '_printful_carrier',
    '_printful_tracking_url',
];

foreach ($tracking_meta_keys as $meta_key) {
    $value = $order->get_meta($meta_key, true);

    if (is_array($value) || is_object($value)) {
        continue;
    }

    $value = trim((string) $value);

    if ($value === '') {
        continue;
    }

    if (!$tracking_number && stripos($meta_key, 'tracking_number') !== false) {
        $tracking_number = $value;
    } elseif (!$tracking_carrier && stripos($meta_key, 'carrier') !== false) {
        $tracking_carrier = $value;
    } elseif (!$tracking_url && (stripos($meta_key, 'tracking_url') !== false || stripos($meta_key, 'tracking_link') !== false)) {
        $tracking_url = $value;
    }
}

$tracking_data = apply_filters(
    'tfp_shipping_confirmation_tracking_data',
    [
        'number'  => $tracking_number,
        'carrier' => $tracking_carrier,
        'url'     => $tracking_url,
    ],
    $order
);

if (is_array($tracking_data)) {
    $tracking_number = trim((string) ($tracking_data['number'] ?? $tracking_number));
    $tracking_carrier = trim((string) ($tracking_data['carrier'] ?? $tracking_carrier));
    $tracking_url = trim((string) ($tracking_data['url'] ?? $tracking_url));
}

$track_order_url = $tracking_url;
if (!$track_order_url && method_exists($order, 'get_view_order_url')) {
    $track_order_url = $order->get_view_order_url();
}
if (!$track_order_url) {
    $track_order_url = $my_account_url;
}

$has_tracking_data = ($tracking_number !== '' || $tracking_carrier !== '' || $tracking_url !== '');
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background:#ffffff;">
    <tr>
        <td align="center" style="padding:4px;background:#f5f5f5;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;margin:0 auto;background:#ffffff;border:1px solid #eeeeee;">

                <tr>
                    <td style="height:42px;background:#00666e;padding:0 40px;text-align:right;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:12px;font-weight:700;line-height:16px;color:#ffffff;">
                        <a href="<?php echo esc_url($view_in_browser_url); ?>" style="color:#ffffff;text-decoration:none;">
                            <?php esc_html_e('View in browser →', 'woocommerce'); ?>
                        </a>
                    </td>
                </tr>

                <tr>
                    <td style="height:150px;background:#fbfbfb;text-align:center;padding:0 40px;">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($blogname); ?>" width="212" height="50" style="display:block;width:212px;height:50px;max-width:212px;margin:20px auto 24px;border:0;outline:none;text-decoration:none;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                            <tr>
                                <td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;color:#1c1b1b;">
                                    <a href="<?php echo esc_url($shop_url); ?>" style="color:#1c1b1b;text-decoration:none;">Shop</a>
                                </td>
                                <td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;color:#1c1b1b;">
                                    <a href="<?php echo esc_url(home_url('/about-us/')); ?>" style="color:#1c1b1b;text-decoration:none;">About Us</a>
                                </td>
                                <td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;color:#1c1b1b;">
                                    <a href="<?php echo esc_url(home_url('/updates/')); ?>" style="color:#1c1b1b;text-decoration:none;">Updates</a>
                                </td>
                                <td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;color:#1c1b1b;">
                                    <a href="<?php echo esc_url(home_url('/contact/')); ?>" style="color:#1c1b1b;text-decoration:none;">Contacts</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 40px 24px;background:#ffffff;">
                        <div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:16px;font-weight:700;line-height:21px;color:#bd9817;">
                            <?php esc_html_e('Your Order Is On Its Way', 'woocommerce'); ?>
                        </div>
                        <div style="padding-top:7px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:28px;font-weight:700;line-height:43px;color:#151411;">
                            <?php esc_html_e("It's shipped!", 'woocommerce'); ?>
                        </div>
                        <div style="padding-top:6px;font-family:'Lora',Georgia,serif;font-size:14px;line-height:25px;color:#2a2c2d;">
                            <?php
                            echo esc_html(
                                sprintf(
                                    __('%s, your order has been fulfilled and is on its way to you.', 'woocommerce'),
                                    $order->get_billing_first_name() ?: $order->get_formatted_billing_full_name()
                                )
                            );
                            ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px 16px;">
                        <div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px 24px;background:#ffffff;">
                        <div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:16px;font-weight:700;line-height:21px;color:#bd9817;">
                            <?php esc_html_e('Tracking Info', 'woocommerce'); ?>
                        </div>

                        <?php if ($has_tracking_data): ?>
                            <div style="padding-top:8px;font-family:'Lora',Georgia,serif;font-size:13px;line-height:21px;color:#2a2c2d;">
                                <?php esc_html_e('Tracking #:', 'woocommerce'); ?>
                                <?php echo esc_html($tracking_number ?: __('Not available', 'woocommerce')); ?>
                                <?php if ($tracking_carrier): ?>
                                    <?php echo ' ' . esc_html(__('via', 'woocommerce')) . ' ' . esc_html($tracking_carrier); ?>
                                <?php endif; ?>
                            </div>
                            <div style="padding-top:14px;">
                                <a href="<?php echo esc_url($track_order_url); ?>" style="display:inline-block;padding:8px 18px;background:#00666e;border-radius:8px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;line-height:18px;color:#ffffff;text-decoration:none;">
                                    <?php esc_html_e('Track Your Order →', 'woocommerce'); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div style="padding-top:8px;font-family:'Lora',Georgia,serif;font-size:13px;line-height:21px;color:#2a2c2d;">
                                <?php esc_html_e('Your tracking information will be available as soon as the carrier registers the shipment.', 'woocommerce'); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px 24px;">
                        <div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px 24px;background:#ffffff;">
                        <div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:16px;font-weight:700;line-height:21px;color:#bd9817;">
                            <?php esc_html_e('Order Summary', 'woocommerce'); ?>
                        </div>
                        <div style="padding-top:8px;font-family:'Lora',Georgia,serif;font-size:13px;line-height:21px;color:#2a2c2d;">
                            <?php
                            echo esc_html(sprintf(
                                __('Order #%s | %s | %d %s', 'woocommerce'),
                                $order->get_order_number(),
                                $order_date,
                                (int) $total_item_count,
                                _n('item', 'items', $total_item_count, 'woocommerce')
                            ));
                            ?>
                        </div>

                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin-top:10px;table-layout:fixed;border-collapse:collapse;">
                            <?php
                            foreach ($items as $item):
                                $product = $item->get_product();
                                $product_name = $item->get_name();
                                $product_image = $product
                                    ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')
                                    : '';
                                $product_description = $product
                                    ? wp_strip_all_tags($product->get_short_description())
                                    : '';
                                $product_description = trim($product_description);
                                if ($product_description === '') {
                                    $product_description = __('Physical Book', 'woocommerce');
                                }
                                if (strlen($product_description) > 56) {
                                    $product_description = substr($product_description, 0, 56) . '...';
                                }
                                $quantity = (int) $item->get_quantity();
                                $item_total = wc_price($item->get_total(), ['currency' => $order->get_currency()]);
                                ?>
                                <tr>
                                    <td width="90" valign="top" style="width:90px;padding:10px 12px 14px 0;border-bottom:1px solid #dddddd;">
                                        <?php if ($product_image): ?>
                                            <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_name); ?>" width="90" height="90" style="display:block;width:90px;height:90px;object-fit:cover;background:#d4c4a8;border:0;">
                                        <?php else: ?>
                                            <div style="width:90px;height:90px;background:#d4c4a8;font-family:'Lora',Georgia,serif;font-size:10px;font-style:italic;line-height:90px;color:#6b7280;text-align:center;">
                                                <?php esc_html_e('Book', 'woocommerce'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td valign="top" style="padding:17px 8px 14px 0;border-bottom:1px solid #dddddd;">
                                        <div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:14px;font-weight:700;line-height:20px;color:#151411;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            <?php echo esc_html($product_name); ?>
                                        </div>
                                        <div style="padding-top:3px;font-family:'Lora',Georgia,serif;font-size:12px;line-height:18px;color:#2a2c2d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            <?php echo esc_html($product_description); ?>
                                        </div>
                                        <div style="padding-top:5px;font-family:Arial,sans-serif;font-size:11px;line-height:15px;color:#969696;">
                                            <?php echo esc_html(sprintf('%s · Qty: %d', __('Physical Book', 'woocommerce'), $quantity)); ?>
                                        </div>
                                    </td>
                                    <td width="82" align="right" valign="top" style="width:82px;padding:16px 0 14px 0;border-bottom:1px solid #dddddd;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#151411;white-space:nowrap;">
                                        <?php echo wp_kses_post($item_total); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>

                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin-top:20px;">
                            <tr>
                                <td style="font-family:'Lora',Georgia,serif;font-size:12px;line-height:22px;color:#2a2c2d;">Subtotal</td>
                                <td align="right" style="font-family:'Lora',Georgia,serif;font-size:12px;line-height:22px;color:#2a2c2d;"><?php echo wp_kses_post($display_subtotal); ?></td>
                            </tr>
                            <tr>
                                <td style="font-family:'Lora',Georgia,serif;font-size:12px;line-height:22px;color:#2a2c2d;">Shipping</td>
                                <td align="right" style="font-family:'Lora',Georgia,serif;font-size:12px;line-height:22px;color:#2a2c2d;"><?php echo wp_kses_post($display_shipping); ?></td>
                            </tr>
                            <tr>
                                <td style="font-family:'Lora',Georgia,serif;font-size:12px;line-height:22px;color:#2a2c2d;"><?php esc_html_e('Tax', 'woocommerce'); ?></td>
                                <td align="right" style="font-family:'Lora',Georgia,serif;font-size:12px;line-height:22px;color:#2a2c2d;"><?php echo wp_kses_post($display_tax); ?></td>
                            </tr>
                            <tr>
                                <td style="padding-top:18px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:17px;font-weight:700;line-height:24px;color:#151411;"><?php esc_html_e('Total', 'woocommerce'); ?></td>
                                <td align="right" style="padding-top:18px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:20px;font-weight:700;line-height:24px;color:#151411;"><?php echo wp_kses_post($display_total); ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px 24px;">
                        <div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px 32px;background:#ffffff;">
                        <div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:17px;font-weight:700;line-height:24px;color:#151411;">
                            <?php esc_html_e('Registered your book.', 'woocommerce'); ?>
                        </div>
                        <div style="padding-top:6px;font-family:'Lora',Georgia,serif;font-size:13px;line-height:21px;color:#2a2c2d;">
                            <?php esc_html_e('Register to unlock your weekly teaching videos and study resources — even before your copy arrives. It only takes 30 seconds.', 'woocommerce'); ?>
                        </div>

                        <?php foreach ($registerable_items as $registerable_item): ?>
                            <div style="padding-top:12px;">
                                <a href="<?php echo esc_url($registerable_item['url']); ?>" style="display:inline-block;padding:8px 18px;background:#00666e;border-radius:8px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;line-height:18px;color:#ffffff;text-decoration:none;">
                                    <?php esc_html_e('Register Book →', 'woocommerce'); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px 24px;">
                        <div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:8px 40px 36px;background:#ffffff;">
                        <div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:16px;font-weight:700;line-height:22px;color:#bd9817;text-align:center;">
                            <?php esc_html_e("While You're Waiting", 'woocommerce'); ?>
                        </div>

                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;margin-top:16px;table-layout:fixed;border-collapse:collapse;">
                            <tr>
                                <td width="50%" valign="top" style="width:50%;padding:0 6px;text-align:center;">
                                    <a href="<?php echo esc_url($waiting_rumble_url); ?>" style="display:block;text-decoration:none;">
                                        <img src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/10/Warm-Rumble-Home-Office-Setup-2.webp" alt="Follow The Follow Project on Rumble" width="100%" style="display:block;width:100%;max-width:100%;height:170px;object-fit:cover;border:0;margin:0 auto;">
                                    </a>
                                    <div style="padding-top:12px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#bd9817;">
                                        <?php esc_html_e('Join Us Live on Rumble', 'woocommerce'); ?>
                                    </div>
                                    <div style="padding-top:3px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;">
                                        <?php esc_html_e('Stay connected between lessons', 'woocommerce'); ?>
                                    </div>
                                    <div style="padding-top:4px;font-family:'Lora',Georgia,serif;font-size:12px;line-height:18px;color:#2a2c2d;">
                                        <?php esc_html_e('Talking through our monthly lessons and teaching twice a week live.', 'woocommerce'); ?>
                                    </div>
                                    <div style="padding-top:10px;">
                                        <a href="<?php echo esc_url($waiting_rumble_url); ?>" style="display:inline-block;padding:8px 16px;background:#00666e;border-radius:8px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:12px;line-height:18px;color:#ffffff;text-decoration:none;">
                                            <?php esc_html_e('Follow on Rumble →', 'woocommerce'); ?>
                                        </a>
                                    </div>
                                </td>

                                <td width="50%" valign="top" style="width:50%;padding:0 6px;text-align:center;">
                                    <a href="<?php echo esc_url($waiting_community_url); ?>" style="display:block;text-decoration:none;">
                                        <img src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/10/Modern-Workspace-with-Colourful-Skool-Display.webp" alt="Join The Follow Project Community" width="100%" style="display:block;width:100%;max-width:100%;height:170px;object-fit:cover;border:0;margin:0 auto;">
                                    </a>
                                    <div style="padding-top:12px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#bd9817;">
                                        <?php esc_html_e('Join Community', 'woocommerce'); ?>
                                    </div>
                                    <div style="padding-top:3px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;">
                                        <?php esc_html_e('Walk this journey with others', 'woocommerce'); ?>
                                    </div>
                                    <div style="padding-top:4px;font-family:'Lora',Georgia,serif;font-size:12px;line-height:18px;color:#2a2c2d;">
                                        <?php esc_html_e('Join believers who are studying, praying and putting Scripture into practice together.', 'woocommerce'); ?>
                                    </div>
                                    <div style="padding-top:10px;">
                                        <a href="<?php echo esc_url($waiting_community_url); ?>" style="display:inline-block;padding:8px 16px;background:#00666e;border-radius:8px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:12px;line-height:18px;color:#ffffff;text-decoration:none;">
                                            <?php esc_html_e('Join Free Skool →', 'woocommerce'); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 40px 28px;background:#00666e;text-align:center;">
                        <div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:24px;font-weight:700;line-height:32px;color:#ffffff;">
                            <?php esc_html_e('FIND US ON SOCIALS', 'woocommerce'); ?>
                        </div>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="300" style="width:300px;max-width:100%;margin:20px auto 0;border-collapse:collapse;">
                            <tr>
                                <td width="25%" align="center" style="padding:0;"><a href="https://www.youtube.com/@TheFollowProject" style="text-decoration:none;"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-youtube.png'); ?>" alt="YouTube" width="44" height="44" style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;"></a></td>
                                <td width="25%" align="center" style="padding:0;"><a href="https://www.instagram.com/thefollowproject.us" style="text-decoration:none;"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-instagram.png'); ?>" alt="Instagram" width="44" height="44" style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;"></a></td>
                                <td width="25%" align="center" style="padding:0;"><a href="https://www.tiktok.com/@thefollowprojectofficial" style="text-decoration:none;"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-tiktok.png'); ?>" alt="TikTok" width="44" height="44" style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;"></a></td>
                                <td width="25%" align="center" style="padding:0;"><a href="https://www.pinterest.com/thefollowproject/" style="text-decoration:none;"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-pinterest.png'); ?>" alt="Pinterest" width="44" height="44" style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;"></a></td>
                            </tr>
                        </table>
                        <div style="padding-top:20px;font-family:'Lora',Georgia,serif;font-size:14px;line-height:23px;color:#ffffff;">
                            <?php esc_html_e('If you have any questions, reply to this email or contact', 'woocommerce'); ?><br>
                            <a href="mailto:care@thefollowproject.com" style="color:#ffffff;text-decoration:none;">care@thefollowproject.com</a>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 40px;background:#ffffff;text-align:center;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:10px;line-height:15px;color:#333333;">
                        <?php
                        $unsubscribe_url = function_exists('wc_get_account_endpoint_url')
                            ? wc_get_account_endpoint_url('dashboard')
                            : $my_account_url;
                        ?>
                        <?php esc_html_e('No longer want to receive these emails?', 'woocommerce'); ?>
                        <a href="<?php echo esc_url($unsubscribe_url); ?>" style="color:#333333;text-decoration:underline;"><?php esc_html_e('Unsubscribe', 'woocommerce'); ?></a>
                        &nbsp;|&nbsp; <?php echo esc_html($blogname); ?> | Faith-Based Ministries
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
