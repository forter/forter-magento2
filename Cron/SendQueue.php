<?php

namespace Forter\Forter\Cron;

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
    private $forterLogger;

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
        ForterLogger $forterLogger
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
                $this->emulate->stopEnvironmentEmulation(); // let detach the store meta data
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('increment_id', $item->getData('increment_id'), 'eq')
                    ->create();
                $orderList = $this->orderRepository->getList($searchCriteria)->getItems();
                $order = reset($orderList);

                if (!$order) {
                    $this->forterConfig->log('Order does not exist, remove from queue');
                    // order does not exist, remove from queue
                    $item->setSyncFlag('1')
                        ->save();
                    continue;
                }

                $method = $order->getPayment()->getMethod();
                // let bind the relevent store in case of multi store settings
                $this->emulate->startEnvironmentEmulation(
                    $order->getStoreId(),
                    'frontend',
                    true
                );

                if ($item->getEntityType() == 'pre_sync_order') {
                    if (strpos($method ?? '', 'adyen') !== false && !$order->getPayment()->getAdyenPspReference()) {
                        $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'Skip Adyen Order Missing Data');
                        $message->metaData->order = $order->getData();
                        $message->metaData->payment = $order->getPayment()->getData();
                        $message->proccessItem = $item;
                        $this->forterLogger->SendLog($message);
                        continue;
                    }
                    $result = $this->handlePreSyncMethod($order, $item);
                    if (!$result) {
                        $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'No Mapped CC Adyen');
                        $message->metaData->order = $order->getData();
                        $message->metaData->payment = $order->getPayment()->getData();
                        $message->proccessItem = $item;
                        $this->forterLogger->SendLog($message);
                        continue;
                    } else {
                        $item->setSyncFlag('1');
                    }
                } elseif ($item->getEntityType() == 'order') {
                    $this->handleForterResponse($order, $item->getData('entity_body'));
                    $item->setSyncFlag('1');
                }


                $item->save();
                $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'CRON Validation Finished');
                $message->metaData->order = $order;
                $message->metaData->payment = $order->getPayment()->getData();
                $message->proccessItem = $item;
                $this->forterLogger->SendLog($message);
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    /**
     * this method will handle the pre payment and validation state
     *
     * @param $order holde the order model
     * @param $item hold the item from the que that can get from at rest of the models such as order and more (use as ref incase this method override it will be in use)
     * @return void
     */
    private function handlePreSyncMethod($order, $item)
    {
        try {
            $this->forterConfig->log('Start pre payment and validation state for order number ' . $order->getIncrementId());
            $data = $this->requestBuilderOrder->buildTransaction($order, 'AFTER_PAYMENT_ACTION');
            $this->forterConfig->log('Pre Payment Validation Request Data: ' . json_encode($data));

            $paymentMethod = $data['payment'][0]['paymentMethodNickname'];

            if ($paymentMethod == 'adyen_cc') {
                if (!isset($data['payment'][0]['creditCard'])) {
                    return false;
                }
                $creditCard = $data['payment'][0]['creditCard'];
                if (!array_key_exists('expirationMonth', $creditCard) || !array_key_exists('expirationYear', $creditCard) || !array_key_exists('lastFourDigits', $creditCard)) {
                    $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'No Mapped CC Details Adyen');
                    $message->metaData->order = $order->getData();
                    $message->metaData->payment = $order->getPayment();
                    $message->proccessItem = $data;
                    $this->forterLogger->SendLog($message);
                    return false;
                }
            }

            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();

            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));

            $this->forterConfig->log('Request for Order ' . $order->getIncrementId() . ': ' . json_encode($data));
            $this->forterConfig->log('Responsefor Order ' . $order->getIncrementId() . ': ' . $response);

            $responseArray = json_decode($response);

            $this->abstractApi->sendOrderStatus($order);

            $order->setForterResponse($response);

            if ($responseArray->status != 'success' || !isset($responseArray->action)) {
                $order->setForterStatus('error');
                $order->save();
                $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'Response Error - SendQueue');
                $message->metaData->order = $order->getData();
                $message->metaData->payment = $order->getPayment();
                $message->proccessItem = $data;
                $this->forterLogger->SendLog($message);

                $this->forterConfig->log('Response Error for Order ' . $order->getIncrementId() . ' - Payment Data: ' . json_encode($order->getPayment()->getData()));

                return true;
            }

            $this->handleForterResponse($order, $responseArray->action);
            $order->addStatusHistoryComment(__('Forter (cron) Decision: %1', $responseArray->action));
            $order->setForterStatus($responseArray->action);
            $order->save();

            $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'Forter CRON Decision');
            $message->metaData->order = $order->getData();
            $message->metaData->payment = $order->getPayment();
            $message->metaData->forterStatus = $responseArray->action;
            $this->forterLogger->SendLog($message);

            $this->forterConfig->log('Payment Data for Order ' . $order->getIncrementId() . ' - Payment Data: ' . json_encode($order->getPayment()->getData()));

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
