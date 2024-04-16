<?php

namespace Forter\Forter\Plugin\Thirdparty\Stripe;

use Forter\Forter\Helper\EntityHelper;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use StripeIntegration\Payments\Helper\Order as StripeIntegrationOrder;

class Order
{
    const FORTER_STATUS_WAITING = "waiting_for_data";
    /**
     * @var ForterLogger
     */
    protected $logger;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    protected $entityHelper;

    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * Cancel constructor.
     * @param OrderResourceModel $orderResourceModel
     */
    public function __construct(
        ForterLogger       $logger,
        OrderResourceModel $orderResourceModel,
        EntityHelper       $entityHelper,
        Config             $forterConfig
    ) {
        $this->logger = $logger;
        $this->orderResourceModel = $orderResourceModel;
        $this->entityHelper = $entityHelper;
        $this->forterConfig = $forterConfig;
    }

    public function aroundOnTransaction(StripeIntegrationOrder $subject, callable $proceed, $order, $object, $transactionId)
    {
        try {
            $forterEntity = $this->entityHelper->getForterEntityByIncrementId($order->getIncrementId(), [self::FORTER_STATUS_WAITING]);
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/stripeDATA.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            $logger->info('in Around transcation plugin');

            if (!$forterEntity) {
                return;
            }

            $payment = $order->getPayment();
            $payment->setAdditionalInformation('stripe_approved', true)->save();

            $logger->info('in Around transcation plugin - setting strpe approve to additional INformation');

            $holdedBeforeTransaction = $order->canUnhold();
            $proceed($order, $object, $transactionId);
            if ($holdedBeforeTransaction && $order->canHold() && $this->forterConfig->isHoldingOrdersEnabled()) {
                $order->hold();
                $this->orderResourceModel->save($order);
            }
        } catch (\Exception $ex) {
            $this->logger->forterConfig->log($ex->getMessage(),'error');
        }
    }
}
