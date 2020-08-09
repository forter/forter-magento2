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
use Magento\Framework\Message\ManagerInterface;
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
     * @var BasicInfo
     */
    private $basicInfo;

    /**
     * PaymentPlaceStart constructor.
     * @param Decline $decline
     * @param ManagerInterface $messageManager
     * @param CheckoutSession $checkoutSession
     * @param AbstractApi $abstractApi
     * @param Config $config
     * @param Order $requestBuilderOrder
     * @param Item $modelCartItem
     */
    public function __construct(
        Queue $queue,
        Decline $decline,
        ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        AbstractApi $abstractApi,
        Config $config,
        Order $requestBuilderOrder,
        Item $modelCartItem,
        BasicInfo $basicInfo
    ) {
        $this->queue = $queue;
        $this->decline = $decline;
        $this->checkoutSession = $checkoutSession;
        $this->modelCartItem = $modelCartItem;
        $this->abstractApi = $abstractApi;
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->requestBuilderOrder = $requestBuilderOrder;
        $this->basicInfo = $basicInfo;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled() || $this->config->getIsPost()) {
            return;
        }

        try {
            $order = $observer->getEvent()->getPayment()->getOrder();
            if ($this->config->getIsCron()) {
                $connectionInformation = $this->basicInfo->getConnectionInformation($order->getRemoteIp(), getallheaders());
                $order->setForterClientDetails(json_encode($connectionInformation));

                $this->queue->setEntityType('pre_sync_order');
                $this->queue->setStoreId($this->config->getStoreId());
                $this->queue->setIncrementId($order->getIncrementId());
                $this->queue->setSyncFlag(0);
                $this->queue->save();
                return;
            }

            $data = $this->requestBuilderOrder->buildTransaction($order, 'BEFORE_PAYMENT_ACTION');

            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));

            $order->setForterResponse($response);

            $response = json_decode($response);

            if ($response->status != 'success' || !isset($response->action)) {
                $order->setForterStatus('error');
                return;
            }

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
