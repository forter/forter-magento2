<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class HandleOrder
 * @package Forter\Forter\Cron
 */
class HandleOrder
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var OrderCollection
     */
    private $orderCollection;
    /**
     * @var Approve
     */
    private $approve;
    /**
     * @var Decline
     */
    private $decline;
    /**
     * @var ForterConfig
     */
    private $forterConfig;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;


    /**
     * HandleOrder constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderCollection $orderCollection
     * @param Approve $approve
     * @param Decline $decline
     * @param ForterConfig $forterConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderCollection $orderCollection,
        Approve $approve,
        Decline $decline,
        ForterConfig $forterConfig,
        StoreManagerInterface $storeManager
    )
    {
        $this->orderRepository = $orderRepository;
        $this->orderCollection = $orderCollection;
        $this->approve = $approve;
        $this->decline = $decline;
        $this->forterConfig = $forterConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @return bool|Decline
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $orderCollection = $this->orderCollection->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('state', ['in' => [Order::STATE_HOLDED]]);

        foreach ($orderCollection as $order) {
            $order->unhold()->save();

            $forterResponse = $order->getForterResponse();
            $forterResponse = json_decode($forterResponse);

            if ($forterResponse->status == 'success') {
                $result = "";
                if ($forterResponse->action == 'approve') {
                    $result = $this->forterConfig->getApprovePost();
                } elseif ($forterResponse->action == "not reviewed") {
                    $result = $this->forterConfig->getNotReviewPost();
                } elseif ($forterResponse->action == "decline") {
                    $result = $this->decline->handlePostTransactionDescision($order);
                }
                if ($result == '1') {
                    $this->approve->handleApproveImmediatly($order);
                }
            }
        }
        return true;
    }
}
