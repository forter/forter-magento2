<?php
/**
 * Paysafe Extension for Forter Fraud Prevention For Magento 2
 *
 * @category Forter
 * @package  Forter_Forter
 * @author   Pniel Cohen | Girit interactive
 * @copyright Copyright (c) 2021 Forter (https://www.Forter.com)
 */

 namespace Forter\Forter\Plugin\Thirdparty\Paysafe;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Magento\Framework\Registry;
use Magento\Payment\Model\InfoInterface;
use Paysafe\Payment\Model\Adapter as PaysafeAdapter;

class Adapter
{
    /**
     * @var ForterConfig
     */
    protected $forterConfig;

    /**
      * @var AbstractApi
      */
    protected $abstractApi;

    /**
      * @var Registry
      */
    protected $registry;

    /**
     * @method __construct
     * @param  ForterConfig $forterConfig
     * @param  AbstractApi  $abstractApi
     * @param  Registry     $registry
     */
    public function __construct(
        ForterConfig $forterConfig,
        AbstractApi $abstractApi,
        Registry $registry
    ) {
        $this->forterConfig = $forterConfig;
        $this->abstractApi = $abstractApi;
        $this->registry = $registry;
    }

    /**
     * @method afterCapture
     * @param  PaysafeAdapter $paysafeAdapter
     * @param  PaysafeAdapter $paysafeAdapterReturn
     * @param  InfoInterface  $payment
     * @param  float          $amount
     * @return PaysafeAdapter
     */
    public function afterCapture(
        PaysafeAdapter $paysafeAdapter,
        PaysafeAdapter $paysafeAdapterReturn,
        InfoInterface $payment,
        $amount
    ) {
        $this->setPaysafeData($payment, $amount);
        return $paysafeAdapterReturn;
    }

    /**
     * @method afterAuthorize
     * @param  PaysafeAdapter $paysafeAdapter
     * @param  PaysafeAdapter $paysafeAdapterReturn
     * @param  InfoInterface  $payment
     * @param  float          $amount
     * @return PaysafeAdapter
     */
    public function afterAuthorize(
        PaysafeAdapter $paysafeAdapter,
        PaysafeAdapter $paysafeAdapterReturn,
        InfoInterface $payment,
        $amount
    ) {
        $this->setPaysafeData($payment, $amount);
        return $paysafeAdapterReturn;
    }

    /**
     * @method setPaysafeData
     * @param  InfoInterface  $payment
     * @param  float          $amount
     */
    private function setPaysafeData(
        InfoInterface $payment,
        $amount
    ) {
        try {
            if (!$this->forterConfig->isEnabled()) {
                return;
            }

            $response = (array) $this->registry->registry('forter_paysafe_response');

            if (!empty($response['authCode'])) {
                $payment->setAdditionalInformation('authCode', $response['authCode']);
            }

            if (!empty($response['avsResponse'])) {
                $payment->setAdditionalInformation('avsResponse', $response['avsResponse']);
            }

            if (!empty($response['cvvVerification'])) {
                $payment->setAdditionalInformation('cvvVerification', $response['cvvVerification']);
            }

            $ccData = [];

            if (!empty($response['card'])) {
                if (!empty($response['card']['type'])) {
                    $ccData['cc_type'] = $response['card']['type'];
                }
                if (!empty($response['card']['lastDigits'])) {
                    $ccData['cc_last_4'] = $response['card']['lastDigits'];
                }
                if (!empty($response['card']['cardExpiry'])) {
                    if (!empty($response['card']['cardExpiry']['month'])) {
                        $ccData['cc_exp_month'] = $response['card']['cardExpiry']['month'];
                    }
                    if (!empty($response['card']['cardExpiry']['year'])) {
                        $ccData['cc_exp_year'] = $response['card']['cardExpiry']['year'];
                    }
                }
            }

            if (!empty($response['profile'])) {
                if (isset($response['profile']['firstName']) && isset($response['profile']['lastName'])) {
                    $ccData['cc_owner'] = trim($response['profile']['firstName'] . " " . $response['profile']['lastName']);
                }
            }

            if ($ccData) {
                $payment->addData($ccData);
            }
        } catch (Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }

        return $this;
    }
}