<?php
declare(strict_types=1);

namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\App\Emulation;
use Forter\Forter\Helper\EntityHelper;
use Forter\Forter\Observer\OrderFullfilment\PaymentSaveAfter;
use Magento\Framework\Event\Observer;

class PaymentSaveAfter implements ObserverInterface
{
    const FORTER_STATUS_COMPLETE = "complete";

    /**
     * @param Config $config
     * @param Emulation $emulate
     * @param ForterLogger $forterLogger
     * @param EntityHelper $entityHelper
     * @param AbstractApi $abstractApi
     */
    public function __construct(
        private readonly Config $config,
        private readonly Emulation $emulate,
        private readonly ForterLogger $forterLogger,
        private readonly EntityHelper $entityHelper,
        private readonly AbstractApi $abstractApi
    ) {  
    }

    public function execute(Observer $observer)
    {
        if (!$this->config->isEnabled() || !$this->config->isOrderFulfillmentEnable()) {
            return false;
        }

        try {
            $order = $observer->getEvent()->getPayment()->getOrder();
            $forterEntity = $this->entityHelper->getForterEntityByIncrementId($order->getIncrementId(), [PaymentSaveAfter::FORTER_STATUS_COMPLETE]);

            if (!$forterEntity) {
                return false;
            }
            $this->emulate->startEnvironmentEmulation($order->getStoreId(), 'frontend', true);
            $this->abstractApi->sendOrderStatus($order);
            $message = new ForterLoggerMessage($this->config->getSiteId(), $order->getIncrementId(), 'Order Status Update');
            $message->metaData->order = $order->getData();
            $message->metaData->payment = $order->getPayment()->getData();
            $this->forterLogger->SendLog($message);
            $this->forterLogger->forterConfig->log('Order no. ' . $order->getIncrementId() . ' Payment Data: ' . json_encode($order->getPayment()->getData()));
            $this->emulate->stopEnvironmentEmulation();

        } catch (\Throwable $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
