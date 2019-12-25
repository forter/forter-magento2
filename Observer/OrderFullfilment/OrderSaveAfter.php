<?php

namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class OrderSaveAfter
 * @package Forter\Forter\Observer\OrderFullfilment
 */
class OrderSaveAfter implements ObserverInterface
{
    const ORDER_FULFILLMENT_STATUS_ENDPOINT = "https://api.forter-secure.com/v2/status/";

    /**
     * @var Config
     */
    private $config;

    /**
     * OrderSaveAfter constructor.
     * @param AbstractApi $abstractApi
     * @param Config $config

     */
    public function __construct(
        AbstractApi $abstractApi,
        Config $config,
        Decline $decline
    ) {
        $this->decline = $decline;
        $this->abstractApi = $abstractApi;
        $this->config = $config;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled() || !$this->config->isOrderFulfillmentEnable()) {
            return false;
        }

        $order = $observer->getEvent()->getOrder();

        $forterResponse = $order->getForterResponse();
        $forterResponse = json_decode($forterResponse);

        if ($this->config->getIsPost()) {
            if ($forterResponse->action == "decline") {
                $this->decline->handlePostTransactionDescision($order);
            }
        }

        $orderState = $order->getState();
        $orderOrigState = $order->getOrigData('state');

        if ($orderState == 'complete' && $orderOrigState != 'complete') {
            $orderState = 'COMPLETED';
        } elseif ($orderState == 'processing' && $orderOrigState != 'processing') {
            $orderState = 'PROCESSING';
        } elseif ($orderState == 'canceled' && $orderOrigState != 'canceled') {
            $orderState = 'CANCELED_BY_MERCHANT';
        } else {
            return false;
        }

        $json = [
            "orderId" => $order->getIncrementId(),
            "eventTime" => time(),
            "updatedStatus" => $orderState,
        ];

        try {
            $url = self::ORDER_FULFILLMENT_STATUS_ENDPOINT . $order->getIncrementId();
            $response = $this->abstractApi->sendApiRequest($url, json_encode($json));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }
}
