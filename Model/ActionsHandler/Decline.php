<?php

namespace Forter\Forter\Model\ActionsHandler;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\Sendmail;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\CreditmemoService;
use Forter\Forter\Model\Order\Recommendation;

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
     * @var Sendmail
     */
    protected $sendMail;
    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Recommendation
     */
    protected $recommendation;

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
        RequestInterface $request,
        AbstractApi $abstractApi,
        Sendmail $sendMail,
        Order $order,
        CreditmemoFactory $creditmemoFactory,
        ForterConfig $forterConfig,
        CheckoutSession $checkoutSession,
        Invoice $invoice,
        CreditmemoService $creditmemoService,
        OrderManagementInterface $orderManagement,
        Recommendation $recommendation
    ) {
        $this->request = $request;
        $this->abstractApi = $abstractApi;
        $this->sendMail = $sendMail;
        $this->orderManagement = $orderManagement;
        $this->checkoutSession = $checkoutSession;
        $this->order = $order;
        $this->forterConfig = $forterConfig;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->invoice = $invoice;
        $this->recommendation = $recommendation;
    }

    /**
     * @param  Order $order
     * @return $this
     */
    public function handlePreTransactionDescision($order)
    {
        $this->sendDeclineMail($order);
        $forterDecision = $this->forterConfig->getDeclinePre();
        $isVerificationRequired3dsChallenge = $this->recommendation->isVerificationRequired3dsChallenge($order);

        if ( $forterDecision == '1' &&  !$isVerificationRequired3dsChallenge ) {
            throw new PaymentException(__($this->forterConfig->getPreThanksMsg()));
        }

        return $this;
    }

    /**
     * @param  Order $order
     * @return $this
     */
    public function sendDeclineMail($order)
    {
        $this->sendMail->sendMail($order);
        return $this;
    }

    /**
     * @param $order
     */
    public function handlePostTransactionDescision($order, $item )
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

            $item->setSyncFlag(1);

        } catch (\Exception $e) {
           // if ($order->canHold()) {
                //$this->holdOrder($order);
            //}

            $retries    = (int)$item->getRetries() + 1;
//            $date       = date('Y-m-d H:i:s',  strtotime(' + ' . $retries . ' hours'));

            $item->setSyncFlag(0);
//            $item->setEntityType( 'order' );
//            $item->setForterStatus( $order->getForterStatus() );
            $item->setRetries( $retries );
//            $item->setUpdatedAt( $date );
//            $item->setSyncDate($date);
            $item->setSyncLastError($e->getMessage());

            $this->forterConfig->addCommentToOrder($order, 'Order Cancellation attempt failed. Internal Error');
            $this->abstractApi->reportToForterOnCatch($e);
        }

        $item->save();
    }

    /**
     * @param $order
     */
    private function cancelOrder($order)
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

            $this->request->setParam('invoice_id', $invoiceobj->getId() );

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
        $this->forterConfig->log('Refund Failure for Order ' . $order->getIncrementId() .' - Order Data: ' . json_encode($order->getData()));
        return;
    }

    /**
     * @param $order
     */
    public function holdOrder($order)
    {
        if ($this->forterConfig->isHoldingOrdersEnabled()) {
            $order->hold()->save();
            $this->forterConfig->addCommentToOrder($order, "Order Has been holded");
            $this->forterConfig->log('Payment Hold for Order ' . $order->getIncrementId() . ' - Order Payment Data: ' . json_encode($order->getPayment()->getData()));
        }
    }

    public function markOrderPaymentReview($order)
    {
        $orderState = Order::STATE_PAYMENT_REVIEW;
        $order->setState($orderState)->setStatus(Order::STATE_PAYMENT_REVIEW);
        $order->save();
        $this->forterConfig->addCommentToOrder($order, 'Order Has been marked for Payment Review');
    }
}
