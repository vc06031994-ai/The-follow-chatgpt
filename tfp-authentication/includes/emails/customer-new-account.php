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
                                            <img src="<?php echo esc_url(plugins_url('assets/images/welcome-icon-books.png', __FILE__)); ?>" alt="" width="32" height="32" style="display:block;width:32px;height:32px;border:0;outline:none;text-decoration:none;">
                                        </span>
                                    </div>
                                    <div style="padding-top:7px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;"><?php esc_html_e('Browse Books', 'woocommerce'); ?></div>
                                    <a href="<?php echo esc_url($shop_url); ?>" style="display:block;padding-top:4px;font-family:Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#00666e;text-decoration:none;"><?php esc_html_e('Visit the shop →', 'woocommerce'); ?></a>
                                </td>
                                <td width="33.33%" align="center" style="padding:0 8px;vertical-align:top;">
                                    <div style="width:62px;height:62px;margin:0 auto;background:#570506;border-radius:50%;font-size:0;line-height:62px;text-align:center;color:#ffffff;">
                                        <span style="display:inline-block;width:32px;height:32px;line-height:0;vertical-align:middle;">
                                            <img src="<?php echo esc_url(plugins_url('assets/images/welcome-icon-account.png', __FILE__)); ?>" alt="" width="32" height="32" style="display:block;width:32px;height:32px;border:0;outline:none;text-decoration:none;">  
                                        </span>
                                    </div>
                                    <div style="padding-top:7px;font-family:Arial,sans-serif;font-size:15px;font-weight:700;line-height:22px;color:#151411;"><?php esc_html_e('My Account', 'woocommerce'); ?></div>
                                    <a href="<?php echo esc_url($set_password_link); ?>" style="display:block;padding-top:4px;font-family:Arial,sans-serif;font-size:13px;font-weight:700;line-height:20px;color:#00666e;text-decoration:none;"><?php echo esc_html($account_link_label); ?></a>
                                </td>
                                <td width="33.33%" align="center" style="padding:0 8px;vertical-align:top;">
                                    <div style="width:62px;height:62px;margin:0 auto;background:#570506;border-radius:50%;font-family:Arial,sans-serif;font-size:26px;line-height:62px;color:#ffffff;">
                                        <span style="display:inline-block;width:32px;height:32px;line-height:0;vertical-align:middle;">
                                            <img src="<?php echo esc_url(plugins_url('assets/images/welcome-icon-community.png', __FILE__)); ?>" alt="" width="32" height="32" style="display:block;width:32px;height:32px;border:0;outline:none;text-decoration:none;">
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="299" height="44" viewBox="0 0 299 44" fill="none">
<path d="M107.048 44H106.952C94.8476 44 85 34.1524 85 22.0484V21.9516C85 9.8476 94.8476 0 106.952 0H107.048C119.152 0 129 9.8476 129 21.9516V22.0484C129 34.1524 119.152 44 107.048 44ZM106.952 1.48935C95.6682 1.48935 86.4894 10.6682 86.4894 21.9516V22.0484C86.4894 33.3317 95.6682 42.5106 106.952 42.5106H107.048C118.332 42.5106 127.511 33.3317 127.511 22.0484V21.9516C127.511 10.6682 118.332 1.48935 107.048 1.48935H106.952Z" fill="white"/>
<path d="M113.578 9.39331H100.424C96.7897 9.39331 93.8334 12.3497 93.8334 15.9837V28.0177C93.8334 31.6517 96.7897 34.6081 100.424 34.6081H113.578C117.212 34.6081 120.168 31.6517 120.168 28.0177V15.9837C120.168 12.3497 117.212 9.39331 113.578 9.39331ZM96.1583 15.9837C96.1583 13.632 98.0721 11.7182 100.424 11.7182H113.578C115.929 11.7182 117.843 13.632 117.843 15.9837V28.0177C117.843 30.3694 115.929 32.2832 113.578 32.2832H100.424C98.0721 32.2832 96.1583 30.3694 96.1583 28.0177V15.9837Z" fill="white"/>
<path d="M107.001 28.1294C110.38 28.1294 113.131 25.3801 113.131 21.9992C113.131 18.6184 110.382 15.869 107.001 15.869C103.62 15.869 100.871 18.6184 100.871 21.9992C100.871 25.3801 103.62 28.1294 107.001 28.1294ZM107.001 18.1954C109.099 18.1954 110.806 19.9022 110.806 22.0007C110.806 24.0992 109.099 25.806 107.001 25.806C104.902 25.806 103.195 24.0992 103.195 22.0007C103.195 19.9022 104.902 18.1954 107.001 18.1954Z" fill="white"/>
<path d="M113.698 16.8594C114.608 16.8594 115.35 16.1192 115.35 15.2077C115.35 14.2963 114.61 13.556 113.698 13.556C112.787 13.556 112.047 14.2963 112.047 15.2077C112.047 16.1192 112.787 16.8594 113.698 16.8594Z" fill="white"/>
<path d="M192.048 44H191.952C179.848 44 170 34.1524 170 22.0484V21.9516C170 9.84759 179.848 0 191.952 0H192.048C204.152 0 214 9.84759 214 21.9516V22.0484C214 34.1524 204.152 44 192.048 44ZM191.952 1.48935C180.668 1.48935 171.489 10.6682 171.489 21.9516V22.0484C171.489 33.3317 180.668 42.5106 191.952 42.5106H192.048C203.332 42.5106 212.511 33.3317 212.511 22.0484V21.9516C212.511 10.6682 203.332 1.48935 192.048 1.48935H191.952Z" fill="white"/>
<path d="M202.276 16.1222V20.136C201.574 20.0675 200.663 19.9082 199.655 19.5388C198.338 19.0563 197.358 18.3965 196.716 17.8856V25.9981L196.7 25.9728C196.71 26.1337 196.716 26.2975 196.716 26.4628C196.716 30.4915 193.438 33.7711 189.408 33.7711C185.378 33.7711 182.1 30.4915 182.1 26.4628C182.1 22.4341 185.378 19.153 189.408 19.153C189.802 19.153 190.19 19.1843 190.568 19.2454V23.2011C190.205 23.0715 189.814 23.0015 189.408 23.0015C187.5 23.0015 185.947 24.5535 185.947 26.4628C185.947 28.3722 187.5 29.9241 189.408 29.9241C191.316 29.9241 192.869 28.3707 192.869 26.4628C192.869 26.3913 192.868 26.3198 192.863 26.2483V10.4835H196.874C196.889 10.8231 196.902 11.1657 196.917 11.5052C196.944 12.174 197.182 12.8158 197.598 13.3416C198.085 13.9597 198.804 14.6775 199.814 15.2509C200.76 15.7856 201.647 16.0165 202.276 16.1252V16.1222Z" fill="white"/>
<path d="M277.048 44H276.952C264.848 44 255 34.1524 255 22.0484V21.9516C255 9.84759 264.848 0 276.952 0H277.048C289.152 0 299 9.84759 299 21.9516V22.0484C299 34.1524 289.152 44 277.048 44ZM276.952 1.48935C265.668 1.48935 256.489 10.6682 256.489 21.9516V22.0484C256.489 33.3318 265.668 42.5106 276.952 42.5106H277.048C288.332 42.5106 297.511 33.3318 297.511 22.0484V21.9516C297.511 10.6682 288.332 1.48935 277.048 1.48935H276.952Z" fill="white"/>
<path d="M275.06 27.8107C275.035 27.8985 275.011 27.973 274.992 28.0475C273.867 32.453 273.742 33.4315 272.586 35.4779C272.035 36.4504 271.414 37.3723 270.728 38.254C270.65 38.3538 270.577 38.4819 270.422 38.4506C270.252 38.4149 270.239 38.263 270.221 38.1259C270.037 36.787 269.934 35.4451 269.978 34.0928C270.037 32.3323 270.254 31.7277 272.524 22.1839C272.556 22.0379 272.519 21.9173 272.472 21.7862C271.928 20.3207 271.821 18.8328 272.296 17.3256C273.324 14.0699 277.014 13.8211 277.659 16.5064C278.057 18.1671 277.005 20.3415 276.198 23.5556C275.529 26.2066 278.651 28.0921 281.318 26.156C283.779 24.3717 284.733 20.0943 284.552 17.062C284.194 11.0167 277.565 9.71055 273.361 11.6571C268.54 13.8867 267.444 19.862 269.621 22.5935C269.897 22.9405 270.416 23.1535 270.325 23.505C270.185 24.0515 270.062 24.6026 269.91 25.1462C269.797 25.5513 269.152 25.6988 268.742 25.5319C267.939 25.2073 267.271 24.6964 266.729 24.0232C264.88 21.7341 264.352 17.2065 266.796 13.3729C269.503 9.12673 274.54 7.408 279.139 7.92928C284.632 8.55332 288.104 12.3065 288.753 16.5646C289.05 18.5037 288.837 23.286 286.114 26.6669C282.982 30.5511 277.905 30.8087 275.562 28.4243C275.382 28.2411 275.237 28.0281 275.06 27.8092V27.8107Z" fill-rule="evenodd" clip-rule="evenodd" fill="white"/>
<path d="M22 44C9.86872 44 0 34.1313 0 22C0 9.86872 9.86872 0 22 0C34.1313 0 44 9.86872 44 22C44 34.1313 34.1313 44 22 44ZM22 1.49345C10.6931 1.49345 1.49345 10.6931 1.49345 22C1.49345 33.3069 10.6931 42.5065 22 42.5065C33.3069 42.5065 42.5065 33.3069 42.5065 22C42.5065 10.6931 33.3069 1.49345 22 1.49345Z" fill="white"/>
<path d="M35.3828 17.5973C35.2155 15.9829 34.8556 14.1982 33.5309 13.2603C32.5049 12.533 31.1564 12.5061 29.8974 12.5076C27.236 12.5091 24.5732 12.5121 21.9119 12.5136C19.3521 12.5166 16.7923 12.5181 14.2326 12.5211C13.1633 12.5226 12.1238 12.4389 11.1307 12.9019C10.2779 13.2991 9.61035 14.0548 9.20861 14.8956C8.65155 16.065 8.53507 17.3897 8.46786 18.683C8.34391 21.0382 8.35735 23.3993 8.5052 25.753C8.61422 27.4705 8.8905 29.3687 10.2182 30.4634C11.395 31.4326 13.0557 31.4804 14.582 31.4819C19.4268 31.4864 24.273 31.4908 29.1193 31.4938C29.7406 31.4953 30.3887 31.4834 31.0219 31.4147C32.2675 31.2803 33.4548 30.9233 34.2552 30.0004C35.0632 29.07 35.2708 27.7752 35.3933 26.549C35.692 23.5741 35.689 20.5708 35.3828 17.5973ZM19.1102 26.1712V17.8303L26.3325 22L19.1102 26.1712Z" fill="white"/>
</svg>
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
