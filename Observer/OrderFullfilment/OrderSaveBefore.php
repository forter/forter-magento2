<?php

namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Helper\AdditionalDataHelper;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPrepere;
use Magento\Framework\Event\ObserverInterface;
use Forter\Forter\Model\EntityFactory as ForterEntityFactory;
use Forter\Forter\Helper\EntityHelper;

/**
 * Class OrderSaveAfter
 * @package Forter\Forter\Observer\OrderFullfilment
 */
class OrderSaveBefore implements ObserverInterface
{
    public const ORDER_FULFILLMENT_STATUS_ENDPOINT = "https://api.forter-secure.com/v2/status/";
    public const FORTER_STATUS_NEW = "new";
    public const FORTER_STATUS_WAITING = "waiting_for_data";
    public const FORTER_STATUS_PRE_POST_VALIDATION = "pre_post_validation";
    public const FORTER_STATUS_COMPLETE = "complete";


    /**
     * @var Config
     */
    private $config;

    /**
     * @var ForterLogger
     */
    private $forterLogger;

    /**
     * @var AdditionalDataHelper
     */
    protected $additionalDataHelper;

    /**
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     * @var PaymentPrepere
     */
    protected $paymentPrepere;

    /**
     * @var EntityHelper
     */
    protected $entityHelper;

    /**
     * OrderSaveAfter constructor.
     * @param AbstractApi $abstractApi
     * @param Config $config
     * @param PaymentPrepere $paymentPrepere
     * @param ForterLogger $forterLogger
     * @param AdditionalDataHelper $additionalDataHelper
     */
    public function __construct(
        AbstractApi $abstractApi,
        Config $config,
        PaymentPrepere $paymentPrepere,
        ForterLogger $forterLogger,
        AdditionalDataHelper $additionalDataHelper,
        EntityHelper $entityHelper
    ) {
        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->paymentPrepere = $paymentPrepere;
        $this->forterLogger = $forterLogger;
        $this->additionalDataHelper = $additionalDataHelper;
        $this->entityHelper = $entityHelper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        if (!$this->config->isEnabled(null, $order->getStoreId()) ||
            !$this->config->isOrderFulfillmentEnable(null, $order->getStoreId())) {
            return false;
        }

        $order = $observer->getEvent()->getOrder();
        $forterEntity = $this->entityHelper->getForterEntityByIncrementId($order->getIncrementId(), [self::FORTER_STATUS_PRE_POST_VALIDATION,self::FORTER_STATUS_COMPLETE]);
        try {
            if (
                !$forterEntity &&
                !($order->getPayment() && $this->config->isActionExcludedPaymentMethod($order->getPayment()->getMethod(), null, $order->getStoreId()))
            ) {
                return false;
            }
            $orderState = $order->getState();
            $orderOrigState = $order->getOrigData('state');

            if ($this->additionalDataHelper->getCreditMemoRmaSize($order)) {
                $orderState = 'RETURNED';
            } elseif ($orderState == 'complete' && $orderOrigState != 'complete') {
                $orderState = 'COMPLETED';
            } elseif ($orderState == 'processing' && $orderOrigState != 'processing') {
                $orderState = 'PROCESSING';
            } elseif ($orderState == 'canceled' && $orderOrigState != 'canceled') {
                $orderState = 'CANCELED_BY_MERCHANT';
            } else {
                return false;
            }

            /* Sends the order status to Forter. */
            $this->abstractApi->sendOrderStatus($order);

            /* This is a logging mechanism that sends the order status to Forter. */
            $message = new ForterLoggerMessage($this->config->getSiteId(), $order->getIncrementId(), 'Order Status Update');
            $message->metaData->order = $order->getData();
            $message->metaData->payment = $order->getPayment()->getData();
            $message->metaData->orderState = $orderState;
            $message->metaData->orderOrigState = $orderOrigState;
            $this->forterLogger->SendLog($message);
            $this->forterLogger->forterConfig->log('Order no. ' . $order->getIncrementId() . ' Order State: ' . $orderState . ' Payment Data: ' . json_encode($order->getPayment()->getData()));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
