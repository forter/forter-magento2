<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\QueueFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

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

    public function __construct(
        Approve $approve,
        Decline $decline,
        QueueFactory $forterQueue,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->approve = $approve;
        $this->decline = $decline;
        $this->forterQueue = $forterQueue;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Process items in Queue
     */
    public function execute()
    {
        $items = $this->forterQueue
        ->create()
        ->getCollection()
        ->addFieldToFilter('sync_flag', '0');

        $items->setPageSize(15)->setCurPage(1);

        foreach ($items as $item) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $item->getData('increment_id'), 'eq')
                ->create();
            $orderList = $this->orderRepository->getList($searchCriteria)->getItems();
            $order = reset($orderList);

            if (!$order) {
                // order does not exist, remove from queue
                $item->setSyncFlag('1');
                return;
            }

            if ($item->getData('entity_body') == 'approve') {
                $this->approve->handleApproveImmediatly($order);
            } elseif ($item->getData('entity_body') == 'decline') {
                if ($order->canUnhold()) {
                    $order->unhold()->save();
                }
                $this->decline->handlePostTransactionDescision($order);
            }

            $item->setSyncFlag('1');
            $item->save();
        }
    }
}
