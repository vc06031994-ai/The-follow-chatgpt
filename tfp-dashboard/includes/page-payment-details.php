<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_payment_token_summary($token)
{
    if (!$token || !is_object($token)) return null;

    $type = method_exists($token, 'get_type') ? (string) $token->get_type() : '';
    $gateway = method_exists($token, 'get_gateway_id') ? (string) $token->get_gateway_id() : '';
    $is_card = $type === 'CC';
    $brand = $is_card && method_exists($token, 'get_card_type') ? (string) $token->get_card_type() : '';
    $last4 = method_exists($token, 'get_last4') ? (string) $token->get_last4() : '';
    $expiry = '';

    if ($is_card && method_exists($token, 'get_expiry_month') && method_exists($token, 'get_expiry_year')) {
        $month = (int) $token->get_expiry_month();
        $year = (string) $token->get_expiry_year();
        if ($month && $year !== '') $expiry = sprintf('%02d / %s', $month, $year);
    }

    $kind = $is_card ? 'card' : (stripos($gateway, 'paypal') !== false ? 'paypal' : 'bank');

    return [
        'id' => method_exists($token, 'get_id') ? (int) $token->get_id() : 0,
        'kind' => $kind,
        'gateway' => $gateway,
        'label' => $is_card ? ($brand ? ucfirst($brand) : __('Card', 'tfp-dashboard')) : ($gateway ? ucwords(str_replace(['_', '-'], ' ', $gateway)) : __('Payment Method', 'tfp-dashboard')),
        'last4' => $last4,
        'expiry' => $expiry,
        'default' => method_exists($token, 'is_default') ? (bool) $token->is_default() : false,
    ];
}

function tfp_dashboard_get_payment_tokens($user_id)
{
    if (!$user_id || !class_exists('WC_Payment_Tokens')) return [];
    $rows = [];
    foreach (WC_Payment_Tokens::get_customer_tokens($user_id) as $token) {
        $summary = tfp_dashboard_payment_token_summary($token);
        if ($summary && !empty($summary['id'])) $rows[] = $summary;
    }
    return $rows;
}

function tfp_dashboard_render_payment_method_icon($kind)
{
    if ($kind === 'paypal') {
        echo '<span class="tfp-payment-method-icon tfp-payment-method-icon--paypal" aria-hidden="true">P</span>';
    } elseif ($kind === 'bank') {
        echo '<span class="tfp-payment-method-icon tfp-payment-method-icon--bank" aria-hidden="true">⌁</span>';
    } else {
        echo '<span class="tfp-payment-method-icon tfp-payment-method-icon--card" aria-hidden="true">▣</span>';
    }
}

function tfp_dashboard_render_payment_method_row($method)
{
    $is_legacy = !empty($method['legacy']);
    $title = $method['label'] . (!empty($method['last4']) ? ' •••• ' . $method['last4'] : '');
    $detail = !empty($method['expiry']) ? $method['expiry'] : '';
    if ($method['kind'] === 'paypal') $detail = tfp_billing_get_billing_email();
    ?>
    <div class="tfp-payment-method-row" data-tfp-payment-token="<?php echo esc_attr($method['id']); ?>">
        <div class="tfp-payment-method-row__main">
            <?php tfp_dashboard_render_payment_method_icon($method['kind']); ?>
            <div class="tfp-payment-method-row__copy">
                <div class="tfp-payment-method-row__title">
                    <strong><?php echo esc_html($title); ?></strong>
                    <?php if ($method['default']) : ?><span class="tfp-payment-default">DEFAULT</span><?php endif; ?>
                </div>
                <?php if ($detail) : ?><span class="tfp-payment-method-row__detail"><?php echo esc_html($detail); ?></span><?php endif; ?>
            </div>
        </div>
        <div class="tfp-payment-method-row__actions">
            <?php if (!$is_legacy) : ?>
                <button type="button" class="tfp-payment-link" data-tfp-payment-default="<?php echo esc_attr($method['id']); ?>" <?php disabled($method['default']); ?>><?php esc_html_e('Default', 'tfp-dashboard'); ?></button>
            <?php endif; ?>
            <button type="button" class="tfp-payment-link" data-tfp-payment-edit="<?php echo esc_attr($method['id']); ?>"><?php esc_html_e('Edit', 'tfp-dashboard'); ?></button>
            <?php if ($is_legacy) : ?>
                <button type="button" class="tfp-payment-link tfp-payment-link--danger" data-tfp-payment-remove-legacy="1"><?php esc_html_e('Remove', 'tfp-dashboard'); ?></button>
            <?php else : ?>
                <button type="button" class="tfp-payment-link tfp-payment-link--danger" data-tfp-payment-remove="<?php echo esc_attr($method['id']); ?>"><?php esc_html_e('Remove', 'tfp-dashboard'); ?></button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function tfp_dashboard_payment_ajax_verify()
{
    if (!is_user_logged_in()) wp_send_json_error(['message' => __('Please sign in again.', 'tfp-dashboard')], 403);
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'tfp_billing_nonce')) {
        wp_send_json_error(['message' => __('Your session expired. Please refresh and try again.', 'tfp-dashboard')], 403);
    }
}

add_action('wp_ajax_tfp_payment_token_remove', function () {
    tfp_dashboard_payment_ajax_verify();
    $token_id = isset($_POST['token_id']) ? absint($_POST['token_id']) : 0;
    $token = $token_id && class_exists('WC_Payment_Tokens') ? WC_Payment_Tokens::get($token_id) : false;
    if (!$token || (int) $token->get_user_id() !== get_current_user_id()) {
        wp_send_json_error(['message' => __('Payment method not found.', 'tfp-dashboard')], 404);
    }
    wp_send_json_success(['message' => $token->delete() ? __('Payment method removed.', 'tfp-dashboard') : __('Could not remove this payment method.', 'tfp-dashboard')]);
});

add_action('wp_ajax_tfp_payment_legacy_remove', function () {
    tfp_dashboard_payment_ajax_verify();

    $user_id = get_current_user_id();
    $payment_method_id = (string) get_user_meta($user_id, '_tfp_saved_payment_method', true);

    if ($payment_method_id === '' && function_exists('tfp_billing_get_program_order')) {
        $order = tfp_billing_get_program_order($user_id);
        if ($order && method_exists($order, 'get_meta')) {
            $intent_id = (string) $order->get_meta('_tfp_stripe_intent_id', true);
            if ($intent_id !== '' && function_exists('tfp_stripe_api')) {
                $intent = tfp_stripe_api('GET', 'payment_intents/' . rawurlencode($intent_id));
                if (!is_wp_error($intent) && !empty($intent['payment_method'])) {
                    $payment_method_id = is_array($intent['payment_method'])
                        ? (string) ($intent['payment_method']['id'] ?? '')
                        : (string) $intent['payment_method'];
                }
            }
        }
    }

    if ($payment_method_id !== '' && function_exists('tfp_stripe_api')) {
        $detached = tfp_stripe_api('POST', 'payment_methods/' . rawurlencode($payment_method_id) . '/detach');
        if (is_wp_error($detached)) {
            $message = strtolower($detached->get_error_message());
            $already_gone = strpos($message, 'no such paymentmethod') !== false
                || strpos($message, 'no such payment method') !== false
                || strpos($message, 'already been detached') !== false;
            if (!$already_gone) {
                wp_send_json_error(['message' => $detached->get_error_message()], 400);
            }
        }
    }

    if (class_exists('WC_Payment_Tokens')) {
        foreach (WC_Payment_Tokens::get_customer_tokens($user_id) as $token) {
            if (method_exists($token, 'get_gateway_id') && $token->get_gateway_id() === 'tfp_stripe') {
                $token->delete();
            }
        }
    }

    delete_user_meta($user_id, '_tfp_saved_payment_method');
    delete_user_meta($user_id, '_tfp_saved_card_summary');
    delete_user_meta($user_id, '_tfp_cardholder_name');

    wp_send_json_success(['message' => __('Payment method removed.', 'tfp-dashboard')]);
});

add_action('wp_ajax_tfp_payment_token_default', function () {
    tfp_dashboard_payment_ajax_verify();
    $token_id = isset($_POST['token_id']) ? absint($_POST['token_id']) : 0;
    $token = $token_id && class_exists('WC_Payment_Tokens') ? WC_Payment_Tokens::get($token_id) : false;
    if (!$token || (int) $token->get_user_id() !== get_current_user_id()) {
        wp_send_json_error(['message' => __('Payment method not found.', 'tfp-dashboard')], 404);
    }
    if (!method_exists($token, 'set_default') || !method_exists($token, 'save')) {
        wp_send_json_error(['message' => __('This payment gateway does not support a default payment method.', 'tfp-dashboard')], 400);
    }
    $token->set_default(true);
    wp_send_json_success(['message' => $token->save() ? __('Default payment method updated.', 'tfp-dashboard') : __('Could not update the default payment method.', 'tfp-dashboard')]);
});

function tfp_dashboard_render_payment_details_content()
{
    $user_id = get_current_user_id();
    $name = tfp_dashboard_user_name();
    $first_name = tfp_dashboard_user_first_name() ?: $name;
    $tokens = tfp_dashboard_get_payment_tokens($user_id);
    $card = function_exists('tfp_billing_get_default_card_summary') ? tfp_billing_get_default_card_summary($user_id) : null;
    $stripe_ready = function_exists('tfp_stripe_is_configured') && tfp_stripe_is_configured();
    $saved_name = (string) get_user_meta($user_id, '_tfp_cardholder_name', true);
    $has_method = !empty($tokens) || !empty($card);

    tfp_dashboard_render_page_header(
        __('Payment Details', 'tfp-dashboard'),
        sprintf(esc_html__('%s, update your billing information', 'tfp-dashboard'), esc_html($first_name)),
        __('Save or update your payment method securely without leaving the Discipleship platform.', 'tfp-dashboard')
    );
    ?>
    <div class="tfp-payment-page" data-tfp-payment-page data-tfp-billing-nonce="<?php echo esc_attr(wp_create_nonce('tfp_billing_nonce')); ?>">
        <section class="tfp-payment-section">
            <div class="tfp-payment-section__intro">
                <h2><?php printf(esc_html__('%s, Setup Payment Methods', 'tfp-dashboard'), esc_html($first_name)); ?></h2>
                <p><?php esc_html_e('Securely manage the cards, bank accounts, and PayPal used for your unlock all your class dashboard.', 'tfp-dashboard'); ?></p>
            </div>

            <?php if ($has_method) : ?>
                <div class="tfp-payment-saved">
                    <div class="tfp-payment-saved__header">
                        <strong><?php esc_html_e('Saved Payment Method', 'tfp-dashboard'); ?></strong>
                        <button type="button" class="tfp-payment-add" data-tfp-payment-open><span aria-hidden="true">+</span><?php esc_html_e('Add Payment Method', 'tfp-dashboard'); ?></button>
                    </div>
                    <div class="tfp-payment-method-list">
                        <?php
                        if ($tokens) {
                            foreach ($tokens as $method) tfp_dashboard_render_payment_method_row($method);
                        } elseif ($card) {
                            tfp_dashboard_render_payment_method_row([
                                'id' => 0, 'kind' => 'card', 'label' => $card['brand'] ?: __('Card', 'tfp-dashboard'),
                                'last4' => $card['last4'], 'expiry' => $card['expiry'], 'default' => true, 'legacy' => true,
                            ]);
                        }
                        ?>
                    </div>
                    <div class="tfp-payment-settings">
                        <div>
                            <strong><?php esc_html_e('Payment Settings', 'tfp-dashboard'); ?></strong>
                            <p><?php esc_html_e('Use default payment methods for future purchases', 'tfp-dashboard'); ?></p>
                            <small><?php esc_html_e('Applies to all new programs enrolled.', 'tfp-dashboard'); ?></small>
                        </div>
                        <label class="tfp-payment-toggle">
                            <input type="checkbox" checked aria-label="<?php esc_attr_e('Use default payment methods for future purchases', 'tfp-dashboard'); ?>"><span></span>
                        </label>
                    </div>
                </div>
            <?php else : ?>
                <div class="tfp-payment-empty">
                    <span class="tfp-payment-empty__icon" aria-hidden="true">▣</span>
                    <h3><?php esc_html_e('No payment method on file — one more step', 'tfp-dashboard'); ?></h3>
                    <p><?php esc_html_e('Add a card, bank account, or PayPal to confirm your place in the program and unlock your class dashboard.', 'tfp-dashboard'); ?></p>
                    <button type="button" class="tfp-payment-add tfp-payment-add--empty" data-tfp-payment-open><span aria-hidden="true">+</span><?php esc_html_e('Add Payment', 'tfp-dashboard'); ?></button>
                </div>
            <?php endif; ?>
        </section>

        <section class="tfp-payment-editor<?php echo $has_method ? '' : ' is-open'; ?>" data-tfp-payment-editor>
            <div class="tfp-payment-editor__head">
                <strong><?php esc_html_e('Add Payment Method', 'tfp-dashboard'); ?></strong>
                <?php if ($has_method) : ?><button type="button" class="tfp-payment-close" data-tfp-payment-close aria-label="<?php esc_attr_e('Close', 'tfp-dashboard'); ?>">×</button><?php endif; ?>
            </div>

            <div class="tfp-payment-tabs" role="tablist">
                <button type="button" class="is-active" data-tfp-payment-tab="card" role="tab" aria-selected="true"><span class="tfp-payment-radio"></span><?php esc_html_e('Card', 'tfp-dashboard'); ?></button>
                <button type="button" data-tfp-payment-tab="bank" role="tab" aria-selected="false"><span class="tfp-payment-radio"></span><?php esc_html_e('Bank Account', 'tfp-dashboard'); ?></button>
                <button type="button" data-tfp-payment-tab="paypal" role="tab" aria-selected="false"><span class="tfp-payment-radio"></span><?php esc_html_e('PayPal', 'tfp-dashboard'); ?></button>
            </div>

            <div class="tfp-payment-panel is-active" data-tfp-payment-panel="card">
                <form class="tfp-payment-card-form" data-tfp-billing-form novalidate>
                    <div class="tfp-payment-field"><label><?php esc_html_e('Card Number', 'tfp-dashboard'); ?></label><div class="tfp-payment-input tfp-stripe-card-element" data-tfp-stripe-card-number></div></div>
                    <div class="tfp-payment-fields-row">
                        <div class="tfp-payment-field"><label><?php esc_html_e('Expiration', 'tfp-dashboard'); ?></label><div class="tfp-payment-input tfp-stripe-card-element" data-tfp-stripe-card-expiry></div></div>
                        <div class="tfp-payment-field"><label><?php esc_html_e('CVC', 'tfp-dashboard'); ?></label><div class="tfp-payment-input tfp-stripe-card-element" data-tfp-stripe-card-cvc></div></div>
                    </div>
                    <div class="tfp-payment-field"><label><?php esc_html_e('Name on Card', 'tfp-dashboard'); ?></label><input type="text" class="tfp-payment-input" data-tfp-cardholder-name value="<?php echo esc_attr($saved_name ?: $name); ?>" autocomplete="cc-name"></div>
                    <label class="tfp-payment-consent"><input type="checkbox" data-tfp-billing-consent required><span><?php esc_html_e('I authorize The Follow Project to process my payment securely and agree to the Terms & Refund Policy. Your payment information is encrypted. We never store full card or account numbers.', 'tfp-dashboard'); ?></span></label>
                    <div class="tfp-payment-actions"><button type="submit" class="tfp-payment-save" data-tfp-billing-submit><?php esc_html_e('Save Payment', 'tfp-dashboard'); ?></button><button type="button" class="tfp-payment-remove-editor" data-tfp-payment-close><?php esc_html_e('Remove', 'tfp-dashboard'); ?></button></div>
                    <p class="tfp-payment-status" data-tfp-billing-status role="status"></p>
                </form>
                <?php if (!$stripe_ready) : ?><p class="tfp-payment-status" data-state="error"><?php esc_html_e('Secure card setup is not configured yet. Please contact support.', 'tfp-dashboard'); ?></p><?php endif; ?>
            </div>

            <div class="tfp-payment-panel" data-tfp-payment-panel="bank">
                <div class="tfp-payment-bank-form">
                    <div class="tfp-payment-field"><label><?php esc_html_e('Account Holder Name', 'tfp-dashboard'); ?></label><input type="text" class="tfp-payment-input" placeholder="<?php esc_attr_e('Jon Doe', 'tfp-dashboard'); ?>"></div>
                    <div class="tfp-payment-segment"><button type="button" class="is-active"><?php esc_html_e('Checking', 'tfp-dashboard'); ?></button><button type="button"><?php esc_html_e('Savings', 'tfp-dashboard'); ?></button></div>
                    <div class="tfp-payment-fields-row">
                        <div class="tfp-payment-field"><label><?php esc_html_e('Routing Number', 'tfp-dashboard'); ?></label><input type="text" class="tfp-payment-input" placeholder="021000021"></div>
                        <div class="tfp-payment-field"><label><?php esc_html_e('Account Number', 'tfp-dashboard'); ?></label><input type="text" class="tfp-payment-input" placeholder="000123456789"></div>
                    </div>
                    <label class="tfp-payment-verification"><input type="checkbox"><span><?php esc_html_e('Two small verification deposits (under $1.00) may be sent to this account within 1–2 business days to confirm ownership.', 'tfp-dashboard'); ?></span></label>
                    <label class="tfp-payment-consent"><input type="checkbox" checked><span><?php esc_html_e('I authorize The Follow Project to process my payment securely and agree to the Terms & Refund Policy. Your payment information is encrypted. We never store full card or account numbers.', 'tfp-dashboard'); ?></span></label>
                    <div class="tfp-payment-actions"><button type="button" class="tfp-payment-save" data-tfp-unavailable-method="bank"><?php esc_html_e('Save Payment', 'tfp-dashboard'); ?></button><button type="button" class="tfp-payment-remove-editor" data-tfp-payment-close><?php esc_html_e('Remove', 'tfp-dashboard'); ?></button></div>
                    <p class="tfp-payment-status" data-tfp-bank-status role="status"></p>
                </div>
            </div>

            <div class="tfp-payment-panel" data-tfp-payment-panel="paypal">
                <div class="tfp-payment-paypal-form">
                    <div class="tfp-payment-paypal-icon" aria-hidden="true">P</div>
                    <p><?php esc_html_e("You'll be redirected to PayPal to securely log in and authorize The Follow Project to charge your linked account.", 'tfp-dashboard'); ?></p>
                    <div class="tfp-payment-field tfp-payment-paypal-email"><label><?php esc_html_e('PayPal Email (For Reference)', 'tfp-dashboard'); ?></label><input type="email" class="tfp-payment-input" value="<?php echo esc_attr(tfp_billing_get_billing_email()); ?>"></div>
                    <label class="tfp-payment-consent"><input type="checkbox" checked><span><?php esc_html_e('I authorize The Follow Project to process my payment securely and agree to the Terms & Refund Policy. Your payment information is encrypted. We never store full card or account numbers.', 'tfp-dashboard'); ?></span></label>
                    <div class="tfp-payment-actions"><button type="button" class="tfp-payment-save" data-tfp-unavailable-method="paypal"><?php esc_html_e('Save Payment', 'tfp-dashboard'); ?></button><button type="button" class="tfp-payment-remove-editor" data-tfp-payment-close><?php esc_html_e('Remove', 'tfp-dashboard'); ?></button></div>
                    <p class="tfp-payment-status" data-tfp-paypal-status role="status"></p>
                </div>
            </div>
        </section>
    </div>
    <?php
}
