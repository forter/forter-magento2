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

            if (!$forterEntity) {
                return false;
            }
            $this->emulate->stopEnvironmentEmulation();
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
}
