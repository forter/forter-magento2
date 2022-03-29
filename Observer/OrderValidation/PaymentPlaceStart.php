<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\Queue;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\RequestBuilder\BasicInfo;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\App\Emulation;

/**
 * Class PaymentPlaceStart
 * @package Forter\Forter\Observer\OrderValidation
 */
class PaymentPlaceStart implements ObserverInterface
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
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Item
     */
    private $modelCartItem;

    /**
     * @var AbstractApi
     */
    private $abstractApi;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Order
     */
    private $requestBuilderOrder;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var BasicInfo
     */
    private $basicInfo;

    /**
     * @var Registry
     */
    private $registry;
    /**
     * @var ForterLogger
     */
    private $forterLogger;
    /**
     * @var Emulation
     */
    private $emulate;

    /**
     * @method __construct
     * @param  RemoteAddress    $remote
     * @param  Queue            $queue
     * @param  Decline          $decline
     * @param  ManagerInterface $messageManager
     * @param  CheckoutSession  $checkoutSession
     * @param  AbstractApi      $abstractApi
     * @param  DateTime         $dateTime
     * @param  Config           $config
     * @param  Order            $requestBuilderOrder
     * @param  Item             $modelCartItem
     * @param  BasicInfo        $basicInfo
     * @param  Registry         $registry
     * @param  ForterLogger     $forterLogger
     */
    public function __construct(
        RemoteAddress $remote,
        Queue $queue,
        Decline $decline,
        ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        AbstractApi $abstractApi,
        DateTime $dateTime,
        Config $config,
        Order $requestBuilderOrder,
        Item $modelCartItem,
        BasicInfo $basicInfo,
        Registry $registry,
        Emulation $emulate,
        ForterLogger $forterLogger
    ) {
        $this->remote = $remote;
        $this->queue = $queue;
        $this->decline = $decline;
        $this->dateTime = $dateTime;
        $this->checkoutSession = $checkoutSession;
        $this->modelCartItem = $modelCartItem;
        $this->abstractApi = $abstractApi;
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->requestBuilderOrder = $requestBuilderOrder;
        $this->basicInfo = $basicInfo;
        $this->registry = $registry;
        $this->emulate = $emulate;
        $this->forterLogger = $forterLogger;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $this->emulate->stopEnvironmentEmulation();
            if (!$this->config->isEnabled()) {
                return;
            }

            if ($this->registry->registry('forter_pre_decision')) {
                $this->registry->unregister('forter_pre_decision');
            }

            $order = $observer->getEvent()->getPayment()->getOrder();
            $storeId = $order->getStoreId();
            // let bind the relevent store in case of multi store settings
            $this->emulate->startEnvironmentEmulation(
                $order->getStoreId(),
                'frontend',
                true
            );
            $connectionInformation = $this->basicInfo->getConnectionInformation(
                $order->getRemoteIp() ?: $this->remote->getRemoteAddress()
            );
            if (!$connectionInformation) {
                return;
            }

            $order->getPayment()->setAdditionalInformation('forter_client_details', $connectionInformation);

            if ($this->config->getIsPost() && !$this->config->getIsPreAndPost()) {
                return;
            }


            if ($this->config->getIsCron()) {
                $currentTime = $this->dateTime->gmtDate();

                $this->queue->setEntityType('pre_sync_order');
                $this->queue->setStoreId($storeId);
                $this->queue->setIncrementId($order->getIncrementId());
                $this->queue->setSyncFlag(0);
                $this->queue->setSyncDate($currentTime);
                $this->queue->save();
                return;
            }

            $data = $this->requestBuilderOrder->buildTransaction($order, 'BEFORE_PAYMENT_ACTION');

            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));

            $this->abstractApi->sendOrderStatus($order);

            $order->setForterResponse($response);

            $response = json_decode($response);

            if ($response->status != 'success' || !isset($response->action)) {
                $this->registry->register('forter_pre_decision', 'error');
                $order->setForterStatus('error');
                $message = new ForterLoggerMessage($this->config->getSiteId(),  $order->getIncrementId(), 'Response Error - Pre-Auth');
                $message->metaData->order = $order;
                $message->metaData->forterDecision = $response->action;
                $this->forterLogger->SendLog($message);
                return;
            }

            $this->registry->register('forter_pre_decision', $response->action);
            $order->setForterStatus($response->action);
            $order->addStatusHistoryComment(__('Forter (pre) Decision: %1', $response->action));
            if ($response->action != 'decline') {
                return;
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
        $message = new ForterLoggerMessage($this->config->getSiteId(),  $order->getIncrementId(), 'Handle Response - Pre-Auth');
        $message->metaData->order = $order;
        $message->metaData->forterDecision = $response->action;
        $this->forterLogger->SendLog($message);
        $this->decline->handlePreTransactionDescision($order);
    }
}
