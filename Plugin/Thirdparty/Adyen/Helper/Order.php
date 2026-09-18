<?php

namespace Forter\Forter\Plugin\Thirdparty\Adyen\Helper;

use Adyen\Payment\Helper\Order as AdyenOrderHelper;
use Forter\Forter\Model\Config;
use Forter\Forter\Helper\EntityHelper;
use Magento\Sales\Model\Order as MagentoOrder;

class Order
{

    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * @var EntityHelper
     */
    protected $entityHelper;

    public function __construct(
        Config $forterConfig,
        EntityHelper $entityHelper
    ) {
        $this->forterConfig = $forterConfig;
        $this->entityHelper = $entityHelper;
    }
    public function afterFinalizeOrder(AdyenOrderHelper $subject, MagentoOrder $order)
    {

        $forterEntity = $this->entityHelper->getForterEntityByIncrementId($order->getIncrementId());
        if (!$forterEntity) {
            return $order;
        }
        if (
            $forterEntity->getForterStatus() == 'decline' &&
            $order->getState() != MagentoOrder::STATE_PAYMENT_REVIEW &&
            $this->forterConfig->getDeclinePost() == '2') {
            $orderState = MagentoOrder::STATE_PAYMENT_REVIEW;
            $order->setState($orderState)->setStatus(MagentoOrder::STATE_PAYMENT_REVIEW);
            $this->forterConfig->addCommentToOrder($order, 'Order Has been re-marked for Payment Review');
        }

        return $order;
    }
}
