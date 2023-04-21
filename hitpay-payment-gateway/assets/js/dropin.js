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