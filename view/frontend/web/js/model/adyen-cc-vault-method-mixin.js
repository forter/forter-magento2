define([
    'jquery',
    'mage/utils/wrapper'
], function ($, wrapper) {
    'use strict';

    return function (target) {
        return target.extend({
            getData: function () {
                var data = this._super();

                // Ensure additional_data exists
                if (!data.additional_data) {
                    data.additional_data = {};
                }

                // Check if methods exist before calling them
                if (typeof this.getExpirationDate === 'function') {
                    data.additional_data.expiryDate = this.getExpirationDate();
                }
                if (typeof this.getMaskedCard === 'function') {
                    data.additional_data.cardSummary = this.getMaskedCard();
                }
                if (typeof this.getCardType === 'function') {
                    data.additional_data.cardBrand = this.getCardType();
                }
                return data;
            }
        });
    };
});
