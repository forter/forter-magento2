<?

namespace Forter\Forter\Model\Mapper;

use Forter\Forter\Model\Mapper\PaymentTypes\IPaymentType;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLoggeabstractr;
use Forter\Forter\Model\ForterLoggerMessage;
use stdClass;

class PaymentHandler implements IPaymentType
{
    public function __construct(
        protected IPaymentType $payment,
        protected Utils $utilsMapping,
        protected Config $config)
    {
    }

    public function setMapper(stdClass $mapper, $storeId=-1, $orderId =-1)
    {
        $mapping = $this->utilsMapping->locateLocalMapperOrFetch($this->config->isDebugEnabled(), $storeId, $orderId);
        $this->payment->setMapper(json_decode($mapping), $storeId, $orderId);
    }

    public function process($order, $payment) {
        return $this->payment->process($order, $payment);
    }

    public function getExtraData($order, $payment) {
        return $this->payment->getExtraData($order, $payment);
    }

}
