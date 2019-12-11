<?php

namespace Forter\Forter\Model\RequestHandler;

use Forter\Forter\Model\Config as ForterConfig;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\CreditmemoService;

class Decline
{
    public function __construct(
        ManagerInterface $messageManager,
        Order $order,
        CreditmemoFactory $creditmemoFactory,
        ForterConfig $forterConfig,
        CheckoutSession $checkoutSession,
        Invoice $invoice,
        CreditmemoService $creditmemoService
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->messageManager = $messageManager;
        $this->forterConfig = $forterConfig;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->invoice = $invoice;
    }

    public function handlePreTransactionDescision()
    {
        $forterDecision = $this->forterConfig->getDeclinePre();
        if ($forterDecision == '1') {
            throw new PaymentException(__($this->forterConfig->getPreThanksMsg()));
        } elseif ($forterDecision == '2') {
            $this->checkoutSession->destroy();
            $this->messageManager->addError($this->forterConfig->getPreThanksMsg());
        }

        return $this;
    }

    public function handlePostTransactionDescision($order)
    {
        $forterDecision = $this->forterConfig->getDeclinePost();
        if ($forterDecision == '1') {
            $this->messageManager->addError($this->forterConfig->getPreThanksMsg());

            $result = $this->cancelOrder($order);
            if ($result) {
                return true;
            }

            $result = $this->createCreditMemo($order);
            if ($result) {
                return true;
            }

            $result = $this->holdOrder($order);
            if ($result) {
                return true;
            }
        } elseif ($forterDecision == '2') {
            $orderState = Order::STATE_PAYMENT_REVIEW;
            $order->setState($orderState)->setStatus(Order::STATE_PAYMENT_REVIEW);
            $order->save();
            $this->addCommentToOrder($order, 'Order Has been marked for Payment Review by Forter');
        }

        return $this;
    }

    private function cancelOrder($order)
    {
        $order->cancel()->save();
        if ($order->isCanceled()) {
            $this->addCommentToOrder($order, 'Order Cancelled by Forter');
            return true;
        }

        $this->addCommentToOrder($order, 'Order Cancellation attempt failed by Forter');
        return false;
    }

    private function createCreditMemo($order)
    {
        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice) {
            $invoiceincrementid = $invoice->getIncrementId();
        }

        $invoiceobj = $this->invoice->loadByIncrementId($invoiceincrementid);
        $creditmemo = $this->creditmemoFactory->createByOrder($order);

        if ($invoiceobj || isset($invoiceobj)) {
            $creditmemo->setInvoice($invoiceobj);
        }

        $this->creditmemoService->refund($creditmemo);
        $totalRefunded = $order->getTotalRefunded();

        if ($totalRefunded > 0) {
            $this->addCommentToOrder($order, 'Order Refunded by Forter');
            return true;
        }

        $this->addCommentToOrder($order, 'Order Refund attempt failed by Forter');
        return false;
    }

    private function holdOrder($order)
    {
        $orderState = Order::STATE_HOLDED;
        $order->setState($orderState)->setStatus(Order::STATE_HOLDED);
        $order->save();

        $this->addCommentToOrder($order, 'Order Has been holded by Forter');
        return true;
    }

    private function addCommentToOrder($order, $message)
    {
        $order->addStatusHistoryComment($message)
          ->setIsCustomerNotified(false)
          ->setEntityName('order')
          ->save();
    }
}
