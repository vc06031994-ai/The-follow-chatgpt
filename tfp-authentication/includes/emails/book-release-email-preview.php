<?php
/**
 * Admin preview and test sender for the TFP New Book Release email.
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page('woocommerce', __('New Book Release Email Preview', 'tfp-authentication'), __('Book Release Email Preview', 'tfp-authentication'), 'manage_woocommerce', 'tfp-book-release-email-preview', 'tfp_render_book_release_email_preview');
});

function tfp_render_book_release_email_preview() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to view this preview.', 'tfp-authentication'));
    }

    $notice = '';
    $notice_type = 'success';
    $recipient = '';
    $products = function_exists('wc_get_products') ? wc_get_products(['status'=>'publish','limit'=>20,'category'=>['books'],'orderby'=>'date','order'=>'DESC']) : [];
    $selected_id = isset($_POST['tfp_book_release_product']) ? absint($_POST['tfp_book_release_product']) : (!empty($products) ? $products[0]->get_id() : 0);
    $book = $selected_id && function_exists('wc_get_product') ? wc_get_product($selected_id) : null;

    if ('POST' === strtoupper($_SERVER['REQUEST_METHOD'] ?? '') && isset($_POST['tfp_send_book_release_test'])) {
        check_admin_referer('tfp_send_book_release_test', 'tfp_book_release_nonce');
        $recipient = isset($_POST['tfp_book_release_recipient']) ? sanitize_email(wp_unslash($_POST['tfp_book_release_recipient'])) : '';
        if (!$recipient || !is_email($recipient)) {
            $notice = __('Please enter a valid recipient email address.', 'tfp-authentication');
            $notice_type = 'error';
        } elseif (!$book) {
            $notice = __('Please select a published book.', 'tfp-authentication');
            $notice_type = 'error';
        } else {
            $blogname = get_bloginfo('name');
            $email_heading = sprintf(__('New book: %s', 'tfp-authentication'), $book->get_name());
            ob_start();
            include TFP_AUTH_PATH . 'includes/emails/customer-book-release.php';
            $message = ob_get_clean();
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($recipient, $email_heading, $message, $headers);
            if ($sent) {
                $notice = sprintf(__('Test book release email sent to %s.', 'tfp-authentication'), $recipient);
            } else {
                $notice = __('Email could not be sent. Check SMTP/mail configuration.', 'tfp-authentication');
                $notice_type = 'error';
            }
        }
    }

    $blogname = get_bloginfo('name');
    ?>
    <div class="wrap" style="max-width:900px;">
        <h1><?php esc_html_e('TFP New Book Release Email Preview', 'tfp-authentication'); ?></h1>
        <?php if ($notice): ?><div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
        <div style="background:#fff;padding:20px;margin:18px 0;border:1px solid #dcdcde;border-radius:6px;">
            <form method="post">
                <?php wp_nonce_field('tfp_send_book_release_test', 'tfp_book_release_nonce'); ?>
                <p><label for="tfp_book_release_product"><strong><?php esc_html_e('Book to preview','tfp-authentication'); ?></strong></label><br>
                <select name="tfp_book_release_product" id="tfp_book_release_product" style="min-width:280px;">
                    <?php foreach ($products as $product): ?><option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($selected_id, $product->get_id()); ?>><?php echo esc_html($product->get_name()); ?></option><?php endforeach; ?>
                </select></p>
                <p><label for="tfp_book_release_recipient"><strong><?php esc_html_e('Test recipient email','tfp-authentication'); ?></strong></label><br>
                <input type="email" name="tfp_book_release_recipient" id="tfp_book_release_recipient" value="<?php echo esc_attr($recipient); ?>" placeholder="you@example.com" style="min-width:280px;" /></p>
                <p><button type="submit" name="tfp_send_book_release_test" class="button button-primary"><?php esc_html_e('Send Test Email','tfp-authentication'); ?></button></p>
            </form>
        </div>
        <div style="background:#eef1f4;padding:24px 10px;border:1px solid #dcdcde;">
            <?php if ($book): include TFP_AUTH_PATH . 'includes/emails/customer-book-release.php'; else: ?>
                <p><?php esc_html_e('No published products found in the Books category. Add/publish a book product to preview this template.','tfp-authentication'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
