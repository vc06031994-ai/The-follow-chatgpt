<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom password reset flow for the custom TFP My Account page.
 *
 * WooCommerce/WordPress still owns reset-key generation and validation, but
 * the reset form is rendered by TFP because the native WooCommerce account
 * endpoint has been replaced by [tfp_my_account].
 */

$GLOBALS['tfp_auth_password_reset_error'] = '';
$GLOBALS['tfp_auth_password_reset_post_key'] = '';
$GLOBALS['tfp_auth_password_reset_post_id'] = '';

function tfp_auth_password_reset_get_user($key, $identifier)
{
    $key = sanitize_text_field(wp_unslash($key));
    $identifier = sanitize_text_field(wp_unslash($identifier));

    if ($key === '' || $identifier === '') {
        return new WP_Error(
            'invalid_reset_link',
            __('This password reset link is invalid or has expired.', 'tfp-authentication')
        );
    }

    $user = ctype_digit($identifier)
        ? get_user_by('id', (int) $identifier)
        : get_user_by('login', $identifier);

    if (!$user) {
        return new WP_Error(
            'invalid_reset_user',
            __('This password reset link is invalid or has expired.', 'tfp-authentication')
        );
    }

    $validated = check_password_reset_key($key, $user->user_login);
    if (is_wp_error($validated)) {
        return $validated;
    }

    return $user;
}

/**
 * Process the password-set form before the page renders.
 */
add_action('init', function () {
    if (empty($_POST['tfp_password_reset_submit'])) {
        return;
    }

    if (
        !isset($_POST['tfp_password_reset_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['tfp_password_reset_nonce'])),
            'tfp-password-reset'
        )
    ) {
        wp_die(
            esc_html__('Security verification failed. Please request a new password reset link.', 'tfp-authentication')
        );
    }

    $key = isset($_POST['key']) ? wp_unslash($_POST['key']) : '';
    $identifier = isset($_POST['id']) ? wp_unslash($_POST['id']) : '';
    if ($identifier === '' && isset($_POST['login'])) {
        $identifier = wp_unslash($_POST['login']);
    }

    $password_1 = isset($_POST['password_1']) ? (string) wp_unslash($_POST['password_1']) : '';
    $password_2 = isset($_POST['password_2']) ? (string) wp_unslash($_POST['password_2']) : '';

    $GLOBALS['tfp_auth_password_reset_post_key'] = $key;
    $GLOBALS['tfp_auth_password_reset_post_id'] = $identifier;

    $user = tfp_auth_password_reset_get_user($key, $identifier);

    if (is_wp_error($user)) {
        $GLOBALS['tfp_auth_password_reset_error'] = $user->get_error_message();
        return;
    }

    if (is_user_logged_in() && get_current_user_id() !== (int) $user->ID) {
        $GLOBALS['tfp_auth_password_reset_error'] = __('This password reset link belongs to a different account. Please log out and open the link again.', 'tfp-authentication');
        return;
    }

    if ($password_1 === '' || strlen($password_1) < 8) {
        $GLOBALS['tfp_auth_password_reset_error'] = __('Password must contain at least 8 characters.', 'tfp-authentication');
        return;
    }

    if ($password_1 !== $password_2) {
        $GLOBALS['tfp_auth_password_reset_error'] = __('Passwords do not match.', 'tfp-authentication');
        return;
    }

    reset_password($user, $password_1);

    // Keep the customer logged out so they use the normal TFP login flow.
    wp_clear_auth_cookie();
    wp_set_current_user(0);

    $success_url = add_query_arg(
        'tfp_password_reset',
        'success',
        wc_get_page_permalink('myaccount')
    );

    wp_safe_redirect($success_url);
    exit;
}, 1);

/**
 * WooCommerce normally intercepts reset links on template_redirect and sends
 * them through its own cookie-based reset screen. The TFP account page has
 * replaced that screen, so let the custom shortcode handle the original key
 * and login/id parameters directly.
 */
add_action('init', function () {
    if (class_exists('WC_Form_Handler')) {
        remove_action('template_redirect', array('WC_Form_Handler', 'redirect_reset_password_link'));
    }
}, 99);

function tfp_auth_password_reset_enqueue_styles()
{
    wp_enqueue_style(
        'tfp-auth-account',
        plugins_url('../../assets/css/account.css', __FILE__),
        [],
        defined('TFP_AUTH_VERSION') ? TFP_AUTH_VERSION : null
    );

    wp_add_inline_style('tfp-auth-account', '
        .tfp-password-reset-wrapper { max-width: 620px; margin: 0 auto 60px; }
        .tfp-password-reset-card { background: #fff; border: 1px solid #C4C4C4; border-radius: 12px; padding: 36px; }
        .tfp-password-reset-title { margin: 0 0 10px; color: #151411; font-family: "Eudoxus Sans", sans-serif; font-size: 32px; font-weight: 700; }
        .tfp-password-reset-description { margin: 0 0 28px; color: #151411; font-family: "Lora", sans-serif; font-size: 14px; line-height: 1.7; }
        .tfp-password-reset-notice { margin-bottom: 24px; padding: 12px 14px; border-radius: 8px; font-family: "Eudoxus Sans", sans-serif; font-size: 13px; }
        .tfp-password-reset-notice--error { background: #fff1f1; border: 1px solid #e1aaaa; color: #a12626; }
        .tfp-password-reset-field { margin: 0 0 18px; }
        .tfp-password-reset-field label { display: block; margin-bottom: 8px; color: #151411; font-family: "Eudoxus Sans", sans-serif; font-size: 13px; font-weight: 700; }
        .tfp-password-reset-field input { width: 100%; box-sizing: border-box; min-height: 50px; padding: 12px 14px; border: 1px solid #C4C4C4; border-radius: 8px; background: #fff; color: #151411; font-family: "Eudoxus Sans", sans-serif; font-size: 14px; }
        .tfp-password-reset-field input:focus { outline: none; border-color: #00666E; box-shadow: 0 0 0 2px rgba(0,102,110,.12); }
        .tfp-password-reset-help { margin: -4px 0 22px; color: #666; font-family: "Lora", sans-serif; font-size: 12px; }
    ');
}

/**
 * Render the custom reset screen.
 */
function tfp_auth_render_password_reset_screen()
{
    tfp_auth_password_reset_enqueue_styles();

    $key = isset($_GET['key']) ? wp_unslash($_GET['key']) : '';
    $identifier = isset($_GET['id']) ? wp_unslash($_GET['id']) : '';
    if ($identifier === '' && isset($_GET['login'])) {
        $identifier = wp_unslash($_GET['login']);
    }

    if (!empty($GLOBALS['tfp_auth_password_reset_post_key'])) {
        $key = $GLOBALS['tfp_auth_password_reset_post_key'];
        $identifier = $GLOBALS['tfp_auth_password_reset_post_id'];
    }

    $user = tfp_auth_password_reset_get_user($key, $identifier);
    $error = $GLOBALS['tfp_auth_password_reset_error'];

    if (!$error && is_wp_error($user)) {
        $error = $user->get_error_message();
    }

    if (!$error && !is_wp_error($user) && is_user_logged_in() && get_current_user_id() !== (int) $user->ID) {
        $error = __('This password reset link belongs to a different account. Please log out and open the link again.', 'tfp-authentication');
        $user = new WP_Error('different_logged_in_user', $error);
    }

    ob_start();
    ?>
    <div class="tfp-password-reset-wrapper">
        <div class="tfp-password-reset-card">
            <?php if ($error) : ?>
                <div class="tfp-password-reset-notice tfp-password-reset-notice--error">
                    <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!is_wp_error($user)) : ?>
                <h2 class="tfp-password-reset-title"><?php esc_html_e('Set Your Password', 'tfp-authentication'); ?></h2>
                <p class="tfp-password-reset-description">
                    <?php esc_html_e('Create a new password for your The Follow Project account.', 'tfp-authentication'); ?>
                </p>

                <form method="post" class="tfp-password-reset-form">
                    <?php wp_nonce_field('tfp-password-reset', 'tfp_password_reset_nonce'); ?>
                    <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
                    <input type="hidden" name="id" value="<?php echo esc_attr($identifier); ?>">
                    <input type="hidden" name="login" value="<?php echo esc_attr($identifier); ?>">

                    <p class="tfp-password-reset-field">
                        <label for="tfp_password_1"><?php esc_html_e('New Password', 'tfp-authentication'); ?></label>
                        <input type="password" id="tfp_password_1" name="password_1" autocomplete="new-password" required minlength="8">
                    </p>

                    <p class="tfp-password-reset-field">
                        <label for="tfp_password_2"><?php esc_html_e('Confirm Password', 'tfp-authentication'); ?></label>
                        <input type="password" id="tfp_password_2" name="password_2" autocomplete="new-password" required minlength="8">
                    </p>

                    <p class="tfp-password-reset-help">
                        <?php esc_html_e('Password must contain at least 8 characters.', 'tfp-authentication'); ?>
                    </p>

                    <button type="submit" name="tfp_password_reset_submit" value="1" class="tfp-password-reset-submit tfp-btn tfp-btn-primary">
                        <?php esc_html_e('Set Password', 'tfp-authentication'); ?>
                    </button>
                </form>
            <?php else : ?>
                <h2 class="tfp-password-reset-title"><?php esc_html_e('Password Reset Link Invalid', 'tfp-authentication'); ?></h2>
                <p class="tfp-password-reset-description">
                    <?php esc_html_e('This password reset link is invalid or has expired. Please request a new password reset link.', 'tfp-authentication'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Re-register the existing shortcode with a small wrapper. Normal account
 * rendering remains untouched; reset-link requests use the custom form.
 */
add_action('init', function () {
    remove_shortcode('tfp_my_account');

    add_shortcode('tfp_my_account', function () {
        $has_reset_key = !empty($_GET['key']) && (!empty($_GET['id']) || !empty($_GET['login']));
        $is_reset_success = isset($_GET['tfp_password_reset']) && $_GET['tfp_password_reset'] === 'success';

        if (!$has_reset_key && !$is_reset_success) {
            return tfp_auth_render_custom_my_account_shortcode();
        }

        if ($is_reset_success) {
            tfp_auth_password_reset_enqueue_styles();

            return '<div class="tfp-password-reset-wrapper"><div class="tfp-password-reset-card"><h2 class="tfp-password-reset-title">' .
                esc_html__('Password Updated', 'tfp-authentication') .
                '</h2><p class="tfp-password-reset-description">' .
                esc_html__('Your password has been set successfully. You can now log in with your new password.', 'tfp-authentication') .
                '</p></div></div>';
        }

        return tfp_auth_render_password_reset_screen();
    });
}, 20);

/**
 * The default WooCommerce new-account email uses this URL for the initial
 * password setup. If the email template is customized, this plugin-owned
 * template keeps that secure setup link available and also makes the visible
 * My account link take the customer to the password setup screen while their
 * password is still generated by WooCommerce.
 */
add_filter('woocommerce_locate_template', function ($template, $template_name, $template_path) {
    if ('emails/customer-new-account.php' === $template_name) {
        $custom_template = TFP_AUTH_PATH . 'includes/emails/customer-new-account.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    if ('emails/plain/customer-new-account.php' === $template_name) {
        $custom_template = TFP_AUTH_PATH . 'includes/emails/plain-customer-new-account.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }

    return $template;
}, 20, 3);
