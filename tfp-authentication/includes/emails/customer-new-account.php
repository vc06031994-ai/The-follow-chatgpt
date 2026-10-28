<?php

/**
 * TFP custom WooCommerce new-account welcome email.
 *
 * Trigger:
 * - Guest e-commerce checkout creates a new customer account.
 * - tfp-ecommerce-checkout-fix triggers WC_Email_Customer_New_Account
 *   after the order has been created.
 *
 * Design source: TFP Welcome Email (Figma node 3454:31237).
 */
defined('ABSPATH') || exit;

$my_account_url = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('myaccount')
    : home_url('/');

$orders_url = function_exists('wc_get_account_endpoint_url')
    ? wc_get_account_endpoint_url('orders')
    : $my_account_url;

$shop_url = function_exists('wc_get_page_permalink')
    ? wc_get_page_permalink('shop')
    : home_url('/');

$view_in_browser_url = apply_filters(
    'tfp_welcome_email_view_in_browser_url',
    home_url('/')
);

$hero_image_url = apply_filters(
    'tfp_welcome_email_hero_image_url',
    function_exists('wp_get_attachment_image_url')
        ? wp_get_attachment_image_url(6887, 'full')
        : ''
);

$logo_url = apply_filters(
    'tfp_welcome_email_logo_url',
    defined('TFP_AUTH_URL')
        ? trailingslashit(TFP_AUTH_URL) . 'assets/images/MAIN_LOGO.png'
        : ''
);

$books = [];
if (function_exists('wc_get_products')) {
    $preferred_slugs = ['follow-me', 'empowered-in-grace'];

    foreach ($preferred_slugs as $slug) {
        $product_id = get_page_by_path($slug, OBJECT, 'product');
        if ($product_id) {
            $product = wc_get_product($product_id->ID);
            if ($product) {
                $books[$product->get_id()] = $product;
            }
        }
    }

    if (count($books) < 2) {
        $fallback_books = wc_get_products([
            'status' => 'publish',
            'limit' => 4,
            'category' => ['books'],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        foreach ($fallback_books as $product) {
            $books[$product->get_id()] = $product;
            if (count($books) >= 2) {
                break;
            }
        }
    }
}

$books = array_values($books);

$book_cards = [];
foreach ($books as $product) {
    $book_cards[] = [
        'title' => $product->get_name(),
        'url' => $product->get_permalink(),
        'image' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
    ];
}

while (count($book_cards) < 2) {
    $book_cards[] = [
        'title' => '',
        'url' => $shop_url,
        'image' => '',
    ];
}

$set_password_link = !empty($password_generated) && !empty($set_password_url)
    ? $set_password_url
    : $my_account_url;

$account_link_label = !empty($password_generated) && !empty($set_password_url)
    ? __('View orders →', 'woocommerce')
    : __('View orders →', 'woocommerce');

$safe_heading = esc_html__('Welcome to The Follow Project', 'woocommerce');
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background:#ffffff;">
    <tr>
        <td align="center" style="padding:4px;background:#f5f5f5;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;margin:0 auto;background:#ffffff;border:1px solid #eeeeee; box-shadow: rgba(0, 0, 0, 0.1) 0px 0px 5px 0px, rgba(0, 0, 0, 0.1) 0px 0px 1px 0px;">
                <tr>
                    <td style="height:42px;background:#00666e;padding:0 40px;text-align:right;font-family:Arial,sans-serif;font-size:12px;font-weight:700;line-height:16px;color:#ffffff;">
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
                                <td style="padding:0 28px;font-family:Arial,sans-serif;font-size:18px;line-height:24px;color:#1c1b1b;">
                                    <a href="<?php echo esc_url($shop_url); ?>" style="color:#1c1b1b;text-decoration:none;"><?php esc_html_e('Shop', 'woocommerce'); ?></a>
                                </td>
                                <td style="padding:0 28px;font-family:Arial,sans-serif;font-size:18px;line-height:24px;color:#1c1b1b;">
                                    <a href="<?php echo esc_url(home_url('/about-us/')); ?>" style="color:#1c1b1b;text-decoration:none;"><?php esc_html_e('About Us', 'woocommerce'); ?></a>
                                </td>
                                <td style="padding:0 28px;font-family:Arial,sans-serif;font-size:18px;line-height:24px;color:#1c1b1b;">
                                    <a href="<?php echo esc_url(home_url('/updates/')); ?>" style="color:#1c1b1b;text-decoration:none;"><?php esc_html_e('Updates', 'woocommerce'); ?></a>
                                </td>
                                <td style="padding:0 28px;font-family:Arial,sans-serif;font-size:18px;line-height:24px;color:#1c1b1b;">
                                    <a href="<?php echo esc_url(home_url('/contact/')); ?>" style="color:#1c1b1b;text-decoration:none;"><?php esc_html_e('Contacts', 'woocommerce'); ?></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="height:300px;background:#3f4546<?php echo $hero_image_url ? ';background-image:url(' . esc_url($hero_image_url) . ');background-size:cover;background-position:center' : ''; ?>;text-align:center;vertical-align:middle;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="padding:0 40px;background:rgba(7,6,6,0.37);height:300px;text-align:center;vertical-align:middle;">
                                    <div style="font-family:Arial,sans-serif;font-size:28px;font-weight:700;line-height:42px;color:#ffffff;">
                                        <?php echo $safe_heading; ?>.
                                    </div>
                                    <div style="padding-top:11px;font-family:Georgia,serif;font-size:15px;font-style:italic;line-height:22px;color:#ffffff;">
                                        <?php esc_html_e('We equip, empower, and engage disciples globally.', 'woocommerce'); ?>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 40px 24px;background:#ffffff;">
                        <div style="font-family:Arial,sans-serif;font-size:28px;font-weight:700;line-height:42px;color:#151411;">
                            <?php esc_html_e("You're all set.", 'woocommerce'); ?>
                        </div>
                        <div style="padding-top:7px;font-family:Georgia,serif;font-size:14px;line-height:25px;color:#2a2c2d;">
                            <?php esc_html_e('Your account is ready. Track your orders and access your study resources all in one place.', 'woocommerce'); ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px;">
                        <div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 40px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td width="33.33%" align="center" style="padding:0 8px;vertical-align:top;">
                                    <div style="width:62px;height:62px;margin:0 auto;background:#570506;border-radius:50%;font-size:0;line-height:62px;text-align:center;color:#ffffff;">
                                        <span style="display:inline-block;width:32px;height:32px;line-height:0;vertical-align:middle;">
                                            <img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/welcome-icon-books.png'); ?>" alt="" width="32" height="32" style="display:block;width:32px;height:32px;border:0;outline:none;text-decoration:none;">
                                        </span>
                                    </div>
                                    <div style="padding-top:7px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;"><?php esc_html_e('Browse Books', 'woocommerce'); ?></div>
                                    <a href="<?php echo esc_url($shop_url); ?>" style="display:block;padding-top:4px;font-family:Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#00666e;text-decoration:none;"><?php esc_html_e('Visit the shop →', 'woocommerce'); ?></a>
                                </td>
                                <td width="33.33%" align="center" style="padding:0 8px;vertical-align:top;">
                                    <div style="width:62px;height:62px;margin:0 auto;background:#570506;border-radius:50%;font-size:0;line-height:62px;text-align:center;color:#ffffff;">
                                        <span style="display:inline-block;width:32px;height:32px;line-height:0;vertical-align:middle;">
                                            <img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/welcome-icon-account.png'); ?>" alt="" width="32" height="32" style="display:block;width:32px;height:32px;border:0;outline:none;text-decoration:none;">  
                                        </span>
                                    </div>
                                    <div style="padding-top:7px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;"><?php esc_html_e('My Account', 'woocommerce'); ?></div>
                                    <a href="<?php echo esc_url($set_password_link); ?>" style="display:block;padding-top:4px;font-family:Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#00666e;text-decoration:none;"><?php echo esc_html($account_link_label); ?></a>
                                </td>
                                <td width="33.33%" align="center" style="padding:0 8px;vertical-align:top;">
                                    <div style="width:62px;height:62px;margin:0 auto;background:#570506;border-radius:50%;font-family:Arial,sans-serif;font-size:26px;line-height:62px;color:#ffffff;">
                                        <span style="display:inline-block;width:32px;height:32px;line-height:0;vertical-align:middle;">
                                            <img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/welcome-icon-community.png'); ?>" alt="" width="32" height="32" style="display:block;width:32px;height:32px;border:0;outline:none;text-decoration:none;">
                                        </span>
                                    </div>
                                    <div style="padding-top:7px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;"><?php esc_html_e('Join Community', 'woocommerce'); ?></div>
                                    <a href="<?php echo esc_url(home_url('/updates/')); ?>" style="display:block;padding-top:4px;font-family:Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#00666e;text-decoration:none;"><?php esc_html_e('Get worksheets →', 'woocommerce'); ?></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 40px;">
                        <div style="height:1px;background:#570506;font-size:1px;line-height:1px;">&nbsp;</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 40px 40px;background:#ffffff;">
                        <div style="font-family:Arial,sans-serif;font-size:16px;font-weight:700;line-height:22px;color:#bd9817;text-align:center;">
                            <?php esc_html_e('Start Your Journey', 'woocommerce'); ?>
                        </div>
                        <div style="padding-top:8px;font-family:Georgia,serif;font-size:14px;line-height:21px;color:#2a2c2d;text-align:center;">
                            <?php esc_html_e('Begin with one of our most-read titles:', 'woocommerce'); ?>
                        </div>

                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:12px;">
                            <tr>
                                <?php foreach ($book_cards as $card): ?>
                                    <td width="50%" valign="top" style="padding:0 12px 0 0;text-align:center;">
                                        <?php if (!empty($card['image'])): ?>
                                            <a href="<?php echo esc_url($card['url']); ?>" style="text-decoration:none;">
                                                <img src="<?php echo esc_url($card['image']); ?>" alt="<?php echo esc_attr($card['title']); ?>" width="250" style="display:block;width:100%;max-width:250px;height:220px;object-fit:cover;background:#d4c4a8;border:0;margin:0 auto;">
                                            </a>
                                        <?php else: ?>
                                            <div style="height:220px;background:#d4c4a8;font-family:Georgia,serif;font-size:13px;font-style:italic;line-height:220px;color:#6b7280;text-align:center;">
                                                <?php esc_html_e('Book Cover', 'woocommerce'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($card['title'])): ?>
                                            <div style="padding-top:12px;font-family:Arial,sans-serif;font-size:16px;font-weight:700;line-height:24px;color:#151411;"><?php echo esc_html($card['title']); ?></div>
                                        <?php endif; ?>
                                        <div style="padding-top:0;font-family:Georgia,serif;font-size:12px;line-height:18px;color:#2a2c2d;">
                                            <?php esc_html_e('Explore more from The Follow Project', 'woocommerce'); ?>
                                        </div>
                                        <div style="padding-top:12px;">
                                            <a href="<?php echo esc_url($card['url']); ?>" style="display:inline-block;padding:8px 20px;background:#00666e;border-radius:8px;font-family:Arial,sans-serif;font-size:14px;line-height:18px;color:#ffffff;text-decoration:none;"><?php esc_html_e('Shop Now →', 'woocommerce'); ?></a>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 40px 28px;background:#00666e;text-align:center;">
                        <div style="font-family:Arial,sans-serif;font-size:24px;font-weight:700;line-height:32px;color:#ffffff;"><?php esc_html_e('FIND US ON SOCIALS', 'woocommerce'); ?></div>
                        <div style="padding-top:20px;font-family:Arial,sans-serif;font-size:22px;line-height:30px;color:#ffffff;">
                            <img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/footer-logo.png'); ?>" alt="" width="299" height="44" style="display:block;width:299px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;">
                        </div>
                        <div style="padding-top:20px;font-family:Georgia,serif;font-size:14px;line-height:23px;color:#ffffff;">
                            <?php esc_html_e('If you have any questions, reply to this email or contact', 'woocommerce'); ?><br>
                            <a href="mailto:care@thefollowproject.com" style="color:#ffffff;text-decoration:none;">care@thefollowproject.com</a>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 40px;background:#ffffff;text-align:center;font-family:Arial,sans-serif;font-size:10px;line-height:15px;color:#333333;">
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
