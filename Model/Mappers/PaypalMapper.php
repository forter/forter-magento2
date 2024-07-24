<?php

namespace Forter\Forter\Model\Mappers;

class PaypalMapper
{
    public function getPaypalDetails($payment)
    {
        return [
            "payerId" => $payment->getAdditionalInformation("paypal_payer_id") ?? '',
            "payerEmail" => $payment->getAdditionalInformation("paypal_payer_email") ?? '',
            "payerStatus" => $payment->getAdditionalInformation("paypal_payer_status"),
            "payerAddressStatus" => $payment->getAdditionalInformation("paypal_address_status"),
            "paymentMethod" => $payment->getMethod(),
            "paymentStatus" => $payment->getAdditionalInformation("paypal_payment_status"),
            "protectionEligibility" => $payment->getAdditionalInformation("paypal_protection_eligibility"),
            "correlationId" => $payment->getAdditionalInformation("paypal_correlation_id"),
            "checkoutToken" => $payment->getAdditionalInformation("paypal_express_checkout_token"),
            "paymentGatewayData" => [
                "gatewayName" => $payment->getMethod(),
                "gatewayTransactionId" => $payment->getTransactionId(),
            ],
            "fullPaypalResponsePayload" => $payment->getAdditionalInformation()
        ];
    }
}