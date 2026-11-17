<?php
/**
 * Central test-email panel for every TFP customer email PHP template.
 *
 * Templates are discovered automatically from customer-*.php files. New
 * templates can also be registered with the tfp_email_test_templates filter.
 * Test messages render the actual template and are sent directly with wp_mail()
 * so no WooCommerce order/customer/automation hooks are fired.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        __('TFP Email Template Test', 'tfp-authentication'),
        __('Email Template Test', 'tfp-authentication'),
        'manage_woocommerce',
        'tfp-email-template-test',
        'tfp_render_email_template_test_page'
    );
});

/** Return the templates available to the test panel. */
function tfp_get_email_test_templates() {
    $directory = TFP_AUTH_PATH . 'includes/emails/';
    $templates = [];

    foreach (glob($directory . 'customer-*.php') ?: [] as $path) {
        $file = basename($path);
        $slug = sanitize_key(str_replace(['customer-', '.php'], '', $file));
        $templates[$slug] = [
            'label' => ucwords(str_replace(['-', '_'], ' ', $slug)),
            'file'  => $path,
        ];
    }

    // Keep non-customer-prefixed templates and special cases available too.
    $special = [
        'password-reset' => ['label' => __('Password Reset', 'tfp-authentication'), 'file' => $directory . 'customer-password-reset.php'],
    ];
    foreach ($special as $slug => $item) {
        if (is_file($item['file'])) {
            $templates[$slug] = $item;
        }
    }

    return apply_filters('tfp_email_test_templates', $templates);
}

/** Build a disposable in-memory order for templates that need order data. */
function tfp_build_generic_email_test_order($recipient = '', $books_only = false) {
    if (!class_exists('WC_Order') || !function_exists('wc_get_products')) {
        return null;
    }

    $order = new WC_Order();
    $order->set_created_via('tfp-email-template-test');
    $order->set_date_created(gmdate('Y-m-d H:i:s'));
    $order->set_currency(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD');
    $order->set_status('pending');
    $order->set_billing_first_name('Test');
    $order->set_billing_last_name('Customer');
    $order->set_billing_company('Sample Company');
    $order->set_billing_address_1('123 Main Street');
    $order->set_billing_city('Nashville');
    $order->set_billing_state('TN');
    $order->set_billing_postcode('37201');
    $order->set_billing_country('US');
    $order->set_billing_email($recipient ?: 'test@example.com');
    $order->set_shipping_first_name('Test');
    $order->set_shipping_last_name('Customer');
    $order->set_shipping_address_1('123 Main Street');
    $order->set_shipping_city('Nashville');
    $order->set_shipping_state('TN');
    $order->set_shipping_postcode('37201');
    $order->set_shipping_country('US');
    $order->set_payment_method_title('Test Payment');

    $products = wc_get_products([
        'status' => 'publish',
        'limit' => 3,
        'category' => $books_only ? ['books'] : [],
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    foreach ($products as $product) {
        if ($product instanceof WC_Product) {
            $order->add_product($product, 1);
            if (count($order->get_items()) >= 2) {
                break;
            }
        }
    }

    $order->set_shipping_total(0);
    $order->calculate_totals(false);
    return $order;
}

/** Create sample variables expected by the existing TFP templates. */
function tfp_get_email_test_template_context($slug, $recipient) {
    $blogname = get_bloginfo('name');
    $user = class_exists('WP_User') ? new WP_User(0) : (object) [];
    $user->ID = 0;
    $user->display_name = 'Test Customer';
    $user->user_login = 'testcustomer';
    $user->user_email = $recipient;
    $user->first_name = 'Test';
    $user->last_name = 'Customer';
    $customer = $user;
    $user_display_name = 'Test Customer';
    $user_login = 'testcustomer';
    $password_generated = true;
    $email_heading = sprintf(__('Test %s Email', 'tfp-authentication'), ucwords(str_replace('-', ' ', $slug)));
    $additional_content = '';
    $set_password_url = function_exists('wc_get_page_permalink') ? (wc_get_page_permalink('myaccount') ?: home_url('/my-account/')) : home_url('/my-account/');
    $reset_url = add_query_arg(['key' => 'SAMPLE-RESET-KEY', 'login' => rawurlencode($user_login)], $set_password_url);
    $reset_password_url = $reset_url;
    $account_url = $set_password_url;
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
    $cart_url = add_query_arg('tfp_test_email', '1', function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'));
    $customer_name = 'Test Customer';
    $customer_email = $recipient;
    $sent_to_admin = false;
    $plain_text = false;
    $email = null;
    $order = tfp_build_generic_email_test_order($recipient, 'book-order-confirmation' === $slug);
    $items = [];
    if ($order) {
        foreach ($order->get_items('line_item') as $item) {
            $items[] = $item;
        }
    }
    $cart_items = [];
    // Abandoned Cart tests use published Books, matching the live email eligibility.
    $sample_cart_products = function_exists('wc_get_products') ? wc_get_products([
        'status' => 'publish',
        'limit' => 2,
        'category' => ['books'],
        'orderby' => 'date',
        'order' => 'DESC',
    ]) : [];
    foreach ($sample_cart_products as $sample_cart_product) {
        if ($sample_cart_product instanceof WC_Product) {
            $cart_items[] = ['product' => $sample_cart_product, 'quantity' => 2];
        }
    }
    if ('abandoned-cart' === $slug) {
        $cart_url = add_query_arg('tfp_cart_test_email', '1', function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/'));
    }
    $blog_url = home_url('/');
    $my_account_url = $set_password_url;
    $email_subject = $email_heading;
    $book = null;
    $product = null;
    $recommendations = [];
    $tfp_refund_data = null;
    if ('refunded-order' === $slug && $order) {
        $sample_order_items = $order->get_items('line_item');
        $sample_item = !empty($sample_order_items) ? reset($sample_order_items) : null;
        $sample_product = $sample_item ? $sample_item->get_product() : null;
        $tfp_refund_data = [
            'amount' => 24.99,
            'date' => current_time('F j, Y'),
            'status' => __('Refund Processed', 'woocommerce'),
            'note' => __('Please allow 5–10 business days for your bank to post the credit.', 'woocommerce'),
            'items' => $sample_item ? [[
                'name' => $sample_item->get_name(),
                'image' => ($sample_product && $sample_product->get_image_id()) ? wp_get_attachment_image_url($sample_product->get_image_id(), 'thumbnail') : '',
                'quantity' => 1,
                'total' => 24.99,
            ]] : [],
        ];
        $refund_amount = 24.99;
    }
    if ('product-review' === $slug && $order) {
        $review_items = $order->get_items('line_item');
        $review_item = !empty($review_items) ? reset($review_items) : null;
        $product = $review_item ? $review_item->get_product() : null;
        $recommendations = $product ? wc_get_products(['status' => 'publish', 'limit' => 2, 'exclude' => [$product->get_id()]]) : [];
    }
    if (function_exists('wc_get_products')) {
        $sample_books = wc_get_products(['status' => 'publish', 'limit' => 1, 'category' => ['books']]);
        $book = !empty($sample_books) ? $sample_books[0] : null;
    }

    return compact(
        'blogname', 'user', 'customer', 'user_display_name', 'user_login',
        'password_generated', 'email_heading', 'additional_content',
        'set_password_url', 'reset_url', 'reset_password_url', 'account_url',
        'shop_url', 'cart_url', 'customer_name', 'customer_email',
        'sent_to_admin', 'plain_text', 'email', 'order', 'items',
        'cart_items', 'blog_url', 'my_account_url', 'email_subject', 'book', 'tfp_refund_data', 'refund_amount', 'product', 'recommendations'
    );
}

/** Render and send exactly one selected template; no automation is invoked. */
function tfp_send_email_template_test($slug, $recipient) {
    $templates = tfp_get_email_test_templates();
    if (empty($templates[$slug]) || empty($templates[$slug]['file']) || !is_file($templates[$slug]['file'])) {
        return new WP_Error('template_missing', __('Selected email template was not found.', 'tfp-authentication'));
    }

    $context = tfp_get_email_test_template_context($slug, $recipient);
    if (in_array($slug, ['book-order-confirmation', 'processing-order', 'shipping-confirmation', 'refunded-order', 'product-review'], true) && empty($context['order'])) {
        return new WP_Error('sample_data_missing', __('Could not build sample order data. Ensure WooCommerce and at least one product are available.', 'tfp-authentication'));
    }

    // Make template variables available in the include scope.
    extract($context, EXTR_SKIP);
    ob_start();
    include $templates[$slug]['file'];
    $message = ob_get_clean();

    if (!is_string($message) || trim($message) === '') {
        return new WP_Error('empty_template', __('The selected template rendered an empty email. Check its required sample data.', 'tfp-authentication'));
    }

    $subject = sprintf('[%s] Test Email: %s', wp_specialchars_decode($blogname, ENT_QUOTES), $templates[$slug]['label']);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Direct wp_mail prevents WooCommerce order/customer status and automation hooks.
    $sent = wp_mail($recipient, $subject, $message, $headers);
    if (!$sent) {
        return new WP_Error('email_send_failed', __('Test email could not be sent. Check your SMTP/mail configuration and WordPress mail logs.', 'tfp-authentication'));
    }

    return true;
}


/** Render a compact test-send form inside an individual email preview page. */
function tfp_render_email_preview_test_form($slug) {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $recipient = '';
    $notice = '';
    $notice_type = 'success';

    if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['tfp_preview_send_test'])) {
        check_admin_referer('tfp_preview_test_' . $slug, 'tfp_preview_test_nonce');
        $posted_slug = isset($_POST['tfp_preview_template']) ? sanitize_key(wp_unslash($_POST['tfp_preview_template'])) : '';
        $recipient = isset($_POST['tfp_preview_recipient']) ? sanitize_email(wp_unslash($_POST['tfp_preview_recipient'])) : '';

        if ($posted_slug !== $slug) {
            $notice = __('Invalid email template selection.', 'tfp-authentication');
            $notice_type = 'error';
        } elseif (!$recipient || !is_email($recipient)) {
            $notice = __('Please enter a valid recipient email address.', 'tfp-authentication');
            $notice_type = 'error';
        } else {
            $result = tfp_send_email_template_test($slug, $recipient);
            if (is_wp_error($result)) {
                $notice = $result->get_error_message();
                $notice_type = 'error';
            } else {
                $notice = sprintf(__('Test email sent successfully to %s.', 'tfp-authentication'), $recipient);
            }
        }
    }

    echo '<div class="notice notice-info" style="padding:12px 16px;margin:16px 0;max-width:760px;"><p><strong>' . esc_html__('Send a test email', 'tfp-authentication') . '</strong></p>';
    if ($notice) {
        echo '<p class="notice-' . esc_attr($notice_type) . '">' . esc_html($notice) . '</p>';
    }
    echo '<form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
    wp_nonce_field('tfp_preview_test_' . $slug, 'tfp_preview_test_nonce');
    echo '<input type="hidden" name="tfp_preview_template" value="' . esc_attr($slug) . '">';
    echo '<label class="screen-reader-text" for="tfp-preview-recipient-' . esc_attr($slug) . '">' . esc_html__('Test recipient email', 'tfp-authentication') . '</label>';
    echo '<input type="email" id="tfp-preview-recipient-' . esc_attr($slug) . '" name="tfp_preview_recipient" value="' . esc_attr($recipient) . '" placeholder="you@example.com" required style="min-width:260px;">';
    echo '<button type="submit" name="tfp_preview_send_test" value="1" class="button button-primary">' . esc_html__('Send Test Email', 'tfp-authentication') . '</button>';
    echo '</form></div>';
}

/** Render the central template selector and test-send form. */
function tfp_render_email_template_test_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to send test emails.', 'tfp-authentication'));
    }

    $templates = tfp_get_email_test_templates();
    $recipient = '';
    $selected = '';
    $notice = '';
    $notice_type = 'success';

    if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['tfp_send_template_test'])) {
        check_admin_referer('tfp_email_template_test_action', 'tfp_email_template_test_nonce');
        $selected = isset($_POST['tfp_test_template']) ? sanitize_key(wp_unslash($_POST['tfp_test_template'])) : '';
        $recipient = isset($_POST['tfp_test_recipient']) ? sanitize_email(wp_unslash($_POST['tfp_test_recipient'])) : '';

        if (!isset($templates[$selected])) {
            $notice = __('Please select a valid email template.', 'tfp-authentication');
            $notice_type = 'error';
        } elseif (!$recipient || !is_email($recipient)) {
            $notice = __('Please enter a valid recipient email address.', 'tfp-authentication');
            $notice_type = 'error';
        } else {
            $result = tfp_send_email_template_test($selected, $recipient);
            if (is_wp_error($result)) {
                $notice = $result->get_error_message();
                $notice_type = 'error';
            } else {
                $notice = sprintf(__('Test email sent successfully to %s.', 'tfp-authentication'), $recipient);
            }
        }
    }

    ?>
    <div class="wrap" style="max-width:900px;">
        <h1><?php esc_html_e('TFP Email Template Test', 'tfp-authentication'); ?></h1>
        <p><?php esc_html_e('Choose any registered email template, enter a test recipient, and send the real template design with sample data. This does not create customers or orders and does not run email automations.', 'tfp-authentication'); ?></p>

        <?php if ($notice) : ?>
            <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <div style="max-width:760px;background:#fff;padding:22px;margin-top:18px;border:1px solid #dcdcde;border-radius:6px;">
            <form method="post">
                <?php wp_nonce_field('tfp_email_template_test_action', 'tfp_email_template_test_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tfp_test_template"><?php esc_html_e('Email Template', 'tfp-authentication'); ?></label></th>
                        <td>
                            <select name="tfp_test_template" id="tfp_test_template" class="regular-text" required>
                                <option value=""><?php esc_html_e('Select an email template', 'tfp-authentication'); ?></option>
                                <?php foreach ($templates as $slug => $template) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($selected, $slug); ?>><?php echo esc_html($template['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tfp_test_recipient"><?php esc_html_e('Test Recipient Email', 'tfp-authentication'); ?></label></th>
                        <td><input type="email" name="tfp_test_recipient" id="tfp_test_recipient" class="regular-text" value="<?php echo esc_attr($recipient); ?>" placeholder="you@example.com" required></td>
                    </tr>
                </table>
                <?php submit_button(__('Send Test Email', 'tfp-authentication'), 'primary', 'tfp_send_template_test'); ?>
            </form>
        </div>
        <p class="description"><?php esc_html_e('New customer-*.php templates are detected automatically. For custom template files, register them with the tfp_email_test_templates filter.', 'tfp-authentication'); ?></p>
    </div>
    <?php
}
