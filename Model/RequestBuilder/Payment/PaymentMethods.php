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

use Forter\Forter\Model\Config as ForterConfig;
use Magento\Customer\Model\Session;

/**
 * Class Payment
 * @package Forter\Forter\Model\RequestBuilder
 */
class PaymentMethods
{
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var ForterConfig
     */
    private $forterConfig;

    /**
     * @method __construct
     * @param  Session      $customerSession
     * @param  ForterConfig $forterConfig
     */
    public function __construct(
        Session $customerSession,
        ForterConfig $forterConfig
    ) {
        $this->customerSession = $customerSession;
        $this->forterConfig = $forterConfig;
    }

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

        $authResult = $payment->getAdditionalInformation('processorAuthorizationCode');
        if ($ccType) {
            $detailsArray['authCode'] = $authResult;
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

        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function getAdyenDetails($payment)
    {
        $detailsArray = [];

        $ccType = $payment->getAdditionalInformation('cc_type');
        if ($ccType) {
            $detailsArray['cardBrand'] = $ccType;
        }

        $adyenExpiryDate = $payment->getAdditionalInformation('adyen_expiry_date');
        if ($adyenExpiryDate) {
            $date = explode("/", $adyenExpiryDate);
            $detailsArray['expirationMonth'] = $date[0];
            $detailsArray['expirationYear'] = $date[1];
        }

        $forter_cc_bin = $payment->getAdditionalInformation('adyen_card_bin');
        if ($forter_cc_bin) {
            $detailsArray['bin'] = $forter_cc_bin;
        }

        $authCode = $payment->getAdditionalInformation('adyen_auth_code');
        if ($authCode) {
            $detailsArray['authCode'] = $authCode;
        }

        $avsFullResult = $payment->getAdditionalInformation('adyen_avs_result');
        if ($avsFullResult) {
            $avsFullResult = (int) $avsFullResult;
            $detailsArray['avsFullResult'] = strval($avsFullResult);
        }

        $cvcFullResult = $payment->getAdditionalInformation('adyen_cvc_result');
        if ($cvcFullResult) {
            $cvcFullResult = (int) $cvcFullResult;
            $detailsArray['cvvResult'] = strval($cvcFullResult);
        }

        $processorResponseText = $payment->getAdditionalInformation('adyen_refusal_reason_raw');
        if ($processorResponseText) {
            $detailsArray['processorResponseText'] = $processorResponseText;
        }

        return $this->preferCcDetails($payment, $detailsArray);
    }

    /**
     * Alias for ForterConfig::getVerificationResultsMapping()
     * @return string|null
     */
    private function getVerResMap($key)
    {
        return $this->forterConfig->getVerificationResultsMapping($key);
    }

    public function preferCcDetails($payment, $detailsArray = [])
    {
        if (array_key_exists("bin", $detailsArray)) {
            $binNumber = $detailsArray['bin'];
        } else {
            $binNumber = $this->customerSession->getForterBin() ? $this->customerSession->getForterBin() : $payment->getAdditionalInformation('bin');
        }

        if (array_key_exists("lastFourDigits", $detailsArray)) {
            $last4cc = $detailsArray['lastFourDigits'];
        } else {
            $last4cc = $this->customerSession->getForterLast4cc() ? $this->customerSession->getForterLast4cc() : $payment->getCcLast4();
        }

        $cardDetails =  [
            "nameOnCard" => array_key_exists("nameOnCard", $detailsArray) ? $detailsArray['nameOnCard'] : $payment->getCcOwner() . "",
            "cardBrand" => array_key_exists("cardBrand", $detailsArray) ? $detailsArray['cardBrand'] : $payment->getCcType() . "",
            "bin" => $binNumber,
            "countryOfIssuance" => array_key_exists('countryOfIssuance', $detailsArray) ? $detailsArray['countryOfIssuance'] : $payment->getAdditionalInformation('country_of_issuance'),
            "cardBank" => array_key_exists("cardBank", $detailsArray) ? $detailsArray['cardBank'] : $payment->getEcheckBankName(),
            "verificationResults" => [
                "authorizationCode" => ($_vrmKey = $this->getVerResMap('authorization_code')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("authCode", $detailsArray) ? $detailsArray['authCode'] : ""),
                "authorizationPolicy" => ($_vrmKey = $this->getVerResMap('authorization_policy')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("authorizationPolicy", $detailsArray) ? $detailsArray['authorizationPolicy'] : ""),
                "avsFullResult" => ($_vrmKey = $this->getVerResMap('avs_full_result')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("avsFullResult", $detailsArray) ? $detailsArray['avsFullResult'] : $payment->getCcAvsStatus() . ""),
                "avsNameResult" => ($_vrmKey = $this->getVerResMap('avs_name_result')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("avsNameResult", $detailsArray) ? $detailsArray['avsNameResult'] : ""),
                "avsStreetResult" => ($_vrmKey = $this->getVerResMap('avs_street_result')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("avsStreetResult", $detailsArray) ? $detailsArray['avsStreetResult'] : ""),
                "avsZipResult" => ($_vrmKey = $this->getVerResMap('avs_zip_result')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("avsZipResult", $detailsArray) ? $detailsArray['avsZipResult'] : ""),
                "cvvResult" => ($_vrmKey = $this->getVerResMap('cvv_result')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("cvvResult", $detailsArray) ? $detailsArray['cvvResult'] : $payment->getCcCidStatus() . ""),
                "cavvResult" => ($_vrmKey = $this->getVerResMap('cavv_result')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("cavvResult", $detailsArray) ? $detailsArray['cavvResult'] : $payment->getCcCidStatus() . ""),
                "eciValue" => ($_vrmKey = $this->getVerResMap('eci_value')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists("eciValue", $detailsArray) ? $detailsArray['eciValue'] : ""),
                "processorResponseCode" => ($_vrmKey = $this->getVerResMap('processor_response_code')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists('processorResponseCode', $detailsArray) ? $detailsArray['processorResponseCode'] : $payment->getAdditionalInformation("processorResponseCode")),
                "processorResponseText" => ($_vrmKey = $this->getVerResMap('processor_response_text')) ? $payment->getAdditionalInformation($_vrmKey) : (array_key_exists('processorResponseText', $detailsArray) ? $detailsArray['processorResponseText'] : $payment->getAdditionalInformation("processorResponseText")),
            ],
            "paymentGatewayData" => [
                "gatewayName" => $payment->getMethod() ? $payment->getMethod() : "",
                "gatewayTransactionId" => $payment->getCcTransId() ? $payment->getCcTransId() : "",
            ],
            "fullResponsePayload" => $payment->getAdditionalInformation() ? $payment->getAdditionalInformation() : ""
        ];

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
