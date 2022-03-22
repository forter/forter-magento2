<?php

namespace Forter\Forter\Model\Mapper\Paymenttypes;

class BrainTreePayment extends BasePayment
{
    public function __construct()
    {
    }

    public function process($order, $payment)
    {
        $paymentMapping = $this->mapper->payments->braintree;
        $detailsArray =[];

        $ccType = $payment->getAdditionalInformation($paymentMapping->cardType);
        if ($ccType) {
            $detailsArray['cardBrand'] = $ccType;
        }

        $authResult = $payment->getAdditionalInformation($paymentMapping->cardAuthorizationCode);
        if ($ccType) {
            $detailsArray['authorizationCode'] = $authResult;
        }

        $cvvResponseCode = $payment->getAdditionalInformation($paymentMapping->cardCvv);
        if ($cvvResponseCode) {
            $detailsArray['cvvResult'] = $cvvResponseCode;
        }

        $avsZipResult = $payment->getAdditionalInformation($paymentMapping->avsPostalCode);
        if ($avsZipResult) {
            $detailsArray['avsZipResult'] = $avsZipResult;
        }

        $avsStreetResult = $payment->getAdditionalInformation($paymentMapping->avsStreet);
        if ($avsStreetResult) {
            $detailsArray['avsStreetResult'] = $avsStreetResult;
        }

        // field below come from the plugin Plugin/Braintree/Gateway/Response/CardDetailsHandler.php up to Forter 2.0.8
        $forter_cc_bin = $payment->getAdditionalInformation($paymentMapping->bin);
        if ($forter_cc_bin) {
            $detailsArray['bin'] = $forter_cc_bin;
        }

        // field below come from the plugin Plugin/Braintree/Gateway/Response/CardDetailsHandler.php up to Forter 2.0.8
        $forter_cc_owner = $payment->getAdditionalInformation($paymentMapping->owner);
        if ($forter_cc_owner) {
            $detailsArray['nameOnCard'] = $forter_cc_owner;
        }

        // field below come from the plugin Plugin/Braintree/Gateway/Response/CardDetailsHandler.php up to Forter 2.0.8
        $forter_cc_country = $payment->getAdditionalInformation($paymentMapping->country);
        if ($forter_cc_country) {
            $detailsArray['countryOfIssuance'] = $forter_cc_country;
        }

        return $this->preferCcDetails($payment, $detailsArray);
    }
    public function installmentService($order, $payment)
    {
        return null;
    }
}
