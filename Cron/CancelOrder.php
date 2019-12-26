<?php


namespace Forter\Forter\Cron;

use Forter\Forter\Model\ActionsHandler\Decline;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollection;

class CancelOrder
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
    )
    {
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
            $order->cancel()->save();
            $this->decline->handlePostTransactionDescision($order);
        }
    }
}