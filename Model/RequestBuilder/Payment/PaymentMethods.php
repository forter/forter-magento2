<?php
/**
 * Forter Payments For Magento 2
 * https://www.Forter.com/
 *
 * @category Forter
 * @package  Forter_Forter
 * @author   Girit-Interactive (https://www.girit-tech.com/)
 */

namespace Forter\Forter\Model\RequestBuilder\Payment;

/**
 * Class Payment
 * @package Forter\Forter\Model\RequestBuilder
 */
class PaymentMethods
{
    public function getPaypalDetails($payment)
    {
        return [
          "payerId" => $payment->getAdditionalInformation("paypal_payer_id"),
          "payerEmail" => $payment->getAdditionalInformation("paypal_payer_email"),
          "payerStatus" => $payment->getAdditionalInformation("paypal_payer_status"),
          "payerAddressStatus" => $payment->getAdditionalInformation("paypal_address_status"),
          "paymentMethod" => $payment->getMethod(),
          "paymentStatus" => $payment->getAdditionalInformation("paypal_payment_status"),
          "protectionEligibility" => $payment->getAdditionalInformation("paypal_protection_eligibility"),
          "correlationId" => $payment->getAdditionalInformation("paypal_correlation_id"),
          "checkoutToken" => $payment->getAdditionalInformation("paypal_express_checkout_token"),
          "paymentGatewayData" => [
              "gatewayName" => $payment->getMethod(),
              "gatewayTransactionId" => $payment->getTransactionId(),
          ],
          "fullPaypalResponsePayload" => $payment->getAdditionalInformation()
      ];
    }

    public function getAuthorizeNetDetails($payment)
    {
        $detailsArray = [];
        $authorize_data = $payment->getAdditionalInformation('authorize_cards');

        $ccLast4 = $payment->getAdditionalInformation('ccLast4');
        if ($ccLast4) {
            $detailsArray['lastFourDigits'] = $ccLast4;
        }

        $cc_type= $payment->getAdditionalInformation('accountType');
        if ($cc_type) {
            $detailsArray['cardBrand'] = $cc_type;
        }

        $cvvResponseCode = $payment->getAdditionalInformation('cvvResultCode');
        if ($cvvResponseCode) {
            $detailsArray['cvvResult'] = $cvvResponseCode;
        }

        $authCode = $payment->getAdditionalInformation('authCode');
        if ($authCode) {
            $detailsArray['authCode'] = $authCode;
        }

        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function getBraintreeDetails($payment)
    {
        $detailsArray =[];

        $ccType = $payment->getAdditionalInformation('cc_type');
        if ($ccType) {
            $detailsArray['cardBrand'] = $ccType;
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

        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function preferCcDetails($payment, $detailsArray=[])
    {
        $cardDetails = [];

        $cardDetails =  [
            "nameOnCard" => array_key_exists("nameOnCard", $detailsArray) ? $detailsArray['nameOnCard'] : $payment->getCcOwner() . "",
            "cardBrand" => array_key_exists("cardBrand", $detailsArray) ? $detailsArray['cardBrand'] : $payment->getCcType(),
            "bin" => $payment->getAdditionalInformation("bin"),
            "countryOfIssuance" => $payment->getData("country_of_issuance"),
            "cardBank" => $payment->getEcheckBankName(),
            "verificationResults" => [
                "cvvResult" => array_key_exists("cvvResult", $detailsArray) ? $detailsArray['cvvResult'] : $payment->getCcCidStatus(),
                "authorizationCode" => array_key_exists("authCode", $detailsArray) ? $detailsArray['authCode'] : null,
                "processorResponseCode" => $payment->getAdditionalInformation("processorResponseCode"),
                "processorResponseText" => $payment->getAdditionalInformation("processorResponseText"),
                "avsStreetResult" => array_key_exists("avsStreetResult", $detailsArray) ? $detailsArray['avsStreetResult'] : null,
                "avsZipResult" => array_key_exists("avsZipResult", $detailsArray) ? $detailsArray['avsZipResult'] : null,
                "avsFullResult" => array_key_exists("avsFullResult", $detailsArray) ? $detailsArray['avsFullResult'] : $payment->getCcAvsStatus()
            ],
            "paymentGatewayData" => [
                "gatewayName" => $payment->getMethod(),
                "gatewayTransactionId" => $payment->getCcTransId(),
            ],
            "fullResponsePayload" => $payment->getAdditionalInformation()
        ];

        if (array_key_exists("expirationMonth", $detailsArray) || $payment->getCcExpMonth()) {
            $cardDetails["expirationMonth"] = array_key_exists("expirationMonth", $detailsArray) ? $detailsArray['expirationMonth'] : str_pad($payment->getCcExpMonth(), 2, "0", STR_PAD_LEFT);
        }

        if (array_key_exists("expirationYear", $detailsArray) || $payment->getCcExpYear()) {
            $cardDetails["expirationYear"] = array_key_exists("expirationMonth", $detailsArray) ? $detailsArray['expirationYear'] : str_pad($payment->getCcExpYear(), 2, "0", STR_PAD_LEFT);
        }

        if (array_key_exists("lastFourDigits", $detailsArray) || $payment->getCcLast4()) {
            $cardDetails["lastFourDigits"] = array_key_exists("lastFourDigits", $detailsArray) ? $detailsArray['lastFourDigits'] : $payment->getCcLast4();
        }

        return $cardDetails;
    }
}
