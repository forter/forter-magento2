define(['jquery'],
        function ($) {
        'use strict';

    return function (target) {
        return target.extend({
                getData : function () {
                    var data = this._super();
                    data.additional_data.expiryDate = this.getExpirationDate();
                    data.additional_data.cardSummary = this.getMaskedCard();
                    data.additional_data.cardBrand = this.getCardType();
                    return data;
                }
        })
    };

});
