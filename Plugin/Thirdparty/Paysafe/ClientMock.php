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
use Magento\Payment\Gateway\Http\TransferInterface;
use Paysafe\Payment\Gateway\Http\Client\ClientMock as GatewayClientMock;

class ClientMock
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
     * @method afterPlaceRequest
     * @param  GatewayClientMock    $gatewayClientMock
     * @param  array|mixed          $response
     * @param  TransferInterface    $transferObject
     * @return array|mixed
     */
    public function afterPlaceRequest(
        GatewayClientMock $gatewayClientMock,
        $response,
        TransferInterface $transferObject
    ) {
        try {
            if (!$this->forterConfig->isEnabled()) {
                return $response;
            }
            $this->registry->unregister('forter_paysafe_response');
            $this->registry->register('forter_paysafe_response', $response, true);
        } catch (Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }

        return $response;
    }
}