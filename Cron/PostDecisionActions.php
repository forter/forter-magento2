<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Helper\EntityHelper;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\QueueFactory;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;

/**
 * Class SendQueue
 * @package Forter\Forter\Cron
 */
class PostDecisionActions
{
    /**
     *
     */
    public const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';
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
    /**
     * @var ForterLogger
     */
    private $forterLogger;
    /**
     * @var Approve
     */
    protected $approve;

    /**
     * @var QueueFactory
     */
    protected $forterQueue;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Emulation
     */
    protected $emulate;

    /**
     * @var Config
     */
    protected $forterConfig;

    /**
     * @var EntityHelper
     */
    protected $entityHelper;

    public function __construct(
        Approve $approve,
        Decline $decline,
        Config $config,
        QueueFactory $forterQueue,
        DateTime $dateTime,
        OrderRepositoryInterface $orderRepository,
        Order $requestBuilderOrder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        AbstractApi $abstractApi,
        Emulation $emulate,
        ForterLogger $forterLogger,
        EntityHelper $entityHelper
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
        $this->emulate = $emulate;
        $this->forterLogger = $forterLogger;
        $this->entityHelper = $entityHelper;
    }

    /**
     * Process items in Queue
     */
    public function execute()
    {
        try {
            $items = $this->entityHelper->getForterEntitiesPostDecisionAction();
            $items->setPageSize(10000)->setCurPage(1);
            foreach ($items as $item) {
                $this->emulate->stopEnvironmentEmulation(); // let detach the store meta data
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('increment_id', $item->getData('order_increment_id'), 'eq')
                    ->create();
                $orderList = $this->orderRepository->getList($searchCriteria)->getItems();
                $order = reset($orderList);

                if (!$order) {
                    $this->forterConfig->log('Order does not exist, remove from queue');
                    // order does not exist, remove from queue
                    $item->setSyncFlag(1)
                        ->save();
                    continue;
                }

                if ($order->getPayment() && $this->forterConfig->isActionExcludedPaymentMethod($order->getPayment()->getMethod(), null, $order->getStoreId())) {
                    continue;
                }

                // let bind the relevent store in case of multi store settings
                $this->emulate->startEnvironmentEmulation(
                    $order->getStoreId(),
                    'frontend',
                    true
                );

                if ($item->getEntityType() == 'order') {
                    $this->handleForterResponse($order, $item->getData('entity_body'), $item);
                    $item->setPostDecisionActionsFlag(1);
                }

                $item->save();
                $message = new ForterLoggerMessage($this->forterConfig->getSiteId(), $order->getIncrementId(), 'CRON Validation Finished');
                $message->metaData->order = $order;
                $message->metaData->payment = $order->getPayment()->getData();
                $message->proccessItem = $item;
                $this->forterLogger->SendLog($message);
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    private function handleForterResponse($order, $response, $item = null)
    {
        if ($order->canUnhold()) {
            $order->unhold();
        }
        if ($response == 'approve') {
            $this->approve->handleApproveImmediatly($order);
        } elseif ($response == 'decline') {
            $this->decline->handlePostTransactionDescision($order, $item);
        }
    }
}
