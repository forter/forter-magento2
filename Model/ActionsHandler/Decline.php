<?php

namespace Forter\Forter\Model\ActionsHandler;

use Forter\Forter\Model\Config as ForterConfig;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\CreditmemoService;

/**
 * Class Decline
 * @package Forter\Forter\Model\ActionsHandler
 */
class Decline
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var Order
     */
    private $order;
    /**
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var ForterConfig
     */
    private $forterConfig;
    /**
     * @var CreditmemoFactory
     */
    private $creditmemoFactory;
    /**
     * @var CreditmemoService
     */
    private $creditmemoService;
    /**
     * @var Invoice
     */
    private $invoice;

    /**
     * Decline constructor.
     * @param ManagerInterface $messageManager
     * @param Order $order
     * @param CreditmemoFactory $creditmemoFactory
     * @param ForterConfig $forterConfig
     * @param CheckoutSession $checkoutSession
     * @param Invoice $invoice
     * @param CreditmemoService $creditmemoService
     */
    public function __construct(
        ManagerInterface $messageManager,
        Order $order,
        CreditmemoFactory $creditmemoFactory,
        ForterConfig $forterConfig,
        CheckoutSession $checkoutSession,
        Invoice $invoice,
        CreditmemoService $creditmemoService,
        OrderManagementInterface $orderManagement
    ) {
        $this->orderManagement = $orderManagement;
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->messageManager = $messageManager;
        $this->forterConfig = $forterConfig;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->invoice = $invoice;
    }

    /**
     * @return $this
     */
    public function handlePreTransactionDescision()
    {
        $forterDecision = $this->forterConfig->getDeclinePre();
        if ($forterDecision == '1') {
            throw new PaymentException(__($this->forterConfig->getPreThanksMsg()));
        } elseif ($forterDecision == '2') {
            $this->checkoutSession->destroy();
            $this->messageManager->addErrorMessage($this->forterConfig->getPreThanksMsg());
        }

        return $this;
    }

    /**
     * @param $order
     * @return $this|bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function handlePostTransactionDescision($order)
    {
        $forterDecision = $this->forterConfig->getDeclinePost();
        $this->messageManager->getMessages(true);
        $this->messageManager->addErrorMessage($this->forterConfig->getPostThanksMsg());
        if ($forterDecision == '1') {
            if ($order->canCancel()) {
                $order->cancel()->save();
            }
            if ($order->getPayment()->canRefund()) {
                $this->createCreditMemo($order);
            }

            if ($order->canHold()) {
                $result = $this->holdOrder($order);
            }
        } elseif ($forterDecision == '2') {
            $orderState = Order::STATE_PAYMENT_REVIEW;
            $order->setState($orderState)->setStatus(Order::STATE_PAYMENT_REVIEW);
            $order->save();
            $this->addCommentToOrder($order, 'Order Has been marked for Payment Review by Forter');
        }

        return $this;
    }

    /**
     * @param $order
     * @return bool
     */
    private function cancelOrder($order)
    {
        $this->orderManagement->cancel($order->getEntityId());
        if ($order->isCanceled()) {
            $this->addCommentToOrder($order, 'Order Cancelled by Forter');
            return true;
        }

        $this->addCommentToOrder($order, 'Order Cancellation attempt failed by Forter');
        return false;
    }

    /**
     * @param $order
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function createCreditMemo($order)
    {
        $invoices = $order->getInvoiceCollection();
        $payment = $order->getPayment();
        foreach ($invoices as $invoice) {
            $invoiceincrementid = $invoice->getIncrementId();
            $invoiceobj = $this->invoice->loadByIncrementId($invoiceincrementid);
            $creditmemo = $this->creditmemoFactory->createByOrder($order);

            if ($invoiceobj || isset($invoiceobj)) {
                $creditmemo->setInvoice($invoiceobj);
            }

            $this->creditmemoService->refund($creditmemo);
        }

        $totalRefunded = $order->getTotalRefunded();

        if ($totalRefunded > 0) {
            $this->addCommentToOrder($order, 'Order Refunded by Forter');
            return true;
        }

        $this->addCommentToOrder($order, 'Order Refund attempt failed by Forter');
        return false;
    }

    /**
     * @param $order
     * @return bool
     */
    public function holdOrder($order)
    {
        $orderState = Order::STATE_HOLDED;
        $order->setState($orderState)->setStatus(Order::STATE_HOLDED);
        $order->save();

        $this->addCommentToOrder($order, 'Order Has been holded by Forter');
        return true;
    }

    /**
     * @param $order
     * @param $message
     */
    private function addCommentToOrder($order, $message)
    {
        $order->addStatusHistoryComment($message)
          ->setIsCustomerNotified(false)
          ->setEntityName('order')
          ->save();
    }
}
