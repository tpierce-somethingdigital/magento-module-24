define([
    'jquery',
    'jquery/jquery.cookie',
    'Magento_Customer/js/customer-data'
], function ($, cookie, customerData) {
    $(document).ready(function ($) {
        /**
         * Estimate cart totals before refresh on Checkout pages
         *
         * Prevent attempting to load Magento_Checkout models on every page
         *
         * @param {Object} e
         * @return void
         */
        function estimateTotals (e) {
            if (window.checkoutConfig) {
                require([
                    'Magento_Checkout/js/model/quote',
                    'Magento_Checkout/js/model/cart/totals-processor/default'
                ], function (quote, totalsDefaultProvider) {
                    var sections = ['cart'];
                    customerData.reload(sections, true);

                    if (e.target.closest("og-offer").getAttribute("location") === "cart") {
                        totalsDefaultProvider.estimateTotals(quote.shippingAddress());
                    }
                });
            }
        }

        $(document).on('click', 'og-optin-button', function (e) {
            e.preventDefault();
            var subscribedProduct = $(this).closest('og-offer').attr("product");
            $.cookie('product_subscribed_' + subscribedProduct, true);
            if (window.location.href.indexOf("checkout") > -1) {
                if (!window.isCustomerLoggedIn) {
                    location.href = window.BASE_URL + 'ordergroove/checksubscription/index';
                    return false;
                } else {
                    estimateTotals(e);
                }
            }
        });

        $(document).on('click', 'og-optout-button', function (e) {
            e.preventDefault();
            var unsubscribedProduct = $(this).closest('og-offer').attr("product");
            $.cookie('product_subscribed_' + unsubscribedProduct, false);
            if (window.location.href.indexOf("checkout") > -1) {
                estimateTotals(e);
            }
        });
    });
});
