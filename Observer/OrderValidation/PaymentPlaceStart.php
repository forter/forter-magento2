<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\Queue;
use Forter\Forter\Model\RequestBuilder\BasicInfo;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote\Item;

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
        Registry $registry
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
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            if (!$this->config->isEnabled()) {
                return;
            }

            if ($this->registry->registry('forter_pre_decision')) {
                $this->registry->unregister('forter_pre_decision');
            }

            $order = $observer->getEvent()->getPayment()->getOrder();

            if (!($connectionInformation = $this->basicInfo->getConnectionInformation($order->getRemoteIp() ?: $this->remote->getRemoteAddress()))) {
                return;
            }

            $order->getPayment()->setAdditionalInformation('forter_client_details', $connectionInformation);

            if ($this->config->getIsPost() && !$this->config->getIsPreAndPost()) {
                return;
            }

            if ($this->config->getIsCron()) {
                $currentTime = $this->dateTime->gmtDate();

                $this->queue->setEntityType('pre_sync_order');
                $this->queue->setStoreId($this->config->getStoreId());
                $this->queue->setIncrementId($order->getIncrementId());
                $this->queue->setSyncFlag(0);
                $this->queue->setSyncDate($currentTime);
                $this->queue->save();
                return;
            }

            $data = $this->requestBuilderOrder->buildTransaction($order, 'BEFORE_PAYMENT_ACTION');

            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));

            $order->setForterResponse($response);

            $response = json_decode($response);

            if ($response->status != 'success' || !isset($response->action)) {
                $this->registry->register('forter_pre_decision', 'error');
                $order->setForterStatus('error');
                return;
            }

            $this->registry->register('forter_pre_decision', $response->action);
            $order->setForterStatus($response->action);

            if ($response->action != 'decline') {
                return;
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }

        $this->decline->handlePreTransactionDescision();
    }
}
