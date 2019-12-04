<?php

/**
* Forter fraud detection module for Magento 2
* https://www.Forter.com/
*
* @category Forter
* @package  Forter_Forter
* @author   Girit-Interactive (https://www.girit-tech.com/)
*/
namespace Forter\Forter\Block\Adminhtml\System\Config;

use Forter\Forter\Model\Config as ForterConfig;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ModuleVersion extends Field
{
    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'Forter_Forter::system/config/module_version.phtml';

    /**
     * @var ForterConfig
     */
    private $forterConfig;

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
     * Return Forter magetno 2 module version
     *
     * @return string
     */
    public function getModuleVersion()
    {
        return $this->forterConfig->getModuleVersion();
    }
}
