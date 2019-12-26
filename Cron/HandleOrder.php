<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\ActionsHandler\Decline;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollection;

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
     * @var Decline
     */
    private $decline;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderCollection $orderCollection,
        Decline $decline
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderCollection = $orderCollection;
        $this->decline = $decline;
    }

    public function execute()
    {
        $orderCollection = $this->orderCollection->create()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('status', ['in' => [Order::STATE_HOLDED]]);
        foreach ($orderCollection as $order) {
            $order->unhold()->save();

            $forterResponse = $order->getForterResponse();
            $forterResponse = json_decode($forterResponse);

            if ($forterResponse->status == 'success') {
                if ($forterResponse->action == 'approve') {
                    $result = $this->forterConfig->getApprovePost();
                } elseif ($forterResponse->action == "not reviewed") {
                    $result = $this->forterConfig->getNotReviewPost();
                } elseif ($forterResponse->action == "decline") {
                    return $this->decline->handlePostTransactionDescision($order);
                } else {
                    return false;
                }

                if ($result == '1') {
                    $this->approve->handleApproveImmediatly($order);
                } else {
                    return false;
                }
            }
        }
    }
}
