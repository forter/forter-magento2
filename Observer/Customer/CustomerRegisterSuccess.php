<?php

namespace Forter\Forter\Observer\Customer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\RequestBuilder\RequestPrepare;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class CustomerRegisterSuccess implements ObserverInterface
{
    const API_ENDPOINT = 'https://api.forter-secure.com/v2/accounts/signup/';

    public function __construct(
        AbstractApi $abstractApi,
        RequestPrepare $requestPrepare,
        RemoteAddress $remoteAddress,
        ForterConfig $forterConfig
    ) {
        $this->abstractApi = $abstractApi;
        $this->requestPrepare = $requestPrepare;
        $this->remoteAddress = $remoteAddress;
        $this->forterConfig = $forterConfig;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        $customer = $observer->getEvent()->getCustomer();
        $json = [
          "accountId" => $customer->getId(),
          "eventTime" => time(),
          "connectionInformation" => $this->requestPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress())
        ];

        try {
            $url = self::API_ENDPOINT . $customer->getId();
            $response = $this->abstractApi->sendApiRequest($url, json_encode($json));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }
}
