<?php

namespace Forter\Forter\Model\ActionsHandler;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\Sendmail;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\PaymentException;
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
     * @var AbstractApi
     */
    private AbstractApi $abstractApi;


    /**
     * @var Order
     */
    private Order $order;
    /**
     * @var ForterConfig
     */
    private ForterConfig $forterConfig;
    /**
     * @var CreditmemoFactory
     */
    private CreditmemoFactory $creditmemoFactory;
    /**
     * @var CreditmemoService
     */
    private CreditmemoService $creditmemoService;
    /**
     * @var Invoice
     */
    private Invoice $invoice;
    /**
     * @var Sendmail
     */
    protected Sendmail $sendMail;
    /**
     * @var OrderManagementInterface
     */
    protected OrderManagementInterface $orderManagement;

    /**
     * Decline constructor.
     * @param Order $order
     * @param CreditmemoFactory $creditmemoFactory
     * @param ForterConfig $forterConfig
     * @param CheckoutSession $checkoutSession
     * @param Invoice $invoice
     * @param CreditmemoService $creditmemoService
     */
    public function __construct(
        AbstractApi $abstractApi,
        Sendmail $sendMail,
        Order $order,
        CreditmemoFactory $creditmemoFactory,
        ForterConfig $forterConfig,
        CheckoutSession $checkoutSession,
        Invoice $invoice,
        CreditmemoService $creditmemoService,
        OrderManagementInterface $orderManagement
    ) {
        $this->abstractApi = $abstractApi;
        $this->sendMail = $sendMail;
        $this->orderManagement = $orderManagement;
        $this->forterConfig = $forterConfig;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->invoice = $invoice;
    }

    /**
     * @param Order $order
     * @return $this
     * @throws PaymentException
     */
    public function handlePreTransactionDescision(Order $order): self
    {
        $this->sendDeclineMail($order);
        $forterDecision = $this->forterConfig->getDeclinePre();
        if ($forterDecision === '1') {
            throw new PaymentException(__($this->forterConfig->getPreThanksMsg()));
        }

        return $this;
    }

    /**
     * @param  Order $order
     * @return $this
     */
    public function sendDeclineMail($order): self
    {
        $this->sendMail->sendMail($order);
        return $this;
    }

    /**
     * @param Order $order
     */
    public function handlePostTransactionDescision(Order $order): void
    {
        try {
            if ($order->canCancel()) {
                $this->cancelOrder($order);
            }

            if ($order->canCreditmemo()) {
                $this->createCreditMemo($order);
            }

            if ($order->canHold()) {
                $this->holdOrder($order);
            }
            $this->sendDeclineMail($order);
        } catch (\Exception $e) {
            if ($order->canHold()) {
                $this->holdOrder($order);
            }
            $this->forterConfig->addCommentToOrder($order, 'Order Cancellation attempt failed. Internal Error');
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    /**
     * @param Order $order
     */
    private function cancelOrder(Order $order): void
    {
        $order->cancel()->save();
        if ($order->isCanceled()) {
            $this->forterConfig->addCommentToOrder($order, 'Order Cancelled');
            $this->forterConfig->log('Canceled Order '. $order->getIncrementId() . ' Payment Data: ' .json_encode($order->getPayment()->getData()));
            return;
        }

        $this->forterConfig->addCommentToOrder($order, 'Order Cancellation attempt failed');
        $this->forterConfig->log('Cancellation Failure for Order ' . $order->getIncrementId() .' - Payment Data: ' . json_encode($order->getPayment()->getData()));
        return;
    }

    /**
     * @param Order $order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function createCreditMemo(Order $order): void
    {
        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice) {
            $invoiceincrementid = $invoice->getIncrementId();
            $invoiceobj = $this->invoice->loadByIncrementId($invoiceincrementid);
            $creditmemo = $this->creditmemoFactory->createByOrder($order);

            if ($invoiceobj || isset($invoiceobj)) {
                $creditmemo->setInvoice($invoiceobj);
                $this->creditmemoService->refund($creditmemo);
                $totalRefunded = $order->getTotalRefunded();
                if ($totalRefunded > 0) {
                    $this->forterConfig->addCommentToOrder($order, $totalRefunded . ' Refunded');
                    return;
                }
            }
        }

        $this->forterConfig->addCommentToOrder($order, 'Order Refund attempt failed');
        $this->forterConfig->log('Refund Failure for Order ' . $order->getIncrementId() .' - Order Data: ' . json_encode($order->getData()));
    }

    /**
     * @param Order $order
     */
    public function holdOrder(Order $order): void
    {
        if ($this->forterConfig->isHoldingOrdersEnabled()) {
            $order->hold()->save();
            $this->forterConfig->addCommentToOrder($order, "Order Has been holded");
            $this->forterConfig->log('Payment Hold for Order ' . $order->getIncrementId() . ' - Order Payment Data: ' . json_encode($order->getPayment()->getData()));
        }
    }

    public function markOrderPaymentReview(Order $order): void
    {
        $orderState = Order::STATE_PAYMENT_REVIEW;
        $order->setState($orderState)->setStatus(Order::STATE_PAYMENT_REVIEW);
        $order->save();
        $this->forterConfig->addCommentToOrder($order, 'Order Has been marked for Payment Review');
    }
}
