<?php

namespace Forter\Forter\Block;

use Forter\Forter\Model\Config as ForterConfig;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Forter
 * @package Forter\Forter\Block
 */
class Forter extends \Magento\Framework\View\Element\Template
{

    /**
     * @var ForterConfig
     */
    private $forterConfig;

    /**
     * Forter Block Constructor
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param ForterConfig $forterConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        ForterConfig $forterConfig,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->forterConfig = $forterConfig;
    }

    /**
     * Return Merchant Site Id
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSiteId()
    {
        return $this->forterConfig->getSiteId();
    }
}
