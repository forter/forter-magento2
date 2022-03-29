<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\QueueFactory as ForterQueueFactory;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;

/**
 * Class PaymentPlaceEnd
 * @package Forter\Forter\Observer\OrderValidation
 */
class PaymentPlaceEnd implements ObserverInterface
{
    const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';

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
     * @var AbstractApi
     */
    private $abstractApi;

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
     * @var ForterLogger
     */
    private $forterLogger;

    /**
     * @method __construct
     * @param  ScopeConfigInterface     $scopeConfig
     * @param  CustomerSession          $customerSession
     * @param  ManagerInterface         $messageManager
     * @param  ForterQueueFactory       $queue
     * @param  Decline                  $decline
     * @param  Approve                  $approve
     * @param  DateTime                 $dateTime
     * @param  AbstractApi              $abstractApi
     * @param  Config                   $forterConfig
     * @param  Order                    $requestBuilderOrder
     * @param  OrderManagementInterface $orderManagement
     * @param  StoreManagerInterface    $storeManager
     * @param  Registry                 $registry
     * @param  ForterLogger             $forterLogger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CustomerSession $customerSession,
        ManagerInterface $messageManager,
        ForterQueueFactory $queue,
        Decline $decline,
        Approve $approve,
        DateTime $dateTime,
        AbstractApi $abstractApi,
        Config $forterConfig,
        Order $requestBuilderOrder,
        OrderManagementInterface $orderManagement,
        StoreManagerInterface $storeManager,
        Registry $registry,
        Emulation $emulate,
        ForterLogger $forterLogger
    ) {
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
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            if (!$this->forterConfig->isEnabled() || (!$this->forterConfig->getIsPost() && !$this->forterConfig->getIsPreAndPost())) {
                if ($this->registry->registry('forter_pre_decision')) {
                    $order = $observer->getEvent()->getPayment()->getOrder();
                    $order->addStatusHistoryComment(__('Forter (pre) Decision: %1', $this->registry->registry('forter_pre_decision')));
                    $order->save();
                }
                return;
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

            $data = $this->requestBuilderOrder->buildTransaction($order, 'AFTER_PAYMENT_ACTION');
            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $forterResponse = $this->abstractApi->sendApiRequest($url, json_encode($data));

            $this->abstractApi->sendOrderStatus($order);

            $order->setForterResponse($forterResponse);
            $forterResponse = json_decode($forterResponse);

            if ($forterResponse->status != 'success' || !isset($forterResponse->action)) {
                $order->setForterStatus('error');
                $order->addStatusHistoryComment(__('Forter (post) Decision: %1', 'error'));
                $order->save();
                $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'Response Error - Post-Auth');
                $message->metaData->order = $order;
                $message->metaData->decision = $forterResponse->action;
                $this->forterLogger->SendLog($message);
                return;
            }

            $order->setForterStatus($forterResponse->action);
            $order->addStatusHistoryComment(__('Forter (post) Decision: %1', $forterResponse->action));
            $this->handleResponse($forterResponse->action, $order);

            $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'After Validation');
            $message->metaData->order = $order;
            $message->metaData->decision = $forterResponse->action;
            $this->forterLogger->SendLog($message);
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    public function handleResponse($forterDecision, $order)
    {
        if ($forterDecision == "decline") {
            $this->handleDecline($order);
        } elseif ($forterDecision == 'approve') {
            $this->handleApprove($order);
        } elseif ($forterDecision == "not reviewed") {
            $this->handleNotReviewed($order);
        } elseif ($forterDecision == "pending" && $this->forterConfig->isPendingOnHoldEnabled()) {
            if ($order->canHold()) {
                $this->decline->holdOrder($order);
            }
        }
        if ($this->forterConfig->isDebugEnabled()) {
            $message = new ForterLoggerMessage($order->getStore()->getWebsiteId(),  $order->getIncrementId(), 'Handle Response - Post-Auth');
            $message->metaData->order = $order;
            $message->metaData->forterDecision = $forterDecision;
            $message->metaData->pendingOnHoldEnabled = $this->forterConfig->isPendingOnHoldEnabled();
            $this->forterLogger->SendLog($message);
        }
    }

    public function handleDecline($order)
    {
        $this->decline->sendDeclineMail($order);
        $result = $this->forterConfig->getDeclinePost();
        if ($result == '1') {
            $this->customerSession->setForterMessage($this->forterConfig->getPostThanksMsg());
            if ($order->canHold()) {
                $order->setCanSendNewEmailFlag(false);
                $this->decline->holdOrder($order);
                $this->setMessageToQueue($order, 'decline');
            }
        } elseif ($result == '2') {
            $order->setCanSendNewEmailFlag(false);
            $this->decline->markOrderPaymentReview($order);
        }
    }

    public function handleApprove($order)
    {
        $result = $this->forterConfig->getApprovePost();
        if ($result == '1') {
            $this->setMessageToQueue($order, 'approve');
        }
    }

    public function handleNotReviewed($order)
    {
        $result = $this->forterConfig->getNotReviewPost();
        if ($result == '1') {
            $this->setMessageToQueue($order, 'approve');
        }
    }

    public function setMessageToQueue($order, $type)
    {
        $storeId = $order->getStore()->getId();
        $currentTime = $this->dateTime->gmtDate();
        $this->forterConfig->log('Increment ID:' . $order->getIncrementId());
        if ($this->forterConfig->isDebugEnabled()) {
            $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'send message to queue');
            $message->metaData->order = $order;
            $message->metaData->currentTime = $currentTime;
            $this->forterLogger->SendLog($message);
        }
        $this->queue->create()
            ->setStoreId($storeId)
            ->setEntityType('order')
            ->setIncrementId($order->getIncrementId()) //TODO need to make this field a text in the table not int
            ->setEntityBody($type)
            ->setSyncDate($currentTime)
            ->save();
    }

    private function clearTempSessionParams()
    {
        $this->customerSession->unsForterBin();
        $this->customerSession->unsForterLast4cc();
    }
}
