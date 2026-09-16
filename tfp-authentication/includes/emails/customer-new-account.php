<?php
/**
 * TFP custom WooCommerce new-account email template.
 *
 * This keeps WooCommerce's normal email variables and styling hooks, but uses
 * the generated set-password URL for the visible account link when the user
 * still needs to create their password.
 */
defined('ABSPATH') || exit;

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php printf(esc_html__('Hi %s,', 'woocommerce'), esc_html($user_display_name)); ?></p>

<?php if (!empty($password_generated) && !empty($set_password_url)) : ?>
    <p><?php printf(esc_html__('Thanks for creating an account on %s. Here’s a copy of your user details.', 'woocommerce'), esc_html($blogname)); ?></p>
    <div class="hr hr-top"></div>
    <p><?php echo wp_kses(sprintf(__('Username: <b>%s</b>', 'woocommerce'), esc_html($user_login)), array('b' => array())); ?></p>
    <p>
        <a href="<?php echo esc_attr($set_password_url); ?>">
            <?php esc_html_e('Set your new password.', 'woocommerce'); ?>
        </a>
    </p>
    <div class="hr hr-bottom"></div>
    <p><?php esc_html_e('You can access your account area to view orders, change your password, and more via the link below:', 'woocommerce'); ?></p>
    <p>
        <a href="<?php echo esc_attr($set_password_url); ?>">
            <?php esc_html_e('My account', 'woocommerce'); ?>
        </a>
    </p>
<?php else : ?>
    <p>
        <?php
        printf(
            esc_html__('Thanks for creating an account on %1$s. Your username is %2$s. You can access your account area to view orders, change your password, and more at: %3$s', 'woocommerce'),
            esc_html($blogname),
            esc_html($user_login),
            make_clickable(esc_url(wc_get_page_permalink('myaccount')))
        );
        ?>
    </p>
<?php endif; ?>

<?php
if ($additional_content) {
    echo wp_kses_post(wpautop(wptexturize($additional_content)));
}

do_action('woocommerce_email_footer', $email);
