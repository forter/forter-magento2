<?php

namespace Forter\Forter\Model\Mappers;

class AdyenMapper
{

    public function getAdyenDetails($payment)
    {
        $additonal_data = $payment->getAdditionalInformation('additionalData');
        $detailsArray = [];
        if ($additonal_data) {
            if (isset($additonal_data['expiryDate'])) {
                $cardDate = explode("/", $additonal_data['expiryDate'] ?? '');
                $detailsArray['expirationMonth'] = strlen($cardDate[0]) > 1 ? $cardDate[0] : '0' . $cardDate[0];
                $detailsArray['expirationYear'] = $cardDate[1];
            }
            if (isset($additonal_data['authCode'])) {
                $detailsArray['verificationResults']['authorizationCode'] = $additonal_data['authCode'];
                $detailsArray['verificationResults']['processorResponseCode'] = $additonal_data['authCode'];
            }
            if (isset($additonal_data['cardHolderName'])) {
                $detailsArray['nameOnCard'] = $additonal_data['cardHolderName'];
            }
            if (isset($additonal_data['paymentMethod'])) {
                $detailsArray['cardBrand'] = $additonal_data['paymentMethod'];
            }
            if (isset($additonal_data['cardBin'])) {
                $detailsArray['bin'] = $additonal_data['cardBin'];
            }
            if (isset($additonal_data['cardIssuingCountry'])) {
                $detailsArray['countryOfIssuance'] = $additonal_data['cardIssuingCountry'];
            }
            if (isset($additonal_data['fundingSource'])) {
                $detailsArray['cardType'] = $additonal_data['fundingSource'];
            }
            if (isset($additonal_data['cardSummary'])) {
                $detailsArray['lastFourDigits'] = $additonal_data['cardSummary'];
            }
            if (isset($additonal_data['avsResultRaw'])) {
                $detailsArray['verificationResults']['avsFullResult'] = $additonal_data['avsResultRaw'];
            }
            if (isset($additonal_data['cvcResultRaw'])) {
                $detailsArray['verificationResults']['cvvResult'] = $additonal_data['cvcResultRaw'];
            }
            if (isset($additonal_data['refusalReasonRaw'])) {
                $detailsArray['verificationResults']['processorResponseText'] = $additonal_data['refusalReasonRaw'];
                $detailsArray['verificationResults']['processorResponseCode'] = $additonal_data['refusalReasonRaw'];
            }
            if (isset($additonal_data['eci'])) {
                $detailsArray['verificationResults']['eciValue']= $additonal_data['eci'] === 'N/A' ? '' : $additonal_data['eci'];
            }
            if (isset($additonal_data['threeds2.threeDS2Result.eci'])) {
                $detailsArray['verificationResults']['eciValue']= $additonal_data['threeds2.threeDS2Result.eci'] === 'N/A' ? '' : $additonal_data['threeds2.threeDS2Result.eci'];
            }
            //3DS mapping
            if (isset($additonal_data['liabilityShift'])) {
                $detailsArray['verificationResults']['liabilityShift'] = $additonal_data['liabilityShift'] === 'true' ? true : false;
            }
            if (isset($additonal_data['threeDAuthenticated'])) {
                $detailsArray['verificationResults']['authorizationProcessedWith3DS'] = $additonal_data['threeDAuthenticated'] === 'true' ? true : false;
            }
            if (isset($additonal_data['threeDOffered'])) {
                $detailsArray['verificationResults']['authenticationTriggered'] = $additonal_data['threeDOffered'] === 'true' ? true : false;
            }
            if (isset($additonal_data['threeDAuthenticatedResponse'])) {
                $detailsArray['verificationResults']['threeDsStatusCode'] = $additonal_data['threeDAuthenticatedResponse'] !== 'N/A' ? $additonal_data['threeDAuthenticatedResponse'] : '';
            }
            if (isset($additonal_data['threeDSVersion'])) {
                $detailsArray['verificationResults']['threeDsVersion'] = $additonal_data['threeDSVersion'];
            }
            if (isset($additonal_data['challengeCancel'])) {
                $detailsArray['verificationResults']['threeDsChallengeCancelCode'] = $additonal_data['challengeCancel'];
            }
            if (isset($additonal_data['cavv'])) {
                $detailsArray['verificationResults']['cavvResult'] = $additonal_data['cavv'];
            }
            $detailsArray['fullResponsePayload'] = $additonal_data;
        }

        $ccType = $payment->getAdditionalInformation('cc_type');
        if ($ccType) {
            $detailsArray['cardBrand'] = $ccType;
        }

        $adyenExpiryDate = $payment->getAdditionalInformation('adyen_expiry_date');
        if ($adyenExpiryDate) {
            $date = explode("/", $adyenExpiryDate ?? '');
            $detailsArray['expirationMonth'] = $date[0];
            $detailsArray['expirationYear'] = $date[1];
        }

        $forter_cc_bin = $payment->getAdditionalInformation('adyen_card_bin');
        if ($forter_cc_bin) {
            $detailsArray['bin'] = $forter_cc_bin;
        }

        $authCode = $payment->getAdditionalInformation('adyen_auth_code');
        if ($authCode) {
            $detailsArray['verificationResults']['authorizationCode'] = $authCode;
            $detailsArray['verificationResults']['processorResponseCode']= $authCode;
        }

        $avsFullResult = $payment->getAdditionalInformation('adyen_avs_result');
        if ($avsFullResult) {
            $avsFullResult = (int) $avsFullResult;
            $detailsArray['verificationResults']['avsFullResult'] = strval($avsFullResult);
        }

        $cvcFullResult = $payment->getAdditionalInformation('adyen_cvc_result');
        if ($cvcFullResult) {
            $cvcFullResult = (int) $cvcFullResult;
            $detailsArray['verificationResults']['cvvResult'] = strval($cvcFullResult);
        }

        $processorResponseText = $payment->getAdditionalInformation('resultCode');
        if ($processorResponseText) {
            $detailsArray['verificationResults']['processorResponseText'] = $processorResponseText;
        }

        $processorResponseText = $payment->getAdditionalInformation('adyen_refusal_reason_raw');
        if ($processorResponseText) {
            $detailsArray['verificationResults']['processorResponseText'] = $processorResponseText;
            $detailsArray['verificationResults']['processorResponseCode'] = $processorResponseText;
        }

        return $detailsArray;
    }

    public function getAdyenGooglePayDetails($payment, $order)
    {
        $additonal_data = $payment->getAdditionalInformation('additionalData');
        $forterData = $payment->getAdditionalInformation('forterData');
        $detailsArray = [];
        if ($additonal_data || $forterData) {
            if (isset($forterData['paymentMethod']) && isset($forterData['paymentMethod']['googlePayCardNetwork'])) {
                $detailsArray['cardBrand'] = $forterData['paymentMethod']['googlePayCardNetwork'];
            }

            if (isset($forterData['paymentMethod']) && isset($forterData['paymentMethod']['googlePayToken'])) {
                $googlePayToken = json_decode($forterData['paymentMethod']['googlePayToken']);
                if (isset($googlePayToken->signature)) {
                    $detailsArray['token'] = $googlePayToken->signature;
                }
            }

            if (isset($forterData['checkoutAttemptId'])) {
                $detailsArray['fingerprint'] = $forterData['checkoutAttemptId'];
            }

            if (isset($forterData['paymentMethod']) && isset($forterData['paymentMethod']['checkoutAttemptId'])) {
                $detailsArray['fingerprint'] = $forterData['paymentMethod']['checkoutAttemptId'];
            }

            if (isset($additonal_data['cardHolderName'])) {
                $detailsArray['nameOnCard'] = $additonal_data['cardHolderName'];
            } else {
                $detailsArray['nameOnCard'] = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
            }

            if (isset($additonal_data['cardBin'])) {
                $detailsArray['bin'] = $additonal_data['cardBin'];
            }

            if (isset($additonal_data['cardIssuingCountry'])) {
                $detailsArray['countryOfIssuance'] = $additonal_data['cardIssuingCountry'];
            }

            if (isset($additonal_data['expiryDate'])) {
                $expiryDate = explode("/", $additonal_data['expiryDate']);

                $month = $expiryDate[0];
                if (strlen($month) == 1) {
                    $month = "0" . $month;
                }

                $detailsArray['expirationMonth']= $month;
                $detailsArray['expirationYear']= $expiryDate[1];
            }

            if (isset($additonal_data['cardSummary'])) {
                $detailsArray['lastFourDigits']= $additonal_data['cardSummary'];
            }

            if (isset($additonal_data['authCode'])) {
                $detailsArray['verificationResults']['authorizationCode']= $additonal_data['authCode'];
                $detailsArray['verificationResults']['processorResponseCode']= $additonal_data['authCode'];
            }

            if (isset($additonal_data['avsResultRaw'])) { //sau avsResult , are text mai mult
                $detailsArray['verificationResults']['avsFullResult']= $additonal_data['avsResultRaw'];
            }

            if (isset($additonal_data['cvcResultRaw'])) { // sau cvcResult
                $detailsArray['verificationResults']['cvvResult']= $additonal_data['cvcResult'][0];
            }

            if (isset($additonal_data['eci'])) {
                $detailsArray['verificationResults']['eciValue']= $additonal_data['eci'] === 'N/A' ? '' : $additonal_data['eci'];
            }

            if (isset($additonal_data['refusalReasonRaw'])) {
                $detailsArray['verificationResults']['processorResponseText']= $additonal_data['refusalReasonRaw'];
                $detailsArray['verificationResults']['processorResponseCode']= $additonal_data['refusalReasonRaw'];
            }

            if (isset($additonal_data['merchantReference'])) {
                $detailsArray['paymentGatewayData']['gatewayMerchantId']= $additonal_data['merchantReference'];
            }

            $detailsArray['cardType'] = 'CREDIT';
        }

        return $detailsArray;
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

}