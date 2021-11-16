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

    public function getPaybrightDetails($order, $payment)
    {
        return [
          "serviceName" => $payment->getMethod(),
          "firstName" => $order->getShippingAddress()->getFirstname(),
          "lastName" => $order->getShippingAddress()->getLastname(),
          "serviceResponseCode" => "200",
          "paymentId" => $payment->getData('last_trans_id'),
          "fullResponsePayload" => $payment->getAdditionalInformation()
        ];
    }

    public function getAdyenKlarnaDetails($order, $payment)
    {
        return [
        "serviceName" => $payment->getData('cc_type'),
        "firstName" => $order->getShippingAddress()->getFirstname(),
        "lastName" => $order->getShippingAddress()->getLastname(),
        "serviceResponseCode" => "200",
        "paymentId" => $payment->getData('cc_trans_id'),
        "fullResponsePayload" => $payment->getAdditionalInformation()
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
            $detailsArray['authorizationCode'] = $authCode;
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

        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function getAdyenDetails($payment)
    {
        $additonal_data = $payment->getAdditionalInformation('additionalData');
        $detailsArray = [];
        if ($additonal_data) {
            if ($additonal_data['expiryDate']) {
                $cardDate = explode("/", $additonal_data['expiryDate']);
                $detailsArray['expirationMonth'] = strlen($cardDate[0]) > 1 ? $cardDate[0] : '0' . $cardDate[0];
                $detailsArray['expirationYear'] = $cardDate[1];
            }
            if ($additonal_data['authCode']) {
                $detailsArray['authorizationCode'] = $additonal_data['authCode'];
            }
            if ($additonal_data['cardHolderName']) {
                $detailsArray['nameOnCard'] = $additonal_data['cardHolderName'];
            }
            if ($additonal_data['paymentMethod']) {
                $detailsArray['cardBrand'] = $additonal_data['paymentMethod'];
            }
            if ($additonal_data['cardBin']) {
                $detailsArray['bin'] = $additonal_data['cardBin'];
            }
            if ($additonal_data['cardSummary']) {
                $detailsArray['lastFourDigits'] = $additonal_data['cardSummary'];
            }
            if ($additonal_data['avsResultRaw']) {
                $detailsArray['avsFullResult'] = $additonal_data['avsResultRaw'];
            }
            if ($additonal_data['cvcResultRaw']) {
                $detailsArray['cvvResult'] = $additonal_data['cvcResultRaw'];
            }
            if ($additonal_data['refusalReasonRaw']) {
                $detailsArray['processorResponseText'] = $additonal_data['refusalReasonRaw'];
            }
            $detailsArray['fullResponsePayload'] = $additonal_data;
        }

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
            $detailsArray['authorizationCode'] = $authCode;
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
     * Get mapped forter field
     * @return mixed
     */

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

        $detailsArray["avsFullResult"] = !empty($detailsArray["avsFullResult"]) ? $detailsArray["avsFullResult"] : $payment->getCcAvsStatus();
        $detailsArray["cvvResult"] = !empty($detailsArray["cvvResult"]) ? $detailsArray["cvvResult"] : $payment->getCcCidStatus();
        $detailsArray["cavvResult"] = !empty($detailsArray["cavvResult"]) ? $detailsArray["cavvResult"] : $payment->getCcCidStatus();

        $cardDetails =  [
            "nameOnCard" => array_key_exists("nameOnCard", $detailsArray) ? $detailsArray['nameOnCard'] : $payment->getCcOwner() . "",
            "cardBrand" => array_key_exists("cardBrand", $detailsArray) ? $detailsArray['cardBrand'] : $payment->getCcType() . "",
            "bin" => $binNumber,
            "countryOfIssuance" => array_key_exists('countryOfIssuance', $detailsArray) ? $detailsArray['countryOfIssuance'] : $payment->getAdditionalInformation('country_of_issuance'),
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
