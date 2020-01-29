<?php

namespace Forter\Forter\Model\ActionsHandler;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
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
        Order $order,
        CreditmemoFactory $creditmemoFactory,
        ForterConfig $forterConfig,
        CheckoutSession $checkoutSession,
        Invoice $invoice,
        CreditmemoService $creditmemoService,
        OrderManagementInterface $orderManagement
    ) {
        $this->abstractApi = $abstractApi;
        $this->orderManagement = $orderManagement;
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
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
        try {
            if ($order->canCancel()) {
                $this->cancelOrder($order);
            }

            if ($order->canCreditmemo()) {
                $this->createCreditMemo($order);
            }

            $state = $order->getState();
            $result = $this->holdOrder($order);

            return true;
        } catch (Exception $e) {
            $this->addCommentToOrder($order, 'Order Cancellation attempt failed. Internal Error');
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param $order
     * @return bool
     */
    private function cancelOrder($order)
    {
        $order->cancel()->save();
        if ($order->isCanceled()) {
            $this->forterConfig->addCommentToOrder($order, 'Order Cancelled');
            return true;
        }

        $this->forterConfig->addCommentToOrder($order, 'Order Cancellation attempt failed');
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
                return true;
            }
        }

        $this->forterConfig->addCommentToOrder($order, 'Order Refund attempt failed');
        return false;
    }

    /**
     * @param $order
     * @return bool
     */
    public function holdOrder($order)
    {
        $order->hold()->save();
        $this->forterConfig->addCommentToOrder($order, "Order Has been holded");

        return true;
    }

    public function markOrderPaymentReview($order)
    {
        $orderState = Order::STATE_PAYMENT_REVIEW;
        $order->setState($orderState)->setStatus(Order::STATE_PAYMENT_REVIEW);
        $order->save();
        $this->forterConfig->addCommentToOrder($order, 'Order Has been marked for Payment Review');
    }
}
