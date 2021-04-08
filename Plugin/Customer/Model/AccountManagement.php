<?php

namespace Forter\Forter\Plugin\Customer\Model;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\BasicInfo;
use Forter\Forter\Model\RequestBuilder\Customer as CustomerPrepere;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AccountManagement as AccountManagementOriginal;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\ExpiredException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class AccountManagement
 * @package Forter\Forter\Plugin\Customer\Model
 */
class AccountManagement
{
    /**
     *
     */
    const PASSWORD_API_ENDPOINT = 'https://api.forter-secure.com/v2/accounts/reset-password/';
    /**
     * @var CustomerPrepere
     */
    private $customerPrepere;
    /**
     * @var State
     */
    private $state;
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * AccountManagement constructor.
     * @param Session $customerSession
     * @param State $state
     * @param CustomerFactory $customer
     * @param CustomerPrepere $customerPrepere
     * @param StoreManagerInterface $store
     * @param AbstractApi $abstractApi
     * @param BasicInfo $basicInfo
     * @param ManagerInterface $messageManager
     * @param Config $forterConfig
     * @param RemoteAddress $remoteAddress
     * @param SearchCriteriaBuilder|null $searchCriteriaBuilder
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        State $state,
        Session $customerSession,
        CustomerPrepere $customerPrepere,
        CustomerFactory $customer,
        StoreManagerInterface $store,
        AbstractApi $abstractApi,
        BasicInfo $basicInfo,
        ManagerInterface $messageManager,
        Config $forterConfig,
        RemoteAddress $remoteAddress,
        SearchCriteriaBuilder $searchCriteriaBuilder = null,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerSession = $customerSession;
        $this->customerPrepere = $customerPrepere;
        $this->state = $state;
        $this->customer = $customer;
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->storemanager = $store;
        $this->abstractApi = $abstractApi;
        $this->basicInfo = $basicInfo;
        $this->remoteAddress = $remoteAddress;
        $this->forterConfig = $forterConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder
            ?: ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
    }

    /**
     * @param AccountManagementOriginal $accountManagement
     * @param $email
     * @param $resetToken
     * @param null $newPassword
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function beforeResetPassword(
        AccountManagementOriginal $accountManagement,
        $email,
        $resetToken,
        $newPassword = null
    ) {
        if (!$this->forterConfig->isEnabled() || !$this->forterConfig->isAccountTouchpointEnabled()) {
            return;
        }

        try {
            $customer = $this->localMatchCustomerByRpToken($resetToken);
            $connectionInformation = $this->getConnectionInformation();
            if ($customer && $connectionInformation) {
                $json = [
                  "accountId" => $customer->getId(),
                  "eventTime" => time(),
                  "connectionInformation" => $connectionInformation,
                  "passwordUpdateTrigger" => 'USER_FORGOT_PASSWORD'
                ];

                $url = self::PASSWORD_API_ENDPOINT . $customer->getId();
                $this->abstractApi->sendApiRequest($url, json_encode($json));
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    /**
     * @param AccountManagementOriginal $accountManagement
     * @param $email
     * @param $currentPassword
     * @param $newPassword
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function beforeChangePassword(
        AccountManagementOriginal $accountManagement,
        $email,
        $currentPassword,
        $newPassword
    ) {
        if (!$this->forterConfig->isEnabled()  || !$this->forterConfig->isAccountTouchpointEnabled()) {
            return false;
        }

        try {
            $websiteID = $this->storemanager->getStore()->getWebsiteId();
            $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($email);
            $connectionInformation = $this->getConnectionInformation();
            if ($customer && $connectionInformation) {
                $json = [
                  "accountId" => $customer->getId(),
                  "eventTime" => time(),
                  "connectionInformation" => $connectionInformation,
                  "passwordUpdateTrigger" => 'LOGGED_IN_USER'
                ];

                $url = self::PASSWORD_API_ENDPOINT . $customer->getId();
                $this->abstractApi->sendApiRequest($url, json_encode($json));
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    /**
     * @param AccountManagementOriginal $subject
     * @param callable $proceed
     * @param $username
     * @param $password
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function aroundAuthenticate(AccountManagementOriginal $subject, callable $proceed, $username, $password)
    {
        if (!$this->forterConfig->isEnabled()  || !$this->forterConfig->isAccountTouchpointEnabled()) {
            $result = $proceed($username, $password);
            return $result;
        }

        try {
            $websiteID = $this->storemanager->getStore()->getWebsiteId();
            $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($username);
            $result = $proceed($username, $password);
            $this->sendLoginAttempt('SUCCESS', $customer);

            return $result;
        } catch (\Exception $e) {
            $this->sendLoginAttempt('FAILED', $customer);
            throw $e;
        }
    }

    /**
     * @param $loginStatus
     * @param $customer
     */
    private function sendLoginAttempt($loginStatus, $customer)
    {
        try {
            $customerAccountData = $this->customerPrepere->getCustomerAccountData(null, $customer);
            $areaCode = ($this->state->getAreaCode() == 'frontend' ? 'END_USER' : 'MERCHANT_ADMIN');
            $type = ($this->state->getAreaCode() == 'frontend' ? 'PRIVATE' : 'MERCHANT_EMPLOYEE');
            $connectionInformation = $this->getConnectionInformation();

            if ($customer) {
                $json = [
                    "accountId" => $customer->getId(),
                    "eventTime" => time(),
                    "connectionInformation" => $connectionInformation,
                    "accountData" => [
                      "type" => $type,
                      "statusChangeBy" => $areaCode,
                      "addressesInAccount" => $this->forterConfig->getAddressInAccount($customer->getAddresses()),
                      "customerEngagement" => $customerAccountData['customerEngagement'],
                      "status" => $customerAccountData['status']
                    ],
                    "loginStatus" => $loginStatus,
                    "loginMethodType" => "PASSWORD"
                ];

                $url = 'https://api.forter-secure.com/v2/accounts/login/' . $customer->getId();
                $this->abstractApi->sendApiRequest($url, json_encode($json));
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    /**
     * Match a customer by their RP token.
     *
     * @param string $rpToken
     * @return CustomerInterface
     * @throws NoSuchEntityException
     *
     * @throws ExpiredException
     * @throws LocalizedException
     */
    private function localMatchCustomerByRpToken(string $rpToken)
    {
        $this->searchCriteriaBuilder->addFilter(
            'rp_token',
            $rpToken
        );
        $this->searchCriteriaBuilder->setPageSize(1);
        $found = $this->customerRepository->getList(
            $this->searchCriteriaBuilder->create()
        );
        if ($found->getTotalCount() > 1) {
            //Failed to generated unique RP token
            throw new ExpiredException(
                new Phrase('Reset password token expired.')
            );
        }
        if ($found->getTotalCount() === 0) {
            //Customer with such token not found.
            throw NoSuchEntityException::singleField(
                'rp_token',
                $rpToken
            );
        }
        //Unique customer found.
        return $found->getItems()[0];
    }

    /**
     * @method getConnectionInformation
     * @return array
     */
    private function getConnectionInformation()
    {
        return $this->basicInfo->getConnectionInformation($this->remoteAddress->getRemoteAddress());
    }
}
