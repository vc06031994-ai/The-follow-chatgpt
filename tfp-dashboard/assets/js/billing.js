(function () {
    'use strict';

    var S = window.tfpDashboardBilling || {};
    var CARDHOLDER_STORAGE_KEY = 'tfp_billing_cardholder_name';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    onReady(function () {
        var form = document.querySelector('[data-tfp-billing-form]');
        var numberMount = document.querySelector('[data-tfp-stripe-card-number]');
        var expiryMount = document.querySelector('[data-tfp-stripe-card-expiry]');
        var cvcMount = document.querySelector('[data-tfp-stripe-card-cvc]');
        var visualCard = document.querySelector('[data-tfp-visual-card]');
        var visualBrand = document.querySelector('[data-tfp-visual-card-brand]');
        var visualType = document.querySelector('[data-tfp-visual-card-type]');
        var visualName = document.querySelector('[data-tfp-visual-card-name]');
        var visualNumber = document.querySelector('[data-tfp-visual-card-number]');
        var visualExpiry = document.querySelector('[data-tfp-visual-card-expiry]');
        var savedCardholderName = '';

        try {
            savedCardholderName = window.localStorage.getItem(CARDHOLDER_STORAGE_KEY) || '';
        } catch (e) {
            savedCardholderName = '';
        }

        function normalizeBrand(brand) {
            var value = String(brand || '').toLowerCase().replace(/[^a-z]/g, '');
            if (value === 'mastercard') return 'mastercard';
            if (value === 'americanexpress' || value === 'amex') return 'amex';
            if (value === 'discover') return 'discover';
            if (value === 'visa') return 'visa';
            return 'generic';
        }

        function brandLabel(brand) {
            var normalized = normalizeBrand(brand);
            if (normalized === 'mastercard') return 'MASTERCARD';
            if (normalized === 'amex') return 'AMEX';
            if (normalized === 'discover') return 'DISCOVER';
            if (normalized === 'visa') return 'VISA';
            return 'CARD';
        }

        function renderVisualCard(data) {
            if (!visualCard) return;

            var brand = data && data.brand ? data.brand : visualCard.getAttribute('data-card-brand');
            var normalized = normalizeBrand(brand);
            var name = data && data.name ? data.name : (visualName ? visualName.textContent : 'Card Holder');
            var last4 = data && data.last4 ? String(data.last4) : '';
            var expiry = data && data.expiry ? data.expiry : (visualExpiry ? visualExpiry.textContent : '12 / 2030');

            visualCard.classList.remove('is-visa', 'is-mastercard', 'is-amex', 'is-discover', 'is-generic');
            visualCard.classList.add('is-' + normalized);
            visualCard.setAttribute('data-card-brand', normalized);

            if (visualBrand) visualBrand.textContent = brandLabel(brand);
            if (visualType) visualType.textContent = normalized === 'generic' ? 'Payment' : 'Credit';
            if (visualName && name) visualName.textContent = name;
            if (visualNumber && last4) visualNumber.textContent = '•••• •••• •••• ' + last4;
            if (visualExpiry && expiry) visualExpiry.textContent = expiry;
        }

        renderVisualCard({
            brand: visualCard ? visualCard.getAttribute('data-card-brand') : 'visa',
            name: savedCardholderName || undefined
        });

        if (form && visualName) {
            var initialNameInput = form.querySelector('[data-tfp-cardholder-name]');
            if (initialNameInput) {
                if (savedCardholderName) {
                    initialNameInput.value = savedCardholderName;
                    visualName.textContent = savedCardholderName;
                }

                initialNameInput.addEventListener('input', function () {
                    visualName.textContent = initialNameInput.value.trim() || 'Card Holder';
                });
            }
        }

        if (!form || !numberMount || !expiryMount || !cvcMount) return;

        var status = form.querySelector('[data-tfp-billing-status]');
        var submit = form.querySelector('[data-tfp-billing-submit]');
        var nameInput = form.querySelector('[data-tfp-cardholder-name]');
        var consent = form.querySelector('[data-tfp-billing-consent]');
        var attempts = 0;
        var started = false;

        function setStatus(message, state) {
            if (!status) return;
            status.textContent = message || '';
            if (state) status.setAttribute('data-state', state);
            else status.removeAttribute('data-state');
        }

        function persistCardholderName(name) {
            try {
                if (name) {
                    window.localStorage.setItem(CARDHOLDER_STORAGE_KEY, name);
                } else {
                    window.localStorage.removeItem(CARDHOLDER_STORAGE_KEY);
                }
            } catch (e) {
                // Storage can be unavailable in privacy-restricted browsers.
            }
        }

        function init() {
            if (started) return;

            if (!S.publishableKey) {
                setStatus('Secure card setup is unavailable because the Stripe publishable key is missing.', 'error');
                return;
            }

            if (typeof window.Stripe !== 'function') {
                attempts++;
                if (attempts < 50) return window.setTimeout(init, 100);
                setStatus('Secure card fields could not load. Please refresh and try again.', 'error');
                return;
            }

            started = true;

            var stripe;
            var elements;
            var cardNumber;
            var cardExpiry;
            var cardCvc;

            var elementStyle = {
                base: {
                    color: '#1F2933',
                    fontFamily: 'Arial, Helvetica, sans-serif',
                    fontSize: '17px',
                    fontSmoothing: 'antialiased',
                    lineHeight: '24px',
                    '::placeholder': {
                        color: '#8A9299'
                    }
                },
                invalid: { color: '#B3261E', iconColor: '#B3261E' }
            };

            try {
                stripe = window.Stripe(S.publishableKey);
                elements = stripe.elements();
                cardNumber = elements.create('cardNumber', { style: elementStyle, placeholder: '1234 1234 1234 1234' });
                cardExpiry = elements.create('cardExpiry', { style: elementStyle, placeholder: 'MM / YY' });
                cardCvc = elements.create('cardCvc', { style: elementStyle, placeholder: 'CVC' });

                cardNumber.mount(numberMount);
                cardExpiry.mount(expiryMount);
                cardCvc.mount(cvcMount);

                [cardNumber, cardExpiry, cardCvc].forEach(function (element) {
                    element.on('ready', function () {
                        var host = element === cardNumber ? numberMount : (element === cardExpiry ? expiryMount : cvcMount);
                        host.setAttribute('data-tfp-stripe-ready', '1');
                    });
                });
            } catch (e) {
                setStatus(e && e.message ? e.message : 'Secure card fields could not be initialized.', 'error');
                return;
            }

            [cardNumber, cardExpiry, cardCvc].forEach(function (element) {
                element.on('change', function (event) {
                    if (event && event.error) setStatus(event.error.message, 'error');
                    else setStatus('');

                    if (element === cardNumber && event && event.brand) {
                        renderVisualCard({ brand: event.brand });
                    }
                });
            });

            function post(action, data) {
                var body = new FormData();
                body.append('action', action);
                body.append('nonce', S.nonce || '');

                Object.keys(data || {}).forEach(function (key) {
                    body.append(key, data[key]);
                });

                return fetch(S.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body
                }).then(function (response) {
                    return response.json().catch(function () {
                        throw new Error('The server returned an invalid response. Please refresh and try again.');
                    });
                });
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();

                if (submit && submit.disabled) return;

                var cardholderName = nameInput ? nameInput.value.trim() : '';

                if (!cardholderName) {
                    setStatus('Please enter the name shown on the card.', 'error');
                    if (nameInput) nameInput.focus();
                    return;
                }

                if (consent && !consent.checked) {
                    setStatus('Please confirm that you authorize the payment before continuing.', 'error');
                    consent.focus();
                    return;
                }

                if (submit) submit.disabled = true;
                setStatus('Preparing secure card update...');

                post('tfp_stripe_create_setup_intent', {})
                    .then(function (response) {
                        if (!response || !response.success || !response.clientSecret) {
                            throw new Error((response && response.message) || 'Could not start secure card setup.');
                        }

                        return stripe.confirmCardSetup(response.clientSecret, {
                            payment_method: {
                                card: cardNumber,
                                billing_details: { name: cardholderName }
                            }
                        });
                    })
                    .then(function (result) {
                        if (result.error) throw new Error(result.error.message || 'Could not verify this card.');
                        if (!result.setupIntent || result.setupIntent.status !== 'succeeded') {
                            throw new Error('Card setup was not completed.');
                        }

                        setStatus('Saving payment method...');

                        return post('tfp_stripe_save_setup_intent', {
                            setup_intent_id: result.setupIntent.id,
                            cardholder_name: cardholderName
                        });
                    })
                    .then(function (response) {
                        if (!response || !response.success) {
                            throw new Error((response && response.message) || 'Could not save your payment method.');
                        }

                        var brand = document.querySelector('[data-tfp-card-brand]');
                        var expiry = document.querySelector('[data-tfp-card-expiry]');
                        var noCard = document.querySelector('[data-tfp-no-card]');

                        persistCardholderName(cardholderName);

                        if (response.card) {
                            if (brand) brand.textContent = response.card.brand + ' ending in ' + response.card.last4;
                            if (expiry) expiry.textContent = response.card.expiry;
                            if (noCard) noCard.textContent = response.card.brand + ' ending in ' + response.card.last4;

                            renderVisualCard({
                                brand: response.card.brand,
                                last4: response.card.last4,
                                expiry: response.card.expiry,
                                name: cardholderName
                            });
                        } else {
                            renderVisualCard({ name: cardholderName });
                        }

                        cardNumber.clear();
                        cardExpiry.clear();
                        cardCvc.clear();
                        setStatus(response.message || 'Payment method saved successfully.', 'success');
                    })
                    .catch(function (error) {
                        setStatus(error && error.message ? error.message : 'A secure payment error occurred. Please try again.', 'error');
                    })
                    .finally(function () {
                        if (submit) submit.disabled = false;
                    });
            });
        }

        init();
    });
})();
