<?php

namespace Forter\Forter\Plugin\Thirdparty\Adyen\RequestBuilder;

use Magento\Framework\ObjectManagerInterface;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\Order as RequestBuilderOrder;
use Magento\Framework\Serialize\SerializerInterface;

class Order
{
    /**
     * @var Config
     */
    protected $forterConfig;

    /**
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Order Plugin constructor.
     * @param Config $forterConfig
     */
    public function __construct(
        Config $forterConfig,
        AbstractApi $abstractApi,
        ObjectManagerInterface $objectManager,
        SerializerInterface $serializer
    ) {
        $this->objectManager = $objectManager;
        $this->forterConfig = $forterConfig;
        $this->abstractApi = $abstractApi;
        $this->serializer = $serializer;
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
            $logArray = [];
            $this->forterConfig->log('Forter Adyen Module integration start');
            $logArray[1] = 'Forter Adyen Module integration start';
            if (!$this->forterConfig->isEnabled()) {
                $result = $proceed($order, $orderStage);
                return $result;
            }

            $result = $proceed($order, $orderStage);

            $method = $order->getPayment()->getMethod();
            if (strpos($method, 'adyen') === false) {
                return $result;
            }

            $notificationFactory = $this->objectManager->create('Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory');
            $notifications = $notificationFactory->create();

            $notifications->addFilter('merchant_reference', $result['orderId'], 'eq');
            $notifications->addFilter('event_code', 'AUTHORISATION', 'eq');
            $notification = $notifications->getFirstItem();

            if ($notifications->getSize() < 1) {
                $logArray[0] = 'Forter Adyen Module:' . $result['orderId'] . ' No AUTHORISATION result was found for this user';
                $this->forterConfig->log('Forter Adyen Module:' . $result['orderId'] . ' No AUTHORISATION result was found for this user');
                $result['additionalInformation']['adyen_debug'] = $logArray;
                return $result;
            }

            
            $payment = $order->getPayment();
            $this->forterConfig->log('Forter Adyen Module:' . $result['orderId'] . ', Payment method is:' . $method);
            $logArray[2] = 'Forter Adyen Module:' . $result['orderId'] . ', Payment method is:' . $method;

            if ($method == 'adyen_cc') {
                $logArray[3] = 'Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_cc method';
                $this->forterConfig->log('Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_cc method');
                $notificationAdditionalData = $this->serializer->unserialize($notification->getAdditionalData());

                if ($notificationAdditionalData['expiryDate']) {
                    $ExpireDate = explode("/", $notificationAdditionalData['expiryDate']);
                }

                if (isset($notificationAdditionalData['cardHolderName'])) {
                    $result['payment'][0]['creditCard']['nameOnCard'] = $notificationAdditionalData['cardHolderName'];
                }

                if (isset($notificationAdditionalData['cardPaymentMethod'])) {
                    $result['payment'][0]['creditCard']['cardBrand'] = $notificationAdditionalData['cardPaymentMethod'];
                }

                if (isset($notificationAdditionalData['cardBin'])) {
                    $result['payment'][0]['creditCard']['bin'] = $notificationAdditionalData['cardBin'];
                }

                if (isset($notificationAdditionalData['cardIssuingCountry'])) {
                    $result['payment'][0]['creditCard']['countryOfIssuance'] = $notificationAdditionalData['cardIssuingCountry'];
                }

                if (isset($notificationAdditionalData['cvcResult'])) {
                    $result['payment'][0]['creditCard']['verificationResults']['cvvResult'] = $notificationAdditionalData['cvcResult'];
                }

                if (isset($notificationAdditionalData['authCode'])) {
                    $result['payment'][0]['creditCard']['verificationResults']['authorizationCode'] = $notificationAdditionalData['authCode'];
                }

                if (isset($notificationAdditionalData['avsResult'])) {
                    $result['payment'][0]['creditCard']['verificationResults']['avsFullResult'] = $notificationAdditionalData['avsResult'];
                }

                if (isset($ExpireDate[0])) {
                    $result['payment'][0]['creditCard']['expirationMonth'] = $ExpireDate[0];
                }

                if (isset($ExpireDate[1])) {
                    $result['payment'][0]['creditCard']['expirationYear'] = $ExpireDate[1];
                }

                if (isset($notificationAdditionalData['cardSummary'])) {
                    $result['payment'][0]['creditCard']['lastFourDigits'] = $notificationAdditionalData['cardSummary'];
                }
            } elseif ($method == 'adyen_hpp' && (strpos($payment->getData('cc_type'), 'paypal') !== false )) {
                $logArray[3] = 'Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method';
                $this->forterConfig->log('Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method');
                $notificationAdditionalData = $this->serializer->unserialize($notification->getAdditionalData());

                if (isset($notificationAdditionalData['paypalPayerId'])) {
                    $result['payment'][0]['paypal']['payerId']= $notificationAdditionalData['paypalPayerId'];
                }

                if (isset($notificationAdditionalData['paypalEmail'])) {
                    $result['payment'][0]['paypal']['payerEmail']= $notificationAdditionalData['paypalEmail'];
                }

                if (isset($notificationAdditionalData['paypalAddressStatus'])) {
                    $result['payment'][0]['paypal']['payerAddressStatus']= $notificationAdditionalData['paypalAddressStatus'];
                }

                if (isset($notificationAdditionalData['paypalPayerStatus'])) {
                    $result['payment'][0]['paypal']['payerStatus']= $notificationAdditionalData['paypalPayerStatus'];
                }

                if (isset($notificationAdditionalData['paypalPaymentStatus'])) {
                    $result['payment'][0]['paypal']['paymentStatus']= $notificationAdditionalData['paypalPaymentStatus'];
                } elseif (isset($notificationAdditionalData['paypalPayerStatus'])) {
                    $result['payment'][0]['paypal']['paymentStatus']= $notificationAdditionalData['paypalPayerStatus'];
                }

                if (isset($notificationAdditionalData['paypalProtectionEligibility'])) {
                    $result['payment'][0]['paypal']['protectionEligibility']= $notificationAdditionalData['paypalProtectionEligibility'];
                }

                if (isset($notificationAdditionalData['paypalPayerResidenceCountry'])) {
                    $result['payment'][0]['paypal']['payerAccountCountry']= $notificationAdditionalData['paypalPayerResidenceCountry'];
                }

                if (isset($notificationAdditionalData['paypalCorrelationId'])) {
                    $result['payment'][0]['paypal']['correlationId']= $notificationAdditionalData['paypalCorrelationId'];
                }

                if (isset($notificationAdditionalData['paypalExpressCheckoutToken'])) {
                    $result['payment'][0]['paypal']['checkoutToken']= $notificationAdditionalData['paypalExpressCheckoutToken'];
                }

                $result['payment'][0]['paypal']['paymentGatewayData']['gatewayName'] = 'adyen_hpp';
                $result['payment'][0]['paypal']['paymentMethod'] = $notification->getPaymentMethod() ? $notification->getPaymentMethod() : '';
                $result['payment'][0]['paypal']['paymentGatewayData']['gatewayTransactionId'] = $order->getPayment()->getCcTransId() ? $order->getPayment()->getCcTransId() : '';
                $result['payment'][0]['paypal']['fullPaypalResponsePayload'] = $notificationAdditionalData ? $notificationAdditionalData : '';
            }
            $logArray[4] = $result['payment'];
            $logArray[5] = json_encode('Forter Adyen Module integration end');
            $this->forterConfig->log('Forter Adyen Module integration end');

            $result['additionalInformation']['adyen_debug'] = $logArray;
            return $result;
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
