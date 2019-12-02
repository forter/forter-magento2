<?php

namespace Forter\Forter\Plugin;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\RequestPrepare;
use Magento\Customer\Model\AccountManagement as AccountManagementOriginal;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
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
        RemoteAddress $remoteAddress
    ) {
        $this->customerSession = $customerSession;
        $this->customer = $customer;
        $this->messageManager = $messageManager;
        $this->storemanager = $store;
        $this->abstractApi = $abstractApi;
        $this->requestPrepare = $requestPrepare;
        $this->remoteAddress = $remoteAddress;
        $this->forterConfig = $forterConfig;
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

        if (!$email) {
            $customer = $this->matchCustomerByRpToken($resetToken);
            $email = $customer->getEmail();
        } else {
            $websiteID = $this->storemanager->getStore()->getWebsiteId();
            $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($email);
        }

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
}
