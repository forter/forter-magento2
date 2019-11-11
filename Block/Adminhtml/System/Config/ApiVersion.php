<?php

namespace Forter\Forter\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Forter\Forter\Model\Config as ForterConfig;

class ApiVersion extends Field
{
    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'Forter_Forter::system/config/api_version.phtml';

    /**
     * @param  Context     $context
     * @param  ForterConfig forterConfig
     * @param  array       $data
     */
    public function __construct(
        Context $context,
        ForterConfig $forterConfig,
        array $data = []
    ) {
        $this->forterConfig = $forterConfig;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate collect button html
     *
     * @return string
     */
    public function getApiVersion()
    {
        return $this->forterConfig->getApiVersion();
    }
}
