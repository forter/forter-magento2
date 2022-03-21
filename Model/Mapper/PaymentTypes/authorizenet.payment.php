<?

namespace Forter\Forter\Model\Mapper\PaymentTypes;

class AuthorizeNetPayment extends BasePayment
{
    public function __construct()
    {
    }
    public function process($order, $payment)
    {
        $paymentMapping = $this->mapper->payments->authorizeNet;
        $detailsArray = [];
        $ccLast4 = $payment->getAdditionalInformation($paymentMapping->cardLast4);
        if ($ccLast4) {
            $detailsArray['lastFourDigits'] = $ccLast4;
        }
        $cc_type= $payment->getAdditionalInformation($paymentMapping->cardType);
        if ($cc_type) {
            $detailsArray['cardBrand'] = $cc_type;
        }
        $cvvResponseCode = $payment->getAdditionalInformation($paymentMapping->cardCvv);
        if ($cvvResponseCode) {
            $detailsArray['cvvResult'] = $cvvResponseCode;
        }
        $authCode = $payment->getAdditionalInformation($paymentMapping->cardAuthorizationCode);
        if ($authCode) {
            $detailsArray['authorizationCode'] = $authCode;
        }
        return $this->preferCcDetails($payment, $detailsArray);
    }
    public function getExtraData($order, $payment)
    {
        return null;
    }
}
