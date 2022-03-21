<?

namespace Forter\Forter\Model\Mapper\PaymentTypes;

class PaybrightPayment extends BasePayment
{
    public function __construct()
    {
    }
    public function process($order, $payment)
    {
        $paymentMapping = $this->mapper->payments->paypal;

        return [
            "serviceName" => $payment->getMethod(),
            "firstName" => $order->getShippingAddress()->getFirstname(),
            "lastName" => $order->getShippingAddress()->getLastname(),
            "serviceResponseCode" => "200",
            "paymentId" => $payment->getData($paymentMapping->paymentId),
            "fullResponsePayload" => $payment->getAdditionalInformation()
        ];
    }
    public function getExtraData($order, $payment)
    {
        return null;
    }
}
