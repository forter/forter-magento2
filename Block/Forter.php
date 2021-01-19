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
    ) {
        parent::__construct($context, $data);
        $this->forterConfig = $forterConfig;
    }

    /**
     * Return Merchant Site Id
     *
     * @return string
     */
    public function getSiteId()
    {
        return $this->forterConfig->getSiteId();
    }

    /**
     * Return if to yes/no regarding observe Last4CC
     *
     * @return boolean
     */
    public function getPreAuthIsActive()
    {
        return $this->forterConfig->getPreAuthIsActive();
    }

    /**
     * Return if to yes/no regarding observe Last4CC
     *
     * @return boolean
     */
    public function getAllowLast4CCListener()
    {
        return $this->forterConfig->getAllowLast4CCListener();
    }

    /**
     * Return if to yes/no regarding observe Bin
     *
     * @return boolean
     */
    public function getAllowBinListener()
    {
        return $this->forterConfig->getAllowBinListener();
    }

    /**
     * Return the element to observe
     *
     * @return string
     */
    public function getElementToObserve()
    {
        return $this->forterConfig->getElementToObserve();
    }
}
