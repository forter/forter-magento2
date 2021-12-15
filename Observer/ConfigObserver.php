<?php

namespace Forter\Forter\Observer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;

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
     * ConfigObserver constructor.
     * @param AbstractApi $abstractApi
     * @param Config $forterConfig
     */
    public function __construct(
        WriterInterface $writeInterface,
        AbstractApi $abstractApi,
        Config $forterConfig
    ) {
        $this->writeInterface = $writeInterface;
        $this->abstractApi = $abstractApi;
        $this->forterConfig = $forterConfig;
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
            $this->validateCredentials();

            $json = [
              "basic_settings" => [
                "active" => $this->forterConfig->isEnabled(),
                "site_id" => $this->forterConfig->getSiteId(),
                "secret_key" => $this->forterConfig->getSecretKey(),
                "module_version" => $this->forterConfig->getModuleVersion(),
                "forter_api_version" => $this->forterConfig->getApiVersion(),
                "debug_mode" => $this->forterConfig->isDebugEnabled(),
                "enhanced_data_mode" => $this->forterConfig->isSandboxMode()
              ],
              "order_validation_settings" => [
                "order_validation_location" => $this->forterConfig->getPrePostDecisionMsg('pre_post_select'),
                "pre" => [
                  "action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_pre'),
                  "success_page_message" => $this->forterConfig->getPreThanksMsg()
                ],
                "post" => [
                  "action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_post'),
                  "action_on_approve" => $this->forterConfig->getPrePostDecisionMsg('approve_post'),
                  "action_on_not_review" => $this->forterConfig->getPrePostDecisionMsg('not_review_post'),
                  "success_page_message" => $this->forterConfig->getPostThanksMsg()
                ],
                "pre_and_post" => [
                  "pre_action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_pre'),
                  "pre_success_page_message" => $this->forterConfig->getPreThanksMsg(),
                  "post_action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_post'),
                  "post_action_on_approve" => $this->forterConfig->getPrePostDecisionMsg('approve_post'),
                  "post_action_on_not_review" => $this->forterConfig->getPrePostDecisionMsg('not_review_post'),
                  "post_success_page_message" => $this->forterConfig->getPostThanksMsg()
                ],
                "cron" => [
                  "action_on_approve" => $this->forterConfig->getPrePostDecisionMsg('approve_cron'),
                  "action_on_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_cron'),
                  "action_on_not_review" => $this->forterConfig->getPrePostDecisionMsg('not_review_cron')
                ]
              ],
              "store" => [
                "storeId" => $this->forterConfig->getStoreId()
              ],
              "connection_information" => $this->forterConfig->getTimeOutSettings(),
              "email_setting_on_decline" => $this->forterConfig->getEmailSettingsOnDecline(),
              "advanced_settings" => [
                "enable_order_holding" => $this->forterConfig->isHoldingOrdersEnabled(),
                "enable_decision_change_controller" => $this->forterConfig->isDecisionControllerEnabled(),
                "hold_order_on_pending_decision" => $this->forterConfig->isPendingOnHoldEnabled(),
                "enable_order_fulfillment" => $this->forterConfig->isOrderFulfillmentEnable(),
                "enable_phone_order" => $this->forterConfig->isPhoneOrderEnabled(),
                "verification_results_mapping" => $this->forterConfig->getVerificationResultsMap(),
              ],
              "advanced_settings_pre_auth" => [
                "enable_creditcard_listener" => $this->forterConfig->isCcListenerActive(),
                "enable_listener_for_last4cc" => $this->forterConfig->getAllowLast4CCListener(),
                "enable_listener_for_bin" => $this->forterConfig->getAllowBinListener(),
                "class_or_id_identifier_for_the_listener" => $this->forterConfig->getElementToObserve()
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
