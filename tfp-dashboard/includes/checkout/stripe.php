<?php
/**
 * Stripe payment processing for the custom TFP checkout wizard.
 *
 * This powers the "Payment" step (Credit/Debit card + Apple Pay + Google Pay)
 * so orders are charged and created inside our own checkout - the customer is
 * never bounced to WooCommerce's default checkout page.
 *
 * Design notes:
 *  - Card data is collected by Stripe Elements on the front-end and sent
 *    straight to Stripe. This server never sees a raw card number (PCI SAQ-A).
 *  - The secret key lives only on the server (option or wp-config constant) and
 *    is never printed to the page or returned to the browser.
 *  - No Composer / SDK: we talk to Stripe's REST API with wp_remote_request().
 *  - PayPal is intentionally NOT handled here - it will be added later via
 *    WooCommerce's own PayPal plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pin the Stripe API version so behaviour doesn't shift under us.
 */
if (!defined('TFP_STRIPE_API_VERSION')) {
    define('TFP_STRIPE_API_VERSION', '2024-06-20');
}

/* -------------------------------------------------------------------------
 * Settings / key helpers
 * ---------------------------------------------------------------------- */

/**
 * Stored settings (with defaults). Keys can also be supplied via wp-config
 * constants, which always win over the stored option.
 */
function tfp_stripe_get_settings()
{
    $defaults = array(
        'test_mode'             => 'yes',
        'test_publishable_key'  => '',
        'test_secret_key'       => '',
        'live_publishable_key'  => '',
        'live_secret_key'       => '',
    );

    $opts = get_option('tfp_stripe_settings', array());
    if (!is_array($opts)) {
        $opts = array();
    }

    return wp_parse_args($opts, $defaults);
}

function tfp_stripe_is_test_mode()
{
    if (defined('TFP_STRIPE_TEST_MODE')) {
        return (bool) TFP_STRIPE_TEST_MODE;
    }

    $settings = tfp_stripe_get_settings();
    return $settings['test_mode'] !== 'no';
}

function tfp_stripe_get_publishable_key()
{
    $test = tfp_stripe_is_test_mode();

    if ($test && defined('TFP_STRIPE_TEST_PUBLISHABLE_KEY')) {
        return trim((string) TFP_STRIPE_TEST_PUBLISHABLE_KEY);
    }
    if (!$test && defined('TFP_STRIPE_LIVE_PUBLISHABLE_KEY')) {
        return trim((string) TFP_STRIPE_LIVE_PUBLISHABLE_KEY);
    }

    $settings = tfp_stripe_get_settings();
    return trim($test ? $settings['test_publishable_key'] : $settings['live_publishable_key']);
}

/**
 * Secret key - server-side only. Never expose this to the browser.
 */
function tfp_stripe_get_secret_key()
{
    $test = tfp_stripe_is_test_mode();

    if ($test && defined('TFP_STRIPE_TEST_SECRET_KEY')) {
        return trim((string) TFP_STRIPE_TEST_SECRET_KEY);
    }
    if (!$test && defined('TFP_STRIPE_LIVE_SECRET_KEY')) {
        return trim((string) TFP_STRIPE_LIVE_SECRET_KEY);
    }

    $settings = tfp_stripe_get_settings();
    return trim($test ? $settings['test_secret_key'] : $settings['live_secret_key']);
}

function tfp_stripe_is_configured()
{
    return tfp_stripe_get_publishable_key() !== '' && tfp_stripe_get_secret_key() !== '';
}

/**
 * The (public) values the front-end needs. NO secret key here.
 */
function tfp_stripe_get_frontend_settings()
{
    $country = 'US';
    if (function_exists('wc_get_base_location')) {
        $base = wc_get_base_location();
        if (!empty($base['country'])) {
            $country = $base['country'];
        }
    }

    return array(
        'publishableKey' => tfp_stripe_get_publishable_key(),
        'isConfigured'   => tfp_stripe_is_configured(),
        'testMode'       => tfp_stripe_is_test_mode(),
        'currency'       => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
        'country'        => $country,
        'ajaxUrl'        => admin_url('admin-ajax.php'),
        'nonce'          => wp_create_nonce('tfp_checkout_nonce'),
    );
}

/**
 * Convert a decimal amount into Stripe's smallest-currency-unit integer.
 * Most currencies are x100; a handful are zero-decimal.
 */
function tfp_stripe_to_minor_units($amount, $currency)
{
    $zero_decimal = array(
        'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
        'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
    );

    $currency = strtolower($currency);
    if (in_array($currency, $zero_decimal, true)) {
        return (int) round((float) $amount);
    }

    return (int) round((float) $amount * 100);
}

/* -------------------------------------------------------------------------
 * Stripe REST helper (no SDK)
 * ---------------------------------------------------------------------- */

/**
 * Make a request to the Stripe API.
 *
 * @return array|WP_Error Decoded response body, or WP_Error on failure.
 */
function tfp_stripe_api($method, $path, $params = array())
{
    $secret = tfp_stripe_get_secret_key();
    if ($secret === '') {
        return new WP_Error('tfp_stripe_no_key', __('Stripe is not configured yet.', 'tfp-dashboard'));
    }

    $method = strtoupper($method);
    $url    = 'https://api.stripe.com/v1/' . ltrim($path, '/');

    $args = array(
        'method'      => $method,
        'timeout'     => 45,
        'redirection' => 0,
        'httpversion' => '1.1',
        'headers'     => array(
            'Authorization'  => 'Bearer ' . $secret,
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'Stripe-Version' => TFP_STRIPE_API_VERSION,
        ),
    );

    if ($method === 'GET') {
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }
    } else {
        $args['body'] = http_build_query($params, '', '&');
    }

    $response = wp_remote_request($url, $args);
    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($code < 200 || $code >= 300) {
        $message = isset($data['error']['message'])
            ? $data['error']['message']
            : __('The payment could not be processed. Please try again.', 'tfp-dashboard');
        return new WP_Error('tfp_stripe_api_error', $message, array('status' => $code, 'body' => $data));
    }

    return is_array($data) ? $data : array();
}

/* -------------------------------------------------------------------------
 * AJAX: create (or update) a PaymentIntent for the current cart
 * ---------------------------------------------------------------------- */

add_action('wp_ajax_tfp_stripe_create_intent', 'tfp_stripe_ajax_create_intent');
add_action('wp_ajax_nopriv_tfp_stripe_create_intent', 'tfp_stripe_ajax_create_intent');

function tfp_stripe_ajax_create_intent()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        wp_send_json(array('success' => false, 'message' => __('Your cart is empty.', 'tfp-dashboard')), 400);
    }

    if (!tfp_stripe_is_configured()) {
        wp_send_json(array('success' => false, 'message' => __('Payments are not configured yet.', 'tfp-dashboard')), 400);
    }

    // Force shipping into the totals (virtual products otherwise zero it out).
    tfp_checkout_recalculate_totals();

    $currency = get_woocommerce_currency();
    $total    = (float) WC()->cart->get_total('edit');
    $amount   = tfp_stripe_to_minor_units($total, $currency);

    if ($amount < 1) {
        wp_send_json(array('success' => false, 'message' => __('This order total is invalid.', 'tfp-dashboard')), 400);
    }

    $session_pi = WC()->session ? (string) WC()->session->get('tfp_stripe_intent_id') : '';
    $intent     = null;

    // Re-use the existing intent for this cart/session where possible so we
    // don't leave a trail of abandoned intents each time the step re-renders.
    if ($session_pi !== '') {
        $existing = tfp_stripe_api('GET', 'payment_intents/' . $session_pi);
        $status   = (!is_wp_error($existing) && isset($existing['status'])) ? $existing['status'] : '';

        if (!is_wp_error($existing) && in_array($status, array('requires_payment_method', 'requires_confirmation', 'requires_action', 'processing'), true)) {
            $intent = tfp_stripe_api('POST', 'payment_intents/' . $session_pi, array(
                'amount'   => $amount,
                'currency' => strtolower($currency),
            ));
            if (is_wp_error($intent)) {
                $intent = null; // fall through to a fresh intent
            }
        }

        if ($intent === null && WC()->session) {
            WC()->session->set('tfp_stripe_intent_id', '');
        }
    }

    if ($intent === null) {
        $params = array(
            'amount'                   => $amount,
            'currency'                 => strtolower($currency),
            'payment_method_types'     => array('card'),
            'description'              => sprintf(
                /* translators: %s: site name */
                __('Order from %s', 'tfp-dashboard'),
                wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
            ),
            'metadata'                 => array(
                'wp_user_id' => (string) get_current_user_id(),
                'cart_hash'  => WC()->cart->get_cart_hash(),
                'source'     => 'tfp_custom_checkout',
            ),
        );

        $email = WC()->customer ? WC()->customer->get_billing_email() : '';
        if ($email) {
            $params['receipt_email'] = $email;
        }

        $intent = tfp_stripe_api('POST', 'payment_intents', $params);
        if (is_wp_error($intent)) {
            wp_send_json(array('success' => false, 'message' => $intent->get_error_message()), 400);
        }

        if (WC()->session && !empty($intent['id'])) {
            WC()->session->set('tfp_stripe_intent_id', $intent['id']);
        }
    }

    if (empty($intent['client_secret'])) {
        wp_send_json(array('success' => false, 'message' => __('Could not start the payment. Please try again.', 'tfp-dashboard')), 400);
    }

    wp_send_json(array(
        'success'      => true,
        'clientSecret' => $intent['client_secret'],
        'amount'       => $amount,
        'currency'     => strtolower($currency),
    ));
}

/* -------------------------------------------------------------------------
 * AJAX: verify the payment and create the WooCommerce order
 * ---------------------------------------------------------------------- */

add_action('wp_ajax_tfp_stripe_complete_order', 'tfp_stripe_ajax_complete_order');
add_action('wp_ajax_nopriv_tfp_stripe_complete_order', 'tfp_stripe_ajax_complete_order');

function tfp_stripe_ajax_complete_order()
{
    tfp_checkout_verify_request();

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json(array('success' => false, 'message' => __('Checkout is not available.', 'tfp-dashboard')), 400);
    }

    $is_course_order = function_exists('tfp_course_cart_cohort_id') && (bool) tfp_course_cart_cohort_id();

    if (!tfp_stripe_is_configured()) {
        wp_send_json(array('success' => false, 'message' => __('Payments are not configured yet.', 'tfp-dashboard')), 400);
    }

    $pi_id = isset($_POST['payment_intent_id']) ? sanitize_text_field(wp_unslash($_POST['payment_intent_id'])) : '';
    if ($pi_id === '') {
        wp_send_json(array('success' => false, 'message' => __('Missing payment reference.', 'tfp-dashboard')), 400);
    }

    // Idempotency: if this intent already produced an order in this session,
    // just send the customer to that order (protects against double submits).
    $order_map = WC()->session ? (array) WC()->session->get('tfp_stripe_orders') : array();
    if (isset($order_map[$pi_id])) {
        $existing = wc_get_order($order_map[$pi_id]);
        if ($existing) {
            if (WC()->cart) {
                WC()->cart->empty_cart();
            }
            wp_send_json(array(
                'success' => true,
                'popup' => $is_course_order,
                'order_id' => $existing->get_id(),
                'order_key' => $existing->get_order_key(),
                'redirect' => $existing->get_checkout_order_received_url(),
            ));
        }
    }

    // Confirm with Stripe that the money was actually captured. Never trust the
    // browser's word that payment succeeded.
    $pi = tfp_stripe_api('GET', 'payment_intents/' . $pi_id);
    if (is_wp_error($pi)) {
        wp_send_json(array('success' => false, 'message' => $pi->get_error_message()), 400);
    }
    if (!isset($pi['status']) || $pi['status'] !== 'succeeded') {
        wp_send_json(array('success' => false, 'message' => __('Payment was not completed.', 'tfp-dashboard')), 400);
    }

    // Cache a safe card summary for the Discipleship Profile. The custom
    // checkout does not expose/store a reusable card token, so only brand,
    // last four digits and expiry are persisted for display.
    if (get_current_user_id() && !empty($pi['payment_method']) && function_exists('tfp_billing_store_card_summary')) {
        $pm_id = is_array($pi['payment_method'])
            ? (string) ($pi['payment_method']['id'] ?? '')
            : (string) $pi['payment_method'];

        if ($pm_id !== '') {
            $pm = tfp_stripe_api('GET', 'payment_methods/' . rawurlencode($pm_id));
            if (!is_wp_error($pm) && !empty($pm['card'])) {
                tfp_billing_store_card_summary(get_current_user_id(), $pm['card']);
            }
        }
    }

    if (WC()->cart->is_empty()) {
        wp_send_json(array('success' => false, 'message' => __('Your cart is empty.', 'tfp-dashboard')), 400);
    }

    // The amount Stripe captured must match the current cart total.
    tfp_checkout_recalculate_totals();
    $currency = get_woocommerce_currency();
    $expected = tfp_stripe_to_minor_units((float) WC()->cart->get_total('edit'), $currency);

    if ((int) $pi['amount'] !== (int) $expected || strtolower($pi['currency']) !== strtolower($currency)) {
        wp_send_json(array('success' => false, 'message' => __('Payment amount mismatch. Please contact support before trying again.', 'tfp-dashboard')), 400);
    }

    if (!defined('WOOCOMMERCE_CHECKOUT')) {
        define('WOOCOMMERCE_CHECKOUT', true);
    }

    $customer = WC()->customer;
    $data = array(
        'customer_id'         => get_current_user_id(),
        'payment_method'      => 'tfp_stripe',
        'billing_first_name'  => $customer->get_billing_first_name(),
        'billing_last_name'   => $customer->get_billing_last_name(),
        'billing_company'     => '',
        'billing_email'       => $customer->get_billing_email(),
        'billing_phone'       => $customer->get_billing_phone(),
        'billing_address_1'   => $customer->get_billing_address_1(),
        'billing_address_2'   => $customer->get_billing_address_2(),
        'billing_city'        => $customer->get_billing_city(),
        'billing_state'       => $customer->get_billing_state(),
        'billing_postcode'    => $customer->get_billing_postcode(),
        'billing_country'     => $customer->get_billing_country() ? $customer->get_billing_country() : 'US',
        'shipping_first_name' => $customer->get_shipping_first_name(),
        'shipping_last_name'  => $customer->get_shipping_last_name(),
        'shipping_company'    => '',
        'shipping_address_1'  => $customer->get_shipping_address_1(),
        'shipping_address_2'  => $customer->get_shipping_address_2(),
        'shipping_city'       => $customer->get_shipping_city(),
        'shipping_state'      => $customer->get_shipping_state(),
        'shipping_postcode'   => $customer->get_shipping_postcode(),
        'shipping_country'    => $customer->get_shipping_country() ? $customer->get_shipping_country() : 'US',
        'order_comments'      => '',
    );

    // Force needs_shipping true so the chosen shipping line is written onto the
    // order even for virtual products (mirrors tfp_checkout_recalculate_totals).
    add_filter('woocommerce_cart_needs_shipping', '__return_true');

    $order = null;
    try {
        $order_id = WC()->checkout()->create_order($data);

        if (is_wp_error($order_id)) {
            remove_filter('woocommerce_cart_needs_shipping', '__return_true');
            wp_send_json(array('success' => false, 'message' => $order_id->get_error_message()), 400);
        }

        $order = wc_get_order($order_id);
    } catch (Exception $e) {
        remove_filter('woocommerce_cart_needs_shipping', '__return_true');
        wp_send_json(array('success' => false, 'message' => $e->getMessage()), 400);
    }

    remove_filter('woocommerce_cart_needs_shipping', '__return_true');

    if (!$order) {
        wp_send_json(array('success' => false, 'message' => __('Your order could not be created. Your card was charged - please contact support.', 'tfp-dashboard')), 400);
    }

    $order->set_payment_method('tfp_stripe');
    $order->set_payment_method_title(
        tfp_stripe_is_test_mode()
            ? __('Card / Wallet (Stripe - test)', 'tfp-dashboard')
            : __('Card / Wallet (Stripe)', 'tfp-dashboard')
    );
    $order->set_transaction_id($pi_id);
    $order->update_meta_data('_tfp_stripe_intent_id', $pi_id);
    if (isset($pi['latest_charge'])) {
        $order->update_meta_data('_tfp_stripe_charge_id', (string) $pi['latest_charge']);
    }
    /* translators: %s: Stripe PaymentIntent id */
    $order->add_order_note(sprintf(__('Stripe payment completed. PaymentIntent: %s', 'tfp-dashboard'), $pi_id));
    $order->payment_complete($pi_id);
    $order->save();

    // Remember this intent -> order so a retry is idempotent, then reset.
    if (WC()->session) {
        $order_map[$pi_id] = $order->get_id();
        WC()->session->set('tfp_stripe_orders', $order_map);
        WC()->session->set('tfp_stripe_intent_id', '');
    }
    WC()->cart->empty_cart();

    wp_send_json(array(
        'success' => true,
        'popup' => $is_course_order,
        'order_id' => $order->get_id(),
        'order_key' => $order->get_order_key(),
        'redirect' => $order->get_checkout_order_received_url(),
    ));
}

/* -------------------------------------------------------------------------
 * Dashboard billing: save/update a card without leaving the discipleship UI
 * ---------------------------------------------------------------------- */

function tfp_stripe_get_or_create_customer($user_id)
{
    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('tfp_stripe_user_missing', __('Could not find your account.', 'tfp-dashboard'));
    }

    $customer_id = (string) get_user_meta($user_id, '_tfp_stripe_customer_id', true);
    if ($customer_id !== '') {
        $existing = tfp_stripe_api('GET', 'customers/' . rawurlencode($customer_id));
        if (!is_wp_error($existing) && !empty($existing['id']) && empty($existing['deleted'])) {
            return $existing;
        }
    }

    $customer = tfp_stripe_api('POST', 'customers', array(
        'email' => $user->user_email,
        'name'  => trim($user->first_name . ' ' . $user->last_name) ?: $user->display_name,
        'metadata' => array(
            'wp_user_id' => (string) $user_id,
            'source'     => 'tfp_dashboard_billing',
        ),
    ));

    if (!is_wp_error($customer) && !empty($customer['id'])) {
        update_user_meta($user_id, '_tfp_stripe_customer_id', sanitize_text_field($customer['id']));
    }

    return $customer;
}

function tfp_stripe_billing_verify_request()
{
    if (!is_user_logged_in()) {
        wp_send_json(array('success' => false, 'message' => __('Please sign in again.', 'tfp-dashboard')), 403);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'tfp_billing_nonce')) {
        wp_send_json(array('success' => false, 'message' => __('Your session expired. Please refresh and try again.', 'tfp-dashboard')), 403);
    }
}

add_action('wp_ajax_tfp_stripe_create_setup_intent', 'tfp_stripe_ajax_create_setup_intent');

function tfp_stripe_ajax_create_setup_intent()
{
    tfp_stripe_billing_verify_request();

    if (!tfp_stripe_is_configured()) {
        wp_send_json(array('success' => false, 'message' => __('Secure card updates are not configured yet.', 'tfp-dashboard')), 400);
    }

    $user_id = get_current_user_id();
    $customer = tfp_stripe_get_or_create_customer($user_id);
    if (is_wp_error($customer) || empty($customer['id'])) {
        $message = is_wp_error($customer) ? $customer->get_error_message() : __('Could not prepare secure card setup.', 'tfp-dashboard');
        wp_send_json(array('success' => false, 'message' => $message), 400);
    }

    $intent = tfp_stripe_api('POST', 'setup_intents', array(
        'customer' => $customer['id'],
        'usage'    => 'off_session',
        'payment_method_types' => array('card'),
        'metadata' => array(
            'wp_user_id' => (string) $user_id,
            'source'     => 'tfp_dashboard_billing',
        ),
    ));

    if (is_wp_error($intent) || empty($intent['client_secret'])) {
        $message = is_wp_error($intent) ? $intent->get_error_message() : __('Could not start secure card setup.', 'tfp-dashboard');
        wp_send_json(array('success' => false, 'message' => $message), 400);
    }

    wp_send_json(array(
        'success'      => true,
        'clientSecret' => $intent['client_secret'],
        'setupIntent'  => $intent['id'],
    ));
}

add_action('wp_ajax_tfp_stripe_save_setup_intent', 'tfp_stripe_ajax_save_setup_intent');

function tfp_stripe_ajax_save_setup_intent()
{
    tfp_stripe_billing_verify_request();

    $setup_intent_id = isset($_POST['setup_intent_id']) ? sanitize_text_field(wp_unslash($_POST['setup_intent_id'])) : '';
    if ($setup_intent_id === '') {
        wp_send_json(array('success' => false, 'message' => __('Missing secure payment reference.', 'tfp-dashboard')), 400);
    }

    $intent = tfp_stripe_api('GET', 'setup_intents/' . rawurlencode($setup_intent_id));
    if (is_wp_error($intent) || empty($intent['id'])) {
        $message = is_wp_error($intent) ? $intent->get_error_message() : __('Could not verify your card.', 'tfp-dashboard');
        wp_send_json(array('success' => false, 'message' => $message), 400);
    }

    $user_id = get_current_user_id();
    $customer_id = (string) get_user_meta($user_id, '_tfp_stripe_customer_id', true);

    if (($intent['status'] ?? '') !== 'succeeded' || empty($intent['payment_method'])) {
        wp_send_json(array('success' => false, 'message' => __('Your card setup was not completed.', 'tfp-dashboard')), 400);
    }

    if ($customer_id === '' || ($intent['customer'] ?? '') !== $customer_id) {
        wp_send_json(array('success' => false, 'message' => __('This payment method does not belong to your account.', 'tfp-dashboard')), 403);
    }

    if (!class_exists('WC_Payment_Token_CC')) {
        wp_send_json(array('success' => false, 'message' => __('Payment storage is not available.', 'tfp-dashboard')), 500);
    }

    $payment_method_id = is_array($intent['payment_method'])
        ? ($intent['payment_method']['id'] ?? '')
        : (string) $intent['payment_method'];

    $payment_method = tfp_stripe_api('GET', 'payment_methods/' . rawurlencode($payment_method_id));
    if (is_wp_error($payment_method) || empty($payment_method['card'])) {
        $message = is_wp_error($payment_method) ? $payment_method->get_error_message() : __('Could not read your card details.', 'tfp-dashboard');
        wp_send_json(array('success' => false, 'message' => $message), 400);
    }

    $card = $payment_method['card'];
    $token = new WC_Payment_Token_CC();
    $token->set_gateway_id('tfp_stripe');
    $token->set_token(sanitize_text_field($payment_method_id));
    $token->set_user_id($user_id);
    $token->set_card_type(sanitize_text_field($card['brand'] ?? 'card'));
    $token->set_last4(sanitize_text_field($card['last4'] ?? ''));
    $token->set_expiry_month((string) ($card['exp_month'] ?? ''));
    $token->set_expiry_year((string) ($card['exp_year'] ?? ''));
    $token->set_default(true);

    $token_id = $token->save();
    if (!$token_id) {
        wp_send_json(array('success' => false, 'message' => __('Could not save your payment method.', 'tfp-dashboard')), 500);
    }

    // Replace prior cards created by this dashboard only. Store payment methods
    // from other WooCommerce gateways remain untouched.
    if (class_exists('WC_Payment_Tokens')) {
        $tokens = WC_Payment_Tokens::get_customer_tokens($user_id);
        foreach ($tokens as $existing) {
            if (
                $existing->get_id() !== $token->get_id() &&
                method_exists($existing, 'get_gateway_id') &&
                $existing->get_gateway_id() === 'tfp_stripe'
            ) {
                $existing->delete();
            }
        }
    }

    update_user_meta($user_id, '_tfp_saved_payment_method', $payment_method_id);

    if (function_exists('tfp_billing_store_card_summary')) {
        tfp_billing_store_card_summary($user_id, $card);
    }

    wp_send_json(array(
        'success' => true,
        'message' => __('Payment method updated successfully.', 'tfp-dashboard'),
        'card'    => array(
            'brand'  => ucfirst((string) ($card['brand'] ?? __('Card', 'tfp-dashboard'))),
            'last4'  => (string) ($card['last4'] ?? ''),
            'expiry' => sprintf('%02d / %s', (int) ($card['exp_month'] ?? 0), (string) ($card['exp_year'] ?? '')),
        ),
    ));
}

/* -------------------------------------------------------------------------
 * Admin settings page (Settings -> TFP Stripe)
 * ---------------------------------------------------------------------- */

add_action('admin_menu', 'tfp_stripe_admin_menu');

function tfp_stripe_admin_menu()
{
    add_options_page(
        __('TFP Stripe Payments', 'tfp-dashboard'),
        __('TFP Stripe', 'tfp-dashboard'),
        'manage_options',
        'tfp-stripe',
        'tfp_stripe_render_settings_page'
    );
}

add_action('admin_init', 'tfp_stripe_register_settings');

function tfp_stripe_register_settings()
{
    register_setting('tfp_stripe_settings_group', 'tfp_stripe_settings', 'tfp_stripe_sanitize_settings');
}

function tfp_stripe_sanitize_settings($input)
{
    $out = array();
    $out['test_mode'] = (isset($input['test_mode']) && $input['test_mode'] === 'no') ? 'no' : 'yes';

    foreach (array('test_publishable_key', 'test_secret_key', 'live_publishable_key', 'live_secret_key') as $key) {
        $out[$key] = isset($input[$key]) ? trim(sanitize_text_field($input[$key])) : '';
    }

    return $out;
}

function tfp_stripe_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = tfp_stripe_get_settings();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('TFP Stripe Payments', 'tfp-dashboard'); ?></h1>
        <p style="max-width:640px;">
            <?php echo esc_html__('Paste your Stripe API keys below. While testing, use your TEST keys (they start with pk_test_ and sk_test_). Your secret key is stored on this server only and is never shown to customers.', 'tfp-dashboard'); ?>
        </p>
        <p style="max-width:640px;">
            <?php
            printf(
                /* translators: %s: Stripe dashboard URL */
                esc_html__('You can find your keys at %s.', 'tfp-dashboard'),
                '<a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener">dashboard.stripe.com/test/apikeys</a>'
            );
            ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields('tfp_stripe_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Mode', 'tfp-dashboard'); ?></th>
                    <td>
                        <label style="margin-right:18px;">
                            <input type="radio" name="tfp_stripe_settings[test_mode]" value="yes" <?php checked($settings['test_mode'], 'yes'); ?>>
                            <?php echo esc_html__('Test mode', 'tfp-dashboard'); ?>
                        </label>
                        <label>
                            <input type="radio" name="tfp_stripe_settings[test_mode]" value="no" <?php checked($settings['test_mode'], 'no'); ?>>
                            <?php echo esc_html__('Live mode', 'tfp-dashboard'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tfp_stripe_test_pk"><?php echo esc_html__('Test Publishable Key', 'tfp-dashboard'); ?></label></th>
                    <td><input type="text" id="tfp_stripe_test_pk" class="regular-text code" name="tfp_stripe_settings[test_publishable_key]" value="<?php echo esc_attr($settings['test_publishable_key']); ?>" placeholder="pk_test_..."></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tfp_stripe_test_sk"><?php echo esc_html__('Test Secret Key', 'tfp-dashboard'); ?></label></th>
                    <td><input type="password" id="tfp_stripe_test_sk" class="regular-text code" name="tfp_stripe_settings[test_secret_key]" value="<?php echo esc_attr($settings['test_secret_key']); ?>" placeholder="sk_test_..." autocomplete="off"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tfp_stripe_live_pk"><?php echo esc_html__('Live Publishable Key', 'tfp-dashboard'); ?></label></th>
                    <td><input type="text" id="tfp_stripe_live_pk" class="regular-text code" name="tfp_stripe_settings[live_publishable_key]" value="<?php echo esc_attr($settings['live_publishable_key']); ?>" placeholder="pk_live_..."></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tfp_stripe_live_sk"><?php echo esc_html__('Live Secret Key', 'tfp-dashboard'); ?></label></th>
                    <td><input type="password" id="tfp_stripe_live_sk" class="regular-text code" name="tfp_stripe_settings[live_secret_key]" value="<?php echo esc_attr($settings['live_secret_key']); ?>" placeholder="sk_live_..." autocomplete="off"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <p>
            <strong><?php echo esc_html__('Status:', 'tfp-dashboard'); ?></strong>
            <?php if (tfp_stripe_is_configured()) : ?>
                <span style="color:#00666e;font-weight:600;"><?php echo esc_html__('Configured', 'tfp-dashboard'); ?></span>
            <?php else : ?>
                <span style="color:#570506;font-weight:600;"><?php echo esc_html__('Not configured yet', 'tfp-dashboard'); ?></span>
            <?php endif; ?>
            &mdash; <?php echo tfp_stripe_is_test_mode() ? esc_html__('test mode', 'tfp-dashboard') : esc_html__('live mode', 'tfp-dashboard'); ?>
        </p>
    </div>
    <?php
}
