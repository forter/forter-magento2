<?php

namespace Forter\Forter\Observer;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Framework\Event\Observer;

/**
 * Class ConfigObserver
 * @package Forter\Forter\Observer
 */
class ConfigObserver implements \Magento\Framework\Event\ObserverInterface
{
    /**
     *
     */
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
        AbstractApi $abstractApi,
        Config $forterConfig
    ) {
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
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        try {
            $json = [
              "general" => [
                "active" => $this->forterConfig->isEnabled(),
                "site_id" => $this->forterConfig->getSiteId(),
                "secret_key" => $this->forterConfig->getSecretKey(),
                "module_version" => $this->forterConfig->getModuleVersion(),
                "api_version" => $this->forterConfig->getApiVersion(),
                "debug_mode" => $this->forterConfig->isDebugEnabled(),
                "sandbox_mode" => $this->forterConfig->isSandboxMode(),
                "log_mode" => $this->forterConfig->isLogging()
              ],
              "pre_post_desicion" => [
                "pre_post_Select" => $this->forterConfig->getPrePostDesicionMsg('pre_post_Select'),
                "pre_decline" => $this->forterConfig->getPrePostDesicionMsg('decline_pre'),
                "pre_thanks_msg" => $this->forterConfig->getPreThanksMsg(),
                "post_decline" => $this->forterConfig->getPrePostDesicionMsg('decline_post'),
                "post_approve" => $this->forterConfig->getPrePostDesicionMsg('approve_post'),
                "post_not_review" => $this->forterConfig->getPrePostDesicionMsg('not_review_post'),
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
            throw new \Exception($e->getMessage());
        }
    }
}
