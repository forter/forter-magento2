<?php

namespace Forter\Forter\Model\Mappers;

class PayBrightMapper
{
    public function getPaybrightDetails($order, $payment)
    {
        return [
            "serviceName" => $payment->getMethod(),
            "firstName" => $order->getShippingAddress()->getFirstname(),
            "lastName" => $order->getShippingAddress()->getLastname(),
            "serviceResponseCode" => "200",
            "paymentId" => $payment->getData('last_trans_id'),
            "fullResponsePayload" => $payment->getAdditionalInformation()
        ];
    }
}