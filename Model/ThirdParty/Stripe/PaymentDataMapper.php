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
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/stripeDATA.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('mapping');

        if (is_object($stripePayment) == false) {
            return $detailsArray;
        }


        if (isset($stripePayment->payment_method_details) &&
            isset($stripePayment->payment_method_details->card) &&
            isset($stripePayment->payment_method_details->card->checks)
        ) {

            if (isset($stripePayment->payment_method_details->card->checks->address_postal_code_check)) {
                $detailsArray['avsZipResult'] = $stripePayment->payment_method_details->card->checks->address_postal_code_check;
            }

            if (isset($stripePayment->payment_method_details->card->checks->address_line1_check)) {
                $detailsArray['avsStreetResult'] = $stripePayment->payment_method_details->card->checks->address_line1_check;
            }

            if (isset($detailsArray['avsZipResult']) && isset($detailsArray['avsStreetResult']) && isset($this->avsMap["{$detailsArray['avsStreetResult']}_{$detailsArray['avsZipResult']}"])) {
                $detailsArray['avsFullResult'] = $this->avsMap["{$detailsArray['avsStreetResult']}_{$detailsArray['avsZipResult']}"];
            }

            if (isset($stripePayment->payment_method_details->card->checks->cvc_check)) {
                $cvvCheck = $stripePayment->payment_method_details->card->checks->cvc_check;

                if (isset($this->cvvMap[$cvvCheck])) {
                    $detailsArray['cvvResult'] = $this->cvvMap[$cvvCheck];
                }
            }
        }

        if (isset($stripePayment->payment_method_details) && isset($stripePayment->payment_method_details->card)) {

            if (isset($stripePayment->payment_method_details->card->exp_month)) {
                $detailsArray['expirationMonth'] = (string)$stripePayment->payment_method_details->card->exp_month;
            }

            if (isset($stripePayment->payment_method_details->card->exp_year)) {
                $detailsArray['expirationYear'] = (string)$stripePayment->payment_method_details->card->exp_year;
            }

            if (isset($stripePayment->payment_method_details->card->last4)) {
                $detailsArray['lastFourDigits'] = $stripePayment->payment_method_details->card->last4;
            }

            if (isset($stripePayment->payment_method_details->card->funding)) {
                $detailsArray['cardType'] = strtoupper($stripePayment->payment_method_details->card->funding);
            }

            if (isset($stripePayment->payment_method_details->card->brand)) {
                $detailsArray['cardBrand'] = strtoupper($stripePayment->payment_method_details->card->brand);
            }

            if (isset($stripePayment->payment_method_details->card->country)) {
                $detailsArray['countryOfIssuance'] = strtoupper($stripePayment->payment_method_details->card->country);
            }
        }

        $logger->info('mapping end');
        $logger->info('mapping json START');
        $logger->info(json_encode($detailsArray));
        $logger->info('mapping json END');
        return $detailsArray;
    }
}
