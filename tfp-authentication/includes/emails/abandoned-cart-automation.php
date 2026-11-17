<?php
/**
 * TFP Abandoned Cart tracking and email delivery.
 *
 * Captures cart additions only for existing registered WordPress customers.
 * Sends one reminder after 60 minutes if no paid/processing purchase has been placed.
 */
defined('ABSPATH') || exit;

// Reminder delay after the latest eligible cart addition (currently 5 minutes).
if (!defined('TFP_ABANDONED_CART_DELAY')) {
    define('TFP_ABANDONED_CART_DELAY', 5 * MINUTE_IN_SECONDS);
}

/** Schedule through WooCommerce Action Scheduler, with WP-Cron as fallback. */
function tfp_abandoned_cart_schedule_reminder($token) {
    $timestamp = time() + TFP_ABANDONED_CART_DELAY;
    $args = [$token];

    // Action Scheduler is bundled with WooCommerce and has a dedicated queue runner.
    if (function_exists('as_schedule_single_action')) {
        $action_id = as_schedule_single_action(
            $timestamp,
            'tfp_send_abandoned_cart_email',
            $args,
            'tfp-abandoned-cart',
            true
        );

        if ($action_id) {
            return ['scheduled' => true, 'runner' => 'action-scheduler', 'id' => absint($action_id)];
        }

        return ['scheduled' => false, 'runner' => 'action-scheduler', 'id' => 0];
    }

    $scheduled = wp_schedule_single_event($timestamp, 'tfp_send_abandoned_cart_email', $args);
    return [
        'scheduled' => !is_wp_error($scheduled) && false !== $scheduled,
        'runner' => 'wp-cron',
        'id' => 0,
    ];
}

/** Remove a pending reminder from both supported queue systems. */
function tfp_abandoned_cart_unschedule_reminder($token) {
    $args = [$token];

    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('tfp_send_abandoned_cart_email', $args, 'tfp-abandoned-cart');
    }

    // Also clear legacy WP-Cron events created by older plugin versions.
    wp_clear_scheduled_hook('tfp_send_abandoned_cart_email', $args);
}

/** Check whether the cart contains at least one physical Book product. */
function tfp_abandoned_cart_get_book_items() {
    if (!function_exists('WC') || !WC()->cart) {
        return [];
    }

    $items = [];
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product = !empty($cart_item['data']) && $cart_item['data'] instanceof WC_Product
            ? $cart_item['data']
            : wc_get_product($cart_item['product_id'] ?? 0);

        if (!$product || !has_term('books', 'product_cat', $product->get_id())) {
            continue;
        }

        // Exclude LearnDash/course products even if a category was assigned incorrectly.
        $is_course = false;
        foreach (['_related_course', '_related_courses', '_learndash_course_id', 'learndash_course_id', '_course_id'] as $meta_key) {
            if (get_post_meta($product->get_id(), $meta_key, true)) {
                $is_course = true;
                break;
            }
        }
        if ($is_course) {
            continue;
        }

        $items[] = [
            'product_id' => $product->get_id(),
            'quantity'   => max(1, absint($cart_item['quantity'] ?? 1)),
        ];
    }

    return $items;
}

/**
 * Resolve only a registered WordPress customer.
 * Logged-in users are preferred; logged-out returning users are matched only
 * when their WooCommerce session already contains the email of a WP account.
 */
function tfp_abandoned_cart_get_registered_customer() {
    $user = is_user_logged_in() ? wp_get_current_user() : false;

    if (!$user && function_exists('WC') && WC()->customer) {
        $session_email = sanitize_email(WC()->customer->get_email());
        if (is_email($session_email)) {
            $user = get_user_by('email', $session_email);
        }
    }

    if (!$user || empty($user->ID) || empty($user->user_email) || !is_email($user->user_email)) {
        return false;
    }

    return $user;
}

/**
 * Save a cart snapshot and schedule a reminder from cart activity.
 * Only existing registered accounts with a valid email are eligible.
 */
function tfp_abandoned_cart_capture_from_cart() {
    if (!function_exists('WC') || !WC()->cart || !WC()->session || WC()->cart->is_empty()) {
        return false;
    }

    $user = tfp_abandoned_cart_get_registered_customer();
    if (!$user) {
        return false; // Never track guest or anonymous visitor carts.
    }

    $email = sanitize_email($user->user_email);
    $blocked = get_option('tfp_abandoned_cart_unsubscribed', []);
    if (is_array($blocked) && in_array(hash('sha256', strtolower($email)), $blocked, true)) {
        return false;
    }

    $items = tfp_abandoned_cart_get_book_items();
    if (!$items) {
        return false;
    }

    // Reset the timer on subsequent cart additions/changes for this session.
    tfp_abandoned_cart_cancel_current();

    $token = wp_generate_password(32, false, false);
    $record = [
        'user_id'     => absint($user->ID),
        'email'       => $email,
        'customer'    => sanitize_text_field($user->display_name ?: $user->user_login),
        'items'       => $items,
        'created'     => time(),
        'sent'        => false,
        'cart_hash'   => WC()->cart->get_cart_hash(),
    ];

    set_transient('tfp_abandoned_cart_' . $token, $record, 7 * DAY_IN_SECONDS);
    WC()->session->set('tfp_abandoned_cart_token', $token);

    $schedule = tfp_abandoned_cart_schedule_reminder($token);

    if (function_exists('wc_get_logger')) {
        wc_get_logger()->info(
            $schedule['scheduled']
                ? sprintf('Registered customer cart captured; %d-minute reminder scheduled using %s.', (int) (TFP_ABANDONED_CART_DELAY / MINUTE_IN_SECONDS), $schedule['runner'])
                : sprintf('Failed to schedule the %d-minute abandoned-cart reminder using %s.', (int) (TFP_ABANDONED_CART_DELAY / MINUTE_IN_SECONDS), $schedule['runner']),
            [
                'source' => 'tfp-abandoned-cart',
                'cart_token' => $token,
                'customer_id' => absint($user->ID),
                'schedule_id' => absint($schedule['id']),
            ]
        );
    }

    if (!$schedule['scheduled']) {
        delete_transient('tfp_abandoned_cart_' . $token);
        WC()->session->__unset('tfp_abandoned_cart_token');
        return false;
    }

    return true;
}

/** Capture cart activity after WooCommerce has added the product to the cart. */
function tfp_abandoned_cart_on_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    tfp_abandoned_cart_capture_from_cart();
}
add_action('woocommerce_add_to_cart', 'tfp_abandoned_cart_on_add_to_cart', 30, 6);

/** Cancel the pending reminder when the active cart is emptied. */
function tfp_abandoned_cart_cancel_current() {
    if (!function_exists('WC') || !WC()->session) {
        return;
    }

    $token = WC()->session->get('tfp_abandoned_cart_token');
    if ($token) {
        tfp_abandoned_cart_unschedule_reminder($token);
        delete_transient('tfp_abandoned_cart_' . sanitize_key($token));
        WC()->session->__unset('tfp_abandoned_cart_token');
    }
}
add_action('woocommerce_cart_emptied', 'tfp_abandoned_cart_cancel_current', 1);

/** Restore a saved cart using a signed, expiring URL token. */
function tfp_abandoned_cart_restore() {
    if (empty($_GET['tfp_restore_cart'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_GET['tfp_restore_cart']));
    if (!preg_match('/^[A-Za-z0-9]{32}$/', $token) || !wp_verify_nonce(
        isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '',
        'tfp_restore_cart_' . $token
    )) {
        wp_die(esc_html__('This cart restoration link is invalid or has expired.', 'tfp-authentication'), '', ['response' => 403]);
    }

    $record = get_transient('tfp_abandoned_cart_' . $token);
    if (empty($record['items']) || !function_exists('WC') || !WC()->cart) {
        wp_die(esc_html__('This saved cart is no longer available.', 'tfp-authentication'), '', ['response' => 404]);
    }

    WC()->cart->empty_cart();
    foreach ($record['items'] as $item) {
        $product_id = absint($item['product_id'] ?? 0);
        $quantity = max(1, absint($item['quantity'] ?? 1));
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
            continue;
        }
        WC()->cart->add_to_cart($product_id, $quantity);
    }

    if (WC()->cart->is_empty()) {
        wp_die(esc_html__('The saved products are no longer available.', 'tfp-authentication'), '', ['response' => 404]);
    }

    delete_transient('tfp_abandoned_cart_' . $token);
    wp_safe_redirect(wc_get_cart_url());
    exit;
}
add_action('template_redirect', 'tfp_abandoned_cart_restore', 1);

/** Do not send a reminder if an order was placed after the cart was captured. */
function tfp_abandoned_cart_has_completed_purchase($email, $created) {
    if (!function_exists('wc_get_orders')) {
        return false;
    }

    $orders = wc_get_orders([
        'billing_email' => $email,
        'status'        => ['processing', 'completed'],
        'date_created'  => '>' . gmdate('Y-m-d H:i:s', $created - 5),
        'limit'         => 1,
        'return'        => 'ids',
    ]);

    return !empty($orders);
}

/** Send the abandoned-cart email once, provided the customer still qualifies. */
function tfp_send_abandoned_cart_email($token) {
    $token = sanitize_text_field($token);
    $record = get_transient('tfp_abandoned_cart_' . $token);

    if (empty($record) || !empty($record['sent']) || empty($record['email']) || !is_email($record['email']) || empty($record['user_id'])) {
        return;
    }

    $registered_user = get_user_by('id', absint($record['user_id']));
    if (!$registered_user || !is_email($registered_user->user_email) || strtolower($registered_user->user_email) !== strtolower($record['email'])) {
        delete_transient('tfp_abandoned_cart_' . $token);
        return;
    }

    if (tfp_abandoned_cart_has_completed_purchase($record['email'], absint($record['created']))) {
        delete_transient('tfp_abandoned_cart_' . $token);
        return;
    }

    $cart_items = [];
    foreach ($record['items'] as $item) {
        $product = wc_get_product(absint($item['product_id'] ?? 0));
        if ($product && $product->is_visible()) {
            $cart_items[] = [
                'product'  => $product,
                'quantity' => max(1, absint($item['quantity'] ?? 1)),
            ];
        }
    }

    if (!$cart_items) {
        delete_transient('tfp_abandoned_cart_' . $token);
        return;
    }

    $restore_url = add_query_arg(
        ['tfp_restore_cart' => rawurlencode($token)],
        wc_get_cart_url()
    );
    $cart_url = wp_nonce_url($restore_url, 'tfp_restore_cart_' . $token);
    $customer_name = $record['customer'] ?? '';
    $blogname = get_bloginfo('name');

    // Create a one-click unsubscribe token tied to this email address.
    $unsubscribe_token = wp_generate_password(32, false, false);
    set_transient('tfp_abandoned_unsub_' . $unsubscribe_token, hash('sha256', strtolower($record['email'])), YEAR_IN_SECONDS);
    $unsubscribe_url = add_query_arg('tfp_abandoned_unsubscribe', $unsubscribe_token, home_url('/'));

    ob_start();
    include TFP_AUTH_PATH . 'includes/emails/customer-abandoned-cart.php';
    $message = ob_get_clean();

    $subject = sprintf(__('You left something behind at %s', 'tfp-authentication'), $blogname);
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $sent = wp_mail($record['email'], $subject, $message, $headers);

    if ($sent) {
        $record['sent'] = true;
        set_transient('tfp_abandoned_cart_' . $token, $record, DAY_IN_SECONDS);
    }

    if (function_exists('wc_get_logger')) {
        wc_get_logger()->info(
            $sent ? 'Abandoned cart email sent.' : 'Abandoned cart email delivery failed.',
            ['source' => 'tfp-abandoned-cart', 'cart_token' => $token]
        );
    }
}
add_action('tfp_send_abandoned_cart_email', 'tfp_send_abandoned_cart_email', 10, 1);

/** One-click unsubscribe for abandoned-cart reminders. */
function tfp_abandoned_cart_unsubscribe() {
    if (empty($_GET['tfp_abandoned_unsubscribe'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_GET['tfp_abandoned_unsubscribe']));
    if (!preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
        wp_die(esc_html__('Invalid unsubscribe link.', 'tfp-authentication'), '', ['response' => 400]);
    }

    $email_hash = get_transient('tfp_abandoned_unsub_' . $token);
    if (!$email_hash) {
        wp_die(esc_html__('This unsubscribe link has expired or has already been used.', 'tfp-authentication'), '', ['response' => 404]);
    }

    $blocked = get_option('tfp_abandoned_cart_unsubscribed', []);
    if (!is_array($blocked)) {
        $blocked = [];
    }
    $blocked[] = $email_hash;
    update_option('tfp_abandoned_cart_unsubscribed', array_values(array_unique($blocked)), false);
    delete_transient('tfp_abandoned_unsub_' . $token);

    wp_die(
        esc_html__('You have been unsubscribed from abandoned-cart reminder emails.', 'tfp-authentication'),
        esc_html__('Unsubscribed', 'tfp-authentication'),
        ['response' => 200]
    );
}
add_action('template_redirect', 'tfp_abandoned_cart_unsubscribe', 2);
