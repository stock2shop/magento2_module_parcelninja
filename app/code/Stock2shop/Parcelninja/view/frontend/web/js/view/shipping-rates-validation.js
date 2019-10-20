define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules',
        '../model/shipping-rates-validator',
        '../model/shipping-rates-validation-rules'
    ],
    function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        parcelninjaShippingRatesValidator,
        parcelninjaShippingRatesValidationRules
    ) {
        'use strict';
        defaultShippingRatesValidator.registerValidator('parcelninja', parcelninjaShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('parcelninja', parcelninjaShippingRatesValidationRules);
        return Component;
    }
);
