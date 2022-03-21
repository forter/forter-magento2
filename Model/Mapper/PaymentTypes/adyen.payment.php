<?php

namespace Forter\Forter\Model\Mapper\PaymentTypes;

class AdyenPayment extends BasePayment
{
    public function __construct()
    {
    }

    public function installmentService($order, $payment)
    {
        $paymentMapping = $this->mapper->payments->adyen->klarna;
        return [
            "serviceName" => $payment->getData($paymentMapping->serviceName),
            "firstName" => $order->getShippingAddress()->getFirstname(),
            "lastName" => $order->getShippingAddress()->getLastname(),
            "serviceResponseCode" => "200",
            "paymentId" => $payment->getData($paymentMapping->tranId),
            "fullResponsePayload" => $payment->getAdditionalInformation()
        ];
    }

    public function process($order, $payment)
    {
        $paymentMapping = $this->mapper->payments->adyen;
        $additonal_data = $payment->getAdditionalInformation('additionalData');
        $detailsArray = [];
        if ($additonal_data) {
            if (isset($additonal_data[$paymentMapping->cardDate])) {
                $cardDate = explode("/", $additonal_data[$paymentMapping->cardDate]);
                $detailsArray['expirationMonth'] = strlen($cardDate[0]) > 1 ? $cardDate[0] : '0' . $cardDate[0];
                $detailsArray['expirationYear'] = $cardDate[1];
            }
            if (isset($additonal_data[$paymentMapping->cardAuthorizationCode1])) {
                $detailsArray['authorizationCode'] = $additonal_data[$paymentMapping->cardAuthorizationCode1];
            }
            if (isset($additonal_data[$paymentMapping->cardName])) {
                $detailsArray['nameOnCard'] = $additonal_data[$paymentMapping->cardName];
            }
            if (isset($additonal_data[$paymentMapping->paymentMethod])) {
                $detailsArray['cardBrand'] = $additonal_data[$paymentMapping->paymentMethod];
            }
            if (isset($additonal_data[$paymentMapping->])) {
                $detailsArray['bin'] = $additonal_data[$paymentMapping->bin1];
            }
            if (isset($additonal_data[$paymentMapping->])) {
                $detailsArray['lastFourDigits'] = $additonal_data[$paymentMapping->cardLast4];
            }
            if (isset($additonal_data[$paymentMapping->avsFullResult1])) {
                $detailsArray['avsFullResult'] = $additonal_data[$paymentMapping->avsFullResult1];
            }
            if (isset($additonal_data[$paymentMapping->cardCVV1])) {
                $detailsArray['cvvResult'] = $additonal_data[$paymentMapping->cardCVV1];
            }
            if (isset($additonal_data[$paymentMapping->processorResponses1])) {
                $detailsArray['processorResponseText'] = $additonal_data[$paymentMapping->processorResponses1];
            }
            $detailsArray['fullResponsePayload'] = $additonal_data;
        }

        $ccType = $payment->getAdditionalInformation($paymentMapping->cardType);
        if ($ccType) {
            $detailsArray['cardBrand'] = $ccType;
        }

        $adyenExpiryDate = $payment->getAdditionalInformation($paymentMapping->cardExpired);
        if ($adyenExpiryDate) {
            $date = explode("/", $adyenExpiryDate);
            $detailsArray['expirationMonth'] = $date[0];
            $detailsArray['expirationYear'] = $date[1];
        }

        $forter_cc_bin = $payment->getAdditionalInformation($paymentMapping->bin2);
        if ($forter_cc_bin) {
            $detailsArray['bin'] = $forter_cc_bin;
        }

        $authCode = $payment->getAdditionalInformation($paymentMapping->cardAuthorizationCode2);
        if ($authCode) {
            $detailsArray['authorizationCode'] = $authCode;
        }

        $avsFullResult = $payment->getAdditionalInformation($paymentMapping->avsFullResult2);
        if ($avsFullResult) {
            $avsFullResult = (int) $avsFullResult;
            $detailsArray['avsFullResult'] = strval($avsFullResult);
        }

        $cvcFullResult = $payment->getAdditionalInformation($paymentMapping->cardCVV2);
        if ($cvcFullResult) {
            $cvcFullResult = (int) $cvcFullResult;
            $detailsArray['cvvResult'] = strval($cvcFullResult);
        }

        $processorResponseText = $payment->getAdditionalInformation($paymentMapping->processorResponses2);
        if ($processorResponseText) {
            $detailsArray['processorResponseText'] = $processorResponseText;
        }

        return $this->preferCcDetails($payment, $detailsArray);
    }
}
