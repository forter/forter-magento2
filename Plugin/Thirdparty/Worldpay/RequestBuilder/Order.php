<?php

namespace Forter\Forter\Plugin\Thirdparty\Worldpay\RequestBuilder;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\Order as RequestBuilderOrder;
use Magento\Framework\App\ResourceConnection;

class Order
{

    /**
     *
     * @var Config
     */
    protected $forterConfig;

    /**
     *
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     *
     * @var ResourceConnection
     */
    protected $_resource;

    /**
     * Order Plugin constructor.
     * @param Config $forterConfig
     */
    public function __construct(
        Config $forterConfig,
        AbstractApi $abstractApi,
        ResourceConnection $resource
    ) {
        $this->forterConfig = $forterConfig;
        $this->abstractApi = $abstractApi;
        $this->_resource = $resource;
    }

    /**
     * @param RequestBuilderOrder $subject
     * @param callable $proceed
     * @param $order
     * @param $orderStage
     * @return string
     */
    public function aroundBuildTransaction(RequestBuilderOrder $subject, callable $proceed, $order, $orderStage)
    {
        try {
            if (!$this->forterConfig->isEnabled()) {
                $result = $proceed($order, $orderStage);
                return $result;
            }

            $result = $proceed($order, $orderStage);

            $method = $order->getPayment()->getMethod();

            if ($method == 'worldpay_cc' || $method == 'worldpay_apm') {
                $connection = $this->_resource->getConnection();
                $tableName = $this->_resource->getTableName('worldpay_payment');
                $select_sql = "Select * FROM " . $tableName . " Where order_id=" . $result['orderId'];
                $dataSet = $connection->fetchAll($select_sql);
                $worldPayPayment = $dataSet[0];
                if (isset($worldPayPayment['card_number'])) {
                    $result['payment'][0]['creditCard']['bin'] = substr($worldPayPayment['card_number'], 0, 6);
                    $result['payment'][0]['creditCard']['lastFourDigits'] = substr($worldPayPayment['card_number'], -4);
                }

                if (isset($worldPayPayment['payment_type'])) {
                    $result['payment'][0]['creditCard']['cardBrand'] = $worldPayPayment['payment_type'];
                }

                if (isset($worldPayPayment['cvc_result'])) {
                    $result['payment'][0]['creditCard']['verificationResults']['cvvResult'] = $worldPayPayment['cvc_result'];
                }

                if (isset($worldPayPayment['avs_result'])) {
                    $result['payment'][0]['creditCard']['verificationResults']['avsFullResult'] = $worldPayPayment['avs_result'];
                }
            }

            return $result;
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }

        return;
    }
}