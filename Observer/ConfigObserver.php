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
              "general" => [
                "active" => $this->forterConfig->isEnabled(),
                "site_id" => $this->forterConfig->getSiteId(),
                "secret_key" => $this->forterConfig->getSecretKey(),
                "module_version" => $this->forterConfig->getModuleVersion(),
                "api_version" => $this->forterConfig->getApiVersion(),
                "debug_mode" => $this->forterConfig->isDebugEnabled(),
                "enhanced_data_mode" => $this->forterConfig->isSandboxMode(),
                "sandbox_mode" => $this->forterConfig->isSandboxMode()
              ],
              "pre_post_decision" => [
                "pre_post_select" => $this->forterConfig->getPrePostDecisionMsg('pre_post_select'),
                "pre_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_pre'),
                "pre_thanks_msg" => $this->forterConfig->getPreThanksMsg(),
                "post_decline" => $this->forterConfig->getPrePostDecisionMsg('decline_post'),
                "post_approve" => $this->forterConfig->getPrePostDecisionMsg('approve_post'),
                "post_not_review" => $this->forterConfig->getPrePostDecisionMsg('not_review_post'),
                "post_thanks_msg" => $this->forterConfig->getPostThanksMsg()
              ],
              "store" => [
                "storeId" => $this->forterConfig->getStoreId()
              ],
              "connection_information" => $this->forterConfig->getTimeOutSettings(),
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
