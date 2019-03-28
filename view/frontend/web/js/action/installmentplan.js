/*browser:true*/
/*global define*/
define([
    'jquery',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'Magento_Checkout/js/model/full-screen-loader'
], function ($, urlBuilder, storage, fullScreenLoader) {
    'use strict';

    return function (calcType, calcValue, methodCode, paymentRenderer) {
        var serviceUrl;
        var request = {
            calcType: calcType,
            calcValue: calcValue,
            grandTotal: window.checkoutConfig.quoteData.grand_total,
            methodCode: methodCode
        };

        //if (!customer.isLoggedIn()) {
        //    serviceUrl = urlBuilder.createUrl('/guest-carts/:quoteId/ratepay-installmentPlan', {
        //        quoteId: quote.getQuoteId()
        //    });
        //} else {
            serviceUrl = urlBuilder.createUrl('/carts/mine/ratepay-installmentPlan', {});
        //}

        fullScreenLoader.startLoader();

        return storage.post(
            serviceUrl,
            JSON.stringify(request)
        ).done(
            function (response) {
                if (response.success === true) {
                    $('#' + methodCode + '_ResultContainer').html(response.installment_html);
                    $('#' + methodCode + '_ContentSwitch').show();
                    paymentRenderer.setIsInstallmentPlanSet(true);
                } else {
                    alert(response.errormessage);
                }
                fullScreenLoader.stopLoader();
            }
        ).fail(
            function (response) {
                alert('An error occured.');
                fullScreenLoader.stopLoader();
            }
        );
    };
});
