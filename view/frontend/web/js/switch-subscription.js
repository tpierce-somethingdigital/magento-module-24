define([
    'jquery',
    'jquery/jquery.cookie',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/cart/totals-processor/default'
], function ($, cookie, customerData, quote, totalsDefaultProvider) {
    $(document).ready(function ($) {
        $(document).on('click', 'og-optin-button', function (e) {
            e.preventDefault();
            var subscribedProduct = $(this).closest('og-offer').attr("product");
            $.cookie('product_subscribed_' + subscribedProduct, true);
            if (window.location.href.indexOf("checkout") > -1) {
                if (!window.isCustomerLoggedIn) {
                    location.href = window.BASE_URL + 'ordergroove/checksubscription/index';
                    return false;
                } else {
                    var sections = ['cart'];
                    customerData.reload(sections, true);
                    
                    if (e.target.closest("og-offer").getAttribute("location") === "cart") {
                        totalsDefaultProvider.estimateTotals(quote.shippingAddress());
                    }
                }
            }
        });

        $(document).on('click', 'og-optout-button', function (e) {
            e.preventDefault();
            var unsubscribedProduct = $(this).closest('og-offer').attr("product");
            $.cookie('product_subscribed_' + unsubscribedProduct, false);
            if (window.location.href.indexOf("checkout") > -1) {
                var sections = ['cart'];
                
                customerData.reload(sections, true);
                if (e.target.closest("og-offer").getAttribute("location") === "cart") {
                    totalsDefaultProvider.estimateTotals(quote.shippingAddress());
                }
            }
        });
    });
});
