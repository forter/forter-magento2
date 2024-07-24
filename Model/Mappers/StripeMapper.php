<?php

namespace Forter\Forter\Model\Mappers;

class StripeMapper
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
        'unchecked' => 'P',
        'U' => 'U' // Default value when cvc check is empty
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
        $threeDsStatus = $card->three_d_secure ?? null;

        if ($checks) {
            $detailsArray['verificationResults']['avsZipResult'] = $checks->address_postal_code_check ?? 'unchecked';
            $detailsArray['verificationResults']['avsStreetResult'] = $checks->address_line1_check ?? 'unchecked';

            // Combined AVS result mapping
            $avsCombinedKey = "{$detailsArray['verificationResults']['avsStreetResult']}_{$detailsArray['verificationResults']['avsZipResult']}";
            $detailsArray['verificationResults']['avsFullResult'] = $this->avsMap[$avsCombinedKey] ?? 'U';

            // CVV result mapping
            $cvvCheck = $checks->cvc_check ?? 'U';
            $detailsArray['verificationResults']['cvvResult'] = $this->cvvMap[$cvvCheck] ?? 'P';
        }

        // Processor response and text mapping
        $outcomeType = $stripePayment->outcome->type ?? '';
        if ($outcomeType === 'authorized') {
            $detailsArray['verificationResults']['processorResponseCode'] = $outcomeType;
            $detailsArray['verificationResults']['processorResponseText'] = $outcomeType;
        } else {
            $detailsArray['verificationResults']['processorResponseCode'] = $outcomeType;
            $detailsArray['verificationResults']['processorResponseText'] = $stripePayment->outcome->reason ?? '';
        }

        if ($card) {
            // Card details mapping
            $expMonth = $card->exp_month ?? '';
            $detailsArray['expirationMonth'] = str_pad($expMonth, 2, '0', STR_PAD_LEFT);
            $detailsArray['expirationYear'] = (string)($card->exp_year ?? '');
            $detailsArray['lastFourDigits'] = $card->last4 ?? '';
            $detailsArray['cardType'] = strtoupper($card->funding ?? '');
            $detailsArray['cardBrand'] = strtoupper($card->brand ?? '');
            $detailsArray['countryOfIssuance'] = strtoupper($card->country ?? '');
        }

        if ($threeDsStatus) {
            // 3DS related details
            $detailsArray['verificationResults']['threeDsVersion'] = $threeDsStatus->version ?? '';
            $detailsArray['verificationResults']['threeDsStatus'] = $threeDsStatus->result ?? '';

            if (isset($threeDsStatus->authentication_flow)) {
                if ($threeDsStatus->authentication_flow === 'challenge') {
                    $detailsArray['verificationResults']['threeDsInteractionMode'] = 'CHALLENGED';
                }
                if ($threeDsStatus->authentication_flow === 'frictionless') {
                    $detailsArray['verificationResults']['threeDsInteractionMode'] = 'FRICTIONLESS';
                }
            } else {
                $detailsArray['verificationResults']['threeDsInteractionMode'] = 'N/A'; // Consider default or N/A for null cases
            }

            $detailsArray['verificationResults']['eciValue'] = $threeDsStatus->electronic_commerce_indicator ?? '';
            $detailsArray['verificationResults']['authorizationPolicy'] = '3DSecure';

            // external3dsVendorPayload mapping
            $detailsArray['verificationResults']['external3dsVendorPayload'] = [
                'authenticated' => $threeDsStatus->authenticated ?? false,
                'authentication_flow' => $threeDsStatus->authentication_flow ?? '',
                'electronic_commerce_indicator' => $threeDsStatus->electronic_commerce_indicator ?? '',
                'exemption_indicator' => $threeDsStatus->exemption_indicator ?? '',
                'result' => $threeDsStatus->result ?? '',
                'result_reason' => $threeDsStatus->result_reason ?? '',
                'succeeded' => $threeDsStatus->succeeded ?? false,
                'transaction_id' => $threeDsStatus->transaction_id ?? '',
                'version' => $threeDsStatus->version ?? ''
            ];
        }

        return $detailsArray;
    }
}
