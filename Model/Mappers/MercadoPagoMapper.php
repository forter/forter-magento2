<?php

namespace Forter\Forter\Model\Mappers;

class MercadoPagoMapper
{

    public function getMercadopagoDetails($payment)
    {
        $detailsArray =[];

        $ccType = $payment->getAdditionalInformation('payment_method');
        if ($ccType) {
            $detailsArray['cardBrand'] = $ccType;
        }

        $expirationMonth = $payment->getAdditionalInformation('card_expiration_month');
        if ($expirationMonth) {
            $detailsArray['expirationMonth'] = $expirationMonth;
        }

        $expirationYear = $payment->getAdditionalInformation('card_expiration_year');
        if ($expirationYear) {
            $detailsArray['expirationYear'] = $expirationYear;
        }

        $nameOnCard = $payment->getAdditionalInformation('card_holder_name');
        if ($nameOnCard) {
            $detailsArray['nameOnCard'] = $nameOnCard;
        }

        $mercadoPayment = $payment->getAdditionalInformation('paymentResponse');

        if ($mercadoPayment) {
            if (isset($mercadoPayment['authorization_code'])) {
                $detailsArray['authorizationCode'] = $mercadoPayment['authorization_code'];
            }

            if (isset($mercadoPayment['card']['first_six_digits'])) {
                $detailsArray['bin'] = $mercadoPayment['card']['first_six_digits'];
            }

            if (isset($mercadoPayment['card']['last_four_digits'])) {
                $detailsArray['lastFourDigits'] = $mercadoPayment['card']['last_four_digits'];
            }
        }

        return $detailsArray;
    }

}