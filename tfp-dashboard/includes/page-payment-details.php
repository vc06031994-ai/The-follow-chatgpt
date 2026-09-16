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
    $card_brand   = $card && !empty($card['brand']) ? strtolower((string) $card['brand']) : 'visa';
    $card_name    = $name ?: __('Card Holder', 'tfp-dashboard');
    $card_last4   = $card && !empty($card['last4']) ? (string) $card['last4'] : '4242';
    $card_expiry  = $card && !empty($card['expiry']) ? (string) $card['expiry'] : '12 / 2030';

    tfp_dashboard_render_page_header(
        __('Payment Details', 'tfp-dashboard'),
        sprintf(esc_html__('%s, update your billing information', 'tfp-dashboard'), esc_html($first_name)),
        __('Save or update your payment method securely without leaving the Discipleship platform.', 'tfp-dashboard')
    );
    ?>
    <style id="tfp-billing-visual-card-styles">
        .tfp-billing-visual-card-wrap{perspective:1200px;width:100%;max-width:270px;margin:0 auto;}
        .tfp-billing-visual-card{position:relative;aspect-ratio:1.586/1;width:100%;border-radius:22px;overflow:hidden;padding:24px;color:#fff;background:linear-gradient(135deg,#303030 0%,#111 52%,#454545 100%);box-shadow:0 18px 35px rgba(0,0,0,.22);transform:rotateY(0deg) rotateX(0deg);transition:transform .45s ease,background .35s ease,box-shadow .35s ease;transform-style:preserve-3d;}
        .tfp-billing-visual-card-wrap:hover .tfp-billing-visual-card{transform:rotateY(-8deg) rotateX(4deg) translateY(-3px);box-shadow:0 24px 42px rgba(0,0,0,.28);}
        .tfp-billing-visual-card:before{content:"";position:absolute;inset:-45% -20% auto auto;width:95%;height:150%;border-radius:50%;background:rgba(255,255,255,.09);transform:rotate(28deg);pointer-events:none;}
        .tfp-billing-visual-card__top,.tfp-billing-visual-card__bottom{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:12px;}
        .tfp-billing-visual-card__type{font-size:11px;letter-spacing:.04em;text-transform:uppercase;opacity:.82;}
        .tfp-billing-visual-card__brand{font-size:20px;font-weight:800;font-style:italic;letter-spacing:-.04em;text-transform:uppercase;}
        .tfp-billing-visual-card__chip{position:relative;z-index:1;width:42px;height:31px;margin:27px 0 20px;border-radius:7px;background:linear-gradient(135deg,#f6e4a5,#a98c45);box-shadow:inset 0 0 0 1px rgba(0,0,0,.16);}
        .tfp-billing-visual-card__chip:after{content:"";position:absolute;inset:7px 0;border-top:1px solid rgba(0,0,0,.2);border-bottom:1px solid rgba(0,0,0,.2);}
        .tfp-billing-visual-card__number{position:relative;z-index:1;font-size:16px;letter-spacing:.12em;font-variant-numeric:tabular-nums;white-space:nowrap;margin-bottom:18px;}
        .tfp-billing-visual-card__name{font-size:11px;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:68%;}
        .tfp-billing-visual-card__expiry{font-size:10px;opacity:.82;white-space:nowrap;}
        .tfp-billing-visual-card.is-visa{background:linear-gradient(135deg,#1d4ed8 0%,#172554 55%,#0f172a 100%);}
        .tfp-billing-visual-card.is-mastercard{background:linear-gradient(135deg,#27272a 0%,#18181b 58%,#7f1d1d 100%);}
        .tfp-billing-visual-card.is-amex{background:linear-gradient(135deg,#0f766e 0%,#164e63 58%,#082f49 100%);}
        .tfp-billing-visual-card.is-discover{background:linear-gradient(135deg,#292524 0%,#431407 58%,#ea580c 100%);}
        .tfp-billing-visual-card.is-generic{background:linear-gradient(135deg,#3f3f46 0%,#18181b 100%);}
        @media (max-width:767px){.tfp-billing-visual-card-wrap{max-width:320px}.tfp-billing-visual-card{padding:22px}.tfp-billing-visual-card__number{font-size:14px}}
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
                        <div class="tfp-billing-visual-card is-generic" data-tfp-visual-card data-card-brand="<?php echo esc_attr($card_brand); ?>">
                            <div class="tfp-billing-visual-card__top">
                                <span class="tfp-billing-visual-card__type" data-tfp-visual-card-type><?php esc_html_e('Credit', 'tfp-dashboard'); ?></span>
                                <span class="tfp-billing-visual-card__brand" data-tfp-visual-card-brand><?php echo esc_html(strtoupper($card_brand)); ?></span>
                            </div>
                            <div class="tfp-billing-visual-card__chip" aria-hidden="true"></div>
                            <div class="tfp-billing-visual-card__number" data-tfp-visual-card-number><?php echo esc_html('•••• •••• •••• ' . $card_last4); ?></div>
                            <div class="tfp-billing-visual-card__bottom">
                                <span class="tfp-billing-visual-card__name" data-tfp-visual-card-name><?php echo esc_html($card_name); ?></span>
                                <span class="tfp-billing-visual-card__expiry" data-tfp-visual-card-expiry><?php echo esc_html($card_expiry); ?></span>
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
                            <label>
                                <span><?php esc_html_e('Card Number', 'tfp-dashboard'); ?></span>
                                <div class="tfp-stripe-card-element" data-tfp-stripe-card-number></div>
                            </label>

                            <label>
                                <span><?php esc_html_e('Expiration', 'tfp-dashboard'); ?></span>
                                <div class="tfp-stripe-card-element" data-tfp-stripe-card-expiry></div>
                            </label>

                            <label>
                                <span><?php esc_html_e('CVC', 'tfp-dashboard'); ?></span>
                                <div class="tfp-stripe-card-element" data-tfp-stripe-card-cvc></div>
                            </label>

                            <label>
                                <span><?php esc_html_e('Name on Card', 'tfp-dashboard'); ?></span>
                                <input type="text"
                                       class="tfp-billing-name-input"
                                       data-tfp-cardholder-name
                                       value="<?php echo esc_attr($name); ?>"
                                       autocomplete="cc-name"
                                       placeholder="<?php esc_attr_e('Name as it appears on card', 'tfp-dashboard'); ?>">
                            </label>

                            <label class="tfp-dash-billing-consent">
                                <input type="checkbox" data-tfp-billing-consent required>
                                <span><?php esc_html_e('I authorize The Follow Project to process my payment securely and agree to the Terms & Refund Policy.', 'tfp-dashboard'); ?></span>
                            </label>

                            <button type="submit" class="tfp-dash-btn tfp-dash-btn--primary" data-tfp-billing-submit>
                                <?php esc_html_e('Save Payment Method', 'tfp-dashboard'); ?>
                            </button>

                            <p class="tfp-dash-form__status" data-tfp-billing-status role="status"></p>
                        </form>
                    <?php else : ?>
                        <p class="tfp-dash-form__status" data-state="error"><?php esc_html_e('Secure card updates are not configured yet. Please contact support.', 'tfp-dashboard'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tfp-dash-billing-detail__next-step">
            <a href="<?php echo esc_url(tfp_dashboard_get_url('tfp-dashboard-profile')); ?>" class="tfp-dash-btn tfp-dash-btn--outline">
                <?php esc_html_e('Back to Profile', 'tfp-dashboard'); ?>
            </a>
            <?php if (!$has_paid) : ?>
                <a href="<?php echo esc_url(tfp_billing_pay_now_url($user_id)); ?>" class="tfp-dash-btn tfp-dash-btn--primary">
                    <?php esc_html_e('Pay Now', 'tfp-dashboard'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
