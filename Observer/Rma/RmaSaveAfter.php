<?php

namespace Forter\Forter\Observer\Rma;

use Forter\Forter\Helper\AdditionalDataHelper;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RmaSaveAfter implements ObserverInterface
{

    /**
     * @var AttributeOptionManagementInterface
     */
    protected $attributeOptionManagement;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var EavConfig
     */
    protected $eavConfig;

    /**
     * @var AdditionalDataHelper
     */
    protected $additionalDataHelper;

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
     * @param AbstractApi $abstractApi
     * @param Config $config
     * @param AttributeOptionManagementInterface $attributeOptionManagement
     * @param EavConfig $eavConfig
     * @param AdditionalDataHelper $additionalDataHelper
     */
    public function __construct(
        AbstractApi                        $abstractApi,
        Config                             $config,
        AttributeOptionManagementInterface $attributeOptionManagement,
        EavConfig                          $eavConfig,
        AdditionalDataHelper               $additionalDataHelper
    ) {
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->eavConfig = $eavConfig;
        $this->additionalDataHelper = $additionalDataHelper;
    }

    public function execute(Observer $observer)
    {
        if (!$this->config->isEnabled() && !$this->config->isOrderRmaStatusEnable()) {
            return false;
        }

        $rma = $observer->getData('data_object');

        $refundAmount = 0;
        $itemsList = [];
        $order = $rma->getOrder();
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

            $compensationType = $this->additionalDataHelper->getRmaAttributeOptionLabel('resolution', $item->getResolution());
            $reason = $this->additionalDataHelper->getRmaAttributeOptionLabel('reason', $item->getReason()) ?? '';

            if ($compensationTypeGranted === null) {
                $compensationTypeGranted = $this->additionalDataHelper->mapResolutionLabel($compensationType ?? '');
            } elseif ($compensationTypeGranted !== $this->additionalDataHelper->mapResolutionLabel($compensationType ?? '')) {
                $compensationTypeGranted = 'MIXED';
            }

            if ($reasonCategory === null) {
                $reasonCategory = $this->additionalDataHelper->mapReasonLabel($reason);
            } elseif ($reasonCategory !== $this->additionalDataHelper->mapReasonLabel($reason)) {
                $reasonCategory = 'UNKNOWN';
            }

            $itemsList[] = [
                'basicItemData' => [
                    'name' => $item->getProductName(),
                    'quantity' => intval($item->getQtyRequested()),
                    'type' => $productType,
                    'price' => [
                        'amountLocalCurrency' => strval($rowTotalInclTax/$product->getQtyOrdered()),
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
                'returnMethodGranted' => 'SHIP_TO_WAREHOUSE',
            ],
            'itemStatus' => $itemsList
        ];

        $statusData = ['compensationStatus' => $this->compensationStatus];

        $this->abstractApi->sendOrderStatus($order, $statusData);
    }
}
