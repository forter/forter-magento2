<?php

namespace Forter\Forter\Observer\Customer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\RequestBuilder\RequestPrepare;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

class CustomerSaveAfterDataObject implements ObserverInterface
{
    const API_ENDPOINT = 'https://api.forter-secure.com/v2/accounts/update/';

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        Session $customerSession,
        AbstractApi $abstractApi,
        RequestPrepare $requestPrepare,
        RemoteAddress $remoteAddress,
        ForterConfig $forterConfig
    ) {
        $this->forterConfig = $forterConfig;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->abstractApi = $abstractApi;
        $this->requestPrepare = $requestPrepare;
        $this->remoteAddress = $remoteAddress;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        $customer = $observer->getEvent()->getCustomer();
        $url = self::API_ENDPOINT . $this->customerSession->getId();
        /*$json = [
          "accountId" => $customer->getId(),
          "eventTime" => time(),
          "connectionInformation" => $this->requestPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress()),
          "accountData" => $this->requestPrepare->
        ];

        $response = $this->abstractApi->sendApiRequest($url,json_encode($json));*/

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/lalal.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $customer = $this->customerRepository->getById($this->customerSession->getId());
        $addresses = $customer->getAddresses();

        foreach ($addresses as $address) {
            $customerAddress[] = $address->getCity();
        }

        $logger->info(json_encode($customerAddress));
    }
}
