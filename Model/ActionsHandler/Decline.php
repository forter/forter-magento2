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
    private $abstractApi;
    /**
     * @var Sendmail
     */
    private $sendmail;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var Order
     */
    private $order;
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
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->forterConfig = $forterConfig;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->invoice = $invoice;
    }

    /**
     * @param  Order $order
     * @return $this
     */
    public function handlePreTransactionDescision(Order $order)
    {
        $this->sendMail->sendMail($order);
        $forterDecision = $this->forterConfig->getDeclinePre();
        if ($forterDecision == '1') {
            throw new PaymentException(__($this->forterConfig->getPreThanksMsg()));
        }

        return $this;
    }

    /**
     * @param $order
     */
    public function handlePostTransactionDescision($order)
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
        } catch (Exception $e) {
            $this->addCommentToOrder($order, 'Order Cancellation attempt failed. Internal Error');
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    /**
     * @param $order
     */
    private function cancelOrder($order)
    {
        $order->cancel()->save();
        if ($order->isCanceled()) {
            $this->forterConfig->addCommentToOrder($order, 'Order Cancelled');
            return;
        }

        $this->forterConfig->addCommentToOrder($order, 'Order Cancellation attempt failed');
        return;
    }

    /**
     * @param $order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function createCreditMemo($order)
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
            }

            if ($totalRefunded > 0) {
                $this->forterConfig->addCommentToOrder($order, $totalRefunded . ' Refunded');
                return;
            }
        }

        $this->forterConfig->addCommentToOrder($order, 'Order Refund attempt failed');
        return;
    }

    /**
     * @param $order
     */
    public function holdOrder($order)
    {
        if ($this->forterConfig->isHoldingOrdersEnabled()) {
            $order->hold()->save();
        }

        $this->forterConfig->addCommentToOrder($order, "Order Has been holded");
    }

    public function markOrderPaymentReview($order)
    {
        $orderState = Order::STATE_PAYMENT_REVIEW;
        $order->setState($orderState)->setStatus(Order::STATE_PAYMENT_REVIEW);
        $order->save();
        $this->forterConfig->addCommentToOrder($order, 'Order Has been marked for Payment Review');
    }
}
