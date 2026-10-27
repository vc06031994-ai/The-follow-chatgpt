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
    plugins_url(
        'assets/images/logo-expanded.svg',
        WP_PLUGIN_DIR . '/tfp-dashboard/tfp-dashboard.php'
    )
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
    ? __('Set password & view orders →', 'woocommerce')
    : __('View orders →', 'woocommerce');

$safe_heading = esc_html__('Welcome to The Follow Project', 'woocommerce');
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0;padding:0;background:#ffffff;">
    <tr>
        <td align="center" style="padding:0;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;margin:0 auto;background:#ffffff;">
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
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none" style="display:block;width:32px;height:32px;">
                                            <path d="M16 11C16 9.93913 16.4214 8.92172 17.1716 8.17157C17.9217 7.42143 18.9391 7 20 7H29V25H20C18.9391 25 17.9217 25.4214 17.1716 26.1716 16.4214 26.9217 16 27.9391 16 29" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="white" />
                                            <path d="M3 25H12C13.0609 25 14.0783 25.4214 14.8284 26.1716C15.5786 26.9217 16 27.9391 16 29V11C16 9.93913 15.5786 8.92172 14.8284 8.17157C14.0786 7.42143 13.0609 7 12 7H3V25Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="white" />
                                            <path d="M20 12H25" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="white" />
                                            <path d="M20 16H25" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="white" />
                                            <path d="M20 20H25" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="white" />
                                            </svg>
                                        </span>
                                    </div>
                                    <div style="padding-top:7px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;"><?php esc_html_e('Browse Books', 'woocommerce'); ?></div>
                                    <a href="<?php echo esc_url($shop_url); ?>" style="display:block;padding-top:4px;font-family:Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#00666e;text-decoration:none;"><?php esc_html_e('Visit the shop →', 'woocommerce'); ?></a>
                                </td>
                                <td width="33.33%" align="center" style="padding:0 8px;vertical-align:top;">
                                    <div style="width:62px;height:62px;margin:0 auto;background:#570506;border-radius:50%;font-size:0;line-height:62px;text-align:center;color:#ffffff;">
                                        <span style="display:inline-block;width:32px;height:32px;line-height:0;vertical-align:middle;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none" style="display:block;width:32px;height:32px;">
                                                <path d="M5.33332 22.6667H4.66666C3.56209 22.6667 2.66666 23.5621 2.66666 24.6667C2.66666 25.7713 3.56209 26.6667 4.66666 26.6667H27.3333C28.4379 26.6667 29.3333 25.7713 29.3333 24.6667C29.3333 23.5621 28.4379 22.6667 27.3333 22.6667H26.6667M5.33332 22.6667H26.6667M5.33332 22.6667V10.9336C5.33332 9.44014 5.33332 8.69285 5.62397 8.12242C5.87963 7.62065 6.28729 7.213 6.78905 6.95734C7.35948 6.66669 8.10678 6.66669 9.60025 6.66669H22.4002C23.8937 6.66669 24.6394 6.66669 25.2099 6.95734C25.7116 7.213 26.1206 7.62065 26.3763 8.12242C26.6667 8.69229 26.6667 9.43868 26.6667 10.9292V22.6667" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="white" />
                                            </svg>  
                                        </span>
                                    </div>
                                    <div style="padding-top:7px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;"><?php esc_html_e('My Account', 'woocommerce'); ?></div>
                                    <a href="<?php echo esc_url($set_password_link); ?>" style="display:block;padding-top:4px;font-family:Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#00666e;text-decoration:none;"><?php echo esc_html($account_link_label); ?></a>
                                </td>
                                <td width="33.33%" align="center" style="padding:0 8px;vertical-align:top;">
                                    <div style="width:62px;height:62px;margin:0 auto;background:#570506;border-radius:50%;font-family:Arial,sans-serif;font-size:26px;line-height:62px;color:#ffffff;">
                                        <span style="display:inline-block;width:32px;height:32px;line-height:0;vertical-align:middle;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none">
                                                <path d="M22.6667 26.6667C22.6667 24.4575 19.6819 22.6667 16 22.6667C12.3181 22.6667 9.33333 24.4575 9.33333 26.6667M28 22.6672C28 21.0269 26.3545 19.6173 24 19M4 22.6672C4 21.0269 5.64546 19.6173 8 19M24 13.6482C24.8183 12.9157 25.3333 11.8514 25.3333 10.6667C25.3333 8.45755 23.5425 6.66669 21.3333 6.66669C20.3089 6.66669 19.3743 7.05183 18.6667 7.68521M8 13.6482C7.18167 12.9157 6.66667 11.8514 6.66667 10.6667C6.66667 8.45755 8.45753 6.66669 10.6667 6.66669C11.6911 6.66669 12.6257 7.05183 13.3333 7.68521M16 18.6667C13.7909 18.6667 12 16.8758 12 14.6667C12 12.4575 13.7909 10.6667 16 10.6667C18.2091 10.6667 20 12.4575 20 14.6667C20 16.8758 18.2091 18.6667 16 18.6667Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke="white"/>
                                            </svg>
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
                        <div style="padding-top:20px;font-family:Arial,sans-serif;font-size:22px;line-height:30px;color:#ffffff;">◯ &nbsp;&nbsp;&nbsp; ◎ &nbsp;&nbsp;&nbsp; ♪ &nbsp;&nbsp;&nbsp; P</div>
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
