<?php
namespace Forter\Forter\Observer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\AuthRequestBuilder;
use Forter\Forter\Model\Config;
use Magento\Framework\Event\ObserverInterface;

class PaymentPlaceEnd implements ObserverInterface
{
    const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';

    public function __construct(
        AbstractApi $abstractApi,
        Config $config,
        AuthRequestBuilder $authRequestBuilder
    ) {
        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->authRequestBuilder = $authRequestBuilder;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled()) {
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

        if ($response) {
            $this->config->log('worked!!');
        }
    }
}
