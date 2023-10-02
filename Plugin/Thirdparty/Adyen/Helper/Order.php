<?php

namespace Forter\Forter\Plugin\Thirdparty\Adyen\Helper;

use Adyen\Payment\Helper\Order as AdyenOrderHelper;
use Magento\Sales\Model\Order as MagentoOrder;
use Forter\Forter\Model\Config;

class Order
{

    /**
     * @var Config
     */
    private $forterConfig;

    public function __construct(Config $forterConfig)
    {
        $this->forterConfig = $forterConfig;
    
    }
    public function afterFinalizeOrder(AdyenOrderHelper $subject, MagentoOrder $order)
    {

        if (
            $order->getForterStatus() == 'decline' && 
            $order->getState() != MagentoOrder::STATE_PAYMENT_REVIEW && 
            $this->forterConfig->getDeclinePost() == '2') {

            $orderState = MagentoOrder::STATE_PAYMENT_REVIEW;
            $order->setState($orderState)->setStatus(MagentoOrder::STATE_PAYMENT_REVIEW);
            $this->forterConfig->addCommentToOrder($order, 'Order Has been re-marked for Payment Review');
        }

        return $order;
    }
}