<?php
namespace Forter\Forter\Block;

use Forter\Forter\Model\Config;
use Magento\Framework\View\Element\Template\Context;

class Forter extends \Magento\Framework\View\Element\Template
{
    /**
     * This block is used by forter.phtml template
     */

    protected $_config;

    /**
     * Forter Block Constructor
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     * @param \Forter\Forter\Model\Config $config
     */
    public function __construct(
        Context $context,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_config = $config;
    }

    public function getSiteId()
    {
        return $this->_config->getSiteId();
    }
}
