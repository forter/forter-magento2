<?php
namespace Forter\Forter\Observer;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\AuthRequestBuilder;
use Magento\Framework\Event\ObserverInterface;

class PaymentPlaceEnd implements ObserverInterface
{

    public function __construct(
        Config $config,
        AuthRequestBuilder $authRequestBuilder
    ) {
        $this->config = $config;
        $this->authRequestBuilder = $authRequestBuilder;
    }


    public function execute(\Magento\Framework\Event\Observer $observer) {
      $this->config->log('1-PaymentPlaceEnd');
      $order = $observer->getEvent()->getPayment()->getOrder();
      $data = $this->authRequestBuilder->buildTransaction($order);
      $this->config->log('PaymentPlaceEnd');
      $this->config->log($data);

    }
}
