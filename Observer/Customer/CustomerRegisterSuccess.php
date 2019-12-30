<?php

namespace Forter\Forter\Observer\Customer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\RequestBuilder\BasicInfo;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Class CustomerRegisterSuccess
 * @package Forter\Forter\Observer\Customer
 */
class CustomerRegisterSuccess implements ObserverInterface
{
    /**
     *
     */
    const API_ENDPOINT = 'https://api.forter-secure.com/v2/accounts/signup/';
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var BasicInfo
     */
    private $basicInfo;
    /**
     * @var RemoteAddress
     */
    private $remoteAddress;
    /**
     * @var ForterConfig
     */
    private $forterConfig;

    /**
     * CustomerRegisterSuccess constructor.
     * @param AbstractApi $abstractApi
     * @param BasicInfo $basicInfo
     * @param RemoteAddress $remoteAddress
     * @param ForterConfig $forterConfig
     */
    public function __construct(
        AbstractApi $abstractApi,
        BasicInfo $basicInfo,
        RemoteAddress $remoteAddress,
        ForterConfig $forterConfig
    ) {
        $this->abstractApi = $abstractApi;
        $this->basicInfo = $basicInfo;
        $this->remoteAddress = $remoteAddress;
        $this->forterConfig = $forterConfig;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->forterConfig->isEnabled() || !$this->forterConfig->isAccountTouchpointEnabled()) {
            return false;
        }

        try {
            $customer = $observer->getEvent()->getCustomer();
            $json = [
              "accountId" => $customer->getId(),
              "eventTime" => time(),
              "connectionInformation" => $this->basicInfo->getConnectionInformation($this->remoteAddress->getRemoteAddress())
            ];

            $url = self::API_ENDPOINT . $customer->getId();
            $this->abstractApi->sendApiRequest($url, json_encode($json));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }
}
