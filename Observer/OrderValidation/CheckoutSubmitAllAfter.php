<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\QueueFactory as ForterQueueFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class CheckoutSubmitAllAfter
 * @package Forter\Forter\Observer\OrderValidation
 */
class CheckoutSubmitAllAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Approve
     */
    private $approve;
    /**
     * @var Config
     */
    private $forterConfig;
    /**
     * @var DateTime
     */
    private $dateTime;
    /**
     * @var ForterQueueFactory
     */
    private $queue;

    /**
     * CheckoutSubmitAllAfter constructor.
     * @param ForterQueueFactory $queue
     * @param Approve $approve
     * @param Config $forterConfig
     * @param DateTime $dateTime
     * @param StoreManagerInterface $storeManager
     */
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
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        $order = $observer->getEvent()->getOrder();
        $forterResponse = $order->getForterResponse();
        $forterResponse = json_decode($forterResponse);

        $storeId = $this->storeManager->getStore()->getId();
        $currentTime = $this->dateTime->gmtDate();

        if ($forterResponse->action == 'decline' && $forterResponse->status == 'success') {
            $this->decline->handlePostTransactionDescision($order);
        }

        if ($forterResponse->status == 'success') {
            if ($forterResponse->action == 'approve') {
                $result = $this->forterConfig->getApprovePost();
            } elseif ($forterResponse->action == "not reviewed") {
                $result = $this->forterConfig->getNotReviewPost();
            } else {
                return false;
            }

            if ($result == '1') {
                $this->queue->create()
                    ->setStoreId($storeId)
                    ->setEntityType('approve_order')
                    ->setEntityId($order->getId())
                    ->setEntityBody('approve')
                    ->setSyncDate($currentTime)
                    ->save();
            } elseif ($result == '2') {
                $this->approve->handleApproveImmediatly($order);
            } else {
                return false;
            }
        }
    }
}
