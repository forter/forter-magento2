<?php

namespace Forter\Forter\Plugin\Customer\Model;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\BasicInfo;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AccountManagement as AccountManagementOriginal;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
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
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * AccountManagement constructor.
     * @param Session $customerSession
     * @param CustomerFactory $customer
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
        Session $customerSession,
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
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function beforeResetPassword(
        AccountManagementOriginal $accountManagement,
        $email,
        $resetToken,
        $newPassword = null
    ) {
        if (!$this->forterConfig->isEnabled() || !$this->forterConfig->isAccountTouchpointEnabled()) {
            return false;
        }

        try {
            $customer = $this->localMatchCustomerByRpToken($resetToken);
            $headers = getallheaders();
            if ($customer) {
                $json = [
                  "accountId" => $customer->getId(),
                  "eventTime" => time(),
                  "connectionInformation" => $this->basicInfo->getConnectionInformation($this->remoteAddress->getRemoteAddress(), $headers),
                  "passwordUpdateTrigger" => 'USER_FORGOT_PASSWORD'
                ];

                $url = self::PASSWORD_API_ENDPOINT . $customer->getId();
                $this->abstractApi->sendApiRequest($url, json_encode($json));
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
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
            $headers = getallheaders();
            $websiteID = $this->storemanager->getStore()->getWebsiteId();
            $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($email);
            if ($customer) {
                $json = [
                  "accountId" => $customer->getId(),
                  "eventTime" => time(),
                  "connectionInformation" => $this->basicInfo->getConnectionInformation($this->remoteAddress->getRemoteAddress(), $headers),
                  "passwordUpdateTrigger" => 'LOGGED_IN_USER'
                ];

                $url = self::PASSWORD_API_ENDPOINT . $customer->getId();
                $this->abstractApi->sendApiRequest($url, json_encode($json));
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
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
            $headers = getallheaders();

            $json = [
                "accountId" => $customer->getId(),
                "eventTime" => time(),
                "connectionInformation" => $this->basicInfo->getConnectionInformation($this->remoteAddress->getRemoteAddress(), $headers),
                "loginStatus" => $loginStatus,
                "loginMethodType" => "PASSWORD"
            ];

            if ($customer) {
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
}
