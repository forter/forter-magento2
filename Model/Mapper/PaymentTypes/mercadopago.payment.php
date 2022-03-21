<?php

namespace Forter\Forter\Model\Mapper\PaymentTypes;

class MercadopagoPayment extends BasePayment
{
    public function __construct()
    {
    }
    public function process($order, $payment)
    {
        $paymentMapping = $this->mapper->payments->mercadopago;
        $detailsArray =[];

        $ccType = $payment->getAdditionalInformation($paymentMapping->cardType);
        if ($ccType) {
            $detailsArray['cardBrand'] = $ccType;
        }

        $expirationMonth = $payment->getAdditionalInformation($paymentMapping->cardMonth);
        if ($expirationMonth) {
            $detailsArray['expirationMonth'] = $expirationMonth;
        }

        $expirationYear = $payment->getAdditionalInformation($paymentMapping->cardYear);
        if ($expirationYear) {
            $detailsArray['expirationYear'] = $expirationYear;
        }

        $nameOnCard = $payment->getAdditionalInformation($paymentMapping->cardName);
        if ($nameOnCard) {
            $detailsArray['nameOnCard'] = $nameOnCard;
        }

        $continuerMapping = $paymentMapping->paymentMeta;
        $mercadoPayment = $payment->getAdditionalInformation($continuerMapping->continuer);

        if ($mercadoPayment) {
            if (isset($mercadoPayment[$continuerMapping->authCode])) {
                $detailsArray['authorizationCode'] = $mercadoPayment[$continuerMapping->authCode];
            }

            if (isset($mercadoPayment[$continuerMapping->cardInfo][$continuerMapping->cardLast6Dig])) {
                $detailsArray['bin'] = $mercadoPayment[$continuerMapping->cardInfo][$continuerMapping->cardInfo];
            }

            if (isset($mercadoPayment[$continuerMapping->cardInfo][$continuerMapping->cardLast4Dig])) {
                $detailsArray['lastFourDigits'] = $mercadoPayment[$continuerMapping->cardInfo]['last_four_digits'];
            }
        }

        return $this->preferCcDetails($payment, $detailsArray);
    }
    public function installmentService($order, $payment)
    {
        return null;
    }
}
