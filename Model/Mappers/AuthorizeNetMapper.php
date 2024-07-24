<?php

namespace Forter\Forter\Model\Mappers;

class AuthorizeNetMapper
{
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

        return $detailsArray;
    }
}