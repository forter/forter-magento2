<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\AuthRequestBuilder;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestHandler\Decline;
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
     * @var AuthRequestBuilder
     */
    private $authRequestBuilder;

    /**
     * PaymentPlaceStart constructor.
     * @param Decline $decline
     * @param ManagerInterface $messageManager
     * @param CheckoutSession $checkoutSession
     * @param AbstractApi $abstractApi
     * @param Config $config
     * @param AuthRequestBuilder $authRequestBuilder
     * @param Item $modelCartItem
     */
    public function __construct(
        Decline $decline,
        ManagerInterface $messageManager,
        CheckoutSession $checkoutSession,
        AbstractApi $abstractApi,
        Config $config,
        AuthRequestBuilder $authRequestBuilder,
        Item $modelCartItem
    ) {
        $this->decline = $decline;
        $this->checkoutSession = $checkoutSession;
        $this->modelCartItem = $modelCartItem;
        $this->abstractApi = $abstractApi;
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->authRequestBuilder = $authRequestBuilder;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled() || $this->config->getIsPost()) {
            return false;
        }

        $order = $observer->getEvent()->getPayment()->getOrder();
        $data = $this->authRequestBuilder->buildTransaction($order);

        try {
            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }

        $response = json_decode($response);

        if ($response->status == 'failed') {
            return true;
        }

        if ($response->action == 'decline' && $response->status == 'success') {
            $this->decline->handlePreTransactionDescision();
        }

        return true;
    }
}
