<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\QueueFactory as ForterQueueFactory;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class PaymentPlaceEnd
 * @package Forter\Forter\Observer\OrderValidation
 */
class PaymentPlaceEnd implements ObserverInterface
{
    /**
     *
     */
    const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';

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
    private $config;
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
     * PaymentPlaceEnd constructor.
     * @param ManagerInterface $messageManager
     * @param Decline $decline
     * @param Approve $approve
     * @param AbstractApi $abstractApi
     * @param Config $config
     * @param Order $requestBuilderOrder
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        ManagerInterface $messageManager,
        ForterQueueFactory $queue,
        Decline $decline,
        Approve $approve,
        DateTime $dateTime,
        AbstractApi $abstractApi,
        Config $forterConfig,
        Order $requestBuilderOrder,
        OrderManagementInterface $orderManagement,
        StoreManagerInterface $storeManager
    ) {
        $this->messageManager = $messageManager;
        $this->dateTime = $dateTime;
        $this->storeManager = $storeManager;
        $this->decline = $decline;
        $this->approve = $approve;
        $this->abstractApi = $abstractApi;
        $this->forterConfig = $forterConfig;
        $this->requestBuilderOrder = $requestBuilderOrder;
        $this->orderManagement = $orderManagement;
        $this->queue = $queue;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->forterConfig->isEnabled() || !$this->forterConfig->getIsPost()) {
            return false;
        }

        $order = $observer->getEvent()->getPayment()->getOrder();

        try {
            $data = $this->requestBuilderOrder->buildTransaction($order);
            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $forterResponse = $this->abstractApi->sendApiRequest($url, json_encode($data));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }

        $order->setForterResponse($forterResponse);
        $forterResponse = json_decode($forterResponse);
        $order->setForterStatus($forterResponse->action);

        if ($forterResponse->status == 'failed') {
            $order->setForterStatus('not reviewed');
            return true;
        }

        $type = null;
        if ($forterResponse->action == "decline") {
            $this->messageManager->getMessages(true);
            $this->messageManager->addErrorMessage($this->forterConfig->getPostThanksMsg());
            $result = $this->forterConfig->getDeclinePost();
            if ($result == '1') {
                if ($order->canHold()) {
                    $this->decline->holdOrder($order);
                    $type = 'decline';
                }
            } elseif ($result == '2') {
                $this->decline->markOrderPaymentReview($order);
                return true;
            } else {
                return true;
            }
        } elseif ($forterResponse->action == 'approve') {
            $result = $this->forterConfig->getApprovePost();
            if ($result == '1') {
                $type = 'approve';
            } else {
                return true;
            }
        } elseif ($forterResponse->action == "not reviewed") {
            $result = $this->forterConfig->getNotReviewPost();
            if ($result == '1') {
                $type = 'approve';
            } else {
                return true;
            }
        }

        $storeId = $order->getStore()->getId();
        $currentTime = $this->dateTime->gmtDate();
        $order->save();
        if ($type) {
            $this->queue->create()
                  ->setStoreId($storeId)
                  ->setEntityType('order')
                  ->setEntityId($order->getId())
                  ->setEntityBody($type)
                  ->setSyncDate($currentTime)
                  ->save();
        }

        return false;
    }
}
