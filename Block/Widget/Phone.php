<?php
namespace Forter\Forter\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

class Phone extends Template implements BlockInterface
{

    /**
     *
     */
    const PHONE_ORDER_IS_ENABLED = 'forter/advanced_settings/phone_order_enabled';

    /**
     * @var string
     */
    protected $_template = "widget/phone.phtml";

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Constructor
     *
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, array  $data = [])
    {
        $this->validator = $context->getValidator();
        $this->resolver = $context->getResolver();
        $this->_filesystem = $context->getFilesystem();
        $this->templateEnginePool = $context->getEnginePool();
        $this->_storeManager = $context->getStoreManager();
        $this->_appState = $context->getAppState();
        $this->templateContext = $this;
        $this->pageConfig = $context->getPageConfig();
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Check if Forter Phone Order Widget is enabled.
     * @return mixed
     */
    public function isPhoneOrderEnabled()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $isEnabled = $this->scopeConfig->getValue(self::PHONE_ORDER_IS_ENABLED, $storeScope);

        return $isEnabled;
    }
}
