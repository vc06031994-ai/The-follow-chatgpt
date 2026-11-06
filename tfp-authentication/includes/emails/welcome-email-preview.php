<?php
/**
 * TFP Welcome Email browser preview and test sender.
 *
 * Admin-only preview for the WooCommerce new-account welcome email.
 * The test sender uses the same WooCommerce email class and mailer used
 * by the live new-account email, without creating an order or customer.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __('TFP Welcome Email Preview', 'tfp-authentication'),
        __('Welcome Email Preview', 'tfp-authentication'),
        'manage_woocommerce',
        'tfp-welcome-email-preview',
        'tfp_render_welcome_email_preview'
    );
});

/**
 * Send the current welcome email template to an arbitrary test recipient
 * through WooCommerce's real email/mailer pipeline.
 *
 * No customer or order is created.
 */
function tfp_send_welcome_email_test($recipient) {
    if (!function_exists('WC') || !WC()->mailer()) {
        return new WP_Error(
            'woocommerce_unavailable',
            __('WooCommerce email services are not available.', 'tfp-authentication')
        );
    }

    $emails = WC()->mailer()->get_emails();

    if (empty($emails['WC_Email_Customer_New_Account'])) {
        return new WP_Error(
            'welcome_email_unavailable',
            __('The WooCommerce new-account email service could not be loaded.', 'tfp-authentication')
        );
    }

    $email = $emails['WC_Email_Customer_New_Account'];

    // Build an in-memory test user so the real WooCommerce email class can
    // render the message without creating a WordPress/WooCommerce customer.
    $test_user = new WP_User(0);
    $test_user->ID = 0;
    $test_user->user_login = 'testcustomer';
    $test_user->user_email = $recipient;
    $test_user->display_name = 'Test Customer';
    $test_user->first_name = 'Test';
    $test_user->last_name = 'Customer';

    $email->object = $test_user;
    $email->user_login = 'testcustomer';
    $email->user_pass = '';
    $email->password_generated = true;
    $email->recipient = $recipient;

    $subject = $email->get_subject();
    $message = $email->get_content();
    $headers = $email->get_headers();
    $attachments = $email->get_attachments();

    $sent = $email->send(
        $recipient,
        $subject,
        $message,
        $headers,
        $attachments
    );

    if (!$sent) {
        return new WP_Error(
            'welcome_email_send_failed',
            __('The test welcome email could not be sent. Check the configured site mailer/SMTP service.', 'tfp-authentication')
        );
    }

    return true;
}

function tfp_render_welcome_email_preview() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to view this preview.', 'tfp-authentication'));
    }

    $test_email = '';
    $send_notice = '';
    $send_notice_type = '';

    if (
        'POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') &&
        isset($_POST['tfp_send_welcome_email'])
    ) {
        check_admin_referer('tfp_send_welcome_email_test', 'tfp_welcome_email_nonce');

        $test_email = isset($_POST['tfp_welcome_test_email'])
            ? sanitize_email(wp_unslash($_POST['tfp_welcome_test_email']))
            : '';

        if (!$test_email || !is_email($test_email)) {
            $send_notice = __('Please enter a valid recipient email address.', 'tfp-authentication');
            $send_notice_type = 'error';
        } else {
            $result = tfp_send_welcome_email_test($test_email);

            if (is_wp_error($result)) {
                $send_notice = $result->get_error_message();
                $send_notice_type = 'error';
            } else {
                $send_notice = sprintf(
                    __('Test welcome email sent successfully to %s.', 'tfp-authentication'),
                    $test_email
                );
                $send_notice_type = 'success';
            }
        }
    }

    $password_generated = true;
    $user_display_name = 'Test Customer';
    $user_login = 'testcustomer';
    $blogname = get_bloginfo('name');
    $email_heading = __('Welcome to The Follow Project', 'woocommerce');
    $additional_content = '';
    $set_password_url = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('myaccount')
        : home_url('/my-account/');

    // The live email template only relies on these values and WooCommerce helpers.
    $email = null;

    $template = TFP_AUTH_PATH . 'includes/emails/customer-new-account.php';

    if (!file_exists($template)) {
        echo '<div class="wrap"><h1>' . esc_html__('TFP Welcome Email Preview', 'tfp-authentication') . '</h1>';
        echo '<div class="notice notice-error"><p>' . esc_html__('The welcome email template could not be found.', 'tfp-authentication') . '</p></div></div>';
        return;
    }

    ?>
    <div class="wrap" style="max-width:700px;">
        <h1><?php esc_html_e('TFP Welcome Email Preview', 'tfp-authentication'); ?></h1>

        <?php if ($send_notice) : ?>
            <div class="notice notice-<?php echo esc_attr($send_notice_type); ?> is-dismissible">
                <p><?php echo esc_html($send_notice); ?></p>
            </div>
        <?php endif; ?>

        <p style="margin-bottom:16px;">
            <?php esc_html_e('Preview the current WooCommerce new-account welcome email below, or send the same template through the live WooCommerce mailer to verify the actual inbox rendering.', 'tfp-authentication'); ?>
        </p>

        <div style="background:#ffffff;padding:20px 22px;margin:0 0 20px;border:1px solid #dcdcde;border-radius:6px;">
            <h2 style="margin:0 0 8px;font-size:18px;">
                <?php esc_html_e('Send Test Email', 'tfp-authentication'); ?>
            </h2>

            <p style="margin:0 0 14px;color:#646970;">
                <?php esc_html_e('Enter any email address. This sends the welcome template through the same WooCommerce email service used for the live new-account email. No customer or order is created.', 'tfp-authentication'); ?>
            </p>

            <form method="post">
                <?php wp_nonce_field('tfp_send_welcome_email_test', 'tfp_welcome_email_nonce'); ?>

                <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div style="flex:1;min-width:280px;">
                        <label for="tfp_welcome_test_email" style="display:block;font-weight:600;margin-bottom:6px;">
                            <?php esc_html_e('Recipient Email', 'tfp-authentication'); ?>
                        </label>
                        <input
                            type="email"
                            id="tfp_welcome_test_email"
                            name="tfp_welcome_test_email"
                            value="<?php echo esc_attr($test_email); ?>"
                            class="regular-text"
                            style="width:100%;"
                            placeholder="you@example.com"
                            required
                        >
                    </div>

                    <button type="submit" name="tfp_send_welcome_email" class="button button-primary">
                        <?php esc_html_e('Send Test Email', 'tfp-authentication'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div style="background:#eef1f4;padding:28px 12px;border:1px solid #dcdcde;">
            <?php include $template; ?>
        </div>
    </div>
    <?php
}
