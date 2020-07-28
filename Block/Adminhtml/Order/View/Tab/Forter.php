<?php

namespace Forter\Forter\Block\Adminhtml\Order\View\Tab;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Helper\Admin;

/**
 * Class Forter
 * @package Forter\Forter\Block\Adminhtml\Order\View\Tab
 */
class Forter extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder implements
    \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /**
     *
     */
    const PHONE_ORDER_IS_ENABLED = 'forter/advanced_settings/phone_order_enabled';

    /**
     * order Object
     */
    protected $orderInterface;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * invoice constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Api\Data\OrderInterface $orderInterface
     * @param \Magento\Sales\Helper\Admin $adminHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Context $context,
        Registry $registry,
        OrderInterface $orderInterface,
        Admin $adminHelper,
        array $data = []
    ) {
        $this->orderInterface = $orderInterface;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $registry, $adminHelper, $data);
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getTabLabel()
    {
        return __('Forter Decision');
    }

    /**
     * @return \Magento\Framework\Phrase
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
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        $order_id = $this->getRequest()->getParam('order_id');
        return $this->orderInterface->load($order_id);
    }
}
