<?php


namespace Forter\Forter\Plugin\Order;

use Magento\Sales\Model\Order\Payment as MagentoPayment;
use Forter\Forter\Model\AbstractApi;

/**
 * Class Payment
 * @package Forter\Forter\Plugin\Customer\Model
 */
class Payment
{
    /**
     * @var AbstractApi
     */
    private $abstractApi;

    /**
     * Payment constructor.
     * @param AbstractApi $abstractApi
     */
    public function __construct(AbstractApi $abstractApi)
    {
        $this->abstractApi = $abstractApi;
    }

    /**
     * @param MagentoPayment $subject
     * @param callable $proceed
     * @return mixed
     * @throws \Exception
     */
    public function aroundPlace(MagentoPayment $subject, callable $proceed)
    {
        try {
            $result = $proceed();
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw $e;
        }
        return $result;
    }
}