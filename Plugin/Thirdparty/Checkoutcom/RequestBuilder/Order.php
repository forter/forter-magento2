<?php

namespace Forter\Forter\Plugin\Thirdparty\Checkoutcom\RequestBuilder;

use Magento\Framework\ObjectManagerInterface;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\Order as RequestBuilderOrder;

class Order
{

    /**
     * @var Config
     */
    public $forterConfig;

    public $checkoutComCollection;

    /**
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Order Plugin constructor.
     * @param Config $forterConfig
     */
    public function __construct(
        Config $forterConfig,
        AbstractApi $abstractApi,
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
        $this->forterConfig = $forterConfig;
        $this->abstractApi = $abstractApi;
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

            if ($result && $result['payment'][0]['paymentMethodNickname'] != 'checkoutcom_card_payment') {
                return $result;
            }

            $this->checkoutComCollection = $this->objectManager->create('CheckoutCom\Magento2\Model\ResourceModel\WebhookEntity\Collection');

            $collection = $this->checkoutComCollection;
            $collection->addFilter('order_id', $result['additionalIdentifiers']['additionalOrderId'], 'eq');
            $collection->addFilter('event_type', 'payment_approved', 'eq');

            if ($collection->getSize() < 1) {
                return $result;
            }

            $paymentCheckoutCom = $collection->getLastItem();
            $paymentCheckoutCom = $paymentCheckoutCom->getEventData();
            $paymentCheckoutCom = json_decode($paymentCheckoutCom, true);
            if (isset($paymentCheckoutCom['data']['source']['name'])) {
                $result['payment'][0]['creditCard']['nameOnCard'] = $paymentCheckoutCom['data']['source']['name'];
            }
            if (isset($paymentCheckoutCom['data']['source']['scheme'])) {
                $result['payment'][0]['creditCard']['cardBrand'] = $paymentCheckoutCom['data']['source']['scheme'];
            }
            if (isset($paymentCheckoutCom['data']['source']['bin'])) {
                $result['payment'][0]['creditCard']['bin'] = $paymentCheckoutCom['data']['source']['bin'];
            }
            if (isset($paymentCheckoutCom['data']['source']['issuer_country'])) {
                $result['payment'][0]['creditCard']['countryOfIssuance'] = $paymentCheckoutCom['data']['source']['issuer_country'];
            }
            if (isset($paymentCheckoutCom['data']['source']['issuer'])) {
                $result['payment'][0]['creditCard']['cardBank'] = $paymentCheckoutCom['data']['source']['issuer'];
            }
            if (isset($paymentCheckoutCom['data']['source']['cvv_check'])) {
                $result['payment'][0]['creditCard']['verificationResults']['cvvResult'] = $paymentCheckoutCom['data']['source']['cvv_check'];
            }
            if (isset($paymentCheckoutCom['data']['auth_code'])) {
                $result['payment'][0]['creditCard']['verificationResults']['authorizationCode'] = $paymentCheckoutCom['data']['auth_code'];
            }
            if (isset($paymentCheckoutCom['data']['response_code'])) {
                $result['payment'][0]['creditCard']['verificationResults']['processorResponseCode'] = $paymentCheckoutCom['data']['response_code'];
            }
            if (isset($paymentCheckoutCom['data']['response_summary'])) {
                $result['payment'][0]['creditCard']['verificationResults']['processorResponseText'] = $paymentCheckoutCom['data']['response_summary'];
            }
            if (isset($paymentCheckoutCom['data']['source']['avs_check'])) {
                $result['payment'][0]['creditCard']['verificationResults']['avsFullResult'] = $paymentCheckoutCom['data']['source']['avs_check'];
            }
            if (isset($paymentCheckoutCom['data']['source']['avs_check'])) {
                $result['payment'][0]['creditCard']['verificationResults']['avsFullResult'] = $paymentCheckoutCom['data']['source']['avs_check'];
            }
            if (isset($paymentCheckoutCom['data']['metadata']['methodId'])) {
                $result['payment'][0]['creditCard']['paymentGatewayData']['gatewayName'] = $paymentCheckoutCom['data']['metadata']['methodId'];
            }
            if (isset($paymentCheckoutCom['data']['processing']['acquirer_transaction_id'])) {
                $result['payment'][0]['creditCard']['paymentGatewayData']['gatewayTransactionId'] = $paymentCheckoutCom['data']['processing']['acquirer_transaction_id'];
            }
            if (isset($paymentCheckoutCom['data']['source']['expiry_month'])) {
                $expiryMonth = strval($paymentCheckoutCom['data']['source']['expiry_month']);
                $result['payment'][0]['creditCard']['expirationMonth'] = strlen($expiryMonth) == 1 ? '0' . $expiryMonth : $expiryMonth;
            }
            if (isset($paymentCheckoutCom['data']['source']['expiry_year'])) {
                $result['payment'][0]['creditCard']['expirationYear'] = strval($paymentCheckoutCom['data']['source']['expiry_year']);
            }
            if (isset($paymentCheckoutCom['data']['source']['last_4'])) {
                $result['payment'][0]['creditCard']['lastFourDigits'] = $paymentCheckoutCom['data']['source']['last_4'];
            }
            if ($paymentCheckoutCom) {
              $result['payment'][0]['creditCard']['fullResponsePayload'] = $paymentCheckoutCom;
            }

            return $result;
        } catch (Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }

        return;
    }
}