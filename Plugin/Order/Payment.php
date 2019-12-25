<?php


namespace Forter\Forter\Plugin\Order;

use Magento\Sales\Model\Order\Payment as MagentoPayment;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\RequestBuilder\Order;

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
    public function __construct(AbstractApi $abstractApi, Order $requestBuilderOrder)
    {
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
        } catch (\Exception $e) {
            $order = $subject->getOrder();
            $data = $this->requestBuilderOrder->buildTransaction($order);
            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $this->abstractApi->sendApiRequest($url, json_encode($data));
            throw new \Exception($e->getMessage());
        }
        return $result;
    }
}