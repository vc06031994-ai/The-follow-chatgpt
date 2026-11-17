<?php
/**
 * TFP Password Reset Email.
 * Receives $user, $reset_url and $blogname from the reset-email integration.
 */
defined('ABSPATH') || exit;

$blogname = isset($blogname) ? $blogname : get_bloginfo('name');
$user_name = !empty($user) && $user instanceof WP_User ? ($user->display_name ?: $user->user_login) : __('there', 'tfp-authentication');
$reset_url = !empty($reset_url) ? $reset_url : home_url('/');
$logo_url = defined('TFP_AUTH_URL') ? trailingslashit(TFP_AUTH_URL) . 'assets/images/MAIN_LOGO.png' : '';
$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
$view_url = apply_filters('tfp_password_reset_email_view_url', home_url('/'));
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style type="text/css">
@import url('https://fonts.googleapis.com/css2?family=Lora:wght@400;700&display=swap');
@import url('https://stijndv.com/fonts/Eudoxus-Sans.css');
@media only screen and (max-width:620px) {
 .tfp-reset-shell{width:100%!important;max-width:100%!important}
 .tfp-reset-pad{padding-left:20px!important;padding-right:20px!important}
 .tfp-reset-logo{width:190px!important;height:auto!important}
 .tfp-reset-nav td{padding-left:9px!important;padding-right:9px!important;font-size:14px!important}
 .tfp-reset-title{font-size:27px!important;line-height:35px!important}
 .tfp-reset-footer{padding-left:20px!important;padding-right:20px!important}
}
</style>
</head>
<body style="margin:0;padding:0;background:#ffffff;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#ffffff;">
<tr><td align="center" style="padding:4px;background:#f5f5f5;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" class="tfp-reset-shell" style="width:600px;max-width:100%;margin:0 auto;background:#ffffff;border:1px solid #eeeeee;">
<tr><td style="height:42px;background:#00666e;padding:0 40px;text-align:right;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:12px;font-weight:700;color:#ffffff;"><a href="<?php echo esc_url($view_url); ?>" style="color:#ffffff;text-decoration:none;">View in browser →</a></td></tr>
<tr><td style="height:150px;background:#fbfbfb;text-align:center;padding:0 40px;" class="tfp-reset-pad">
<img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($blogname); ?>" width="212" height="50" class="tfp-reset-logo" style="display:block;width:212px;height:50px;max-width:100%;margin:20px auto 24px;border:0;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" class="tfp-reset-nav"><tr>
<td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;"><a href="<?php echo esc_url($shop_url); ?>" style="color:#1c1b1b;text-decoration:none;">Shop</a></td>
<td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;"><a href="<?php echo esc_url(home_url('/about-us/')); ?>" style="color:#1c1b1b;text-decoration:none;">About Us</a></td>
<td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;"><a href="<?php echo esc_url(home_url('/updates/')); ?>" style="color:#1c1b1b;text-decoration:none;">Updates</a></td>
<td style="padding:0 28px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:18px;line-height:24px;"><a href="<?php echo esc_url(home_url('/contact/')); ?>" style="color:#1c1b1b;text-decoration:none;">Contacts</a></td>
</tr></table>
</td></tr>
<tr><td align="center" class="tfp-reset-pad" style="padding:36px 40px 18px;text-align:center;border-top:1px solid #8b3434;">
<h1 class="tfp-reset-title" style="margin:0;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:30px;font-weight:700;line-height:40px;color:#151411;">Reset Your Password.</h1>
<p style="margin:10px 0 0;font-family:'Lora',Georgia,serif;font-size:13px;line-height:22px;color:#2a2c2d;">We received a request to reset your password. If this was you, click below.<br>If not, you can safely ignore this email.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:46px auto 0;"><tr><td align="center" bgcolor="#00666e" style="border-radius:8px;"><a href="<?php echo esc_url($reset_url); ?>" style="display:inline-block;padding:14px 32px;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:14px;line-height:18px;color:#ffffff;text-decoration:none;border-radius:8px;">Reset My Password →</a></td></tr></table>
<p style="margin:52px 0 0;font-family:'Lora',Georgia,serif;font-size:12px;line-height:22px;color:#555555;">This link expires in 24 hours. Questions? Email us <a href="mailto:care@thefollowproject.com" style="color:#555555;text-decoration:none;">care@thefollowproject.com</a></p>
</td></tr>
<tr><td class="tfp-reset-footer" style="padding:26px 40px 28px;background:#00666e;text-align:center;">
<div style="font-family:'Eudoxus Sans',Arial,sans-serif;font-size:24px;font-weight:700;line-height:32px;color:#ffffff;">FIND US ON SOCIALS</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="300" style="width:300px;max-width:100%;margin:20px auto 0;border-collapse:collapse;"><tr>
<td width="25%" align="center"><a href="https://www.youtube.com/@TheFollowProject"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-youtube.png'); ?>" alt="YouTube" width="44" height="44" style="display:block;width:44px;height:44px;border:0;margin:0 auto;"></a></td>
<td width="25%" align="center"><a href="https://www.instagram.com/thefollowproject.us"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-instagram.png'); ?>" alt="Instagram" width="44" height="44" style="display:block;width:44px;height:44px;border:0;margin:0 auto;"></a></td>
<td width="25%" align="center"><a href="https://www.tiktok.com/@thefollowprojectofficial"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-tiktok.png'); ?>" alt="TikTok" width="44" height="44" style="display:block;width:44px;height:44px;border:0;margin:0 auto;"></a></td>
<td width="25%" align="center"><a href="https://www.pinterest.com/thefollowproject/"><img src="<?php echo esc_url(trailingslashit(TFP_AUTH_URL) . 'assets/images/social-pinterest.png'); ?>" alt="Pinterest" width="44" height="44" style="display:block;width:44px;height:44px;border:0;margin:0 auto;"></a></td>
</tr></table>
<div style="padding-top:38px;font-family:'Lora',Georgia,serif;font-size:14px;line-height:23px;color:#ffffff;">If you have any questions, reply to this email or contact<br><a href="mailto:care@thefollowproject.com" style="color:#ffffff;text-decoration:none;">care@thefollowproject.com</a></div>
</td></tr>
<tr><td style="padding:22px 40px;background:#ffffff;text-align:center;font-family:'Eudoxus Sans',Arial,sans-serif;font-size:10px;line-height:15px;color:#333333;">No longer want to receive these emails? <a href="<?php echo esc_url(function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('dashboard') : home_url('/my-account/')); ?>" style="color:#333333;text-decoration:underline;">Unsubscribe</a>. | <?php echo esc_html($blogname); ?> | Faith-Based Ministries</td></tr>
</table></td></tr></table>
</body>
</html>
