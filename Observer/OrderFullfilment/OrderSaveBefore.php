<?php

namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Helper\AdditionalDataHelper;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
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
     * @var AdditionalDataHelper
     */
    protected $additionalDataHelper;

    /**
     * OrderSaveAfter constructor.
     * @param AbstractApi $abstractApi
     * @param Config $config
     * @param PaymentPrepere $paymentPrepere
     * @param ForterLogger $forterLogger
     * @param AdditionalDataHelper $additionalDataHelper
     */
    public function __construct(
        AbstractApi $abstractApi,
        Config $config,
        PaymentPrepere $paymentPrepere,
        ForterLogger $forterLogger,
        AdditionalDataHelper $additionalDataHelper
    ) {
        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->paymentPrepere = $paymentPrepere;
        $this->forterLogger = $forterLogger;
        $this->additionalDataHelper = $additionalDataHelper;
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

            if ($this->additionalDataHelper->getCreditMemoRmaSize($order)) {
                $orderState = 'RETURNED';
            } elseif ($orderState == 'complete' && $orderOrigState != 'complete') {
                $orderState = 'COMPLETED';
            } elseif ($orderState == 'processing' && $orderOrigState != 'processing') {
                $orderState = 'PROCESSING';
            } elseif ($orderState == 'canceled' && $orderOrigState != 'canceled') {
                $orderState = 'CANCELED_BY_MERCHANT';
            } else {
                return false;
            }

            /* Sends the order status to Forter. */
            $this->abstractApi->sendOrderStatus($order);

            /* This is a logging mechanism that sends the order status to Forter. */
            $message = new ForterLoggerMessage($this->config->getSiteId(), $order->getIncrementId(), 'Order Status Update');
            $message->metaData->order = $order->getData();
            $message->metaData->payment = $order->getPayment()->getData();
            $message->metaData->orderState = $orderState;
            $message->metaData->orderOrigState = $orderOrigState;
            $this->forterLogger->SendLog($message);
            $this->forterLogger->forterConfig->log('Order no. ' . $order->getIncrementId() . ' Order State: ' . $orderState . ' Payment Data: ' . json_encode($order->getPayment()->getData()));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
