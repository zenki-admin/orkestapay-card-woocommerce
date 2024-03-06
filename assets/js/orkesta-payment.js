jQuery(document).ready(async function () {
    var $form = jQuery('form.checkout');
    const orkesta = initOrkestaPay();
    console.log('orkesta.js is ready!', orkesta);

    // Orkesta params
    const apiUrl = orkestapay_card_payment_args.orkestapay_api_url;
    const pluginPaymentGatewayId = orkestapay_card_payment_args.plugin_payment_gateway_id;
    const merchantId = orkestapay_card_payment_args.merchant_id;
    const deviceKey = orkestapay_card_payment_args.device_key;
    const getAccessTokenUrl = orkestapay_card_payment_args.get_access_token_url;

    console.log('orkestapay_card_payment_args', orkestapay_card_payment_args);

    await setDeviceSessionId(orkesta, merchantId, deviceKey);

    jQuery('body').on('click', 'form.checkout button:submit', function () {
        jQuery('.woocommerce-error').remove();
        // Make sure there's not an old orkesta_payment_method_id on the form
        jQuery('form.checkout').find('[name=orkesta_customer_id]').remove();
        jQuery('form.checkout').find('[name=orkesta_payment_method_id]').remove();
    });

    // Bind to the checkout_place_order event to add the token
    jQuery('form.checkout').bind('checkout_place_order', function (e) {
        if (jQuery('input[name=payment_method]:checked').val() !== pluginPaymentGatewayId) {
            return true;
        }

        // Pass if we have a customer_id and payment_method_id
        if ($form.find('[name=orkesta_customer_id]').length && $form.find('[name=orkesta_payment_method_id]').length) {
            return true;
        }

        $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

        handleRequests();

        return false; // Prevent the form from submitting with the default action
    });

    async function handleRequests() {
        try {
            const accessTokenResponse = await doAjax(getAccessTokenUrl);
            const headers = { Authorization: `Bearer ${accessTokenResponse.data.access_token}` };
            const name = jQuery('#billing_first_name').val().trim();
            const lastName = jQuery('#billing_last_name').val().trim();
            const email = jQuery('#billing_email').val().trim();

            // Buscamos al customer por email
            const retrieveCustomer = await doAjax(`${apiUrl}/v1/customers?email=${email}&limit=1`, null, 'GET', headers);
            let orkestaCustomerId = retrieveCustomer.length ? retrieveCustomer[0].id : null;

            // Si el orkestaCustomerId es null, creamos el customer
            if (orkestaCustomerId === null) {
                const customerData = { name, lastName, email };
                const customer = await doAjax(`${apiUrl}/v1/customers`, customerData, 'POST', headers);
                orkestaCustomerId = customer.id;
            }

            $form.append('<input type="hidden" name="orkesta_customer_id" value="' + orkestaCustomerId + '" />');

            const expires = cardExpiryVal(jQuery('#orkesta-card-expiry').val());
            const cardNumber = jQuery('#orkesta-card-number').val();
            const holderName = jQuery('#orkesta-holder-name').val();
            const cvv = jQuery('#orkesta-card-cvc').val();

            const paymentMethodData = {
                type: 'CARD',
                card: {
                    holder_name: jQuery('#billing_first_name').val(),
                    holder_last_name: jQuery('#billing_last_name').val(),
                    number: cardNumber.replace(/ /g, ''),
                    expiration_month: expires['month'],
                    expiration_year: expires['year'],
                    cvv: parseInt(cvv),
                    one_time_use: true,
                },
            };

            const paymentMethod = await doAjax(`${apiUrl}/v1/customers/${orkestaCustomerId}/payment-methods`, paymentMethodData, 'POST', headers);

            $form.append('<input type="hidden" name="orkesta_payment_method_id" value="' + paymentMethod.id + '" />');

            $form.submit();
        } catch (err) {
            displayErrorMessage(err);
        }
    }
});

async function setDeviceSessionId(orkesta, merchantId, deviceKey) {
    const credentials = { merchant_id: merchantId, device_key: deviceKey };

    try {
        const { deviceSessionId } = await orkesta.getDeviceInfo(credentials);
        console.log('setDeviceSessionId', deviceSessionId);
        jQuery('#orkesta_device_session_id').val(deviceSessionId);
    } catch (err) {
        console.error('setDeviceSessionId', err);
    }
}

async function doAjax(url, data, method = 'POST', headers = {}) {
    let request = {
        url,
        type: method,
        contentType: 'application/json',
        headers,
    };

    if (data !== null) {
        request.data = JSON.stringify(data);
    }

    try {
        return await jQuery.ajax(request);
    } catch (error) {
        console.error('doAjax', error.responseJSON);

        throw new Error(error.responseJSON.message);
    }
}

function displayErrorMessage(error) {
    jQuery('form.checkout').unblock();
    jQuery('.woocommerce-error').remove();
    jQuery('form.checkout')
        .closest('div')
        .before(
            '<ul style="background-color: #e2401c; color: #fff; margin-bottom: 10px; margin-top: 10px; border-radius: 8px;" class="woocommerce_error woocommerce-error"><li> ' + error + ' </li></ul>'
        );
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
