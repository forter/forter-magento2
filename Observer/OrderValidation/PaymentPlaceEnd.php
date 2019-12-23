<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\AuthRequestBuilder;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestHandler\Approve;
use Forter\Forter\Model\RequestHandler\Decline;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderManagementInterface;

/**
 * Class PaymentPlaceEnd
 * @package Forter\Forter\Observer\OrderValidation
 */
class PaymentPlaceEnd implements ObserverInterface
{
    /**
     *
     */
    const VALIDATION_API_ENDPOINT = 'https://api.forter-secure.com/v2/orders/';
    /**
     * @var Decline
     */
    private $decline;
    /**
     * @var Approve
     */
    private $approve;
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var AuthRequestBuilder
     */
    private $authRequestBuilder;
    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * PaymentPlaceEnd constructor.
     * @param Decline $decline
     * @param Approve $approve
     * @param AbstractApi $abstractApi
     * @param Config $config
     * @param AuthRequestBuilder $authRequestBuilder
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        Decline $decline,
        Approve $approve,
        AbstractApi $abstractApi,
        Config $config,
        AuthRequestBuilder $authRequestBuilder,
        OrderManagementInterface $orderManagement
    ) {
        $this->decline = $decline;
        $this->approve = $approve;
        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->authRequestBuilder = $authRequestBuilder;
        $this->orderManagement = $orderManagement;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->config->isEnabled() || !$this->config->getIsPost()) {
            return false;
        }

        $order = $observer->getEvent()->getPayment()->getOrder();
        $data = $this->authRequestBuilder->buildTransaction($order);

        try {
            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();
            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }

        $order->setForterResponse($response);
        $response = json_decode($response);

        if ($response->status == 'failed') {
            $order->setForterStatus('not reviewed');
            return true;
        }

        $order->setForterStatus($response->action);

        if ($response->action == 'decline' && $response->status == 'success') {
            $this->decline->handlePostTransactionDescision($order);
        }
    }
}
