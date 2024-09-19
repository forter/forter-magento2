<?php
/**
 * Created by PhpStorm.
 * User: Ion Bogatu
 * Date: 5/9/2018
 * Time: 4:54 PM
 */

namespace Forter\Forter\Helper;

use Forter\Forter\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const PAYMENT_METHOD = 'adyen_cc';

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ModuleListInterface
     */
    protected $moduleList;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ModuleListInterface $moduleList
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleList,
        ScopeConfigInterface $scopeConfig,
        JsonSerializer $jsonSerializer,
        Config $config
    ) {
        $this->storeManager = $storeManager;
        $this->moduleList = $moduleList;
        $this->scopeConfig = $scopeConfig;
        $this->jsonSerializer = $jsonSerializer;
        $this->config = $config;
    }

    public function getConfig()
    {
        $methodSetting = $this->config->getMappedPrePos(self::PAYMENT_METHOD);
        $isAdyenVersionGreaterOrEqual = $this->isAdyenVersionGreaterOrEqual();

        if ($methodSetting) {
            $customMap = $this->mapCustomPrePostSelect($methodSetting);
            $forterPreAuth = $customMap === '1' || $customMap === '4' ? true : false;
        } else {
            $forterPreAuth = $this->isForterPreAuth() === '1' || $this->isForterPreAuth() === '4' ? true : false;
        }

        return [
            'forter' => [
                'isAdyenVersionGreaterOrEqual' => $isAdyenVersionGreaterOrEqual,
                'forterPreAuth' => $forterPreAuth,
            ],
        ];
    }

    protected function isAdyenVersionGreaterOrEqual()
    {
        $adyenModule = $this->moduleList->getOne('Adyen_Payment');

        if (isset($adyenModule) && isset($adyenModule['setup_version'])) {
            $adyenVersion = $adyenModule['setup_version'];
            return version_compare($adyenVersion, '8.11.0') >= 0;
        }

        return true;
    }

    protected function isForterPreAuth()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/pre_post_select');
    }

    protected function mapCustomPrePostSelect($methodSetting)
    {
        $mapping = [
            'pre' => '1',
            'post' => '2',
            'prepost' => '4',
            'cron' => '3'
        ];

        return isset($mapping[$methodSetting]) ? $mapping[$methodSetting] : false;
    }
}
