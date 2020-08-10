<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\QueueFactory;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Class SendQueue
 * @package Forter\Forter\Cron
 */
class SendQueue
{
    /**
     *
     */
    const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';
    /**
     * @var Decline
     */
    private $decline;
    /**
     * @var Order
     */
    private $requestBuilderOrder;
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var DateTime
     */
    private $dateTime;
    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Approve $approve,
        Decline $decline,
        Config $config,
        QueueFactory $forterQueue,
        DateTime $dateTime,
        OrderRepositoryInterface $orderRepository,
        Order $requestBuilderOrder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        AbstractApi $abstractApi
    ) {
        $this->approve = $approve;
        $this->decline = $decline;
        $this->forterQueue = $forterQueue;
        $this->dateTime = $dateTime;
        $this->forterConfig = $config;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->requestBuilderOrder = $requestBuilderOrder;
        $this->abstractApi = $abstractApi;
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
            } else {
                $method = $order->getPayment()->getMethod();
                if ($method == 'adyen_cc' && $order->getPayment()->getAdyenPspReference()) {
                    $forterResponse = $this->handleAdyenMethod($order);

                    if ($forterResponse) {
                        $item->setSyncFlag('1');
                        $storeId = $order->getStore()->getId();
                        $currentTime = $this->dateTime->gmtDate();
                        $this->forterConfig->log('Increment ID:' . $order->getIncrementId());
                        $this->forterQueue->create()
                        ->setStoreId($storeId)
                        ->setEntityType('order')
                        ->setIncrementId($order->getIncrementId())
                        ->setEntityBody($order->getForterStatus())
                        ->setSyncDate($currentTime)
                        ->save();
                    }
                }

                return;
            }

            $item->setSyncFlag('1');
            $item->save();
        }
    }

    private function handleAdyenMethod($order)
    {
        $data = $this->requestBuilderOrder->buildTransaction($order, 'AFTER_PAYMENT_ACTION');
        $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();

        $response = $this->abstractApi->sendApiRequest($url, json_encode($data));
        $order->setForterResponse($response);
        $response = json_decode($response);
        $order->setForterStatus($response->action);
        $order->save();

        return $response->status ? true : false;
    }
}
