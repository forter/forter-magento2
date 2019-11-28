<?php

namespace Forter\Forter\Plugin;

use Magento\Customer\Model\AccountManagement as AccountManagementOriginal;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\CustomerFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\RequestBuilder\RequestPrepare;

class AccountManagement
{

  const PASSWORD_API_ENDPOINT = 'https://api.forter-secure.com/v2/accounts/reset-password/';

  public function __construct(
      Session $customerSession,
      CustomerFactory $customer,
      StoreManagerInterface $store,
      AbstractApi $abstractApi,
      RequestPrepare $requestPrepare,
      RemoteAddress $remoteAddress
  ) {
      $this->customerSession = $customerSession;
      $this->customer = $customer;
      $this->storemanager = $store;
      $this->abstractApi = $abstractApi;
      $this->requestPrepare = $requestPrepare;
      $this->remoteAddress = $remoteAddress;
  }

  public function beforeInitiatePasswordReset(
      AccountManagementOriginal $accountManagement,
      $email,
      $template,
      $websiteId = null
  ) {

    $websiteID = $this->storemanager->getStore()->getWebsiteId();
    $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($email);
    if($customer){
      $url = self::PASSWORD_API_ENDPOINT . $customer->getId();
      $json = [
        "accountId" => $customer->getId(),
        "eventTime" => time(),
        "connectionInformation" => $this->requestPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress()),
        "passwordUpdateTrigger" => 'USER_FORGOT_PASSWORD'
      ];

      $response = $this->abstractApi->sendApiRequest($url,json_encode($json));
    }
  }

    public function beforeChangePassword(
        AccountManagementOriginal $accountManagement,
        $email,
        $currentPassword,
        $newPassword
    ) {

      $websiteID = $this->storemanager->getStore()->getWebsiteId();
      $customer = $this->customer->create()->setWebsiteId($websiteID)->loadByEmail($email);
      if($customer){
        $url = self::PASSWORD_API_ENDPOINT . $customer->getId();
        $json = [
          "accountId" => $customer->getId(),
          "eventTime" => time(),
          "connectionInformation" => $this->requestPrepare->getConnectionInformation($this->remoteAddress->getRemoteAddress()),
          "passwordUpdateTrigger" => 'LOGGED_IN_USER'
        ];

        $response = $this->abstractApi->sendApiRequest($url,json_encode($json));
      }
  }
}
