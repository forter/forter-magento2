<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\Queue;
use Forter\Forter\Model\RequestBuilder\BasicInfo;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
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
    public const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';

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
     * @var RemoteAddress
     */
    private $remote;
    /**
     * @var RequestInterface
     */
    private $request;

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
        ForterLogger $forterLogger,
        RequestInterface $request
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
        $this->request = $request;
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

            $cardData = $this->getCardExtraData();
            $this->setPaymentExtraCardData($order, $cardData);

            $this->forterLogger->forterConfig->log('Connection Information for Order ' . $order->getIncrementId() . ' : ' . json_encode($connectionInformation));

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
            $forterResponse = $this->abstractApi->sendApiRequest($url, json_encode($data));

            $this->forterLogger->forterConfig->log('BEFORE_PAYMENT_ACTION Order ' . $order->getIncrementId() . ' Data: ' . json_encode($data));

            $this->abstractApi->sendOrderStatus($order);

            $order->setForterResponse($forterResponse);

            $this->forterLogger->forterConfig->log($forterResponse);

            $forterResponse = json_decode($forterResponse);

            if ($forterResponse->status != 'success' || !isset($forterResponse->action)) {
                $this->registry->register('forter_pre_decision', 'error');
                $order->setForterStatus('error');
                $message = new ForterLoggerMessage($this->config->getSiteId(), $order->getIncrementId(), 'Response Error - Pre-Auth');
                $message->metaData->order = $order->getData();
                $message->metaData->payment = $order->getPayment()->getData();
                $message->metaData->forterDecision = $forterResponse->action;
                $this->forterLogger->SendLog($message);
                return;
            }

            $this->registry->register('forter_pre_decision', $forterResponse->action);
            $order->setForterStatus($forterResponse->action);
            $order->setForterReason($forterResponse->reasonCode);
            $order->addStatusHistoryComment(__('Forter (pre) Decision: %1%2', $forterResponse->action, $this->config->getResponseRecommendationsNote($forterResponse)));
            $this->abstractApi->triggerRecommendationEvents($forterResponse, $order, 'pre');
            if ($forterResponse->action != 'decline') {
                return;
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
        $message = new ForterLoggerMessage($this->config->getSiteId(), $order->getIncrementId(), 'Handle Response - Pre-Auth');
        $message->metaData->order = $order->getData();
        $message->metaData->payment = $order->getPayment()->getData();
        $message->metaData->forterDecision = $forterResponse->action;
        $this->forterLogger->SendLog($message);
        $this->decline->handlePreTransactionDescision($order);
    }

    /**
     * @return array
     */
    public function getCardExtraData()
    {
        $cardData = [];
        $requestData = json_decode($this->request->getContent());

        if ($requestData && isset($requestData->paymentMethod->additional_data)) {
            $cardData['cardBin'] = $requestData->paymentMethod->additional_data->cardBin ?? null;
            $cardData['cardLast4'] = $requestData->paymentMethod->additional_data->cardLast4 ?? null;
        }

        return $cardData;
    }

    /**
     * Sets additional information and ccLast4 for payment based on card data.
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $cardData
     */
    private function setPaymentExtraCardData(\Magento\Sales\Model\Order $order, array $cardData)
    {
        if (isset($cardData['cardBin']) && $cardData['cardBin']) {
            if ($order->getPayment()->getMethod() === 'adyen_cc') {
                $order->getPayment()->setAdditionalInformation('adyen_card_bin', $cardData['cardBin']);
            }
            $order->getPayment()->setAdditionalInformation('forter_cc_bin', $cardData['cardBin']);
        }

        if (isset($cardData['cardLast4']) && $cardData['cardLast4']) {
            $order->getPayment()->setCcLast4($cardData['cardLast4']);
        }
    }

}
