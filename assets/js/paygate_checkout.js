jQuery(function() {
    jQuery.ajaxSetup({
        complete: function(xhr, textStatus) {
            var result = JSON.parse(xhr.responseText);
            if (result.VERSION == 21) {
                jQuery('.woocommerce-error').remove();
                initPayPopup(result);
                return false;
            }
            return;
        }
    });
    jQuery(document).ajaxComplete(function() {
        if (jQuery('body').hasClass('woocommerce-checkout') || jQuery('body').hasClass('woocommerce-cart')) {
            jQuery('html, body').stop();
        }
    });
});

function initPayPopup(result) {
    jQuery("body").append("<div id='payPopup'></div>");
    jQuery("#payPopup").append("<div id='payPopupContent'></div>");
    var xy = '';
    for (var key in result) {
        if (result.hasOwnProperty(key)) {

            xy += "<input type='hidden' name='" +key+ "' value='" + result[key] + "'>"; 
        }
    }
    jQuery("#payPopupContent").append("<form target='myIframe' name='paygate_checkout' id='paygate_checkout' action='https://www.paygate.co.za/paysubs/process.trans' method='post'>" + xy + "</form><iframe style='width:100%;height:100%;' id='payPopupFrame' name='myIframe'  src='#' ></iframe><script type='text/javascript'>document.getElementById('paygate_checkout').submit();</script>");
}
jQuery(document).on('submit', 'form#order_review', function(e) {
    jQuery("#place_order").attr("disabled", "disabled");
    var contine = true;
    if( jQuery('#terms').length ){
        if( !jQuery("#terms").is(":checked") == true) {
            contine = false;
        };
    }
    if ( contine && jQuery('#payment_method_paygate').length && jQuery("#payment_method_paygate").is(":checked") == true ) {
        e.preventDefault();
        jQuery.ajax({
            'url': wc_add_to_cart_params.ajax_url,
            'type': 'POST',
            'dataType': 'json',
            'data': {
                'action': 'order_pay_payment',
                'order_id': paygate_checkout_js.order_id
            },
            'async': false
        }).complete(function(result) {
            var result = JSON.parse(result);
            initPayPopup(result);
        });
    }
});