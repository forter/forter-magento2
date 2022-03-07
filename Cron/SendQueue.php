<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Common\ForterLogger;
use Forter\Forter\Common\ForterLoggerMessage;
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
    /**
     * @var ForterLogger
     */
    private $logger;

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
        ForterLogger $logger
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
        $this->logger = $logger;
    }

    /**
     * Process items in Queue
     */
    public function execute()
    {
        try {
            $items = $this->forterQueue
                ->create()
                ->getCollection()
                ->addFieldToFilter('sync_flag', '0')
                ->addFieldToFilter(
                    'sync_date',
                    [
                      'from' => date('Y-m-d H:i:s', strtotime('-7 days'))
                    ]
                );

            $items->setPageSize(10000)->setCurPage(1);

            foreach ($items as $item) {
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('increment_id', $item->getData('increment_id'), 'eq')
                    ->create();

                $orderList = $this->orderRepository->getList($searchCriteria)->getItems();
                $order = reset($orderList);

                if (!$order) {
                    // order does not exist, remove from queue
                    $item->setSyncFlag('1')
                        ->save();
                    continue;
                }

                $method = $order->getPayment()->getMethod();

                if ($item->getEntityType() == 'pre_sync_order') {
                    if (strpos($method, 'adyen') !== false && !$order->getPayment()->getAdyenPspReference()) {
                        continue;
                    }
                    $result = $this->handlePreSyncMethod($order, $item);
                    if (!$result) {
                        continue;
                    } else {
                        $item->setSyncFlag('1');
                    }
                } elseif ($item->getEntityType() == 'order') {
                    $this->handleForterResponse($order, $item->getData('entity_body'));
                    $item->setSyncFlag('1');

                    $message = new ForterLoggerMessage($order->getStoreId(),  $order->getIncrementId(), 'CRON Validation');
                    $message->metaData->order = $order; 
                    $this->logger->SendLog($message);
                }

              
                $item->save();
                $message = new ForterLoggerMessage($order->getStoreId(),  $order->getIncrementId(), 'CRON Validation');
                $message->metaData->order = $order;
                $message->proccessItem = $item;
                ForterLogger::getInstance()->SendLog($message);
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    private function handlePreSyncMethod($order, $item)
    {
        try {
            $data = $this->requestBuilderOrder->buildTransaction($order, 'AFTER_PAYMENT_ACTION');
            $paymentMethod = $data['payment'][0]['paymentMethodNickname'];

            if ($paymentMethod == 'adyen_cc') {
                if (!isset($data['payment'][0]['creditCard'])) {
                    return false;
                }
                $creditCard = $data['payment'][0]['creditCard'];
                if (!array_key_exists('expirationMonth', $creditCard) || !array_key_exists('expirationYear', $creditCard) || !array_key_exists('lastFourDigits', $creditCard)) {
                    return false;
                }
            }

            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();

            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));
            $responseArray = json_decode($response);

            $this->abstractApi->sendOrderStatus($order);

            $order->setForterResponse($response);

            if ($responseArray->status != 'success' || !isset($responseArray->action)) {
                $order->setForterStatus('error');
                $order->save();
                return true;
            }

            $this->handleForterResponse($order, $responseArray->action);
            $order->addStatusHistoryComment(__('Forter (cron) Decision: %1', $responseArray->action));
            $order->setForterStatus($responseArray->action);
            $order->save();

            $message = new ForterLoggerMessage($order->getStoreId(),  $order->getIncrementId(), 'Forter CRON Decision');
            $message->metaData->order = $order; 
            $message->metaData->forterStatus = $responseArray->action; 
            $this->logger->SendLog($message);

            return $responseArray->status ? true : false;
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    private function handleForterResponse($order, $response)
    {
        if ($order->canUnhold()) {
            $order->unhold()->save();
        }

        if ($this->forterConfig->getIsCron()) {
            if ($response == 'approve') {
                if ($this->forterConfig->getApproveCron() == '1') {
                    $this->approve->handleApproveImmediatly($order);
                }
            } elseif ($response == 'not reviewed') {
                if ($this->forterConfig->getNotReviewCron() == '1') {
                    $this->approve->handleApproveImmediatly($order);
                }
            } elseif ($response == 'decline') {
                switch ($this->forterConfig->getDeclineCron()) {
                    case 1:
                        $this->decline->handlePostTransactionDescision($order);
                        return;
                    case 2:
                        $this->decline->markOrderPaymentReview();
                        return;
                }
            }

            return;
        } else {
            if ($response == 'approve') {
                $this->approve->handleApproveImmediatly($order);
            } elseif ($response == 'decline') {
                $this->decline->handlePostTransactionDescision($order);
            }
        }
    }
}
