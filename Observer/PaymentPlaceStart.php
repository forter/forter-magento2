<?php
namespace Forter\Forter\Observer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\AuthRequestBuilder;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestHandler\Decline;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote\Item;

class PaymentPlaceStart implements ObserverInterface
{
    const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';

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

        if ($response->action == 'decline') {
            $this->decline->handlePreTransactionDescision();
        }

        return true;
    }
}
