<?php

namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Helper\AdditionalDataHelper;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\Entity as ForterEntity;
use Forter\Forter\Model\EntityFactory as ForterEntityFactory;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\QueueFactory as ForterQueueFactory;
use Forter\Forter\Model\RequestBuilder\Order;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPrepere;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Forter\Forter\Helper\EntityHelper;


/**
 * Class OrderSaveAfter
 * @package Forter\Forter\Observer\OrderFullfilment
 */
class PaymentSaveAfter implements ObserverInterface
{
    const FORTER_STATUS_WAITING = "waiting_for_data";
    const FORTER_STATUS_COMPLETE = "complete";

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
     * @var ForterEntity
     */
    protected $forterEntity;

    protected $forterEntityFactory;

    public const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var Decline
     */
    private $decline;

    /**
     * @var ForterQueueFactory
     */
    private $queue;

    /**
     * @var Approve
     */
    private $approve;

    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * @var Order
     */
    private $requestBuilderOrder;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Registry
     */
    private $registry;
    /**
     * @var Emulation
     */
    private $emulate;
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
        ForterEntity $forterEntity,
        ForterEntityFactory $forterEntityFactory,
        ScopeConfigInterface $scopeConfig,
        CustomerSession $customerSession,
        ManagerInterface $messageManager,
        ForterQueueFactory $queue,
        Decline $decline,
        Approve $approve,
        DateTime $dateTime,
        Config $forterConfig,
        Order $requestBuilderOrder,
        OrderManagementInterface $orderManagement,
        StoreManagerInterface $storeManager,
        Registry $registry,
        Emulation $emulate,
        EntityHelper $entityHelper
    ) {
        $this->config = $config;
        $this->paymentPrepere = $paymentPrepere;
        $this->additionalDataHelper = $additionalDataHelper;
        $this->customerSession = $customerSession;
        $this->messageManager = $messageManager;
        $this->dateTime = $dateTime;
        $this->storeManager = $storeManager;
        $this->decline = $decline;
        $this->approve = $approve;
        $this->scopeConfig = $scopeConfig;
        $this->abstractApi = $abstractApi;
        $this->forterConfig = $forterConfig;
        $this->requestBuilderOrder = $requestBuilderOrder;
        $this->orderManagement = $orderManagement;
        $this->queue = $queue;
        $this->registry = $registry;
        $this->emulate = $emulate;
        $this->forterLogger = $forterLogger;
        $this->forterEntity = $forterEntity;
        $this->forterEntityFactory = $forterEntityFactory;
        $this->entityHelper = $entityHelper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled() || !$this->config->isOrderFulfillmentEnable()) {
            return false;
        }

        $order = $observer->getEvent()->getPayment()->getOrder();

        try {
            $forterEntity = $this->entityHelper->getForterEntityByIncrementId($order->getIncrementId(), [self::FORTER_STATUS_COMPLETE]);

            if (!$forterEntity->getId()) {
                return false;
            }
            $this->emulate->stopEnvironmentEmulation();
            $this->clearTempSessionParams();
            $order = $observer->getEvent()->getPayment()->getOrder();
            // let bind the relevent store in case of multi store settings
            $this->emulate->startEnvironmentEmulation(
                $order->getStoreId(),
                'frontend',
                true
            );

            $this->abstractApi->sendOrderStatus($order);

            /* This is a logging mechanism that sends the order status to Forter. */
            $message = new ForterLoggerMessage($this->config->getSiteId(), $order->getIncrementId(), 'Order Status Update');
            $message->metaData->order = $order->getData();
            $message->metaData->payment = $order->getPayment()->getData();
            $this->forterLogger->SendLog($message);
            $this->forterLogger->forterConfig->log('Order no. ' . $order->getIncrementId() . ' Payment Data: ' . json_encode($order->getPayment()->getData()));

        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

//    public function handleResponse($forterDecision, $order)
//    {
//        if ($forterDecision == "decline") {
//            $this->handleDecline($order);
//        } elseif ($forterDecision == 'approve') {
//            $this->handleApprove($order);
//        } elseif ($forterDecision == "not reviewed") {
//            $this->handleNotReviewed($order);
//        } elseif ($forterDecision == "pending" && $this->forterConfig->isPendingOnHoldEnabled()) {
//            if ($order->canHold()) {
//                $this->decline->holdOrder($order);
//            }
//        }
//        if ($this->forterConfig->isDebugEnabled()) {
//            $message = new ForterLoggerMessage($order->getStore()->getWebsiteId(), $order->getIncrementId(), 'Handling Order With Forter');
//            $message->metaData->order = $order->getData();
//            $message->metaData->payment = $order->getPayment()->getData();
//            $message->metaData->forterDecision = $forterDecision;
//            $message->metaData->pendingOnHoldEnabled = $this->forterConfig->isPendingOnHoldEnabled();
//            $this->forterLogger->SendLog($message);
//        }
//    }

//    public function handleDecline($order)
//    {
//        $this->decline->sendDeclineMail($order);
//        $result = $this->forterConfig->getDeclinePost();
//        if ($result == '1') {
//            $this->customerSession->setForterMessage($this->forterConfig->getPostThanksMsg());
//
//            // the order will be canceled only if the order is in hold state and the force holding orders is disabled
//            if ($this->forterConfig->forceHoldingOrders() && !$order->canHold()) {
//                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
//            }
//
//            if ($order->canHold()) {
//                $order->setCanSendNewEmailFlag(false);
//                $this->decline->holdOrder($order);
//                $this->setMessageToQueue($order, 'decline');
//            }
//        } elseif ($result == '2') {
//            $order->setCanSendNewEmailFlag(false);
//            $this->decline->markOrderPaymentReview($order);
//        }
//    }
//
//    public function handleApprove($order)
//    {
//        $result = $this->forterConfig->getApprovePost();
//        if ($result == '1') {
//            $this->setMessageToQueue($order, 'approve');
//        }
//    }
//
//    public function handleNotReviewed($order)
//    {
//        $result = $this->forterConfig->getNotReviewPost();
//        if ($result == '1') {
//            $this->setMessageToQueue($order, 'approve');
//        }
//    }

//    public function setMessageToQueue($order, $type)
//    {
//        $storeId = $order->getStore()->getId();
//        $currentTime = $this->dateTime->gmtDate();
//        $this->forterConfig->log('Increment ID:' . $order->getIncrementId());
//        if ($this->forterConfig->isDebugEnabled()) {
//            $message = new ForterLoggerMessage($this->forterConfig->getSiteId(), $order->getIncrementId(), 'processing message to queue');
//            $message->metaData->order = $order->getData();
//            $message->metaData->payment = $order->getPayment();
//            $message->metaData->currentTime = $currentTime;
//            $this->forterLogger->SendLog($message);
//        }
//    }

    private function clearTempSessionParams()
    {
        $this->customerSession->unsForterBin();
        $this->customerSession->unsForterLast4cc();
    }

//    protected function logForterPreDecision($order)
//    {
//        $order->addStatusHistoryComment(__('Forter (pre) Decision: %1', $this->registry->registry('forter_pre_decision')));
//        $order->save();
//        $message = new ForterLoggerMessage($this->forterConfig->getSiteId(), $order->getIncrementId(), 'Pre-Auth');
//        $message->metaData->order = $order->getData();
//        $message->metaData->payment = $order->getPayment()->getData();
//        $this->forterConfig->log('Order ' . $order->getIncrementId() . ' Payment Data: ' . json_encode($order->getPayment()->getData()));
//        $this->forterLogger->SendLog($message);
//    }

}
