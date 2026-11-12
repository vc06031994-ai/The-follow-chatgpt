<?php
/**
 * TFP New Book Release email template.
 * Reuses the established TFP email header/footer and WooCommerce product data.
 */
defined('ABSPATH') || exit;

$book = isset($book) && $book instanceof WC_Product ? $book : null;
if (!$book && function_exists('wc_get_products')) {
    $latest = wc_get_products(['status' => 'publish', 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'category' => ['books']]);
    $book = !empty($latest) ? $latest[0] : null;
}
if (!$book) {
    echo '<p>' . esc_html__('No published book is available for this email preview.', 'tfp-authentication') . '</p>';
    return;
}

$book_title = $book->get_name();
$book_subtitle = (string) get_post_meta($book->get_id(), '_tfp_book_subtitle', true);
$scripture_reference = (string) get_post_meta($book->get_id(), '_tfp_scripture_reference', true);
$book_cover = wp_get_attachment_image_url($book->get_image_id(), 'large');
$book_url = $book->get_permalink();
$hero_copy = apply_filters('tfp_book_release_intro', __('Every book in this series was written to move you from knowing Scripture to living it. This one is no different.', 'tfp-authentication'), $book);
$view_url = apply_filters('tfp_book_release_view_url', home_url('/'));
$logo_url = defined('TFP_AUTH_URL') ? trailingslashit(TFP_AUTH_URL) . 'assets/images/MAIN_LOGO.png' : '';
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$related = function_exists('wc_get_products') ? wc_get_products(['status' => 'publish', 'limit' => 2, 'exclude' => [$book->get_id()], 'category' => ['books'], 'orderby' => 'date', 'order' => 'DESC']) : [];
$blogname = get_bloginfo('name');
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
    style="margin:0;padding:0;background:#fff;">
    <tr>
        <td align="center" style="padding:4px;background:#f5f5f5;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"
                style="width:600px;max-width:100%;margin:0 auto;background:#fff;border:1px solid #eee;border-collapse:collapse;">
                <tr>
                    <td
                        style="height:42px;background:#00666e;padding:0 24px;text-align:right;font:700 12px/16px Arial,sans-serif;color:#fff;">
                        <a href="<?php echo esc_url($view_url); ?>"
                            style="color:#fff;text-decoration:none;"><?php esc_html_e('View in browser →', 'tfp-authentication'); ?></a>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="height:150px;background:#fbfbfb;padding:18px 20px 12px;">
                        <?php if ($logo_url): ?><a href="<?php echo esc_url(home_url('/')); ?>"><img
                                    src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($blogname); ?>"
                                    width="212"
                                    style="display:block;width:212px;max-width:100%;height:auto;margin:0 auto 22px;border:0;"></a><?php endif; ?>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                            <tr>
                                <?php foreach ([__('Shop', 'tfp-authentication') => $shop_url, __('About Us', 'tfp-authentication') => home_url('/about-us/'), __('Updates', 'tfp-authentication') => home_url('/updates/'), __('Contacts', 'tfp-authentication') => home_url('/contact/')] as $label => $url): ?>
                                    <td style="padding:0 12px;font:16px/24px Arial,sans-serif;white-space:nowrap;"><a
                                            href="<?php echo esc_url($url); ?>"
                                            style="color:#1c1b1b;text-decoration:none;"><?php echo esc_html($label); ?></a>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td align="center"
                        style="background:#00666e;padding:13px 20px;font:700 12px/18px Arial,sans-serif;letter-spacing:2px;text-transform:uppercase;color:#fff;">
                        <?php esc_html_e('New Book Release', 'tfp-authentication'); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:36px 36px 0;">
                        <div style="font:700 15px/22px Arial,sans-serif;color:#bd9817;">
                            <?php esc_html_e('Just Released', 'tfp-authentication'); ?>
                        </div>
                        <h1 style="margin:8px 0 10px;font:800 28px/1.25 Arial,sans-serif;color:#151411;">
                            <?php esc_html_e('A new book from', 'tfp-authentication'); ?><br><?php esc_html_e('The Follow Project.', 'tfp-authentication'); ?>
                        </h1>
                        <p style="margin:0 0 10px;font:14px/25px Georgia,serif;color:#2a2c2d;">
                            <?php echo esc_html($hero_copy); ?>
                        </p>
                        <?php if ($book_cover): ?><a href="<?php echo esc_url($book_url); ?>"><img
                                    src="<?php echo esc_url($book_cover); ?>" alt="<?php echo esc_attr($book_title); ?>"
                                    width="528"
                                    style="display:block;width:100%;height:auto;max-height:340px;object-fit:contain;background:#d4c4a8;border:0;"></a><?php else: ?>
                            <div style="height:300px;background:#d4c4a8;"></div><?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 36px 32px;">
                        <div style="font:700 15px/22px Arial,sans-serif;color:#bd9817;">
                            <?php echo esc_html($book_title); ?>
                        </div>
                        <h2 style="margin:6px 0;font:700 23px/1.35 Arial,sans-serif;color:#151411;">
                            <?php echo esc_html($book_title); ?>
                        </h2>
                        <?php if ($book_subtitle): ?>
                            <p style="margin:0 0 10px;font:14px/24px Georgia,serif;color:#2a2c2d;">
                                <?php echo esc_html($book_subtitle); ?>
                            </p><?php endif; ?>
                        <?php if ($scripture_reference): ?><span
                                style="display:inline-block;padding:6px 13px;border:1px solid rgba(189,152,23,.5);border-radius:30px;font:700 11px/16px Arial,sans-serif;color:#bd9817;"><?php echo esc_html($scripture_reference); ?></span><?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 36px;">
                        <div style="height:1px;background:rgba(189,152,23,.28);font-size:1px;">&nbsp;</div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:34px 28px;">
                        <h3 style="margin:0 0 22px;font:700 16px/24px Arial,sans-serif;color:#bd9817;">
                            <?php esc_html_e("What's Inside", 'tfp-authentication'); ?>
                        </h3>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td valign="top" align="center" style="padding:0 5px;">
                                    <div style="width:75px;height:75px;margin:0 auto;"><img
                                            src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Daily-scripture.png"
                                            alt="" width="70" height="70"
                                            style="display:block;width:76px;height:76px;margin:8px auto;border:0;outline:none;text-decoration:none;">
                                    </div><strong
                                        style="display:block;padding-top:10px;font:700 13px/20px Arial,sans-serif;color:#151411;"><?php esc_html_e('Daily Scripture', 'tfp-authentication'); ?></strong><span
                                        style="display:block;padding-top:4px;font:11px/18px Georgia,serif;color:#2a2c2d;"><?php esc_html_e('A passage to read and sit with each day', 'tfp-authentication'); ?></span>
                                </td>
                                <td valign="top" align="center" style="padding:0 5px;">
                                    <div style="width:75px;height:75px;margin:0 auto;"><img
                                            src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Guided-reflection.png"
                                            alt="" width="70" height="70"
                                            style="display:block;width:76px;height:76px;margin:8px auto;border:0;outline:none;text-decoration:none;">
                                    </div><strong
                                        style="display:block;padding-top:10px;font:700 13px/20px Arial,sans-serif;color:#151411;"><?php esc_html_e('Guided Reflection', 'tfp-authentication'); ?></strong><span
                                        style="display:block;padding-top:4px;font:11px/18px Georgia,serif;color:#2a2c2d;"><?php esc_html_e("Questions to help you apply what you've read", 'tfp-authentication'); ?></span>
                                </td>
                                <td valign="top" align="center" style="padding:0 5px;">
                                    <div><img
                                            src="https://thefollowproject.com/thefollowprojectstaging/wp-content/uploads/2026/11/Teaching-videos.png"
                                            alt="" width="76" height="76"
                                            style="display:block;width:76px;height:76px;margin:8px auto;border:0;outline:none;text-decoration:none;">
                                    </div><strong
                                        style="display:block;padding-top:10px;font:700 13px/20px Arial,sans-serif;color:#151411;"><?php esc_html_e('Teaching Videos', 'tfp-authentication'); ?></strong><span
                                        style="display:block;padding-top:4px;font:11px/18px Georgia,serif;color:#2a2c2d;"><?php esc_html_e('Register your book to unlock weekly teachings', 'tfp-authentication'); ?></span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 36px;">
                        <div style="height:1px;background:rgba(189,152,23,.28);font-size:1px;">&nbsp;</div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:34px 28px 42px;">
                        <h3 style="margin:0;font:700 18px/27px Arial,sans-serif;color:#151411;">
                            <?php esc_html_e('Ready to go deeper?', 'tfp-authentication'); ?>
                        </h3>
                        <p style="margin:8px 0 20px;font:14px/23px Georgia,serif;color:#2a2c2d;">
                            <?php esc_html_e('Get your copy today and start the journey.', 'tfp-authentication'); ?>
                        </p>
                        <a href="<?php echo esc_url($book_url); ?>"
                            style="display:inline-block;padding:13px 34px;border-radius:8px;background:#00666e;font:14px/20px Arial,sans-serif;color:#fff;text-decoration:none;"><?php esc_html_e('Get Your Copy →', 'tfp-authentication'); ?></a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 36px;">
                        <div style="height:1px;background:rgba(189,152,23,.28);font-size:1px;">&nbsp;</div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:30px 30px 36px;">
                        <h3 style="margin:0 0 18px;font:700 16px/24px Arial,sans-serif;color:#bd9817;">
                            <?php esc_html_e('More from The Follow Project', 'tfp-authentication'); ?>
                        </h3>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <?php foreach ($related as $related_book): ?>
                                    <td width="50%" valign="top" align="center" style="padding:0 7px;">
                                        <?php $related_image = wp_get_attachment_image_url($related_book->get_image_id(), 'medium');
                                        if ($related_image): ?><a
                                                href="<?php echo esc_url($related_book->get_permalink()); ?>"><img
                                                    src="<?php echo esc_url($related_image); ?>"
                                                    alt="<?php echo esc_attr($related_book->get_name()); ?>" width="240"
                                                    style="display:block;width:100%;height:170px;object-fit:cover;background:#d4c4a8;border:0;"></a><?php else: ?>
                                            <div style="height:170px;background:#d4c4a8;"></div><?php endif; ?><strong
                                            style="display:block;padding-top:10px;font:700 13px/20px Arial,sans-serif;color:#151411;"><?php echo esc_html($related_book->get_name()); ?></strong><a
                                            href="<?php echo esc_url($related_book->get_permalink()); ?>"
                                            style="display:inline-block;margin-top:10px;padding:8px 18px;background:#00666e;border-radius:7px;color:#fff;text-decoration:none;font:13px/18px Arial,sans-serif;"><?php esc_html_e('Shop Now →', 'tfp-authentication'); ?></a>
                                    </td><?php endforeach; ?>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 40px 28px;background:#00666e;text-align:center;">
                        <div
                            style="font-family:Arial,sans-serif;font-size:24px;font-weight:700;line-height:32px;color:#ffffff;">
                            <?php esc_html_e('FIND US ON SOCIALS', 'tfp-authentication'); ?>
                        </div>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="300"
                            style="width:300px;max-width:100%;margin:20px auto 0;border-collapse:collapse;">
                            <tr>
                                <td width="25%" align="center" style="padding:0;"><a
                                        href="https://www.youtube.com/@TheFollowProject"
                                        style="text-decoration:none;"><img
                                            src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-youtube.png'); ?>"
                                            alt="YouTube" width="44" height="44"
                                            style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;"></a>
                                </td>
                                <td width="25%" align="center" style="padding:0;"><a
                                        href="https://www.instagram.com/thefollowproject.us"
                                        style="text-decoration:none;"><img
                                            src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-instagram.png'); ?>"
                                            alt="Instagram" width="44" height="44"
                                            style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;"></a>
                                </td>
                                <td width="25%" align="center" style="padding:0;"><a
                                        href="https://www.tiktok.com/@thefollowprojectofficial"
                                        style="text-decoration:none;"><img
                                            src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-tiktok.png'); ?>"
                                            alt="TikTok" width="44" height="44"
                                            style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;"></a>
                                </td>
                                <td width="25%" align="center" style="padding:0;"><a
                                        href="https://www.pinterest.com/thefollowproject/"
                                        style="text-decoration:none;"><img
                                            src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-pinterest.png'); ?>"
                                            alt="Pinterest" width="44" height="44"
                                            style="display:block;width:44px;height:44px;border:0;outline:none;text-decoration:none;margin:0 auto;"></a>
                                </td>
                            </tr>
                        </table>
                        <div
                            style="padding-top:20px;font-family:Georgia,serif;font-size:14px;line-height:23px;color:#ffffff;">
                            <?php esc_html_e('If you have any questions, reply to this email or contact', 'tfp-authentication'); ?><br><a
                                href="mailto:care@thefollowproject.com"
                                style="color:#ffffff;text-decoration:none;">care@thefollowproject.com</a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:20px 24px;font:10px/16px Arial,sans-serif;color:#333;">
                        <?php esc_html_e('No longer want to receive these emails?', 'tfp-authentication'); ?> <a
                            href="<?php echo esc_url(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/')); ?>"
                            style="color:#333;text-decoration:underline;"><?php esc_html_e('Unsubscribe', 'tfp-authentication'); ?></a>
                        | <?php echo esc_html($blogname); ?> | Faith-Based Ministries
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>