<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\EntityFactory as ForterEntityFactory;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\QueueFactory;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;
use Forter\Forter\Helper\EntityHelper;


/**
 * Class SendQueue
 * @package Forter\Forter\Cron
 */
class ForterQueue
{
    /**
     *
     */
    public const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';

    const FORTER_STATUS_NEW = "new";
    const FORTER_STATUS_WAITING = "waiting_for_data";
    const FORTER_STATUS_PRE_POST_VALIDATION = "pre_post_validation";
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

    protected $forterEntityFactory;

    protected $orderFactory;
    protected $entityHelper;


    public function __construct(
        Approve                           $approve,
        Decline                           $decline,
        Config                            $config,
        QueueFactory                      $forterQueue,
        DateTime                          $dateTime,
        OrderRepositoryInterface          $orderRepository,
        Order                             $requestBuilderOrder,
        SearchCriteriaBuilder             $searchCriteriaBuilder,
        AbstractApi                       $abstractApi,
        Emulation                         $emulate,
        ForterLogger                      $forterLogger,
        ForterEntityFactory               $forterEntityFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        EntityHelper     $entityHelper
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
        $this->orderFactory = $orderFactory;
        $this->entityHelper = $entityHelper;
    }

    /**
     * Process items in Queue
     */
    public function execute()
    {
        try {

            $items = $this->entityHelper->getForterEntities();
//            $items = $this->forterEntityFactory
//                ->create()
//                ->getCollection()
//                ->addFieldToFilter('status', ['in' => [self::FORTER_STATUS_WAITING, self::FORTER_STATUS_NEW, self::FORTER_STATUS_PRE_POST_VALIDATION]])
//                ->addFieldToFilter('sync_flag', 0)
//                ->addFieldToFilter('sync_retries', ['lt' => 10])
//                ->addFieldToFilter(
//                    'updated_at',
//                    [
//                        'from' => date('Y-m-d H:i:s', strtotime('-7 days'))
//                    ]
//                )->addFieldToFilter(
//                    'updated_at',
//                    [
//                        'to' => date('Y-m-d H:i:s')
//                    ]
//                );

            $items->setPageSize(10000)->setCurPage(1);
            foreach ($items as $item) {
                $this->emulate->stopEnvironmentEmulation(); // let detach the store meta data
                $order = $this->orderFactory->create()->loadByIncrementId($item->getData('order_increment_id'));
                if (!$order) {
                    $this->forterConfig->log('Order does not exist, remove from queue');
                    // order does not exist, remove from queue
                    $item->delete();
                    continue;
                }
                $payment = $order->getPayment();
                $method = $payment->getMethod();




                // let bind the relevent store in case of multi store settings
                $this->emulate->startEnvironmentEmulation(
                    $order->getStoreId(),
                    'frontend',
                    true
                );

                if (strpos($method ?? '', 'adyen') !== false && !$order->getPayment()->getAdyenPspReference()) {
                    $message = new ForterLoggerMessage($this->forterConfig->getSiteId(), $order->getIncrementId(), 'Skip Adyen Order Missing Data');
                    $message->metaData->order = $order->getData();
                    $message->metaData->payment = $order->getPayment()->getData();
                    $message->proccessItem = $item;
                    $this->forterLogger->SendLog($message);
                    continue;
                }

                //de adaugat si getLastTransId() POATE/De discutat
                if (!$payment->getCcTransId()) {
                    continue;
                }

                $result = $this->handlePreSyncMethod($order, $item);
                if (!$result) {
                    $message = new ForterLoggerMessage($this->forterConfig->getSiteId(), $order->getIncrementId(), 'No Mapped CC Adyen');
                    $message->metaData->order = $order->getData();
                    $message->metaData->payment = $order->getPayment()->getData();
                    $message->proccessItem = $item;
                    $this->forterLogger->SendLog($message);
                    continue;
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

    /**
     * this method will handle the pre payment and validation state
     *
     * @param $order holde the order model
     * @param $item hold the item from the que that can get from at rest of the models such as order and more (use as ref incase this method override it will be in use)
     * @return boolean
     */
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
                    $message = new ForterLoggerMessage($this->forterConfig->getSiteId(), $order->getIncrementId(), 'No Mapped CC Details Adyen');
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
            $this->forterConfig->log('Response for Order ' . $order->getIncrementId() . ': ' . $response);

            $forterResponse = json_decode($response);

            $this->abstractApi->sendOrderStatus($order);
            $retries = $item->getRetries();
            $item->setRetries($retries++);

            $item->setForterResponse($response);
            $order->setForterResponse($response);

            if ($forterResponse->status != 'success' || !isset($forterResponse->action)) {
                $order->setForterStatus('error');
                $item->setForterStatus('error');
                $order->save();
                $message = new ForterLoggerMessage($this->forterConfig->getSiteId(), $order->getIncrementId(), 'Response Error - SendQueue');
                $message->metaData->order = $order->getData();
                $message->metaData->payment = $order->getPayment();
                $message->proccessItem = $data;
                $this->forterLogger->SendLog($message);

                $this->forterConfig->log('Response Error for Order ' . $order->getIncrementId() . ' - Payment Data: ' . json_encode($order->getPayment()->getData()));
                $this->entityHelper->updateForterEntity($item, $order, $forterResponse, $message, null);

                return true;
            }

            if ($forterResponse->status) {
                $item->setSyncFlag(1);
            }

            $order->addStatusHistoryComment(__('Forter (cron) Decision: %1%2', $forterResponse->action, $this->forterConfig->getResponseRecommendationsNote($forterResponse)));
            $order->addStatusHistoryComment(__('Forter (cron) Decision Reason: %1', $forterResponse->reasonCode));
            $order->setForterStatus($forterResponse->action);
            $order->setForterReason($forterResponse->reasonCode);
            $order->save();

            $this->handleForterResponse($order, $forterResponse->action, $item);


            $message = new ForterLoggerMessage($this->forterConfig->getSiteId(), $order->getIncrementId(), 'Forter CRON Decision');
            $message->metaData->order = $order->getData();
            $message->metaData->payment = $order->getPayment();
            $message->metaData->forterStatus = $forterResponse->action;
            $message->metaData->forterReason = $forterResponse->reasonCode;
            $this->entityHelper->updateForterEntity($item, $order, $forterResponse, $message);
            $this->abstractApi->triggerRecommendationEvents($forterResponse, $order, 'cron');
            $this->forterLogger->SendLog($message);
            $this->forterConfig->log('Payment Data for Order ' . $order->getIncrementId() . ' - Payment Data: ' . json_encode($order->getPayment()->getData()));

            return $forterResponse->status ? true : false;
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
