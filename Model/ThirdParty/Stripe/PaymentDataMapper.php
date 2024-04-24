<?php

namespace Forter\Forter\Model\ThirdParty\Stripe;

class PaymentDataMapper
{
    protected $avsMap = [
        'pass_pass' => 'Y',
        'pass_fail' => 'A',
        'fail_pass' => 'Z',
        'fail_fail' => 'N',
        'pass_unchecked' => 'B',
        'unchecked_pass' => 'P',
        'unchecked_fail' => 'N',
        'fail_unchecked' => 'N',
        'unchecked_unchecked' => 'U'
    ];

    protected $cvvMap = [
        'pass' => 'M',
        'fail' => 'N',
        'unchecked' => 'P'
    ];

    public function dataMapper($payment, $detailsArray, $stripePayment)
    {
        if (!is_object($stripePayment)) {
            return $detailsArray;
        }

        if (isset($stripePayment->payment_method)) {
            $detailsArray['token'] = $stripePayment->payment_method;
        }

        if (isset($stripePayment->id)) {
            $detailsArray['paymentGatewayData']['gatewayTransactionId'] = $stripePayment->id;
        }

        $card = $stripePayment->payment_method_details->card ?? null;
        $checks = $card->checks ?? null;
        $threeDsStatus = $card->three_d_secure ?? null ;

        if ($checks) {
            $detailsArray['verificationResults']['avsZipResult'] = $checks->address_postal_code_check ?? '';
            $detailsArray['verificationResults']['avsStreetResult'] = $checks->address_line1_check ?? '';

            if (isset($detailsArray['verificationResults']['avsZipResult'], $detailsArray['verificationResults']['avsStreetResult'], $this->avsMap["{$detailsArray['verificationResults']['avsStreetResult']}_{$detailsArray['verificationResults']['avsZipResult']}"])) {
                $detailsArray['verificationResults']['avsFullResult'] = $this->avsMap["{$detailsArray['verificationResults']['avsStreetResult']}_{$detailsArray['verificationResults']['avsZipResult']}"];
            }

            $cvvCheck = $checks->cvc_check ?? null;
            if (isset($cvvCheck, $this->cvvMap[$cvvCheck])) {
                $detailsArray['verificationResults']['cvvResult'] = $this->cvvMap[$cvvCheck];
            }
        }

        if (isset($stripePayment->outcome) && isset($stripePayment->outcome->type)) {
            $detailsArray['verificationResults']['processorResponseText'] = $stripePayment->outcome->type;
        }

        if ($card) {
            $expMonth = $card->exp_month ? $card->exp_month : '';
            $detailsArray['expirationMonth'] = (string)(strlen($expMonth) > 1 ? $expMonth : (strlen($expMonth) == 0 ? $expMonth : '0' . $expMonth));
            $detailsArray['expirationYear'] = (string)($card->exp_year ?? '');
            $detailsArray['lastFourDigits'] = $card->last4 ?? '';
            $detailsArray['cardType'] = strtoupper($card->funding ?? '');
            $detailsArray['cardBrand'] = strtoupper($card->brand ?? '');
            $detailsArray['countryOfIssuance'] = strtoupper($card->country ?? '');
        }

        if ($threeDsStatus) {
            $detailsArray['verificationResults']['cvvResult'] = (string)$threeDsStatus->authenticated ?? '';
            $detailsArray['verificationResults']['threeDsVersion'] = $threeDsStatus->version ?? '';
            $detailsArray['verificationResults']['eciValue'] = $threeDsStatus->electronic_commerce_indicator ?? '';
            $detailsArray['verificationResults']['authorizationPolicy'] = '3DS';
            $detailsArray['verificationResults']['threeDsInteractionMode'] = 'FRICTIONLESS';
        }

        return $detailsArray;
    }
}
