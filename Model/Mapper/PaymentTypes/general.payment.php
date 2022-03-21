<?php

namespace Forter\Forter\Model\Mapper\PaymentTypes;

class GeneralPayment extends BasePayment
{
    public function __construct()
    {
    }

    public function installmentService($order, $payment)
    {
        return null
    }

    public function process($order, $payment)
    {
        return $this->preferCcDetails($payment);
    }
}
