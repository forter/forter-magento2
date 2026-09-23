<?php

namespace Forter\Forter\Observer\Shipment;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Framework\Event\ObserverInterface;

class ShipmentSaveAfter implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     * OrderSaveAfter constructor.
     * @param AbstractApi $abstractApi
     * @param Config $config
     */
    public function __construct(
        AbstractApi $abstractApi,
        Config      $config,
    ) {
        $this->abstractApi = $abstractApi;
        $this->config = $config;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled() || !$this->config->isOrderShippingStatusEnable()) {
            return false;
        }

        $currentShipment = $observer->getEvent()->getShipment();
        $order = $currentShipment->getOrder();

        $data = [
            'carrier' => '',
            'trackingNumber' => ''
        ];

        $shipments = $order->getShipmentsCollection();
        if (!$shipments) {
            return $data;
        }

        $trackingNumbers = [];
        $carrierCodes = [];

        foreach ($shipments as $shipment) {
            if ($shipment->getId() == $currentShipment->getId()) {
                $tracksCollection = $currentShipment->getTracksCollection();
                foreach ($tracksCollection as $track) {
                    $trackingNumbers[] = $track->getNumber() ?? '';
                    $carrierCodes[] = $track->getTitle() ?? '';
                }
            } else {
                $tracksCollection = $shipment->getTracksCollection();
                foreach ($tracksCollection as $track) {
                    $trackingNumbers[] = $track->getNumber() ?? '';
                    $carrierCodes[] = $track->getTitle() ?? '';
                }
            }
        }

        if (!empty($trackingNumbers)) {
            $data['trackingNumber'] = implode(',', $trackingNumbers);
        }

        if (!empty($carrierCodes)) {
            $data['carrier'] = implode(',', $carrierCodes);
        }

        $shipmentData['deliveryStatusInfo'] = $data;
        $this->abstractApi->sendOrderStatus($order, $shipmentData);
    }
}
