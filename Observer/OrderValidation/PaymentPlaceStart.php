<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
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
     * @var Config
     */
    private $config;
    /**
     * @var Order
     */
    private $requestBuilderOrder;

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
        Decline $decline,
        ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        AbstractApi $abstractApi,
        Config $config,
        Order $requestBuilderOrder,
        Item $modelCartItem
    ) {
        $this->decline = $decline;
        $this->checkoutSession = $checkoutSession;
        $this->modelCartItem = $modelCartItem;
        $this->abstractApi = $abstractApi;
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->requestBuilderOrder = $requestBuilderOrder;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $payment = $observer->getEvent()->getPayment()->getCcLast4();
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test-for.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('test +' . $payment);
        if (!$this->config->isEnabled() || $this->config->getIsPost()) {
            return false;
        }

        try {
            $order = $observer->getEvent()->getPayment()->getOrder();

            $data = $this->requestBuilderOrder->buildTransaction($order, 'BEFORE_PAYMENT_ACTION');

            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));

            $order->setForterResponse($response);

            $response = json_decode($response);

            if ($response->status != 'success' || !isset($response->action)) {
                $order->setForterStatus('error');
                return false;
            }

            $order->setForterStatus($response->action);

            if ($response->action != 'decline') {
                return true;
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }

        $this->decline->handlePreTransactionDescision();
        return true;
    }
}
