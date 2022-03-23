<?php
namespace Forter\Forter\Model\Mapper\PaymentTypes;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\Mapper\Utils;
interface IPaymentType {
    public function setup(Config $config,Utils $utilsMapping);
    public function setMapper(\stdClass $mapper = null, $storeId = -1, $orderId= -1 );
    public function process($order, $payment);
    public function installmentService($order, $payment);
}
