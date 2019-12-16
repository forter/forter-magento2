<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\QueueFactory;
use Forter\Forter\Model\RequestHandler\Approve;
use Magento\Sales\Api\OrderRepositoryInterface;

class SendQueue
{

    /**
     * @param  AbstractApi     $abstractApi
     * @param  ForterConfig forterConfig
     */
    public function __construct(
        Approve $approve,
        OrderRepositoryInterface $orderRepository,
        QueueFactory $forterQueue
    ) {
        $this->abstractApi = $abstractApi;
        $this->approve = $approve;
        $this->forterQueue = $forterQueue;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Send to forter items in Queue
     * @return boolval
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
            $order = $this->orderRepository->get($item->getData('entity_id'));
            $this->approve->handleApproveImmediatly($order);
            $item->setSyncFlag('1');
            $item->save();
        }

        return true;
    }
}
