<?php

namespace Forter\Forter\Observer\Customer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Forter\Forter\Model\AbstractApi;
use \Forter\Forter\Model\RequestBuilder\RequestPrepare;


class CustomerLogin implements ObserverInterface
{
    const API_ENDPOINT = 'https://api.forter-secure.com/v2/accounts/login/';

    public function __construct(
      AbstractApi $abstractApi,
      RequestPrepare $requestPrepare,
      RemoteAddress $remoteAddress
    ) {
      $this->abstractApi = $abstractApi;
      $this->requestPrepare = $requestPrepare;
      $this->remoteAddress = $remoteAddress;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
      $customer = $observer->getEvent()->getCustomer();
      $url = self::API_ENDPOINT . $customer->getId();
      $json = [
        "accountId" => $customer->getId(),
        "eventTime" => time(),
        "connectionInformation" => $this->requestPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress()),
        "loginStatus" => "SUCCESS",
        "loginMethodType" => "PASSWORD"
      ];

      $response = $this->abstractApi->sendApiRequest($url,json_encode($json));
    }

}
