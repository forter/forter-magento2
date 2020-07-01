<?php

namespace Forter\Forter\Observer\OrderFullfilment;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class OrderSaveAfter
 * @package Forter\Forter\Observer\OrderFullfilment
 */
class OrderSaveBefore implements ObserverInterface
{
    const ORDER_FULFILLMENT_STATUS_ENDPOINT = "https://api.forter-secure.com/v2/status/";

    /**
     * @var Config
     */
    private $config;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * OrderSaveAfter constructor.
     * @param AbstractApi $abstractApi
     * @param Config $config

     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        AbstractApi $abstractApi,
        Config $config

    ) {
        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        //get forter client number
        $forterNumber = ($this->request->getPost('forter_web_id') != "") ? $this->request->getPost('forter_web_id') : "";
        $order = $observer->getEvent()->getOrder();
        $order->setForterWebId($forterNumber);

        if (!$this->config->isEnabled() || !$this->config->isOrderFulfillmentEnable()) {
            return false;
        }

        try {
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
            $url = self::ORDER_FULFILLMENT_STATUS_ENDPOINT . $order->getIncrementId();
            $this->abstractApi->sendApiRequest($url, json_encode($json));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
