<?php
/**
 * Created by PhpStorm.
 * User: Ion Bogatu
 * Date: 5/9/2018
 * Time: 4:54 PM
 */

namespace Forter\Forter\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
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
     * @param StoreManagerInterface $storeManager
     * @param ModuleListInterface $moduleList
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ModuleListInterface $moduleList,
        ScopeConfigInterface $scopeConfig,
        JsonSerializer $jsonSerializer
    ) {
        $this->storeManager = $storeManager;
        $this->moduleList = $moduleList;
        $this->scopeConfig = $scopeConfig;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function getConfig()
    {
        $isAdyenVersionGreaterOrEqual = $this->isAdyenVersionGreaterOrEqual();
        $forterPreAuth = $this->isForterPreAuth() === '1' ? true : false;

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

        if (isset($adyenModule)) {
            $adyenVersion = $adyenModule['setup_version'];
            return version_compare($adyenVersion, '8.11.0') >= 0;
        }

        return true;
    }

    protected function isForterPreAuth()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/pre_post_select');
    }
}
