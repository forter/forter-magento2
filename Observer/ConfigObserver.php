<?php

namespace Forter\Forter\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Forter\Forter\Model\Config;

class ConfigObserver implements \Magento\Framework\Event\ObserverInterface
{

  public function __construct(
      ScopeConfigInterface $scopeConfig,
      Config $forterConfig
  )
  {
      $this->scopeConfig = $scopeConfig;
      $this->forterConfig = $forterConfig;
  }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer) {
      $json = [
        "general" => [
          "active" => $this->getSettings('enabled'),
          "site_id" => $this->forterConfig->getSiteId(),
          "secret_key" => $this->forterConfig->getSecretKey(),
          "module_version" => $this->forterConfig->getModuleVersion(),
          "api_version" => $this->forterConfig->getApiVersion(),
          "debug_mode" => $this->getSettings('debug_mode'),
          "sandbox_mode" => $this->getSettings('sandbox_mode')
        ],
        "pre_post_desicion" => [
          "pre_thanks_msg" => $this->getPrePostDesicion('pre_thanks_msg'),
          "post_thanks_msg" => $this->getPrePostDesicion('post_thanks_msg')
        ],
        "eventTime" => time()
      ];
      $json = json_encode($json);
      $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/testZ.log');
      $logger = new \Zend\Log\Logger();
      $logger->addWriter($writer);
      $logger->info($json);
    }

    private function getSettings($type){
      $result = $this->scopeConfig->getValue('forter/settings/'.$type, ScopeInterface::SCOPE_WEBSITE);

      switch ($result) {
        case 0:
          $result = 'disable';
          break;
        case 1:
          $result = 'enable';
          break;
      }

      return $result;
   }

   private function getPrePostDesicion($type){
     $result = $this->scopeConfig->getValue('forter/immediate_post_pre_decision/'.$type, ScopeInterface::SCOPE_WEBSITE);
     return $result;
   }
}
