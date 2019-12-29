<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\QueueFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Class SendQueue
 * @package Forter\Forter\Cron
 */
class SendQueue
{
    /**
     * @var Decline
     */
    private $decline;

    /**
     * @param  AbstractApi     $abstractApi
     * @param  ForterConfig forterConfig
     */
    public function __construct(
        Approve $approve,
        Decline $decline,
        QueueFactory $forterQueue,
        Order $order,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->approve = $approve;
        $this->decline = $decline;
        $this->forterQueue = $forterQueue;
        $this->order = $order;
    }

    /**
     * Send to forter items in Queue
     * @return boolval
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $items = $this->forterQueue
        ->create()
        ->getCollection()
        ->addFieldToFilter('sync_flag', '0');

        $items
       ->setPageSize(3)->setCurPage(1);

        foreach ($items as $item) {
            if ($item->getData('entity_body') == 'approve') {
                $order = $this->order->loadByIncrementId($item->getData('entity_id'));
                $order = $this->orderRepository->get($order->getId());
                $this->approve->handleApproveImmediatly($order);
            } elseif ($item->getData('entity_body') == 'decline') {
                $order = $this->orderRepository->get($item->getData('entity_id'));
                if ($order->canUnhold()) {
                    $order->unhold()->save();
                }
                $this->decline->handlePostTransactionDescision($order);
            }

            $item->setSyncFlag('1');
            $item->save();
        }

        return true;
    }
}
