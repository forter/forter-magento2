<?php

namespace Forter\Forter\Observer\Customer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\BasicInfo as BasicInfoPrepere;
use Forter\Forter\Model\RequestBuilder\Customer as CustomerPrepere;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class AddressSaveAfter
 * @package Forter\Forter\Observer\Customer
 */
class AddressSaveAfter implements ObserverInterface
{

  /**
   *
   */
    const API_ENDPOINT = "https://api.forter-secure.com/v2/accounts/update/";
    /**
     * @var PaymentPrepere
     */
    private $basicInfoPrepare;
    /**
     * @var CustomerPrepere
     */
    private $customerPrepere;
    /**
     * @var State
     */
    private $state;
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;
    /**
     * @var RemoteAddress
     */
    private $remoteAddress;

    /**
     * CustomerRepository constructor.
     * @param State $state
     * @param BasicInfoPrepere $basicInfoPrepare
     * @param CustomerPrepere $customerPrepere
     * @param AbstractApi $abstractApi
     * @param GroupRepositoryInterface $groupRepository
     * @param RemoteAddress $remoteAddress
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        State $state,
        BasicInfoPrepere $basicInfoPrepare,
        CustomerPrepere $customerPrepere,
        AbstractApi $abstractApi,
        Config $forterConfig,
        GroupRepositoryInterface $groupRepository,
        RemoteAddress $remoteAddress,
        StoreManagerInterface $storeManager
    ) {
        $this->basicInfoPrepare = $basicInfoPrepare;
        $this->customerPrepere = $customerPrepere;
        $this->state = $state;
        $this->abstractApi = $abstractApi;
        $this->storeManager = $storeManager;
        $this->forterConfig = $forterConfig;
        $this->groupRepository = $groupRepository;
        $this->remoteAddress = $remoteAddress;
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
            $customer = $observer->getCustomerAddress()->getCustomer();

            $headers = getallheaders();
            $customerGroup = $this->groupRepository->getById($customer->getGroupId());
            $customerAccountData = $this->customerPrepere->getCustomerAccountData(null, $customer);
            $areaCode = ($this->state->getAreaCode() == 'frontend' ? 'END_USER' : 'MERCHANT_ADMIN');
            $type = ($this->state->getAreaCode() == 'frontend' ? 'PRIVATE' : 'MERCHANT_EMPLOYEE');

            $json = [
              "accountId" => $customer->getId(),
              "eventTime" => time(),
              "connectionInformation" => $this->basicInfoPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress(), $headers),
              "accountData" => [
                "type" => $type,
                "statusChangeBy" => $areaCode,
                "addressesInAccount" => $this->forterConfig->getAddressInAccount($customer->getAddresses()),
                "customerEngagement" => $customerAccountData['customerEngagement'],
                "status" => $customerAccountData['status']
              ],
              "merchantIdentifiers" => [
                'merchantId' => $this->storeManager->getStore()->getId(),
                'merchantDomain' => $this->storeManager->getStore()->getUrl(),
                'merchantName' => $this->storeManager->getStore()->getName()
              ]
            ];

            $url = self::API_ENDPOINT . $customer->getId();
            $this->abstractApi->sendApiRequest($url, json_encode($json));

            return true;
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }
}
