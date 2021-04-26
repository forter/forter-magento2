<?php

namespace Forter\Forter\Plugin\Order;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Sales\Model\Order\Payment as MagentoPayment;

/**
 * Class Payment
 * @package Forter\Forter\Plugin\Customer\Model
 */
class Payment
{
    /**
     *
     */
    const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';
    /**
     * @var forterConfig
     */
    private $forterConfig;
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var Order
     */
    private $requestBuilderOrder;

    /**
     * Payment constructor.
     * @param AbstractApi $abstractApi
     * @param Order $requestBuilderOrder
     */
    public function __construct(
        ForterConfig $forterConfig,
        AbstractApi $abstractApi,
        Order $requestBuilderOrder
    ) {
        $this->forterConfig = $forterConfig;
        $this->abstractApi = $abstractApi;
        $this->requestBuilderOrder = $requestBuilderOrder;
    }

    /**
     * @param MagentoPayment $subject
     * @param callable $proceed
     * @return mixed
     * @throws \Exception
     */
    public function aroundPlace(MagentoPayment $subject, callable $proceed)
    {
        try {
            $result = $proceed();
            return $result;
        } catch (\Exception $e) {
            $this->notifyForterOfPaymentFailure($e, $subject);
            throw $e;
        }
    }

    public function notifyForterOfPaymentFailure($e, $subject)
    {
        try {
            if (!$this->forterConfig->isEnabled()) {
                return;
            }
            if ($e->getMessage() == $this->forterConfig->getPreThanksMsg()) {
                return;
            }
            $order = $subject->getOrder();
            $data = $this->requestBuilderOrder->buildTransaction($order, 'PAYMENT_ACTION_FAILURE');
            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $this->abstractApi->sendApiRequest($url, json_encode($data));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
