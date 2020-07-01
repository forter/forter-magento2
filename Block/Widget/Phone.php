<?php
namespace Forter\Forter\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

class Phone extends Template implements BlockInterface {

    /**
     *
     */
    const XML_IS_ENABLED = 'forter/settings/widget_enabled';

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
    public function __construct(Template\Context $context, array $data = [], \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
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
     * Check if Forter Widget Extension is enabled.
     * @return mixed
     */
    public function isEnabled()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $isEnabled = $this->scopeConfig->getValue(self::XML_IS_ENABLED, $storeScope);

        return $isEnabled;
    }
}