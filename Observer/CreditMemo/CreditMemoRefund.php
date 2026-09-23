<?php

namespace Forter\Forter\Observer\CreditMemo;

use Forter\Forter\Helper\AdditionalDataHelper;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;

class CreditMemoRefund implements ObserverInterface
{
    public const STATE_OPEN = Creditmemo::STATE_OPEN;
    public const STATE_REFUNDED = Creditmemo::STATE_REFUNDED;
    public const STATE_CANCELED = Creditmemo::STATE_CANCELED;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     * @var AdditionalDataHelper
     */
    protected $additionalDataHelper;

    protected $refundInformation = [
        'refundMethod' => '',
        'refundAuthorizationCode' => '',
        'refundStatus' => '',
        'refundInitiatedBy' => '',
        'isAutomatedRefund' => '',
        'isFullRefund' => null,
        'refundAmount' => [
            'amountLocalCurrency' => '',
            'currency' => '',
        ]
    ];

    /**
     * OrderSaveAfter constructor.
     * @param AbstractApi $abstractApi
     * @param Config $config
     * @param AdditionalDataHelper $additionalDataHelper
     */
    public function __construct(
        AbstractApi          $abstractApi,
        Config               $config,
        AdditionalDataHelper $additionalDataHelper
    ) {
        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->additionalDataHelper = $additionalDataHelper;
    }

    public function execute(Observer $observer)
    {
        if (!$this->config->isEnabled() || !$this->config->isOrderCreditMemoStatusEnable()) {
            return false;
        }

        /** @var Creditmemo $creditMemo */
        $creditMemo = $observer->getEvent()->getCreditmemo();
        $order = $creditMemo->getOrder();
        $payment = $order->getPayment();
        $refundMethod = $creditMemo->getTransactionId() ? 'ORIGINAL_PAYMENT_METHOD' : 'DIFFERENT_PAYMENT_METHOD';
        $refundAmount = $creditMemo->getGrandTotal();
        $baseRefundAmount = $creditMemo->getBaseGrandTotal();

        // Refund Information
        $this->refundInformation = [
            'refundMethod' => $refundMethod,
            'refundAuthorizationCode' => $creditMemo->getTransactionId() ? $payment->getLastTransId() : '',
            'refundStatus' => $this->additionalDataHelper->getCreditMemoState($creditMemo->getState()),
            'refundInitiatedBy' => 'MERCHANT',
            'isAutomatedRefund' => false,
            'isFullRefund' => $refundAmount == $order->getGrandTotal(),
            'refundAmount' => [
                'amountLocalCurrency' => strval($refundAmount),
                'currency' => $creditMemo->getOrderCurrencyCode(),
            ]
        ];

        $statusData = [
            'refundInformation' => $this->refundInformation,
        ];

        $this->abstractApi->sendOrderStatus($order, $statusData);
    }
}
