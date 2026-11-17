<?php
/**
 * Automatic New Book Release emails.
 *
 * Sends only to customers with recorded marketing-email consent.
 */
defined('ABSPATH') || exit;

/**
 * Queue a release campaign when a WooCommerce Book product is first published.
 */
function tfp_is_book_release_product($product_id) {
    $product_id = absint($product_id);
    $product = wc_get_product($product_id);

    if (!$product || 'product' !== get_post_type($product_id)) {
        return false;
    }

    // A Book must be explicitly assigned to the Books product category.
    if (!has_term('books', 'product_cat', $product_id)) {
        return false;
    }

    // Never treat a course product as a book, even if categories were misassigned.
    if (has_term(['course', 'courses'], 'product_cat', $product_id)) {
        return false;
    }

    // LearnDash-linked course products commonly store their linked course ID in product meta.
    foreach (['_related_course', '_related_courses', '_learndash_course_id', 'learndash_course_id', '_course_id'] as $meta_key) {
        $linked_course = get_post_meta($product_id, $meta_key, true);
        if (!empty($linked_course)) {
            return false;
        }
    }

    return true;
}

function tfp_queue_book_release_on_publish($new_status, $old_status, $post) {
    if (!$post || 'product' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status) {
        return;
    }

    // Trigger only for explicitly categorized Book products, never Courses or other products.
    $book = wc_get_product($post->ID);
    if (!$book || !tfp_is_book_release_product($post->ID)) {
        return;
    }

    $logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
    $customers = wc_get_customers([
        'role'    => 'customer',
        'limit'   => -1,
        'return'  => 'objects',
        'orderby' => 'ID',
        'order'   => 'ASC',
    ]);

    $queued = 0;
    foreach ($customers as $customer) {
        $user_id = $customer->get_id();
        $email   = sanitize_email($customer->get_billing_email() ?: $customer->get_email());

        // Never send promotional release emails without an explicit opt-in record.
        $consent = get_user_meta($user_id, 'tfp_marketing_opt_in', true);
        if (!$consent) {
            $consent = get_user_meta($user_id, 'woocommerce_email_marketing_consent', true);
        }
        $consented = in_array(strtolower((string) $consent), ['1', 'yes', 'true', 'on'], true);

        if (!$email || !is_email($email) || !$consented) {
            continue;
        }

        $sent_key = '_tfp_book_release_sent_' . absint($post->ID);
        if (get_user_meta($user_id, $sent_key, true)) {
            continue;
        }

        $args = [$post->ID, $user_id, $email];
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('tfp_send_book_release_email', $args, 'tfp-book-release');
        } else {
            wp_schedule_single_event(time() + ($queued * 15), 'tfp_send_book_release_email', $args);
        }
        $queued++;
    }

    if ($logger) {
        $logger->info(sprintf('Book release #%d: queued %d consented customer email(s).', $post->ID, $queued), ['source' => 'tfp-book-release']);
    }
}
add_action('transition_post_status', 'tfp_queue_book_release_on_publish', 20, 3);

/**
 * Send one queued release email and record the result per customer/book.
 */
function tfp_send_book_release_email($product_id, $user_id, $recipient) {
    $book = wc_get_product(absint($product_id));
    $customer = get_user_by('id', absint($user_id));
    $recipient = sanitize_email($recipient);

    if (!$book || 'publish' !== get_post_status($product_id) || !tfp_is_book_release_product($product_id) || !$customer || !is_email($recipient)) {
        return;
    }

    // Re-check consent at send time so a revoked opt-in is respected.
    $consent = get_user_meta($user_id, 'tfp_marketing_opt_in', true);
    if (!$consent) {
        $consent = get_user_meta($user_id, 'woocommerce_email_marketing_consent', true);
    }
    if (!in_array(strtolower((string) $consent), ['1', 'yes', 'true', 'on'], true)) {
        return;
    }

    $sent_key = '_tfp_book_release_sent_' . absint($product_id);
    if (get_user_meta($user_id, $sent_key, true)) {
        return;
    }

    $lock_key = 'tfp_book_release_lock_' . absint($product_id) . '_' . absint($user_id);
    if (get_transient($lock_key)) {
        return;
    }
    set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);

    $email_heading = sprintf(__('New book: %s', 'tfp-authentication'), $book->get_name());
    $blogname = get_bloginfo('name');

    ob_start();
    include TFP_AUTH_PATH . 'includes/emails/customer-book-release.php';
    $message = ob_get_clean();

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $sent = wp_mail($recipient, $email_heading, $message, $headers);

    if ($sent) {
        update_user_meta($user_id, $sent_key, current_time('mysql'));
    }

    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $context = ['source' => 'tfp-book-release'];
        if ($sent) {
            $logger->info(sprintf('Book #%d release email sent to customer #%d (%s).', $product_id, $user_id, $recipient), $context);
        } else {
            $logger->error(sprintf('Book #%d release email failed for customer #%d (%s).', $product_id, $user_id, $recipient), $context);
        }
    }

    delete_transient($lock_key);
}
add_action('tfp_send_book_release_email', 'tfp_send_book_release_email', 10, 3);
