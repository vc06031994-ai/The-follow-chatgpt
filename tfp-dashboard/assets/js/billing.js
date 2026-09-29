(function () {
    'use strict';
    var S = window.tfpDashboardBilling || {};

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    ready(function () {
        var page = document.querySelector('[data-tfp-payment-page]');
        if (!page) return;

        var editor = document.querySelector('[data-tfp-payment-editor]');
        var nonce = page.getAttribute('data-tfp-billing-nonce') || S.nonce || '';
        var form = document.querySelector('[data-tfp-billing-form]');
        var status = form && form.querySelector('[data-tfp-billing-status]');
        var submit = form && form.querySelector('[data-tfp-billing-submit]');
        var nameInput = form && form.querySelector('[data-tfp-cardholder-name]');
        var consent = form && form.querySelector('[data-tfp-billing-consent]');
        var brandBadge = document.querySelector('[data-tfp-card-brand-badge]');
        var currentBrand = '';
        var attempts = 0;
        var started = false;

        function updateBrandBadge(brand) {
            if (typeof brand !== 'undefined') {
                currentBrand = brand || '';
            }
            if (!brandBadge) return;
            var activeTab = document.querySelector('[data-tfp-payment-tab].is-active');
            var isCardActive = activeTab && activeTab.getAttribute('data-tfp-payment-tab') === 'card';
            if (isCardActive && currentBrand && currentBrand !== 'unknown') {
                var names = {
                    'visa': 'VISA',
                    'mastercard': 'MASTERCARD',
                    'amex': 'AMEX',
                    'discover': 'DISCOVER',
                    'diners': 'DINERS',
                    'jcb': 'JCB',
                    'unionpay': 'UNIONPAY'
                };
                brandBadge.textContent = names[currentBrand] || currentBrand.toUpperCase();
                brandBadge.style.display = 'inline-flex';
            } else {
                brandBadge.textContent = '';
                brandBadge.style.display = 'none';
            }
        }

        function msg(el, text, state) {
            if (!el) return;
            el.textContent = text || '';
            if (state) el.setAttribute('data-state', state);
            else el.removeAttribute('data-state');
        }

        function post(action, data) {
            var body = new FormData();
            body.append('action', action);
            body.append('nonce', nonce);
            Object.keys(data || {}).forEach(function (k) {
                body.append(k, data[k]);
            });
            return fetch(S.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) {
                return r.json();
            });
        }

        document.querySelectorAll('[data-tfp-payment-open]').forEach(function (b) {
            b.addEventListener('click', function () {
                var section = document.querySelector('[data-tfp-payment-section]');
                if (section) {
                    section.style.display = 'none';
                }
                if (editor) {
                    editor.style.display = 'block';
                    editor.classList.add('is-open');
                    editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                updateBrandBadge(currentBrand);
            });
        });

        document.querySelectorAll('[data-tfp-payment-close]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (editor) {
                    editor.style.display = 'none';
                    editor.classList.remove('is-open');
                }
                var section = document.querySelector('[data-tfp-payment-section]');
                if (section) {
                    section.style.display = 'block';
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        document.querySelectorAll('[data-tfp-payment-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.getAttribute('data-tfp-payment-tab');
                document.querySelectorAll('[data-tfp-payment-tab]').forEach(function (t) {
                    t.classList.toggle('is-active', t === tab);
                    t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });
                document.querySelectorAll('[data-tfp-payment-panel]').forEach(function (p) {
                    p.classList.toggle('is-active', p.getAttribute('data-tfp-payment-panel') === key);
                });
                updateBrandBadge(currentBrand);
            });
        });

        document.querySelectorAll('[data-tfp-payment-segment], .tfp-payment-segment').forEach(function (group) {
            var input = group.querySelector('[data-tfp-account-type-input]');
            var buttons = group.querySelectorAll('button');
            buttons.forEach(function (b) {
                b.addEventListener('click', function (e) {
                    e.preventDefault();
                    buttons.forEach(function (x) {
                        var isActive = (x === b);
                        x.classList.toggle('is-active', isActive);
                        x.setAttribute('aria-checked', isActive ? 'true' : 'false');
                    });
                    var type = b.getAttribute('data-account-type') || (b.textContent || '').trim().toLowerCase();
                    if (input) {
                        input.value = type;
                    }
                });
            });
        });

        document.querySelectorAll('[data-tfp-unavailable-method]').forEach(function (b) {
            b.addEventListener('click', function () {
                var key = b.getAttribute('data-tfp-unavailable-method');
                var target = document.querySelector('[data-tfp-' + key + '-status]');
                msg(target, key === 'paypal'
                    ? 'PayPal connection needs the PayPal gateway/vault configuration before it can be saved from this screen.'
                    : 'Bank account connection needs the ACH/US bank gateway configuration before it can be saved from this screen.',
                    'error');
            });
        });

        function tokenAction(action, id, row) {
            post(action, { token_id: id }).then(function (r) {
                if (!r || !r.success) {
                    throw new Error(r && r.data && r.data.message || r && r.message || 'Could not update payment method.');
                }
                if (action === 'tfp_payment_token_remove') {
                    if (row) row.remove();
                } else {
                    window.location.reload();
                }
            }).catch(function (e) {
                window.alert(e.message);
            });
        }

        document.querySelectorAll('[data-tfp-payment-remove]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (!window.confirm('Remove this payment method?')) return;
                tokenAction('tfp_payment_token_remove', b.getAttribute('data-tfp-payment-remove'), b.closest('[data-tfp-payment-token]'));
            });
        });

        document.querySelectorAll('[data-tfp-payment-remove-legacy]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (!window.confirm('Remove this payment method?')) return;
                post('tfp_payment_legacy_remove', {}).then(function (r) {
                    if (!r || !r.success) {
                        throw new Error(r && r.data && r.data.message || r && r.message || 'Could not remove this payment method.');
                    }
                    window.location.reload();
                }).catch(function (e) {
                    window.alert(e.message);
                });
            });
        });

        document.querySelectorAll('[data-tfp-payment-default]').forEach(function (b) {
            b.addEventListener('click', function () {
                if (!b.disabled) {
                    tokenAction('tfp_payment_token_default', b.getAttribute('data-tfp-payment-default'), null);
                }
            });
        });

        document.querySelectorAll('[data-tfp-payment-edit]').forEach(function (b) {
            b.addEventListener('click', function () {
                var section = document.querySelector('[data-tfp-payment-section]');
                if (section) {
                    section.style.display = 'none';
                }
                if (editor) {
                    editor.style.display = 'block';
                    editor.classList.add('is-open');
                }
                var tab = document.querySelector('[data-tfp-payment-tab="card"]');
                if (tab) tab.click();
                if (editor) {
                    editor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                updateBrandBadge(currentBrand);
            });
        });

        if (!form || !S.publishableKey) return;
        var numberMount = form.querySelector('[data-tfp-stripe-card-number]');
        var expiryMount = form.querySelector('[data-tfp-stripe-card-expiry]');
        var cvcMount = form.querySelector('[data-tfp-stripe-card-cvc]');
        if (!numberMount || !expiryMount || !cvcMount) return;

        function init() {
            if (started) return;
            if (typeof window.Stripe !== 'function') {
                attempts++;
                if (attempts < 50) setTimeout(init, 100);
                return;
            }
            started = true;
            var stripe, elements, cardNumber, cardExpiry, cardCvc;
            var style = {
                base: {
                    color: '#1F2933',
                    fontFamily: 'Arial, Helvetica, sans-serif',
                    fontSize: '16px',
                    fontSmoothing: 'antialiased',
                    lineHeight: '22px',
                    '::placeholder': { color: '#8A9299' }
                },
                invalid: { color: '#B3261E', iconColor: '#B3261E' }
            };

            try {
                stripe = window.Stripe(S.publishableKey);
                elements = stripe.elements();
                cardNumber = elements.create('cardNumber', { style: style, placeholder: '1234 5678 9012 3456' });
                cardExpiry = elements.create('cardExpiry', { style: style, placeholder: 'MM/YY' });
                cardCvc = elements.create('cardCvc', { style: style, placeholder: '123' });
                cardNumber.mount(numberMount);
                cardExpiry.mount(expiryMount);
                cardCvc.mount(cvcMount);
            } catch (e) {
                msg(status, e.message || 'Secure card fields could not be initialized.', 'error');
                return;
            }

            [cardNumber, cardExpiry, cardCvc].forEach(function (el) {
                el.on('change', function (ev) {
                    msg(status, ev && ev.error ? ev.error.message : '', ev && ev.error ? 'error' : null);
                    if (el === cardNumber && ev) {
                        updateBrandBadge(ev.brand);
                    }
                });
            });

            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                if (submit && submit.disabled) return;
                var cardholder = nameInput ? nameInput.value.trim() : '';
                if (!cardholder) {
                    msg(status, 'Please enter the name shown on the card.', 'error');
                    if (nameInput) nameInput.focus();
                    return;
                }
                if (consent && !consent.checked) {
                    msg(status, 'Please confirm that you authorize the payment before continuing.', 'error');
                    consent.focus();
                    return;
                }
                if (submit) submit.disabled = true;
                msg(status, 'Preparing secure card update...');
                post('tfp_stripe_create_setup_intent', {}).then(function (r) {
                    if (!r || !r.success || !r.clientSecret) {
                        throw new Error(r && r.message || r && r.data && r.data.message || 'Could not start secure card setup.');
                    }
                    return stripe.confirmCardSetup(r.clientSecret, {
                        payment_method: {
                            card: cardNumber,
                            billing_details: { name: cardholder }
                        }
                    });
                }).then(function (result) {
                    if (result.error) throw new Error(result.error.message || 'Could not verify this card.');
                    if (!result.setupIntent || result.setupIntent.status !== 'succeeded') {
                        throw new Error('Card setup was not completed.');
                    }
                    msg(status, 'Saving payment method...');
                    return post('tfp_stripe_save_setup_intent', {
                        setup_intent_id: result.setupIntent.id,
                        cardholder_name: cardholder
                    });
                }).then(function (r) {
                    if (!r || !r.success) {
                        throw new Error(r && r.message || r && r.data && r.data.message || 'Could not save your payment method.');
                    }
                    msg(status, r.message || r.data && r.data.message || 'Payment method saved successfully.', 'success');
                    setTimeout(function () {
                        window.location.reload();
                    }, 700);
                }).catch(function (e) {
                    msg(status, e.message || 'A secure payment error occurred. Please try again.', 'error');
                }).finally(function () {
                    if (submit) submit.disabled = false;
                });
            });
        }

        init();
    });
})();