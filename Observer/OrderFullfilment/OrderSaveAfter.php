<?php

namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Model\AbstractApi;
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
     * @param Config $config
     * @param  AbstractApi $abstractApi
     */
    public function __construct(
        AbstractApi $abstractApi,
        Config $config
    ) {
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
        if (!$this->config->isEnabled()) {
            return false;
        }

        $order = $observer->getEvent()->getOrder();

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
