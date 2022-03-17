<?
namespace Forter\Forter\Model\Mapper\PaymentTypes;

interface IPaymentType {
    public function setMapper(\stdClass $mapper);
    public function process($order, $payment);
}
