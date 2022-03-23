<?php
namespace Forter\Forter\Model\Mapper;

use Forter\Forter\Model\Mapper\PaymentTypes\IPaymentType;
use Forter\Forter\Model\Config;

class PaymentHandler implements IPaymentType
{
    private IPaymentType $payment;
    protected Config $config;
    protected Utils $utilsMapping;
    public function __construct()  {
    }

    public function setPayment(IPaymentType $payment,  Config $config,Utils $utilsMapping) {
        $this->payment = $payment;
        $this->config = $config;
        $this->utilsMapping = $utilsMapping;
        $this->setup($config, $utilsMapping);
    }

    public function setup(Config $config,Utils $utilsMapping) {
        $this->payment->setup($config, $utilsMapping);
    }

    public function setMapper(\stdClass $mapper = null, $storeId=-1, $orderId =-1)
    {
        $mapping = $this->utilsMapping->locateLocalMapperOrFetch($this->config->isDebugEnabled(), $storeId, $orderId);
        $this->payment->setMapper(json_decode($mapping), $storeId, $orderId);
    }

    public function process($order, $payment) {
        return $this->payment->process($order, $payment);
    }

    public function installmentService($order, $payment) {
        return $this->payment->installmentService($order, $payment);
    }

}
