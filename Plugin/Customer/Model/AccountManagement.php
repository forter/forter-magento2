<?php

namespace Forter\Forter\Plugin\Customer\Model;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\RequestPrepare;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\AccountManagement as AccountManagementOriginal;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class AccountManagement
{
    const PASSWORD_API_ENDPOINT = 'https://api.forter-secure.com/v2/accounts/reset-password/';

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    public function __construct(
        Session $customerSession,
        CustomerFactory $customer,
        StoreManagerInterface $store,
        AbstractApi $abstractApi,
        RequestPrepare $requestPrepare,
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
        $this->requestPrepare = $requestPrepare;
        $this->remoteAddress = $remoteAddress;
        $this->forterConfig = $forterConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder
            ?: ObjectManager::getInstance()->get(SearchCriteriaBuilder::class);
    }

    public function beforeResetPassword(
        AccountManagementOriginal $accountManagement,
        $email,
        $resetToken,
        $newPassword = null
    ) {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        $customer = $this->localMatchCustomerByRpToken($resetToken);

        if ($customer) {
            $json = [
              "accountId" => $customer->getId(),
              "eventTime" => time(),
              "connectionInformation" => $this->requestPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress()),
              "passwordUpdateTrigger" => 'USER_FORGOT_PASSWORD'
            ];

            try {
                $url = self::PASSWORD_API_ENDPOINT . $customer->getId();
                $response = $this->abstractApi->sendApiRequest($url, json_encode($json));
            } catch (\Exception $e) {
                $this->abstractApi->reportToForterOnCatch($e);
                throw new \Exception($e->getMessage());
            }
        }
    }

    public function beforeChangePassword(
        AccountManagementOriginal $accountManagement,
        $email,
        $currentPassword,
        $newPassword
    ) {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }
        $websiteID = $this->storemanager->getStore()->getWebsiteId();
        $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($email);
        if ($customer) {
            $json = [
          "accountId" => $customer->getId(),
          "eventTime" => time(),
          "connectionInformation" => $this->requestPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress()),
          "passwordUpdateTrigger" => 'LOGGED_IN_USER'
        ];

            try {
                $url = self::PASSWORD_API_ENDPOINT . $customer->getId();
                $response = $this->abstractApi->sendApiRequest($url, json_encode($json));
            } catch (\Exception $e) {
                $this->abstractApi->reportToForterOnCatch($e);
                throw new \Exception($e->getMessage());
            }
        }
    }

    public function aroundAuthenticate(AccountManagementOriginal $subject, callable $proceed, $username, $password)
    {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        $websiteID = $this->storemanager->getStore()->getWebsiteId();
        $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($username);

        try {
            $result = $proceed($username, $password);
        } catch (\Exception $e) {
            $this->sendLoginAttempt('FAILED', $customer);
            throw $e;
        }

        $this->sendLoginAttempt('SUCCESS', $customer);

        return $result;
    }

    private function sendLoginAttempt($loginStatus, $customer)
    {
        try {
            $json = [
              "accountId" => $customer->getId(),
              "eventTime" => time(),
              "connectionInformation" => $this->requestPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress()),
              "loginStatus" => $loginStatus,
              "loginMethodType" => "PASSWORD"
            ];

            if ($customer) {
                $url = 'https://api.forter-secure.com/v2/accounts/login/' . $customer->getId();
                $response = $this->abstractApi->sendApiRequest($url, json_encode($json));
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    /**
     * Match a customer by their RP token.
     *
     * @param string $rpToken
     * @throws ExpiredException
     * @throws NoSuchEntityException
     *
     * @return CustomerInterface
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
