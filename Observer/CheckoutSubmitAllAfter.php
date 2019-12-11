<?php

namespace Forter\Forter\Observer;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\QueueFactory as ForterQueueFactory;
use Forter\Forter\Model\RequestHandler\Approve;
use Magento\Framework\Event\Observer;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutSubmitAllAfter implements \Magento\Framework\Event\ObserverInterface
{
    public function __construct(
        ForterQueueFactory $queue,
        Approve $approve,
        Config $forterConfig,
        DateTime $dateTime,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->approve = $approve;
        $this->forterConfig = $forterConfig;
        $this->dateTime = $dateTime;
        $this->queue = $queue;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        $order = $observer->getEvent()->getOrder();
        $ForterResponse = $order->getForterResponse();
        $ForterResponse = json_decode($ForterResponse);

        $storeId = $this->storeManager->getStore()->getId();
        $currentTime = $this->dateTime->gmtDate();

        if ($ForterResponse->action == 'approve') {
            $result = $this->forterConfig->captureInvoice();
            if ($result == '1') {
                $this->queue->create()
                  ->setStoreId($storeId)
                  ->setEntityType('approve_order')
                  ->setEntityId($order->getId())
                  ->setEntityBody('approve')
                  ->setSyncDate($currentTime)
                  ->save();
            } else {
                $this->approve->handleApproveImmediatly($order);
            }
        }
    }
}
