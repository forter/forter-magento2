<?php

namespace Forter\Forter\Observer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class ConfigObserver
 * @package Forter\Forter\Observer
 */
class ConfigObserver implements \Magento\Framework\Event\ObserverInterface
{
    const Test_Api = "https://api.forter-secure.com/credentials/test";
    const SETTINGS_API_ENDPOINT = 'https://api.forter-secure.com/ext/settings/';
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var Config
     */
    private $forterConfig;
    /**
     * @var Config
     */
    private $storeManagerInterface;
    /**
     * ConfigObserver constructor.
     * @param AbstractApi $abstractApi
     * @param Config $forterConfig
     */
    public function __construct(
        WriterInterface $writeInterface,
        AbstractApi $abstractApi,
        Config $forterConfig,
        StoreManagerInterface $storeManagerInterface
    ) {
        $this->writeInterface = $writeInterface;
        $this->abstractApi = $abstractApi;
        $this->forterConfig = $forterConfig;
        $this->storeManagerInterface = $storeManagerInterface;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            $website = $observer->getWebsite();
            $store   = $observer->getStore();
            $scopeData['scope'] = null;
            $scopeData['scope_id'] = null;

            if ($website) {
                $scopeData['scope']    = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES;
                $scopeData['scope_id'] = $website;
            }

            if ($store) {
                $scopeData['scope']    = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
                $scopeData['scope_id'] = $store;
            }

            $this->validateCredentials();

            $json = [
              "basic_settings" => [
                "active" => $this->forterConfig->isEnabled($scopeData['scope'], $scopeData['scope_id']),
                "site_id" => $this->forterConfig->getSiteId($scopeData['scope'], $scopeData['scope_id']),
                "secret_key" => $this->forterConfig->getSecretKey($scopeData['scope'], $scopeData['scope_id']),
                "module_version" => $this->forterConfig->getModuleVersion(),
                "forter_api_version" => $this->forterConfig->getApiVersion($scopeData['scope'], $scopeData['scope_id']),
                "debug_mode" => $this->forterConfig->isDebugEnabled($scopeData['scope'], $scopeData['scope_id']),
                "enhanced_data_mode" => $this->forterConfig->isSandboxMode($scopeData['scope'], $scopeData['scope_id'])
              ],
              "order_validation_settings" => [
                "order_validation_location" => $this->forterConfig->getPrePostDecisionMsg('pre_post_select', $scopeData['scope'], $scopeData['scope_id']),
                "pre" => [
                  "action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_pre', $scopeData['scope'], $scopeData['scope_id']),
                  "success_page_message" => $this->forterConfig->getPreThanksMsg($scopeData['scope'], $scopeData['scope_id'])
                ],
                "post" => [
                  "action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_post', $scopeData['scope'], $scopeData['scope_id']),
                  "action_on_approve" => $this->forterConfig->getPrePostDecisionMsg('approve_post', $scopeData['scope'], $scopeData['scope_id']),
                  "action_on_not_review" => $this->forterConfig->getPrePostDecisionMsg('not_review_post', $scopeData['scope'], $scopeData['scope_id']),
                  "success_page_message" => $this->forterConfig->getPostThanksMsg($scopeData['scope'], $scopeData['scope_id'])
                ],
                "pre_and_post" => [
                  "pre_action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_pre', $scopeData['scope'], $scopeData['scope_id']),
                  "pre_success_page_message" => $this->forterConfig->getPreThanksMsg($scopeData['scope'], $scopeData['scope_id']),
                  "post_action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_post', $scopeData['scope'], $scopeData['scope_id']),
                  "post_action_on_approve" => $this->forterConfig->getPrePostDecisionMsg('approve_post', $scopeData['scope'], $scopeData['scope_id']),
                  "post_action_on_not_review" => $this->forterConfig->getPrePostDecisionMsg('not_review_post', $scopeData['scope'], $scopeData['scope_id']),
                  "post_success_page_message" => $this->forterConfig->getPostThanksMsg($scopeData['scope'], $scopeData['scope_id'])
                ],
                "cron" => [
                  "action_on_approve" => $this->forterConfig->getPrePostDecisionMsg('approve_cron', $scopeData['scope'], $scopeData['scope_id']),
                  "action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_cron', $scopeData['scope'], $scopeData['scope_id']),
                  "action_on_not_review" => $this->forterConfig->getPrePostDecisionMsg('not_review_cron', $scopeData['scope'], $scopeData['scope_id'])
                ]
              ],
              "store" => [
                "storeId" => $this->forterConfig->getStoreId($scopeData['scope_id'])
              ],
              "connection_information" => $this->forterConfig->getTimeOutSettings($scopeData['scope'], $scopeData['scope_id']),
              "email_setting_on_decline" => $this->forterConfig->getEmailSettingsOnDecline($scopeData['scope'], $scopeData['scope_id']),
              "advanced_settings" => [
                "enable_order_holding" => $this->forterConfig->isHoldingOrdersEnabled($scopeData['scope'], $scopeData['scope_id']),
                "enable_decision_change_controller" => $this->forterConfig->isDecisionControllerEnabled($scopeData['scope'], $scopeData['scope_id']),
                "hold_order_on_pending_decision" => $this->forterConfig->isPendingOnHoldEnabled($scopeData['scope'], $scopeData['scope_id']),
                "enable_order_fulfillment" => $this->forterConfig->isOrderFulfillmentEnable($scopeData['scope'], $scopeData['scope_id']),
                "enable_phone_order" => $this->forterConfig->isPhoneOrderEnabled($scopeData['scope'], $scopeData['scope_id']),
                "verification_results_mapping" => $this->forterConfig->getVerificationResultsMap($scopeData['scope'], $scopeData['scope_id']),
              ],
              "advanced_settings_pre_auth" => [
                "enable_creditcard_listener" => $this->forterConfig->isCcListenerActive($scopeData['scope'], $scopeData['scope_id']),
                "enable_listener_for_last4cc" => $this->forterConfig->getAllowLast4CCListener($scopeData['scope'], $scopeData['scope_id']),
                "enable_listener_for_bin" => $this->forterConfig->getAllowBinListener($scopeData['scope'], $scopeData['scope_id']),
                "class_or_id_identifier_for_the_listener" => $this->forterConfig->getElementToObserve($scopeData['scope'], $scopeData['scope_id'])
              ],
              "eventTime" => time()
            ];

            $url = self::SETTINGS_API_ENDPOINT;
            $this->abstractApi->sendApiRequest($url, json_encode($json));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    private function validateCredentials()
    {
        $url = self::Test_Api;
        $response = $this->abstractApi->sendApiRequest($url, null, 'get');
        $response = json_decode($response);
        if ($response->status == 'failed') {
            throw new \Exception('Site ID and Secret Key are incorrect');
        }
    }
}
