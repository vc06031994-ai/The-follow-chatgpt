<?php
if (!defined('ABSPATH')) exit;

function tfp_dashboard_render_payment_details_content()
{
    $user_id      = get_current_user_id();
    $name         = tfp_dashboard_user_name();
    $first_name   = tfp_dashboard_user_first_name() ?: $name;
    $has_paid     = tfp_billing_user_has_paid($user_id);
    $card         = function_exists('tfp_billing_get_default_card_summary') ? tfp_billing_get_default_card_summary($user_id) : null;
    $stripe_ready = function_exists('tfp_stripe_is_configured') && tfp_stripe_is_configured();

    $raw_brand  = $card && !empty($card['brand']) ? strtolower((string) $card['brand']) : 'generic';
    $brand_map  = array('visa', 'mastercard', 'amex', 'discover');
    $card_brand = in_array($raw_brand, $brand_map, true) ? $raw_brand : 'generic';
    $saved_name = (string) get_user_meta($user_id, '_tfp_cardholder_name', true);
    $card_name  = $saved_name ?: ($name ?: __('Card Holder', 'tfp-dashboard'));
    $last4      = $card && !empty($card['last4']) ? (string) $card['last4'] : '4242';
    $expiry     = $card && !empty($card['expiry']) ? (string) $card['expiry'] : '12 / 2030';

    tfp_dashboard_render_page_header(
        __('Payment Details', 'tfp-dashboard'),
        sprintf(esc_html__('%s, update your billing information', 'tfp-dashboard'), esc_html($first_name)),
        __('Save or update your payment method securely without leaving the Discipleship platform.', 'tfp-dashboard')
    );
    ?>
    <style id="tfp-billing-visual-card-styles">
        .tfp-billing-visual-card-wrap{width:100%;max-width:300px;margin:0 auto;perspective:1100px;}
        .tfp-billing-visual-card{position:relative;display:grid;grid-template-rows:auto auto 1fr auto;gap:0;width:100%;aspect-ratio:1.586/1;box-sizing:border-box;overflow:hidden;padding:22px;border-radius:20px;color:#fff;background:linear-gradient(135deg,#3f3f46,#18181b);box-shadow:0 16px 30px rgba(15,23,42,.2);transform:translateZ(0);transform-style:preserve-3d;transition:transform .35s ease,box-shadow .35s ease,background .35s ease;}
        .tfp-billing-visual-card-wrap:hover .tfp-billing-visual-card{transform:rotateX(3deg) rotateY(-5deg) translateY(-2px);box-shadow:0 22px 38px rgba(15,23,42,.26);}
        .tfp-billing-visual-card:before{content:"";position:absolute;inset:-38% -25% auto auto;width:105%;height:145%;border-radius:50%;background:rgba(255,255,255,.10);transform:rotate(24deg);pointer-events:none;}
        .tfp-billing-visual-card:after{content:"";position:absolute;left:-20%;bottom:-65%;width:100%;height:125%;border-radius:50%;background:rgba(0,0,0,.14);pointer-events:none;}
        .tfp-billing-visual-card__top,.tfp-billing-visual-card__chip,.tfp-billing-visual-card__number,.tfp-billing-visual-card__bottom{position:relative;z-index:1;min-width:0;}
        .tfp-billing-visual-card__top{display:flex;align-items:center;justify-content:space-between;gap:12px;}
        .tfp-billing-visual-card__type{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;opacity:.82;}
        .tfp-billing-visual-card__brand{font-size:19px;font-weight:800;font-style:italic;letter-spacing:-.045em;text-transform:uppercase;white-space:nowrap;}
        .tfp-billing-visual-card__chip{width:40px;height:29px;margin:22px 0 12px;border-radius:7px;background:linear-gradient(135deg,#f8e7a7,#b28c3b);box-shadow:inset 0 0 0 1px rgba(0,0,0,.16);}
        .tfp-billing-visual-card__chip:after{content:"";position:absolute;inset:7px 0;border-top:1px solid rgba(0,0,0,.22);border-bottom:1px solid rgba(0,0,0,.22);}
        .tfp-billing-visual-card__number{align-self:end;margin:0 0 15px;font-size:15px;font-weight:700;letter-spacing:.11em;line-height:1.2;white-space:nowrap;font-variant-numeric:tabular-nums;}
        .tfp-billing-visual-card__bottom{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:10px;}
        .tfp-billing-visual-card__name{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;font-weight:700;letter-spacing:.045em;line-height:1.25;text-transform:uppercase;}
        .tfp-billing-visual-card__expiry{display:block;font-size:10px;font-weight:600;line-height:1.25;white-space:nowrap;opacity:.9;}
        .tfp-billing-visual-card.is-visa{background:linear-gradient(135deg,#2563eb 0%,#1e3a8a 58%,#172554 100%);}
        .tfp-billing-visual-card.is-mastercard{background:linear-gradient(120deg,#27272a 0%,#18181b 52%,#991b1b 100%);}
        .tfp-billing-visual-card.is-amex{background:linear-gradient(135deg,#0f766e 0%,#155e75 55%,#082f49 100%);}
        .tfp-billing-visual-card.is-discover{background:linear-gradient(135deg,#292524 0%,#7c2d12 60%,#ea580c 100%);}
        .tfp-billing-visual-card.is-generic{background:linear-gradient(135deg,#3f3f46 0%,#18181b 100%);}
        @media (max-width:767px){.tfp-billing-visual-card-wrap{max-width:330px}.tfp-billing-visual-card{padding:20px}.tfp-billing-visual-card__number{font-size:14px}.tfp-billing-visual-card__name{font-size:10px}}
    </style>
    <div class="tfp-billing-page">
        <div class="tfp-dash-billing-detail">
            <div class="tfp-dash-billing-detail__intro">
                <h2 class="tfp-dash-section__hi"><?php printf(esc_html__('Hi, %s — Update payment method', 'tfp-dashboard'), esc_html($name)); ?></h2>
                <p class="tfp-dash-section__lead"><?php esc_html_e('Your card details are securely collected by Stripe. The Discipleship platform never sees or stores your full card number.', 'tfp-dashboard'); ?></p>
            </div>

            <div class="tfp-dash-billing-detail__grid">
                <div class="tfp-dash-billing-detail__card-preview">
                    <div class="tfp-billing-visual-card-wrap">
                        <div class="tfp-billing-visual-card is-<?php echo esc_attr($card_brand); ?>" data-tfp-visual-card data-card-brand="<?php echo esc_attr($card_brand); ?>">
                            <div class="tfp-billing-visual-card__top">
                                <span class="tfp-billing-visual-card__type" data-tfp-visual-card-type><?php echo esc_html($card_brand === 'generic' ? 'Payment' : 'Credit'); ?></span>
                                <span class="tfp-billing-visual-card__brand" data-tfp-visual-card-brand><?php echo esc_html($card_brand === 'generic' ? 'CARD' : strtoupper($card_brand)); ?></span>
                            </div>
                            <div class="tfp-billing-visual-card__chip" aria-hidden="true"></div>
                            <div class="tfp-billing-visual-card__number" data-tfp-visual-card-number><?php echo esc_html('•••• •••• •••• ' . $last4); ?></div>
                            <div class="tfp-billing-visual-card__bottom">
                                <span class="tfp-billing-visual-card__name" data-tfp-visual-card-name><?php echo esc_html($card_name); ?></span>
                                <span class="tfp-billing-visual-card__expiry" data-tfp-visual-card-expiry><?php echo esc_html($expiry); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tfp-dash-billing-detail__form">
                    <?php if ($card) : ?>
                        <div class="tfp-dash-billing-saved-card">
                            <span><?php esc_html_e('Current payment method', 'tfp-dashboard'); ?></span>
                            <strong data-tfp-card-brand><?php printf('%s ending in %s', esc_html($card['brand']), esc_html($card['last4'])); ?></strong>
                            <small data-tfp-card-expiry><?php echo esc_html($card['expiry']); ?></small>
                        </div>
                    <?php else : ?>
                        <p class="tfp-dash-section__lead tfp-dash-billing-no-card" data-tfp-no-card><?php esc_html_e('No payment method saved yet.', 'tfp-dashboard'); ?></p>
                    <?php endif; ?>

                    <?php if ($stripe_ready) : ?>
                        <form class="tfp-dash-billing-secure-form" data-tfp-billing-form novalidate>
                            <label><span><?php esc_html_e('Card Number', 'tfp-dashboard'); ?></span><div class="tfp-stripe-card-element" data-tfp-stripe-card-number></div></label>
                            <label><span><?php esc_html_e('Expiration', 'tfp-dashboard'); ?></span><div class="tfp-stripe-card-element" data-tfp-stripe-card-expiry></div></label>
                            <label><span><?php esc_html_e('CVC', 'tfp-dashboard'); ?></span><div class="tfp-stripe-card-element" data-tfp-stripe-card-cvc></div></label>
                            <label><span><?php esc_html_e('Name on Card', 'tfp-dashboard'); ?></span><input type="text" class="tfp-billing-name-input" data-tfp-cardholder-name value="<?php echo esc_attr($saved_name ?: $name); ?>" autocomplete="cc-name" placeholder="<?php esc_attr_e('Name as it appears on card', 'tfp-dashboard'); ?>"></label>
                            <label class="tfp-dash-billing-consent"><input type="checkbox" data-tfp-billing-consent required><span><?php esc_html_e('I authorize The Follow Project to process my payment securely and agree to the Terms & Refund Policy.', 'tfp-dashboard'); ?></span></label>
                            <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary" data-tfp-billing-submit><?php esc_html_e('Save Payment Method', 'tfp-dashboard'); ?></button>
                            <p class="tfp-dash-form__status" data-tfp-billing-status role="status"></p>
                        </form>
                    <?php else : ?>
                        <p class="tfp-dash-form__status" data-state="error"><?php esc_html_e('Secure card updates are not configured yet. Please contact support.', 'tfp-dashboard'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tfp-dash-billing-detail__next-step">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-profile')); ?>" class="tfp-dash-btn tfp-dash-btn--outline"><?php esc_html_e('Back to Profile', 'tfp-dashboard'); ?></a>
            <?php if (!$has_paid) : ?><a href="<?php echo esc_url(tfp_billing_pay_now_url($user_id)); ?>" class="tfp-dash-btn tfp-dash-btn--primary"><?php esc_html_e('Pay Now', 'tfp-dashboard'); ?></a><?php endif; ?>
        </div>
    </div>
    <?php
}
