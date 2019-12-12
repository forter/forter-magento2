<?php
/**
* Forter Payments For Magento 2
* https://www.Forter.com/
*
* @category Forter
* @package  Forter_Forter
* @author   Girit-Interactive (https://www.girit-tech.com/)
*/
namespace Forter\Forter\Model\RequestBuilder;

class Payment
{
    public function generatePaymentInfo($order)
    {
        $billingAddress = $order->getBillingAddress();
        $payment = $order->getPayment();

        if (!$payment) {
            return [];
        }

        $paymentMethodInfo = $this->getSpecificPaymentMethodInfo($order);

        $paymentData = [];

        // If paypal:
        if (strpos($payment->getMethod(), 'paypal') !== false) {
            $paymentData["paypal"] = [
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
        } elseif ($paymentMethodInfo['cc_last4']) {
            $paymentData["creditCard"] = [
                "nameOnCard" => $paymentMethodInfo['cc_owner'],
                "cardBrand" => $paymentMethodInfo['credit_card_brand'],
                "bin" => $paymentMethodInfo['cc_bin'],
                "lastFourDigits" => $paymentMethodInfo['cc_last4'],
                "expirationMonth" => $paymentMethodInfo['cc_exp_month'],
                "expirationYear" => $paymentMethodInfo['cc_exp_year'],
                "countryOfIssuance" => $payment->getData("country_of_issuance"),
                "cardBank" => $payment->getEcheckBankName(),
                "verificationResults" => [
                    "cvvResult" => array_key_exists('cvv_result_code', $paymentMethodInfo) ? $paymentMethodInfo['cvv_result_code'] : null,
                    "authorizationCode" => $paymentMethodInfo['auth_code'],
                    "processorResponseCode" => $payment->getAdditionalInformation("processorResponseCode"),
                    "processorResponseText" => $payment->getAdditionalInformation("processorResponseText"),
                    "avsStreetResult" => array_key_exists('avs_street_code', $paymentMethodInfo) ? $paymentMethodInfo['avs_street_code'] : null,
                    "avsZipResult" => array_key_exists('avs_zip_code', $paymentMethodInfo) ? $paymentMethodInfo['avs_zip_code'] : null,
                    "avsFullResult" => array_key_exists('avs_result_code', $paymentMethodInfo) ? $paymentMethodInfo['avs_result_code'] : null
                ],
                "paymentGatewayData" => [
                    "gatewayName" => $payment->getMethod(),
                    "gatewayTransactionId" => $payment->getCcTransId(),
                ],
                "fullResponsePayload" => $payment->getAdditionalInformation()
            ];
        }

        $billingDetails = [];
        $billingDetails["personalDetails"] = [
          "firstName" => $billingAddress->getFirstName(),
          "lastName" => $billingAddress->getLastName()
      ];

        if ($billingAddress) {
            $billingDetails["address"] = $this->getAddressData($billingAddress);

            if ($billingAddress->getTelephone()) {
                $billingDetails["phone"] = [
                  [
                      "phone" => $billingAddress->getTelephone()
                  ]
              ];
            }
        }

        $paymentData["billingDetails"] = $billingDetails;
        $paymentData["paymentMethodNickname"] = $payment->getMethod();
        $paymentData["amount"] = [
          "amountLocalCurrency" => strval($order->getGrandTotal()),
          "currency" => $order->getOrderCurrency()->getCurrencyCode()
      ];

        return [$paymentData];
    }

    public function getSpecificPaymentMethodInfo($order)
    {
        $payment = $order->getPayment();
        if (!$payment) {
            return null;
        }

        $payment_method = $payment->getMethod();
        try {
            switch ($payment_method) {
                case 'authorizenet_directpost':
                    $authorize_data = $payment->getAdditionalInformation('authorize_cards');
                    if ($authorize_data && is_array($authorize_data)) {
                        $cards_data = array_values($authorize_data);
                        if ($cards_data && $cards_data[0]) {
                            $card_data = $cards_data[0];
                            if (isset($card_data['cc_type'])) {
                                $credit_card_brand = $card_data['cc_type'];
                            }
                            if (isset($card_data['cc_last4'])) {
                                $cc_last4 = $payment->decrypt($card_data['cc_last4']);
                            }
                            if (isset($card_data['cc_response_code'])) {
                                $cvv_result_code = $card_data['cc_response_code'];
                            }
                            if (isset($card_data['cc_avs_result_code'])) {
                                $avs_result_code = $card_data['cc_avs_result_code'];
                            }
                        }
                    }
                    break;
                    $cc_last4 = isset($cc_last4) ? $cc_last4 : $payment->decrypt($payment->getCcLast4());
                  //  return $this->ccInfo($payment, $cc_last4, $cvv_result_code, $avs_result_code, $credit_card_brand, null, null, null, null, null, null, null);
                case 'braintree':
                    $credit_card_brand = $payment->getAdditionalInformation('cc_type');
                    $cvv_result_code = $payment->getAdditionalInformation('cvvResponseCode');
                    $zipVerification = $payment->getAdditionalInformation('avsPostalCodeResponseCode');
                    $streetVerification = $payment->getAdditionalInformation('avsStreetAddressResponseCode');
                    break;
                //    return $this->ccInfo($payment, null, $cvv_result_code, null, $credit_card_brand, $zipVerification, $streetVerification, null, null, null, null, null);
                case 'paypal_direct':
                case 'paypaluk_direct':
                    $cc_last4 = $payment->getCcLast4();
                    $credit_card_brand = $payment->getCcType();
                    $avs_result_code = $payment->getAdditionalInformation('paypal_avs_code');
                    $cvv_result_code = $payment->getAdditionalInformation('paypal_cvv2_match');
                    break;
                //    return $this->ccInfo($payment, $cc_last4, $cvv_result_code, $avs_result_code, $credit_card_brand, null, null, null, null, null, null, null);
              }
        } catch (\Exception $e) {
            $this->logger->error("Exception in getSpecificPaymentMethodInfo: ", $e, $order->getIncrementId());
        }
        return $this->ccInfo($payment, null, null, null, null, null, null, null, null, null, null, null);
    }

    public function ccInfo($payment, $cc_last4, $cvv_result_code, $avs_result_code, $credit_card_brand, $avs_zip_code, $avs_street_code, $cc_bin, $cc_owner, $cc_exp_month, $cc_exp_year, $auth_code)
    {
        $cc_last4 = $cc_last4 ? $cc_last4 : $payment->getCcLast4();
        $cvv_result_code = $cvv_result_code ? $cvv_result_code : $payment->getCcCidStatus();
        $avs_result_code = $avs_result_code ? $avs_result_code : $payment->getCcAvsStatus();
        $credit_card_brand = $credit_card_brand ? $credit_card_brand : $payment->getCcType();
        $cc_bin = $cc_bin ? $cc_bin : $this->getBinNumber($payment);
        $cc_owner = $cc_owner ? $cc_owner : $payment->getCcOwner() . "";
        $cc_exp_month = $cc_exp_month ? $cc_exp_month : str_pad($payment->getCcExpMonth(), 2, "0", STR_PAD_LEFT);
        $cc_exp_year = $cc_exp_year ? $cc_exp_year : str_pad($payment->getCcExpYear(), 4, "20", STR_PAD_LEFT);
        $auth_code = $auth_code ? $auth_code : ($payment->getCcApproval() != null ? $payment->getCcApproval() : $payment->getAdditionalInformation("processorAuthorizationCode"));

        return [
            'avs_result_code' => $avs_result_code,
            'cvv_result_code' => $cvv_result_code,
            'cc_last4' => $cc_last4,
            'credit_card_brand' => $credit_card_brand,
            'avs_zip_code' => $avs_zip_code,
            'avs_street_code' => $avs_street_code,
            'cc_bin' => $cc_bin,
            'cc_owner' => $cc_owner,
            'cc_exp_month' => $cc_exp_month,
            'cc_exp_year' => $cc_exp_year,
            'auth_code' => $auth_code
        ];
    }
}
