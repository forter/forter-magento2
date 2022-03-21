<?php
namespace Forter\Forter\Model\Mapper\PaymentTypes;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\Mapper\Utils;

abstract class BasePayment implements IPaymentType {
    public function __construct() {}
    protected $mapper;
    protected $storeId;
    protected $orderId;
    protected Config $config;
    protected Utils $utilsMapping;
    public function setup(Config $config,Utils $utilsMapping) {
        $this->config = $config;
        $this->utilsMapping = $utilsMapping;
    }

    public function setMapper(\stdClass $mapper = null, $storeId=-1, $orderId =-1)
    {
        $this->storeId = $storeId;
        $this->orderId = $orderId;
        $this->mapper = $mapper;
    }
    /**
     * Get mapped forter field
     * @method getVerificationResultsField
     * @param  Payment  $payment
     * @param  string   $key
     * @param  array    $detailsArray
     * @param  string   $default
     * @return mixed
     */
    private function getVerificationResultsField($payment, $key, $detailsArray = [], $default = "")
    {
        if (($vrmKey = $this->forterConfig->getVerificationResultsMapping($payment->getMethod(), $key))) {
            if (($val = $payment->getData($vrmKey))) {
                return $val;
            } elseif (($val = $payment->getAdditionalInformation($vrmKey))) {
                return $val;
            } elseif (!empty($detailsArray[$vrmKey])) {
                return $detailsArray[$vrmKey];
            } elseif (!empty($detailsArray[$key])) {
                return $detailsArray[$key];
            }
        }
        return $default;
    }

    public function preferCcDetails($payment, $detailsArray = [])
    {
        $generalMapping =  $this->mapper->general;
        if (array_key_exists("bin", $detailsArray)) {
            $binNumber = $detailsArray['bin'];
        } else {
            $binNumber = $this->customerSession->getForterBin() ? $this->customerSession->getForterBin() : $payment->getAdditionalInformation($generalMapping->bin);
        }

        if (array_key_exists("lastFourDigits", $detailsArray)) {
            $last4cc = $detailsArray['lastFourDigits'];
        } else {
            $last4cc = $this->customerSession->getForterLast4cc() ? $this->customerSession->getForterLast4cc() : $payment->getCcLast4();
        }

        $detailsArray["avsFullResult"] = !empty($detailsArray["avsFullResult"]) ? $detailsArray["avsFullResult"] : $payment->getCcAvsStatus();
        $detailsArray["cvvResult"] = !empty($detailsArray["cvvResult"]) ? $detailsArray["cvvResult"] : $payment->getCcCidStatus();
        $detailsArray["cavvResult"] = !empty($detailsArray["cavvResult"]) ? $detailsArray["cavvResult"] : $payment->getCcCidStatus();

        $cardDetails =  [
            "nameOnCard" => array_key_exists("nameOnCard", $detailsArray) ? $detailsArray['nameOnCard'] : $payment->getCcOwner() . "",
            "cardBrand" => array_key_exists("cardBrand", $detailsArray) ? $detailsArray['cardBrand'] : $payment->getCcType() . "",
            "bin" => $binNumber,
            "countryOfIssuance" => array_key_exists('countryOfIssuance', $detailsArray) ? $detailsArray['countryOfIssuance'] : $payment->getAdditionalInformation($generalMapping->countryIssuance),
            "cardBank" => array_key_exists("cardBank", $detailsArray) ? $detailsArray['cardBank'] : $payment->getEcheckBankName(),
            "verificationResults" => [],
            "paymentGatewayData" => [
                "gatewayName" => $payment->getMethod() ? $payment->getMethod() : "",
                "gatewayTransactionId" => $payment->getCcTransId() ? $payment->getCcTransId() : "",
            ],
            "fullResponsePayload" => $payment->getAdditionalInformation() ? $payment->getAdditionalInformation() : ""
        ];

        foreach ($this->forterConfig->getVerificationResultsMethodFields($payment->getMethod()) as $field) {
            $cardDetails["verificationResults"][$field] = $this->getVerificationResultsField($payment, $field, $detailsArray);
        }

        if (array_key_exists("expirationMonth", $detailsArray) || $payment->getCcExpMonth()) {
            $cardDetails["expirationMonth"] = array_key_exists("expirationMonth", $detailsArray) ? $detailsArray['expirationMonth'] : str_pad($payment->getCcExpMonth(), 2, "0", STR_PAD_LEFT);
        }

        if (array_key_exists("expirationYear", $detailsArray) || $payment->getCcExpYear()) {
            $cardDetails["expirationYear"] = array_key_exists("expirationMonth", $detailsArray) ? $detailsArray['expirationYear'] : str_pad($payment->getCcExpYear(), 2, "0", STR_PAD_LEFT);
        }

        if (array_key_exists("lastFourDigits", $detailsArray) || $payment->getCcLast4() || $last4cc) {
            $cardDetails["lastFourDigits"] = $last4cc;
        }

        return $cardDetails;
    }
}
