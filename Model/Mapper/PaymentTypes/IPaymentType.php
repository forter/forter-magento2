<?
namespace Forter\Forter\Model\Mapper\PaymentTypes;

interface IPaymentType {
    public function setMapper(\stdClass $mapper, $storeId = -1, $orderId= -1 );
    public function process($order, $payment);
    public function getExtraData($order, $payment);
}
