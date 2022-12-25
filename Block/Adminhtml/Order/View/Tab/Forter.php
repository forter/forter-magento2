<?php

namespace Forter\Forter\Block\Adminhtml\Order\View\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Helper\Admin;
use Magento\Sales\Model\Order;
use Forter\Forter\Model\Config as ForterConfig;

/**
 * Class Forter
 * @package Forter\Forter\Block\Adminhtml\Order\View\Tab
 */
class Forter extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder implements
    \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /**
     * @var OrderInterface
     */
    protected $orderInterface;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ForterConfig
     */
    private $forterConfig;

    /**
     * @method __construct
     * @param  ScopeConfigInterface $scopeConfig
     * @param  Context              $context
     * @param  Registry             $registry
     * @param  OrderInterface       $orderInterface
     * @param  Admin                $adminHelper
     * @param  ForterConfig         $forterConfig
     * @param  array                $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Context $context,
        Registry $registry,
        OrderInterface $orderInterface,
        Admin $adminHelper,
        ForterConfig $forterConfig,
        array $data = []
    ) {
        parent::__construct($context, $registry, $adminHelper, $data);
        $this->orderInterface = $orderInterface;
        $this->scopeConfig = $scopeConfig;
        $this->forterConfig = $forterConfig;
    }

    /**
     * @return Phrase
     */
    public function getTabLabel()
    {
        return __('Forter Decision');
    }

    /**
     * @return Phrase
     */
    public function getTabTitle()
    {
        return __('Forter Decision');
    }

    /**
     * @return bool
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * @param $orderId
     * @return string
     */
    public function getViewUrl($orderId)
    {
        return $this->getUrl('sales/*/*', ['order_id' => $orderId]);
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        $order_id = $this->getRequest()->getParam('order_id');
        return $this->orderInterface->load($order_id);
    }

    /**
     * @method getRecommendationMessageByKey
     * @param  string                        $recommendationKey
     * @return string
     */
    public function getRecommendationMessageByKey($recommendationKey)
    {
        return $this->forterConfig->getRecommendationMessageByKey($recommendationKey);
    }
}
