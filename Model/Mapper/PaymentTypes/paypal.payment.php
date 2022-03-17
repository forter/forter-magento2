<?

namespace Forter\Forter\Model\Mapper\PaymentTypes;

class PaypalPayment extends BasePayment
{
    public function __construct()
    {
    }
    public function process($order, $payment)
    {
        $paymentMapping = $this->mapper->payments->paypal;
        return [
            "payerId" => $payment->getAdditionalInformation($paymentMapping->paypal_payer_id),
            "payerEmail" => $payment->getAdditionalInformation($paymentMapping->paypal_payer_email),
            "payerStatus" => $payment->getAdditionalInformation($paymentMapping->paypal_payer_status),
            "payerAddressStatus" => $payment->getAdditionalInformation($paymentMapping->paypal_address_status),
            "paymentMethod" => $payment->getMethod(),
            "paymentStatus" => $payment->getAdditionalInformation($paymentMapping->paypal_payment_status),
            "protectionEligibility" => $payment->getAdditionalInformation($paymentMapping->paypal_protection_eligibility),
            "checkoutToken" => $payment->getAdditionalInformation($paymentMapping->paypal_express_checkout_token),
            "correlationId" => $payment->getAdditionalInformation($paymentMapping->paypal_correlation_id),
            "paymentGatewayData" => [
                "gatewayName" => $payment->getMethod(),
                "gatewayTransactionId" => $payment->getTransactionId(),
            ],
            "fullPaypalResponsePayload" => $payment->getAdditionalInformation()
        ];
    }
    public function getExtraData($order, $payment)
    {
        return null;
    }
}
