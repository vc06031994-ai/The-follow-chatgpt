<?php

/**
 * TFP Processing Order email browser preview and test sender.
 *
 * Admin-only preview for the WooCommerce processing-order email.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __('TFP Order Confirmation Preview', 'tfp-authentication'),
        __('Order Confirmation Preview', 'tfp-authentication'),
        'manage_woocommerce',
        'tfp-processing-order-email-preview',
        'tfp_render_processing_order_email_preview'
    );
});

/**
 * Build an in-memory order for preview/testing.
 *
 * No order is saved to the database.
 */
function tfp_build_processing_order_preview_order($recipient = '')
{
    if (!class_exists('WC_Order') || !function_exists('wc_get_products')) {
        return null;
    }

    $order = new WC_Order();

    // Keep the temporary preview order in WooCommerce's default pending state.
    // Setting it to "processing" triggers real WooCommerce notification hooks,
    // which can send additional order/stock emails during a preview.
    $order->set_created_via('tfp-processing-order-preview');
    $order->set_date_created(gmdate('Y-m-d H:i:s'));
    $order->set_currency(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD');

    $order->set_billing_first_name('Test');
    $order->set_billing_last_name('Customer');
    $order->set_billing_company('');
    $order->set_billing_address_1('123 Main Street');
    $order->set_billing_address_2('');
    $order->set_billing_city('Nashville');
    $order->set_billing_state('TN');
    $order->set_billing_postcode('37201');
    $order->set_billing_country('US');
    $order->set_billing_email($recipient ?: 'test@example.com');

    $order->set_shipping_first_name('Test');
    $order->set_shipping_last_name('Customer');
    $order->set_shipping_company('');
    $order->set_shipping_address_1('123 Main Street');
    $order->set_shipping_address_2('');
    $order->set_shipping_city('Nashville');
    $order->set_shipping_state('TN');
    $order->set_shipping_postcode('37201');
    $order->set_shipping_country('US');

    $order->set_payment_method_title('Credit Card');

    $products = wc_get_products([
        'status' => 'publish',
        'limit' => 6,
        'category' => ['books'],
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    if (empty($products)) {
        $products = wc_get_products([
            'status' => 'publish',
            'limit' => 6,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }

    $added = 0;

    foreach ($products as $product) {
        if (!$product instanceof WC_Product) {
            continue;
        }

        $order->add_product($product, 1);
        $added++;

        if ($added >= 2) {
            break;
        }
    }

    if ($added === 0) {
        return null;
    }

    $order->set_shipping_total(0);
    $order->calculate_totals(false);

    return $order;
}

/**
 * Send the processing-order template through WooCommerce's real mailer.
 *
 * No order is saved to the database.
 */
function tfp_send_processing_order_email_test($recipient)
{
    if (!function_exists('WC') || !WC()->mailer()) {
        return new WP_Error(
            'woocommerce_unavailable',
            __('WooCommerce email services are not available.', 'tfp-authentication')
        );
    }

    $order = tfp_build_processing_order_preview_order($recipient);

    if (!$order) {
        return new WP_Error(
            'preview_order_unavailable',
            __('A preview order could not be created. Make sure WooCommerce products are available.', 'tfp-authentication')
        );
    }

    $emails = WC()->mailer()->get_emails();

    if (empty($emails['WC_Email_Customer_Processing_Order'])) {
        return new WP_Error(
            'processing_order_email_unavailable',
            __('The WooCommerce processing-order email service could not be loaded.', 'tfp-authentication')
        );
    }

    $email = $emails['WC_Email_Customer_Processing_Order'];

    $email->object = $order;
    $email->recipient = $recipient;

    $subject = $email->get_subject();
    $message = $email->get_content();
    $headers = $email->get_headers();
    $attachments = $email->get_attachments();

    // Send exactly one test message. Using wp_mail() directly avoids triggering
    // WooCommerce notification hooks or any additional email pipelines.
    $sent = wp_mail(
        $recipient,
        $subject,
        $message,
        $headers,
        $attachments
    );

    if (!$sent) {
        return new WP_Error(
            'processing_order_email_send_failed',
            __('The test order-confirmation email could not be sent. Check the configured site mailer/SMTP service.', 'tfp-authentication')
        );
    }

    return true;
}

function tfp_render_processing_order_email_preview()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to view this preview.', 'tfp-authentication'));
    }

    $test_email = '';
    $send_notice = '';
    $send_notice_type = '';

    if (
        'POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') &&
        isset($_POST['tfp_send_processing_order_email'])
    ) {
        check_admin_referer('tfp_send_processing_order_email_test', 'tfp_processing_order_email_nonce');

        $test_email = isset($_POST['tfp_processing_order_test_email'])
            ? sanitize_email(wp_unslash($_POST['tfp_processing_order_test_email']))
            : '';

        if (!$test_email || !is_email($test_email)) {
            $send_notice = __('Please enter a valid recipient email address.', 'tfp-authentication');
            $send_notice_type = 'error';
        } else {
            $result = tfp_send_processing_order_email_test($test_email);

            if (is_wp_error($result)) {
                $send_notice = $result->get_error_message();
                $send_notice_type = 'error';
            } else {
                $send_notice = sprintf(
                    __('Test order-confirmation email sent successfully to %s.', 'tfp-authentication'),
                    $test_email
                );
                $send_notice_type = 'success';
            }
        }
    }

    $template = TFP_AUTH_PATH . 'includes/emails/customer-processing-order.php';

    if (!file_exists($template)) {
        echo '<div class="wrap"><h1>' . esc_html__('TFP Order Confirmation Preview', 'tfp-authentication') . '</h1>';
        echo '<div class="notice notice-error"><p>' . esc_html__('The processing-order email template could not be found.', 'tfp-authentication') . '</p></div></div>';
        return;
    }

    $preview_order = tfp_build_processing_order_preview_order($test_email);

    if (!$preview_order) {
        echo '<div class="wrap"><h1>' . esc_html__('TFP Order Confirmation Preview', 'tfp-authentication') . '</h1>';
        echo '<div class="notice notice-error"><p>' . esc_html__('A preview order could not be created. Make sure WooCommerce products are available.', 'tfp-authentication') . '</p></div></div>';
        return;
    }

    $order = $preview_order;
    $sent_to_admin = false;
    $plain_text = false;
    $email = null;
    $blogname = get_bloginfo('name');

    ?>
    <div class="wrap" style="max-width:700px;">
        <h1><?php esc_html_e('TFP Order Confirmation Preview', 'tfp-authentication'); ?></h1>

        <?php if ($send_notice): ?>
            <div class="notice notice-<?php echo esc_attr($send_notice_type); ?> is-dismissible">
                <p><?php echo esc_html($send_notice); ?></p>
            </div>
        <?php endif; ?>

        <p style="margin-bottom:16px;">
            <?php esc_html_e('Preview the current WooCommerce processing-order email below, or send the same template through the real WooCommerce mailer to verify inbox rendering.', 'tfp-authentication'); ?>
        </p>

        <div style="background:#ffffff;padding:20px 22px;margin:0 0 20px;border:1px solid #dcdcde;border-radius:6px;">
            <h2 style="margin:0 0 8px;font-size:18px;">
                <?php esc_html_e('Send Test Email', 'tfp-authentication'); ?>
            </h2>

            <p style="margin:0 0 14px;color:#646970;">
                <?php esc_html_e('The test uses a temporary in-memory order. No customer or order is created in WooCommerce.', 'tfp-authentication'); ?>
            </p>

            <form method="post">
                <?php wp_nonce_field('tfp_send_processing_order_email_test', 'tfp_processing_order_email_nonce'); ?>

                <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div style="flex:1;min-width:280px;">
                        <label for="tfp_processing_order_test_email" style="display:block;font-weight:600;margin-bottom:6px;">
                            <?php esc_html_e('Recipient Email', 'tfp-authentication'); ?>
                        </label>
                        <input
                            type="email"
                            id="tfp_processing_order_test_email"
                            name="tfp_processing_order_test_email"
                            value="<?php echo esc_attr($test_email); ?>"
                            class="regular-text"
                            style="width:100%;"
                            placeholder="you@example.com"
                            required
                        >
                    </div>

                    <button type="submit" name="tfp_send_processing_order_email" class="button button-primary">
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
