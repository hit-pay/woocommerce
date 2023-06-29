var hitpayPaymentId = '';
var hitpayRedirectUrl = '';

var interceptHitpayPlaceOrder = function( event, data ) {
    if (jQuery('#payment_method_hitpay').is(':checked')) {
        showHitpayCustomLayer();
        if (!window.HitPay.inited) {
            window.HitPay.init(data.redirect, {
              domain: data.domain,
              apiDomain: data.apiDomain,
            },
            {
              onClose: onHitpayDropInClose,
              onSuccess: onHitpayDropInSuccess,
              onError: onHitpayDropInError
            });
        }
        
        hitpayRedirectUrl = data.redirect_url;
        hitpayPaymentId = data.payment_request_id;

        window.HitPay.toggle({
            paymentRequest: data.payment_request_id,          
        });
        
        jQuery('.woocommerce-error').hide();
        return false;
    } else {
        return true;
    }
};

var interceptHitpayBlockPlaceOrder = function(processingResponse, emitResponse ) {
    if (jQuery('#radio-control-wc-payment-method-options-hitpay').is(':checked')) {
        
        jQuery('.wc-block-components-notice-banner__content').hide();
        jQuery('.wc-block-components-notice-banner').hide();
        
        showHitpayCustomLayer();
        
        if (!window.HitPay.inited) {
            window.HitPay.init(processingResponse.paymentDetails.redirect, {
              domain: processingResponse.paymentDetails.domain,
              apiDomain: processingResponse.paymentDetails.apiDomain,
            },
            {
              onClose: onHitpayBlockDropInClose,
              onSuccess: onHitpayBlockDropInSuccess,
              onError: onHitpayBlockDropInError
            });
        }
        
        hitpayRedirectUrl = processingResponse.paymentDetails.redirect_url;
        hitpayPaymentId = processingResponse.paymentDetails.payment_request_id;

        window.HitPay.toggle({
            paymentRequest: processingResponse.paymentDetails.payment_request_id,          
        });
        
        jQuery('.wc-block-components-notice-banner__content').hide();
        jQuery('.wc-block-components-notice-banner').hide();
                    
        return {
            type: emitResponse.responseTypes.ERROR,
            messageContext: emitResponse.noticeContexts.PAYMENTS,
            retry: true,
        };
    } else {
        return true;
    }
};

jQuery(function($){
    var checkout_form = $( 'form.woocommerce-checkout' );
    checkout_form.on( 'checkout_place_order_success', interceptHitpayPlaceOrder );
});

function onHitpayDropInSuccess (data) {
    location.href = hitpayRedirectUrl+'&reference='+hitpayPaymentId+'&status=completed'
}

function onHitpayDropInClose (data) {
    jQuery('.woocommerce-error').hide();
    hideHitpayCustomLayer();
}

function onHitpayDropInError (error) {
    jQuery('.woocommerce-error').hide();
    hideHitpayCustomLayer();
    wc_checkout_form.submit_error( '<div class="woocommerce-error">' + error + '</div>' );
}

function showHitpayCustomLayer() {
    jQuery('#hitpay_background_layer').show();
}
function hideHitpayCustomLayer() {
    jQuery('#hitpay_background_layer').hide();
}

function onHitpayBlockDropInSuccess (data) {
    jQuery('.wc-block-components-notice-banner__content').hide();
    jQuery('.wc-block-components-notice-banner').hide();
    location.href = hitpayRedirectUrl+'&reference='+hitpayPaymentId+'&status=completed'
}

function onHitpayBlockDropInClose (data) {
    jQuery('.wc-block-components-notice-banner__content').hide();
    jQuery('.wc-block-components-notice-banner').hide();
    hideHitpayCustomLayer();
}

function onHitpayBlockDropInError (error) {
    jQuery('.wc-block-components-notice-banner__content').show();
    jQuery('.wc-block-components-notice-banner').show();
    hideHitpayCustomLayer();
    jQuery('.wc-block-components-notice-banner__content').html(error);
}

