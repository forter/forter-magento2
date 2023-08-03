define(function () {
    'use strict';
    var mixin = {
        /**
         *
         * @param {Column} elem
         */
        handleNonce: function (data) {
            if (
                data &&
                data.details &&
                data.details.bin &&
                data.details.lastFour
            ) {
                this.additionalData.cardBin = data.details.bin;
                this.additionalData.cardLast4 = data.details.lastFour;
            }

            this._super();
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
