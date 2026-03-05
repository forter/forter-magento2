<?php

namespace Forter\Forter\Test\Unit\Model\ActionsHandler;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\Order\Recommendation;
use Forter\Forter\Model\Sendmail;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Service\CreditmemoService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Decline::cancelOrder() payment void fallback
 */
class DeclineTest extends TestCase
{
    /** @var Decline */
    private $decline;

    /** @var ForterConfig|\PHPUnit\Framework\MockObject\MockObject */
    private $forterConfig;

    /** @var AbstractApi|\PHPUnit\Framework\MockObject\MockObject */
    private $abstractApi;

    protected function setUp(): void
    {
        $this->forterConfig = $this->createMock(ForterConfig::class);
        $this->abstractApi  = $this->createMock(AbstractApi::class);

        $this->decline = new Decline(
            $this->createMock(RequestInterface::class),
            $this->abstractApi,
            $this->createMock(Sendmail::class),
            $this->createMock(Order::class),
            $this->createMock(CreditmemoFactory::class),
            $this->forterConfig,
            $this->createMock(CheckoutSession::class),
            $this->createMock(Invoice::class),
            $this->createMock(CreditmemoService::class),
            $this->createMock(OrderManagementInterface::class),
            $this->createMock(Recommendation::class)
        );
    }

    // ------------------------------------------------------------------
    // Helper: build an order mock that returns isCanceled() = $canceled
    // ------------------------------------------------------------------
    private function buildOrderMock(bool $canceled): \PHPUnit\Framework\MockObject\MockObject
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $order->method('cancel')->willReturnSelf();
        $order->method('save')->willReturnSelf();
        $order->method('isCanceled')->willReturn($canceled);
        $order->method('getIncrementId')->willReturn('100000001');

        return $order;
    }

    // ------------------------------------------------------------------
    // Helper: build a payment mock with or without an auth transaction
    // ------------------------------------------------------------------
    private function buildPaymentMock(bool $hasAuthTransaction): \PHPUnit\Framework\MockObject\MockObject
    {
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $authTransaction = $hasAuthTransaction
            ? $this->createMock(Transaction::class)
            : null;

        $payment->method('getAuthorizationTransaction')->willReturn($authTransaction);
        $payment->method('getData')->willReturn([]);

        return $payment;
    }

    /**
     * When Magento's $order->cancel() succeeds, the order should be commented
     * as "Order Cancelled" and no void attempt should be made.
     */
    public function testHandlePostTransactionDecision_SuccessfulCancel(): void
    {
        $order   = $this->buildOrderMock(true);
        $payment = $this->buildPaymentMock(false);
        $order->method('getPayment')->willReturn($payment);
        $order->method('canCancel')->willReturn(true);
        $order->method('canCreditmemo')->willReturn(false);
        $order->method('canHold')->willReturn(false);

        $item = $this->getMockBuilder(\Magento\Framework\DataObject::class)
            ->addMethods(['setSyncFlag', 'setRetries', 'setSyncLastError', 'getRetries', 'save'])
            ->getMock();
        $item->method('setSyncFlag')->willReturnSelf();
        $item->method('save')->willReturnSelf();

        $this->forterConfig->expects($this->once())
            ->method('addCommentToOrder')
            ->with($order, 'Order Cancelled');

        // void must NOT be called
        $payment->expects($this->never())->method('void');

        $this->decline->handlePostTransactionDescision($order, $item);
    }

    /**
     * When $order->cancel() fails but a payment authorization transaction
     * exists and void() succeeds, the fallback comment should be added.
     */
    public function testHandlePostTransactionDecision_FailedCancelVoidSucceeds(): void
    {
        $order   = $this->buildOrderMock(false);
        $payment = $this->buildPaymentMock(true);
        $order->method('getPayment')->willReturn($payment);
        $order->method('canCancel')->willReturn(true);
        $order->method('canCreditmemo')->willReturn(false);
        $order->method('canHold')->willReturn(false);

        $item = $this->getMockBuilder(\Magento\Framework\DataObject::class)
            ->addMethods(['setSyncFlag', 'setRetries', 'setSyncLastError', 'getRetries', 'save'])
            ->getMock();
        $item->method('setSyncFlag')->willReturnSelf();
        $item->method('save')->willReturnSelf();

        $payment->expects($this->once())->method('void');

        $this->forterConfig->expects($this->once())
            ->method('addCommentToOrder')
            ->with($order, 'Order payment voided (fallback)');

        $this->decline->handlePostTransactionDescision($order, $item);
    }

    /**
     * When $order->cancel() fails and void() also throws an exception, both
     * the void error and the final cancellation failure comment should be logged.
     */
    public function testHandlePostTransactionDecision_FailedCancelVoidFails(): void
    {
        $order   = $this->buildOrderMock(false);
        $payment = $this->buildPaymentMock(true);
        $order->method('getPayment')->willReturn($payment);
        $order->method('canCancel')->willReturn(true);
        $order->method('canCreditmemo')->willReturn(false);
        $order->method('canHold')->willReturn(false);

        $item = $this->getMockBuilder(\Magento\Framework\DataObject::class)
            ->addMethods(['setSyncFlag', 'setRetries', 'setSyncLastError', 'getRetries', 'save'])
            ->getMock();
        $item->method('setSyncFlag')->willReturnSelf();
        $item->method('save')->willReturnSelf();

        $payment->expects($this->once())
            ->method('void')
            ->willThrowException(new \Exception('Gateway error'));

        // Expect: void failure log, then final cancellation failure comment
        $this->forterConfig->expects($this->once())
            ->method('addCommentToOrder')
            ->with($order, 'Order Cancellation attempt failed');

        $this->forterConfig->expects($this->atLeastOnce())
            ->method('log')
            ->withConsecutive(
                [$this->stringContains('Payment void fallback failed')],
                [$this->stringContains('Cancellation Failure')]
            );

        $this->decline->handlePostTransactionDescision($order, $item);
    }

    /**
     * When $order->cancel() fails but there is no authorization transaction,
     * void() should not be attempted and the cancellation failure comment is added.
     */
    public function testHandlePostTransactionDecision_FailedCancelNoAuthTransaction(): void
    {
        $order   = $this->buildOrderMock(false);
        $payment = $this->buildPaymentMock(false); // no auth transaction
        $order->method('getPayment')->willReturn($payment);
        $order->method('canCancel')->willReturn(true);
        $order->method('canCreditmemo')->willReturn(false);
        $order->method('canHold')->willReturn(false);

        $item = $this->getMockBuilder(\Magento\Framework\DataObject::class)
            ->addMethods(['setSyncFlag', 'setRetries', 'setSyncLastError', 'getRetries', 'save'])
            ->getMock();
        $item->method('setSyncFlag')->willReturnSelf();
        $item->method('save')->willReturnSelf();

        $payment->expects($this->never())->method('void');

        $this->forterConfig->expects($this->once())
            ->method('addCommentToOrder')
            ->with($order, 'Order Cancellation attempt failed');

        $this->decline->handlePostTransactionDescision($order, $item);
    }
}
