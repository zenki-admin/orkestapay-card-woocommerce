jQuery(document).ready(async function () {
    var $form = jQuery('form.checkout');

    // Orkesta params
    const plugin_payment_gateway_id = orkestapay_card_payment_args.plugin_payment_gateway_id;
    const merchant_id = orkestapay_card_payment_args.merchant_id;
    const public_key = orkestapay_card_payment_args.public_key;
    const is_sandbox = orkestapay_card_payment_args.is_sandbox === '1';
    const orkestapay_checkout_url = orkestapay_card_payment_args.orkestapay_checkout_url;
    const orkestapay_complete_3ds_payment_url = orkestapay_card_payment_args.orkestapay_complete_3ds_payment_url;
    // const promotions_params = { currency: orkestapay_card_payment_args.currency, total_amount: orkestapay_card_payment_args.total_amount };

    const orkestapay = initOrkestaPay({ merchant_id, public_key, is_sandbox });
    console.log('orkestapay.js is ready!', orkestapay);

    const orkestapay_card = await orkestapay.createCard();

    await setDeviceSessionId(orkestapay);

    jQuery('body').on('click', 'form.checkout button:submit', function () {
        jQuery('.woocommerce-error').remove();
        // Make sure there's not an old orkestapay_payment_method_id on the form
        jQuery('form.checkout').find('[name=orkestapay_payment_method_id]').remove();
    });

    // Bind to the checkout_place_order event to add the token
    jQuery('form.checkout').bind('checkout_place_order', function (e) {
        if (jQuery('input[name=payment_method]:checked').val() !== plugin_payment_gateway_id) {
            return true;
        }

        // Pass if we have a customer_id and payment_method_id
        if ($form.find('[name=orkestapay_payment_method_id]').length) {
            return true;
        }

        $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

        handleRequests();

        return false; // Prevent the form from submitting with the default action
    });

    async function handleRequests() {
        try {
            const card_number = document.getElementById('orkestapay-card-number');
            const holder_name = document.getElementById('orkestapay-holder-name');
            const verification_code = document.getElementById('orkestapay-card-cvc');
            const expiration_date = document.getElementById('orkestapay-card-expiry');
            const expiry = cardExpiryVal(expiration_date.value);

            const card = {
                card_number: card_number.value,
                expiration_date: { expiration_month: expiry['month'], expiration_year: expiry['year'] },
                verification_code: verification_code.value,
                holder_name: holder_name.value,
            };

            const payment_method = await orkestapay_card.createToken({
                card,
                one_time_use: true,
            });

            $form.append('<input type="hidden" name="orkestapay_payment_method_id" value="' + payment_method.payment_method_id + '" />');

            paymentRequest();
        } catch (err) {
            logError(handleRequests.name, err);
            displayErrorMessage(err);
        }
    }

    function paymentRequest() {
        jQuery.ajax({
            type: 'POST',
            url: orkestapay_checkout_url,
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            enctype: 'multipart/form-data',
            data: $form.serializeArray(),
            success: async function (response) {
                const { data } = response;

                $form.append('<input type="hidden" name="orkestapay_payment_id" value="' + data.payment_id + '" />');
                $form.append('<input type="hidden" name="orkestapay_order_id" value="' + data.order_id + '" />');

                // If the payment is completed, submit the form
                if (data.status === 'COMPLETED') {
                    $form.submit();
                }

                // If the payment is failed or rejected, display the error message
                if (data.status === 'FAILED' || data.status === 'REJECTED') {
                    displayErrorMessage(data.message);
                    jQuery('form.checkout').unblock();
                    return;
                }

                // Start the 3DSecure Global
                if (data.status === 'PAYMENT_ACTION_REQUIRED' && data.user_action_required.type === 'THREE_D_SECURE_AUTHENTICATION') {
                    const result = await orkestapay.startModal3DSecure({
                        merchant_provider_id: data.user_action_required.three_d_secure_authentication.merchant_provider_id,
                        payment_id: data.payment_id,
                        order_id: data.order_id,
                    });

                    console.log('startModal3DSecure', result);

                    if (result) {
                        complete3DSPayment({ orkestapay_payment_id: data.payment_id });
                    }
                }

                // Redirect to the 3DSecure page
                if (data.status === 'PAYMENT_ACTION_REQUIRED' && data.user_action_required.type === 'THREE_D_SECURE_SPECIFIC') {
                    window.location.href = data.user_action_required.three_d_secure_specific.three_ds_redirect_url;
                    return;
                }
            },
            error: function (error) {
                console.error('paymentRequest error', error.responseJSON); // For testing (to be removed)
                displayErrorMessage(error.responseJSON.data.message);
            },
        });
    }

    function complete3DSPayment(data) {
        jQuery.ajax({
            type: 'POST',
            url: orkestapay_complete_3ds_payment_url,
            contentType: 'application/json; charset=UTF-8',
            data: JSON.stringify(data),
            success: async function (response) {
                const { data } = response;

                if (data.status !== 'SUCCESS') {
                    displayErrorMessage(data.message);
                    jQuery('form.checkout').unblock();

                    return;
                }

                // If the payment is completed, submit the form
                $form.submit();
            },
            error: function (error) {
                console.error('complete3DSPayment error', error.responseJSON); // For testing (to be removed)
                displayErrorMessage(error.responseJSON.data.message);
            },
        });
    }
});

async function setDeviceSessionId(orkestapay) {
    try {
        const { device_session_id } = await orkestapay.getDeviceInfo();
        console.log('setDeviceSessionId', device_session_id);
        jQuery('#orkestapay_device_session_id').val(device_session_id);
    } catch (err) {
        console.error('setDeviceSessionId', err);
    }
}

function handlePromotionChanges(orkestapay_card) {
    console.log('handlePromotionChanges', orkestapay_card);
    const card_promotions = document.getElementById('orkestapay-installments');

    orkestapay_card.card_number.promotions$.subscribe((promotions) => {
        card_promotions.replaceChildren();

        const option = document.createElement('option');
        option.value = null;
        option.textContent = 'Select promotion';
        card_promotions.appendChild(option);

        for (const type of promotions) {
            for (const promotion of type.installments) {
                const option = document.createElement('option');
                option.value = promotion;
                option.textContent = `${promotion} ${type.type}`;
                card_promotions.appendChild(option);
            }
        }
    });
}

function displayErrorMessage(error) {
    jQuery('form.checkout').unblock();
    jQuery('.woocommerce-error').remove();
    jQuery('html, body').animate({ scrollTop: jQuery('.woocommerce-notices-wrapper').offset().top });
    jQuery('form.checkout')
        .closest('div')
        .before(
            '<ul style="background-color: #e2401c; color: #fff; margin-bottom: 10px; margin-top: 10px; border-radius: 8px;" class="woocommerce_error woocommerce-error"><li> ' + error + ' </li></ul>'
        );
}

function logError(origin, error) {
    error && console.error(origin, error.code, error);
}

function cardExpiryVal(value) {
    var month, prefix, year, _ref;
    value = value.replace(/\s/g, '');
    (_ref = value.split('/', 2)), (month = _ref[0]), (year = _ref[1]);
    if ((year != null ? year.length : void 0) === 2 && /^\d+$/.test(year)) {
        prefix = new Date().getFullYear();
        prefix = prefix.toString().slice(0, 2);
        year = prefix + year;
    }

    return {
        month: month,
        year: year,
    };
}
