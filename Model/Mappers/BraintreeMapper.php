<?php

namespace Forter\Forter\Model\Mappers;

class BraintreeMapper
{
    public function getBraintreeDetails($payment)
    {
        $detailsArray =[];

        $ccType = $payment->getAdditionalInformation('cc_type');
        if ($ccType) {
            $detailsArray['cardBrand'] = $ccType;
        }

        $authResult = $payment->getAdditionalInformation('processorAuthorizationCode');
        if ($ccType) {
            $detailsArray['authorizationCode'] = $authResult;
        }

        $cvvResponseCode = $payment->getAdditionalInformation('cvvResponseCode');
        if ($cvvResponseCode) {
            $detailsArray['cvvResult'] = $cvvResponseCode;
        }

        $avsZipResult = $payment->getAdditionalInformation('avsPostalCodeResponseCode');
        if ($avsZipResult) {
            $detailsArray['avsZipResult'] = $avsZipResult;
        }

        $avsStreetResult = $payment->getAdditionalInformation('avsStreetAddressResponseCode');
        if ($avsStreetResult) {
            $detailsArray['avsStreetResult'] = $avsStreetResult;
        }

        // field below come from the plugin Plugin/Braintree/Gateway/Response/CardDetailsHandler.php up to Forter 2.0.8
        $forter_cc_bin = $payment->getAdditionalInformation('forter_cc_bin');
        if ($forter_cc_bin) {
            $detailsArray['bin'] = $forter_cc_bin;
        }

        // field below come from the plugin Plugin/Braintree/Gateway/Response/CardDetailsHandler.php up to Forter 2.0.8
        $forter_cc_owner = $payment->getAdditionalInformation('forter_cc_owner');
        if ($forter_cc_owner) {
            $detailsArray['nameOnCard'] = $forter_cc_owner;
        }

        // field below come from the plugin Plugin/Braintree/Gateway/Response/CardDetailsHandler.php up to Forter 2.0.8
        $forter_cc_country = $payment->getAdditionalInformation('forter_cc_country');
        if ($forter_cc_country) {
            $detailsArray['countryOfIssuance'] = $forter_cc_country;
        }

        $lastFourDigits = $payment->getAdditionalInformation('cc_last4');
        if ($lastFourDigits) {
            $detailsArray['lastFourDigits'] = $lastFourDigits;
        }

        return $detailsArray;
    }

}