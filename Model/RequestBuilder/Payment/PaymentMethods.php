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
        if ($authorize_data && is_array($authorize_data)) {
            $cards_data = array_values($authorize_data);
            if ($cards_data && $cards_data[0]) {
                $card_data = $cards_data[0];
                if (isset($card_data['cc_type'])) {
                    $detailsArray['cardBrand'] = $card_data['cc_type'];
                }
                if (isset($card_data['cc_last4'])) {
                    $detailsArray['lastFourDigits'] = $payment->decrypt($card_data['cc_last4']);
                    $detailsArray['lastFourDigits'] = isset($detailsArray['lastFourDigits']) ? $detailsArray['lastFourDigits'] : $payment->decrypt($payment->getCcLast4());
                }
                if (isset($card_data['cc_response_code'])) {
                    $detailsArray['cvvResult'] = $card_data['cc_response_code'];
                }
                if (isset($card_data['cc_avs_result_code'])) {
                    $detailsArray['avsFullResult'] = $card_data['cc_avs_result_code'];
                }
            }
        }

        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function getBraintreeDetails($payment)
    {
        $detailsArray = [
        'cardBrand' => $payment->getAdditionalInformation('cc_type'),
        'cvvResult' => $payment->getAdditionalInformation('cvvResponseCode'),
        'avsZipResult' => $payment->getAdditionalInformation('avsPostalCodeResponseCode'),
        'avsStreetResult' => $payment->getAdditionalInformation('avsStreetAddressResponseCode')
      ];

        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function preferCcDetails($payment, $detailsArray=[])
    {
        $authorizationCode = array_key_exists("authorizationCode", $detailsArray) ? $detailsArray['authorizationCode'] : (
            $payment->getCcApproval() != null ? $payment->getCcApproval() : $payment->getAdditionalInformation("processorAuthorizationCode")
        );

        return [
            "nameOnCard" => array_key_exists("nameOnCard", $detailsArray) ? $detailsArray['nameOnCard'] : $payment->getCcOwner() . "",
            "cardBrand" => array_key_exists("cardBrand", $detailsArray) ? $detailsArray['cardBrand'] : $payment->getCcType(),
            "bin" => $payment->getAdditionalInformation("bin"),
            "lastFourDigits" => array_key_exists("lastFourDigits", $detailsArray) ? $detailsArray['lastFourDigits'] : $payment->getCcLast4(),
            "expirationMonth" => array_key_exists("expirationMonth", $detailsArray) ? $detailsArray['expirationMonth'] : str_pad($payment->getCcExpMonth(), 2, "0", STR_PAD_LEFT),
            "expirationYear" => array_key_exists("expirationYear", $detailsArray) ? $detailsArray['expirationYear'] : str_pad($payment->getCcExpYear(), 4, "20", STR_PAD_LEFT),
            "countryOfIssuance" => $payment->getData("country_of_issuance"),
            "cardBank" => $payment->getEcheckBankName(),
            "verificationResults" => [
                "cvvResult" => array_key_exists("cvvResult", $detailsArray) ? $detailsArray['cvvResult'] : $payment->getCcCidStatus(),
                "authorizationCode" => $authorizationCode,
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
    }
}
