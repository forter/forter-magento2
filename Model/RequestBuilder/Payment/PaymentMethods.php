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

use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\Mappers\AdyenMapper;
use Forter\Forter\Model\Mappers\BraintreeMapper;
use Forter\Forter\Model\Mappers\MercadoPagoMapper;
use Forter\Forter\Model\Mappers\AuthorizeNetMapper;
use Forter\Forter\Model\Mappers\PaypalMapper;
use Forter\Forter\Model\Mappers\PayBrightMapper;
use Magento\Customer\Model\Session;

/**
 * Class Payment
 * @package Forter\Forter\Model\RequestBuilder
 */
class PaymentMethods
{
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var ForterConfig
     */
    private $forterConfig;

    private $forterSanitizationMap = [
        'liabilityShift'                => 'boolean',
        'authenticationTriggered'       => 'boolean',
        'authorizationProcessedWith3DS' => 'boolean'
    ];

    protected $stripePaymentDataMapper;

    /**
     * @var AdyenMapper
     */
    protected $adyenMapper;

    /**
     * @var MercadoPagoMapper
     */
    protected $mercadoPagoMapper;

    /**
     * @var BraintreeMapper
     */
    protected $braintreeMapper;

    /**
     * @var AuthorizeNetMapper
     */
    protected $authorizenetMapper;

    /**
     * @var PayBrightMapper
     */
    protected $payBrightMapper;

    /**
     * @var PaypalMapper
     */
    protected $paypalMapper;

    /**
     * @method __construct
     * @param  Session      $customerSession
     * @param  ForterConfig $forterConfig
     */
    public function __construct(
        Session $customerSession,
        ForterConfig $forterConfig,
        \Forter\Forter\Model\Mappers\StripeMapper $stripePaymentDataMapper,
        MercadoPagoMapper $mercadoPagoMapper,
        AdyenMapper $adyenMapper,
        BraintreeMapper $braintreeMapper,
        AuthorizeNetMapper $authorizenetMapper,
        PayBrightMapper $payBrightMapper,
        PaypalMapper $paypalMapper
    ) {
        $this->customerSession = $customerSession;
        $this->forterConfig = $forterConfig;
        $this->stripePaymentDataMapper = $stripePaymentDataMapper;
        $this->mercadoPagoMapper = $mercadoPagoMapper;
        $this->adyenMapper = $adyenMapper;
        $this->braintreeMapper = $braintreeMapper;
        $this->authorizenetMapper = $authorizenetMapper;
        $this->payBrightMapper = $payBrightMapper;
        $this->paypalMapper = $paypalMapper;
    }

    public function getPaypalDetails($payment)
    {
        return $this->paypalMapper->getPaypalDetails($payment);
    }

    public function getPaybrightDetails($order, $payment)
    {
        return $this->payBrightMapper->getPaybrightDetails($order, $payment);
    }

    public function getAdyenKlarnaDetails($order, $payment)
    {
        return $this->adyenMapper->getAdyenKlarnaDetails($order, $payment);
    }

    public function getAuthorizeNetDetails($payment)
    {
        $detailsArray = $this->authorizenetMapper->getAuthorizeNetDetails($payment);
        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function getBraintreeDetails($payment)
    {
        $detailsArray = $this->braintreeMapper->getBraintreeDetails($payment);
        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function getMercadopagoDetails($payment)
    {
        $detailsArray = $this->mercadoPagoMapper->getMercadopagoDetails($payment);
        return $this->preferCcDetails($payment, $detailsArray);
    }

    public function getAdyenDetails($payment)
    {
        $detailsArray = $this->adyenMapper->getAdyenDetails($payment);
        $preferCcDetailsArray = $this->preferCcDetails($payment, $detailsArray);
        $mergedArray = $this->mergeArrays($preferCcDetailsArray, $detailsArray);

        return $mergedArray;
    }

    public function getAdyenGooglePayDetails($payment, $order)
    {
        $detailsArray = $this->adyenMapper->getAdyenGooglePayDetails($payment, $order);
        $preferCcDetailsArray = $this->preferCcDetails($payment, $detailsArray);
        $mergedArray = $this->mergeArrays($preferCcDetailsArray, $detailsArray);
        return $mergedArray;
    }

    public function getStripePaymentDetails($payment, $stripePayment)
    {
        $detailsArray = [];

        $detailsArray = $this->stripePaymentDataMapper->dataMapper($payment, $detailsArray, $stripePayment);

        if (isset($stripePayment)) {
            $payment->setAdditionalInformation('stripeChargeData',json_encode($stripePayment));
        }
        $preferCcDetailsArray = $this->preferCcDetails($payment, $detailsArray);
        $mergedArray = $this->mergeArrays($preferCcDetailsArray, $detailsArray);

        return $mergedArray;
    }

    /**
     * Get mapped forter field
     * @return mixed
     */

    /**
     * Get mapped forter field
     * @method getVerificationResultsField
     * @param  Payment  $payment
     * @param  string   $key
     * @param  array    $detailsArray
     * @param  string   $default
     * @return mixed
     */
    private function getVerificationResultsField($payment, $key, $detailsArray = [], $default = "")
    {
        if (($vrmKey = $this->forterConfig->getVerificationResultsMapping($payment->getMethod(), $key))) {
            if (($val = $payment->getData($vrmKey))) {
                return $val;
            } elseif (($val = $payment->getAdditionalInformation($vrmKey))) {
                return $val;
            } elseif (!empty($detailsArray[$vrmKey])) {
                return $detailsArray[$vrmKey];
            } elseif (!empty($detailsArray[$key])) {
                return $detailsArray[$key];
            } elseif (!is_null($this->getPaymentAdditionalData($payment, $vrmKey))) {
                return $this->getPaymentAdditionalData($payment, $vrmKey);
            }
        }
        return $default;
    }

    protected function getPaymentAdditionalData($payment, $vrmKey)
    {
        $key = explode('.', $vrmKey ?? '');
        if (isset($key[1]) && $payment->getAdditionalInformation($key[0])) {
            $additionalData = $payment->getAdditionalInformation($key[0]);
            if (isset($additionalData[$key[1]])) {

                /* Force boolean value */
                if ($additionalData[$key[1]] == 'true') {
                    return true;
                }

                /* Force boolean value */
                if ($additionalData[$key[1]] == 'false') {
                    return false;
                }

                /* Sent empty value instead of N/A as forter accespts 2 chars on some properties */
                if ($additionalData[$key[1]] === 'N/A') {
                    return '';
                }

                if (isset($this->forterSanitizationMap[$key[1]])) {
                    return settype($additionalData[$key[1]], $this->forterSanitizationMap[$key[1]]);
                }

                return $additionalData[$key[1]];
            }
        }
    }

    public function preferCcDetails($payment, $detailsArray = [])
    {
        if (array_key_exists("bin", $detailsArray)) {
            $binNumber = $detailsArray['bin'];
        } else {
            $binNumber = $this->customerSession->getForterBin() ? $this->customerSession->getForterBin() : $payment->getAdditionalInformation('bin');
        }

        if (array_key_exists("lastFourDigits", $detailsArray)) {
            $last4cc = $detailsArray['lastFourDigits'];
        } else {
            $last4cc = $this->customerSession->getForterLast4cc() ? $this->customerSession->getForterLast4cc() : $payment->getCcLast4();
        }

        $additionalDetails = $payment->getAdditionalInformation();

        if ( !$binNumber && isset($additionalDetails['forter_cc_bin'])) {
            $binNumber = $additionalDetails['forter_cc_bin'];
        }

        $ccToken = null;
        if ( isset($additionalDetails['forter_cc_token'])) {
            $ccToken = $additionalDetails['forter_cc_token'];
        }

        $detailsArray["avsFullResult"] = !empty($detailsArray["avsFullResult"]) ? $detailsArray["avsFullResult"] : $payment->getCcAvsStatus();
        $detailsArray["cvvResult"] = !empty($detailsArray["cvvResult"]) ? $detailsArray["cvvResult"] : $payment->getCcCidStatus();
        $detailsArray["cavvResult"] = !empty($detailsArray["cavvResult"]) ? $detailsArray["cavvResult"] : $payment->getCcCidStatus();

        $cardDetails =  [
            "nameOnCard" => array_key_exists("nameOnCard", $detailsArray) ? $detailsArray['nameOnCard'] : $payment->getCcOwner() . "",
            "cardBrand" => array_key_exists("cardBrand", $detailsArray) ? $detailsArray['cardBrand'] : $payment->getCcType() . "",
            "bin" => $binNumber,
            "countryOfIssuance" => array_key_exists('countryOfIssuance', $detailsArray) ? $detailsArray['countryOfIssuance'] : $payment->getAdditionalInformation('country_of_issuance'),
            "cardBank" => array_key_exists("cardBank", $detailsArray) ? $detailsArray['cardBank'] : $payment->getEcheckBankName(),
            "verificationResults" => [],
            "paymentGatewayData" => [
                "gatewayName" => $payment->getMethod() ? $payment->getMethod() : "",
                "gatewayTransactionId" => $payment->getCcTransId() ? $payment->getCcTransId() : "",
            ],
            "fullResponsePayload" => $payment->getAdditionalInformation() ? $payment->getAdditionalInformation() : ""
        ];

        foreach ($this->forterConfig->getVerificationResultsMethodFields($payment->getMethod()) as $field) {
            $cardDetails["verificationResults"][$field] = $this->getVerificationResultsField($payment, $field, $detailsArray);
        }

        if (array_key_exists("expirationMonth", $detailsArray) || $payment->getCcExpMonth()) {
            $cardDetails["expirationMonth"] = array_key_exists("expirationMonth", $detailsArray) ? $detailsArray['expirationMonth'] : str_pad($payment->getCcExpMonth() ?? '', 2, "0", STR_PAD_LEFT);
        }

        if (array_key_exists("expirationYear", $detailsArray) || $payment->getCcExpYear()) {
            $cardDetails["expirationYear"] = array_key_exists("expirationMonth", $detailsArray) ? $detailsArray['expirationYear'] : str_pad($payment->getCcExpYear() ?? '', 2, "0", STR_PAD_LEFT);
        }

        if (array_key_exists("lastFourDigits", $detailsArray) || $payment->getCcLast4() || $last4cc) {
            $cardDetails["lastFourDigits"] = $last4cc;
        }

        return $cardDetails;
    }

    protected function mergeArrays($array1, $array2) {

        if (!is_array($array1)) {
            $array1 = [];
        }
        if (!is_array($array2)) {
            return $array1;
        }

        foreach ($array2 as $key => $value) {
            if (isset($array1[$key]) && is_array($value) && is_array($array1[$key])) {
                $array1[$key] = $this->mergeArrays($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }
}
