<?php

namespace Forter\Forter\Plugin\Order;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\RequestBuilder\Order;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPrepare;
use Magento\Sales\Model\Order\Payment as MagentoPayment;

/**
 * Class Payment
 * @package Forter\Forter\Plugin\Customer\Model
 */
class Payment
{

    public const CANCELED_BY_MERCHANT = 'CANCELED_BY_MERCHANT';

    /**
     *
     */
    public const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';
    /**
     * @var ForterConfig
     */
    private ForterConfig $forterConfig;
    /**
     * @var AbstractApi
     */
    private AbstractApi $abstractApi;
    /**
     * @var Order
     */
    private Order $requestBuilderOrder;

    /**
     * Payment Prefer
     *
     * @var PaymentPrepare
     */
    private $paymentPrepare;

    /**
     * Payment constructor.
     * @param AbstractApi $abstractApi
     * @param Order $requestBuilderOrder
     */
    public function __construct(
        ForterConfig $forterConfig,
        AbstractApi $abstractApi,
        Order $requestBuilderOrder,
        PaymentPrepare $paymentPrepare
    ) {
        $this->forterConfig = $forterConfig;
        $this->abstractApi = $abstractApi;
        $this->requestBuilderOrder = $requestBuilderOrder;
        $this->paymentPrepare = $paymentPrepare;
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
            return  $proceed();
        } catch (\Exception $e) {
            $this->notifyForterOfPaymentFailure($e, $subject);
            throw $e;
        }
    }

    public function notifyForterOfPaymentFailure(\Throwable $e, $subject)
    {
        try {
            if (!$this->forterConfig->isEnabled()) {
                return;
            }
            if ($e->getMessage() === $this->forterConfig->getPreThanksMsg()) {
                return;
            }
            $order = $subject->getOrder();
            $data = $this->requestBuilderOrder->buildTransaction($order, 'PAYMENT_ACTION_FAILURE');
            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $this->abstractApi->sendApiRequest($url, json_encode($data));
            $this->forterConfig->log(sprintf(
                'PAYMENT_ACTION_FAILURE Order %s:%s',
                $order->getIncrementId(),
                json_encode($data)
            ));
            $this->forterConfig->log(sprintf(
                'Payment Failure for Order %s  - Order Payment Data: %s',
                 $order->getIncrementId(),
                json_encode($order->getPayment()->getData())
            ));
            $this->sendOrderStatus($order);
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    public function sendOrderStatus($order)
    {
        $data = [
            "orderId" => $order->getIncrementId(),
            "eventTime" => time(),
            "updatedStatus" => self::CANCELED_BY_MERCHANT,
            "payment" => $this->paymentPrepare->generatePaymentInfo($order)
        ];

        $url = "https://api.forter-secure.com/v2/status/" . $order->getIncrementId();
        $this->abstractApi->sendApiRequest($url, json_encode($data));
    }
}
