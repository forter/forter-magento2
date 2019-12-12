<?php
namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\QueueFactory as ForterQueueFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;

class OrderSaveAfter implements ObserverInterface
{

    /**
      * @var \Forter\Forter\Queue
      */
    protected $queue;

    /**
      * @var \Magento\Framework\Stdlib\DateTime\DateTime
      */
    protected $dateTime;

    /**
      * @var \Magento\Store\Model\StoreManagerInterface
      */
    protected $storeManager;

    public function __construct(
        Config $config,
        ForterQueueFactory $queue,
        DateTime $dateTime,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->dateTime = $dateTime;
        $this->queue = $queue;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $order = $observer->getEvent()->getOrder();

        $orderState = $order->getState();
        $orderOrigState = $order->getOrigData('state');

        if ($orderState == 'complete' && $orderOrigState != 'complete') {
            $orderState = 'COMPLETED';
        } elseif ($orderState == 'processing' && $orderOrigState != 'processing') {
            $orderState = 'PROCESSING';
        } elseif ($orderState == 'canceled' && $orderOrigState  != 'canceled') {
            $orderState = 'CANCELED_BY_MERCHANT';
        } else {
            return false;
        }

        $json = [
        "orderId" => $order->getId(),
        "eventTime" => time(),
        "updatedStatus" =>  $orderState,
      ];
        $json = json_encode($json);
        $storeId = $this->storeManager->getStore()->getId();
        $currentTime = $this->dateTime->gmtDate();

        $this->queue->create()
        ->setStoreId($storeId)
        ->setEntityType('order_fulfillment_status')
        ->setEntityId($order->getId())
        ->setEntityBody($json)
        ->setSyncDate($currentTime)
        ->save();
    }
}
