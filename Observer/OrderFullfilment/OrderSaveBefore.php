<?php

namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPrepere;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class OrderSaveAfter
 * @package Forter\Forter\Observer\OrderFullfilment
 */
class OrderSaveBefore implements ObserverInterface
{
    const ORDER_FULFILLMENT_STATUS_ENDPOINT = "https://api.forter-secure.com/v2/status/";

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ForterLogger
     */
    private $forterLogger;

    /**
     * OrderSaveAfter constructor.
     * @param AbstractApi $abstractApi
     * @param Config $config

     */
    public function __construct(
        AbstractApi $abstractApi,
        Config $config,
        PaymentPrepere $paymentPrepere,
        ForterLogger $forterLogger
    ) {
        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->paymentPrepere = $paymentPrepere;
        $this->forterLogger = $forterLogger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled() || !$this->config->isOrderFulfillmentEnable()) {
            return false;
        }

        try {
            $order = $observer->getEvent()->getOrder();
            if (!$order->getForterStatus()) {
                return false;
            }
            $orderState = $order->getState();
            $orderOrigState = $order->getOrigData('state');

            if ($orderState == 'complete' && $orderOrigState != 'complete') {
                $orderState = 'COMPLETED';
            } elseif ($orderState == 'processing' && $orderOrigState != 'processing') {
                $orderState = 'PROCESSING';
            } elseif ($orderState == 'canceled' && $orderOrigState != 'canceled') {
                $orderState = 'CANCELED_BY_MERCHANT';
            } else {
                return false;
            }

            $message = new ForterLoggerMessage($this->config->getSiteId(),  $order->getIncrementId(), 'Order Status Update');
            $message->metaData->order = $order->getData();
            $message->metaData->payment = $order->getPayment()->getData();
            $message->metaData->orderState = $orderState;
            $message->metaData->orderOrigState = $orderOrigState;
            $this->forterLogger->SendLog($message);
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
