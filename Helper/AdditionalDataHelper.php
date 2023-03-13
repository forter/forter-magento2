<?php

namespace Forter\Forter\Helper;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\RmaFactory\ForterRmaCollectionFactory as ForterRmaCollectionFactory;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Rma\Api\RmaAttributesManagementInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\CollectionFactory as CreditmemoCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;

class AdditionalDataHelper
{
    const STATE_OPEN = Creditmemo::STATE_OPEN;
    const STATE_REFUNDED = Creditmemo::STATE_REFUNDED;
    const STATE_CANCELED = Creditmemo::STATE_CANCELED;

    /**
     * @var AttributeOptionManagementInterface
     */
    protected $attributeOptionManagement;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CreditmemoCollectionFactory
     */
    protected $creditmemoFactory;

    /**
     * @var ShipmentCollectionFactory
     */
    protected $shipmentCollection;

    /**
     * @var ForterRmaCollectionFactory
     */
    protected $rmaCollectionFactory;

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
        ],
        'refundDate' => ''
    ];

    protected $compensationStatus = [
        'totalGrantedAmount' => [
            'amountLocalCurrency' => '',
            'currency' => '',
        ],
        'statusData' => [
            'updatedStatus' => '',
            'compensationTypeGranted' => '',
            'reasonCategory' => '',
            'returnMethodGranted' => '',
        ]
    ];

    /**
     * OrderSaveAfter constructor.
     * @param Config $config
     * @param AttributeOptionManagementInterface $attributeOptionManagement
     * @param CreditmemoCollectionFactory $creditmemoFactory
     * @param ModuleManager $moduleManager
     * @param ShipmentCollectionFactory $shipmentCollection
     */
    public function __construct(
        Config                             $config,
        AttributeOptionManagementInterface $attributeOptionManagement,
        CreditmemoCollectionFactory        $creditmemoFactory,
        ModuleManager                      $moduleManager,
        ShipmentCollectionFactory          $shipmentCollection,
        ForterRmaCollectionFactory         $rmaCollectionFactory
    )
    {
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->config = $config;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->moduleManager = $moduleManager;
        $this->shipmentCollection = $shipmentCollection;
        $this->rmaCollectionFactory = $rmaCollectionFactory;
    }

    /**
     * @param $order
     * @return string[]|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getShipmentData($order)
    {
        if (!$this->config->isEnabled() && !$this->config->isOrderShippingStatusEnable()) {
            return;
        }

        $data = [
            'carrier' => '',
            'trackingNumber' => ''
        ];

        $shipments = $this->shipmentCollection->create()->addFieldToFilter('order_id', $order->getId());

        if ($shipments->getSize() == 0) {
            return $data;
        }

        $trackingNumbers = [];
        $carrierCodes = [];

        foreach ($shipments as $shipment) {
            $tracksCollection = $shipment->getTracksCollection();
            foreach ($tracksCollection as $track) {
                $trackingNumbers[] = $track->getNumber() ?? '';
                $carrierCodes[] = $track->getTitle() ?? '';
            }
        }

        if (!empty($trackingNumbers)) {
            $data['trackingNumber'] = implode(',', $trackingNumbers);
        }

        if (!empty($carrierCodes)) {
            $data['carrier'] = implode(',', $carrierCodes);
        }
        return $data;
    }

    /**
     * @param $order
     * @return array|false
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCreditMemoData($order)
    {
        if (!$this->config->isEnabled() && !$this->config->isOrderCreditMemoStatusEnable()) {
            return false;
        }

        $creditMemoCollection = $this->creditmemoFactory->create()->addFieldToFilter('order_id', $order->getId())->setOrder('created_at', 'DESC');

        if ($creditMemoCollection->getSize() > 0) {
            $creditMemo = $creditMemoCollection->getFirstItem();
            $payment = $order->getPayment();
            $refundMethod = $creditMemo->getTransactionId() ? 'ORIGINAL_PAYMENT_METHOD' : 'DIFFERENT_PAYMENT_METHOD';
            $refundAmount = $creditMemo->getGrandTotal();
            $baseRefundAmount = $creditMemo->getBaseGrandTotal();

            // Refund Information
            $this->refundInformation = [
                'refundMethod' => $refundMethod,
                'refundAuthorizationCode' => $creditMemo->getTransactionId() ? $payment->getLastTransId() : '',
                'refundStatus' => $this->getCreditMemoState($creditMemo->getState()),
                'refundInitiatedBy' => 'MERCHANT',
                'isAutomatedRefund' => false,
                'isFullRefund' => $refundAmount == $order->getGrandTotal(),
                'refundAmount' => [
                    'amountLocalCurrency' => strval($refundAmount),
                    'currency' => $creditMemo->getOrderCurrencyCode(),
                ]
            ];
            return $this->refundInformation;
        }
        return null;
    }

    /**
     * @param $order
     * @return array|null
     * @throws InputException
     * @throws StateException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getRmaData($order)
    {
        if (!$this->moduleManager->isEnabled('Magento_Rma') || (!$this->config->isEnabled() && !$this->config->isOrderRmaStatusEnable())) {
            return null;
        }

        $rmaCollection = $this->rmaCollectionFactory->getForterRmaCollection($order->getId());

        if ($rmaCollection->getSize() > 0) {
            $rma = $rmaCollection->getFirstItem();
            $refundAmount = 0;
            $itemsList = [];
            $orderItems = $order->getItems();
            $orderCurrencyCode = $order->getOrderCurrencyCode();
            $orderStore = $order->getStore();
            $compensationTypeGranted = null;
            $reasonCategory = null;

            foreach ($rma->getItems() as $item) {
                $product = $orderItems[$item->getOrderItemId()];
                $rowTotalInclTax = $product->getRowTotalInclTax() * $item->getQtyRequested();
                $basePriceInclTax = $product->getBasePriceInclTax();
                $refundAmount += $rowTotalInclTax;
                $productType = $product->getProductType() == 'virtual' ? 'NON_TANGIBLE' : 'TANGIBLE';

                $compensationType = $this->getRmaAttributeOptionLabel('resolution', $item->getResolution());
                $reason = $this->getRmaAttributeOptionLabel('reason', $item->getReason()) ?? '';

                if ($compensationTypeGranted === null) {
                    $compensationTypeGranted = $this->mapResolutionLabel($compensationType ?? '');
                } elseif ($compensationTypeGranted !== $this->mapResolutionLabel($compensationType ?? '')) {
                    $compensationTypeGranted = 'MIXED';
                }

                if ($reasonCategory === null) {
                    $reasonCategory = $this->mapReasonLabel($reason);
                } elseif ($reasonCategory !== $this->mapReasonLabel($reason)) {
                    $reasonCategory = 'UNKNOWN';
                }

                $itemsList[] = [
                    'basicItemData' => [
                        'name' => $item->getProductName(),
                        'quantity' => intval($item->getQtyRequested()),
                        'type' => $productType,
                        'price' => [
                            'amountLocalCurrency' => strval($rowTotalInclTax / $product->getQtyOrdered()),
                            'currency' => $orderCurrencyCode,

                        ],
                        'value' => [
                            'amountLocalCurrency' => strval($basePriceInclTax),
                            'currency' => $orderCurrencyCode,
                        ],
                        'productId' => $product->getProductId()
                    ]
                ];
            }

            $this->compensationStatus = [
                'totalGrantedAmount' => [
                    'amountLocalCurrency' => strval($refundAmount),
                    'currency' => $orderCurrencyCode,
                ],
                'statusData' => [
                    'updatedStatus' => 'ACCEPTED_BY_MERCHANT',
                    'compensationTypeGranted' => $compensationTypeGranted,
                    'reasonCategory' => $reasonCategory,
                    'returnMethodGranted' => 'SHIP_TO_WAREHOUSE'
                ],
                'itemStatus' => $itemsList
            ];
            return $this->compensationStatus;
        }
        return null;
    }

    /**
     * @param $state
     * @return string
     */
    public function getCreditMemoState($state)
    {
        $states = [
            Creditmemo::STATE_OPEN => 'REQUESTED',
            Creditmemo::STATE_REFUNDED => 'COMPLETED',
            Creditmemo::STATE_CANCELED => 'DECLINED'
        ];
        return isset($states[$state]) ? $states[$state] : '';
    }

    /**
     * Get attribute option label by attribute code and value ID.
     *
     * @param string $attributeCode
     * @param int $valueId
     *
     * @return string|null
     * @throws InputException
     * @throws StateException
     */
    public function getRmaAttributeOptionLabel(string $attributeCode, int $valueId): ?string
    {
        $options = $this->attributeOptionManagement->getItems(
            RmaAttributesManagementInterface::ENTITY_TYPE,
            $attributeCode
        );

        foreach ($options as $option) {
            if ($option->getValue() == $valueId) {
                return strtolower($option->getLabel());
            }
        }

        return null;
    }

    /**
     * @param string $reasonLabel
     * @return string|null
     */
    public function mapReasonLabel(string $reasonLabel): ?string
    {
        switch ($reasonLabel) {
            case 'wrong color':
            case 'wrong size':
                return 'WRONG_ITEM';
            case 'out of service':
                return 'DAMAGED_GOODS';
            default:
                return 'OTHER';
        }
    }

    /**
     * @param string $resolutionLabel
     * @return string|null
     */
    public function mapResolutionLabel(string $resolutionLabel): ?string
    {
        switch ($resolutionLabel) {
            case 'exchange':
                return 'REPLACEMENT';
            case 'refund':
                return 'REFUND';
            case 'store credit':
                return 'CREDIT';
            default:
                return 'UNKNOWN';
        }
    }

    /**
     * @param $order
     * @return bool
     */
    public function getCreditMemoRmaSize($order)
    {
        $creditMemoCollection = $this->creditmemoFactory->create()->addFieldToFilter('order_id', $order->getId());
        $rmaCollection = null;
        if ($this->moduleManager->isEnabled('Magento_Rma')) {
            $rmaCollection = $this->rmaCollectionFactory->getForterRmaCollection($order->getId());
        }
        if ($rmaCollection !== null) {
            return ($creditMemoCollection->getSize() > 0 || $rmaCollection->getSize() > 0);
        }
        return ($creditMemoCollection->getSize() > 0);
    }
}
