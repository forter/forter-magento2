<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\QueueFactory as ForterQueueFactory;
use Forter\Forter\Model\RequestHandler\Approve;
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
        $ForterResponse = $order->getForterResponse();
        $ForterResponse = json_decode($ForterResponse);

        $storeId = $this->storeManager->getStore()->getId();
        $currentTime = $this->dateTime->gmtDate();

        if ( && $ForterResponse->status == 'success') {
            if($ForterResponse->action == 'approve'){
              $result = $this->forterConfig->getApprovePost();
            } elseif ($ForterResponse->action == "not reviewed" ){
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
