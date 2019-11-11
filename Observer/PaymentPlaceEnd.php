<?php
namespace Forter\Forter\Observer;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\AuthRequestBuilder;
use Forter\Forter\Model\AbstractApi;
use Magento\Framework\Event\ObserverInterface;

class PaymentPlaceEnd implements ObserverInterface
{

    public function __construct(
        AbstractApi $abstractApi,
        Config $config,
        AuthRequestBuilder $authRequestBuilder
    ) {

        $this->abstractApi = $abstractApi;
        $this->config = $config;
        $this->authRequestBuilder = $authRequestBuilder;
    }


    public function execute(\Magento\Framework\Event\Observer $observer) {
      $this->config->log('1-PaymentPlaceEnd');
      $order = $observer->getEvent()->getPayment()->getOrder();
      $data = $this->authRequestBuilder->buildTransaction($order);
      $test = $this->abstractApi->getCurlHeaders();
      $test = json_encode($test);
      $this->config->log($test);
      $this->config->log($data);

    }
}
