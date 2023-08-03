define(
    [
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/model/adyen-checkout',
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/model/installments',
    ],
    function (adyenConfiguration, adyenCheckout, quote, installmentsHelper) {
    'use strict';
    var binValue;
    var cardLast4;
    var mixin = {
        //Only Adyen over 8.x.x version supported
        renderCCPaymentMethod: function () {
             if (window.checkoutConfig.forter.isAdyenVersionGreaterOrEqual) {
                let self = this;
                if (!self.getClientKey) {
                    return false;
                }

                self.installments(0);

                let allInstallments = self.getAllInstallments();

                let componentConfig = {
                    enableStoreDetails: self.getEnableStoreDetails(),
                    brands: self.getBrands(),
                    hasHolderName: adyenConfiguration.getHasHolderName(),
                    holderNameRequired: adyenConfiguration.getHasHolderName() &&
                        adyenConfiguration.getHolderNameRequired(),
                    onChange: function(state, component) {
                        self.placeOrderAllowed(!!state.isValid);
                        self.storeCc = !!state.data.storePaymentMethod;
                    },

                    onBrand: function(state) {

                        let creditCardType = self.getCcCodeByAltCode(
                            state.brand);
                        if (creditCardType) {
                            if (!self.creditCardType() ||
                                self.creditCardType() &&
                                self.creditCardType() != creditCardType) {
                                let numberOfInstallments = [];

                                if (creditCardType in allInstallments) {
                                    let cardInstallments = allInstallments[creditCardType];
                                    let grandTotal = self.grandTotal();
                                    let precision = quote.getPriceFormat().precision;
                                    let currencyCode = quote.totals().quote_currency_code;

                                    numberOfInstallments = installmentsHelper.getInstallmentsWithPrices(
                                        cardInstallments, grandTotal,
                                        precision, currencyCode);
                                }

                                if (numberOfInstallments) {
                                    self.installments(numberOfInstallments);
                                } else {
                                    self.installments(0);
                                }
                            }

                            self.creditCardType(creditCardType);
                        } else {
                            self.creditCardType('');
                            self.installments(0);
                        }
                    },
                    onBinValue: function (binData) {
                        if (binData.binValue.length == 6) {
                            binValue = binData.binValue;
                        }
                    },
                    onFieldValid: function (onFieldValid) {
                        if (onFieldValid.fieldType === 'encryptedCardNumber') {
                            cardLast4 = onFieldValid.endDigits;
                        }
                    }
                }

                self.cardComponent = adyenCheckout.mountPaymentMethodComponent(
                    this.checkoutComponent,
                    'card',
                    componentConfig,
                    '#cardContainer'
                )

                return true
            } else {
                var self = this;
                if (!self.getClientKey) {
                    return false;
                }

                self.installments(0);

                let allInstallments = self.getAllInstallments();

                let componentConfig = {
                    enableStoreDetails: self.getEnableStoreDetails(),
                    brands: self.getAvailableCardTypeAltCodes(),
                    hasHolderName: adyenConfiguration.getHasHolderName(),
                    holderNameRequired: adyenConfiguration.getHasHolderName() &&
                        adyenConfiguration.getHolderNameRequired(),
                    onChange: function(state, component) {
                        self.placeOrderAllowed(!!state.isValid);
                        self.storeCc = !!state.data.storePaymentMethod;
                    },
                    onBrand: function(state) {
                        var creditCardType = self.getCcCodeByAltCode(
                            state.brand);
                        if (creditCardType) {
                            // If the credit card type is already set, check if it changed or not
                            if (!self.creditCardType() ||
                                self.creditCardType() &&
                                self.creditCardType() != creditCardType) {
                                var numberOfInstallments = [];

                                if (creditCardType in allInstallments) {
                                    // get for the creditcard the installments
                                    var installmentCreditcard = allInstallments[creditCardType];
                                    var grandTotal = self.grandTotal();
                                    var precision = quote.getPriceFormat().precision;
                                    var currencyCode = quote.totals().quote_currency_code;

                                    numberOfInstallments = installmentsHelper.getInstallmentsWithPrices(
                                        installmentCreditcard, grandTotal,
                                        precision, currencyCode);
                                }

                                if (numberOfInstallments) {
                                    self.installments(numberOfInstallments);
                                } else {
                                    self.installments(0);
                                }
                            }

                            self.creditCardType(creditCardType);
                        } else {
                            self.creditCardType('');
                            self.installments(0);
                        }
                    },
                    onBinValue: function (binData) {
                        if (binData.binValue.length == 6) {
                            binValue = binData.binValue;
                        }
                    },
                    onFieldValid: function (onFieldValid) {
                        if (onFieldValid.fieldType === 'encryptedCardNumber') {
                            cardLast4 = onFieldValid.endDigits;
                        }
                    }
                }

                self.cardComponent = adyenCheckout.mountPaymentMethodComponent(
                    this.checkoutComponent,
                    'card',
                    componentConfig,
                    '#cardContainer'
                )

                return true
            }
        },
        getData: function (key) {
            var returnInformation = this._super();
            returnInformation.additional_data.cardBin = binValue;
            returnInformation.additional_data.cardLast4 = cardLast4;
            return returnInformation;
        }
    };
    if (window.checkoutConfig.forter.forterPreAuth === false || window.checkoutConfig.forter.isAdyenVersionGreaterOrEqual === false) {
        mixin = {};
    }
    return function (target) {
        return target.extend(mixin);
    };
});
