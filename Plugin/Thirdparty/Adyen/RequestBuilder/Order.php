<?php

namespace Forter\Forter\Plugin\Thirdparty\Adyen\RequestBuilder;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\RequestBuilder\Order as RequestBuilderOrder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Serialize;

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
     * @var Serialize
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
        Serialize $serializer
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
            $brandCode = $order->getPayment()->getAdditionalInformation()['brand_code'] ?? null;
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
            $notificationAdditionalData = $this->serializer->unserialize($notification->getAdditionalData());

            if ($method == 'adyen_cc') {
                $logArray[3] = 'Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method';
                $this->forterConfig->log('Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method');
                $result = $this->handleAdyenCc($result, $notificationAdditionalData, $notification, $order);
            }

            if (($method == 'adyen_hpp' && (strpos($payment->getData('cc_type'), 'paypal') !== false)) || $method === 'adyen_paypal') {
                $logArray[3] = 'Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method';
                $this->forterConfig->log('Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method');
                $result = $this->handleAdyenPaypal($result, $notificationAdditionalData, $notification, $order);
            }

            if (($method == 'adyen_hpp' && $brandCode == 'googlepay') || ($method === 'adyen_googlepay')) {
                $logArray[3] = 'Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method';
                $this->forterConfig->log('Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method');
                $result = $this->handleAdyenGooglePay($result, $notificationAdditionalData, $notification, $order);
            }

            if (($method == 'adyen_hpp' && $brandCode == 'applepay') || ($method === 'adyen_applepay')) {
                $logArray[3] = 'Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method';
                $this->forterConfig->log('Forter Adyen Module:' . $result['orderId'] . ', Entered adyen_hpp method');
                $result = $this->handleAdyenApplePay($result, $notificationAdditionalData, $notification, $order);
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

    private function handleAdyenCc($result, $notificationAdditionalData, $notification, $order)
    {
        if (isset($notificationAdditionalData['paypalPayerId'])) {
            $result['payment'][0]['paypal']['payerId']= $notificationAdditionalData['paypalPayerId'];
        } else {
            $result['payment'][0]['paypal']['payerId'] = '';
        }

        if (isset($notificationAdditionalData['paypalEmail'])) {
            $result['payment'][0]['paypal']['payerEmail']= $notificationAdditionalData['paypalEmail'];
        } else {
            $result['payment'][0]['paypal']['payerEmail']= '';
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

        return $result;
    }

    private function handleAdyenPaypal($result, $notificationAdditionalData, $notification, $order)
    {
        if (isset($notificationAdditionalData['paypalPayerId'])) {
            $result['payment'][0]['paypal']['payerId']= $notificationAdditionalData['paypalPayerId'];
        } else {
            $result['payment'][0]['paypal']['payerId'] = '';
        }

        if (isset($notificationAdditionalData['paypalEmail'])) {
            $result['payment'][0]['paypal']['payerEmail']= $notificationAdditionalData['paypalEmail'];
        } else {
            $result['payment'][0]['paypal']['payerEmail']= '';
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

        return $result;
    }

    private function handleAdyenGooglePay($result, $notificationAdditionalData, $notification, $order)
    {
        unset($result['payment'][0]['creditCard']);

        if (isset($notificationAdditionalData['checkout.cardAddedBrand'])) {
            $result['payment'][0]['androidPay']['cardBrand']= $notificationAdditionalData['checkout.cardAddedBrand'];
        }

        if (isset($notificationAdditionalData['cardHolderName'])) {
            $result['payment'][0]['androidPay']['nameOnCard']= $notificationAdditionalData['cardHolderName'];
        }

        if (isset($notificationAdditionalData['expiryDate'])) {
            $expiryDate = explode("/", $notificationAdditionalData['expiryDate']);

            $month = $expiryDate[0];
            if (strlen($month) == 1) {
                $month = "0" . $month;
            }

            $result['payment'][0]['androidPay']['expirationMonth']= $month;
            $result['payment'][0]['androidPay']['expirationYear']= $expiryDate[1];
        }

        if (isset($notificationAdditionalData['cardSummary'])) {
            $result['payment'][0]['androidPay']['lastFourDigits']= $notificationAdditionalData['cardSummary'];
        }

        if (isset($notificationAdditionalData['authCode'])) {
            $result['payment'][0]['androidPay']['verificationResults']['authorizationCode']= $notificationAdditionalData['authCode'];
            $result['payment'][0]['androidPay']['verificationResults']['processorResponseCode']= $notificationAdditionalData['authCode'];
        }

        if (isset($notificationAdditionalData['avsResultRaw'])) { //sau avsResult , are text mai mult
            $result['payment'][0]['androidPay']['verificationResults']['avsFullResult']= $notificationAdditionalData['avsResultRaw'];
        }

        if (isset($notificationAdditionalData['cvcResultRaw'])) { // sau cvcResult
            $result['payment'][0]['androidPay']['verificationResults']['cvvResult']= $notificationAdditionalData['cvcResult'][0];
        }

        if (isset($notificationAdditionalData['eci'])) {
            $result['payment'][0]['androidPay']['verificationResults']['eciValue']= $notificationAdditionalData['eci'] === 'N/A' ? '' : $notificationAdditionalData['eci'];
        }

        if (isset($notificationAdditionalData['refusalReasonRaw'])) {
            $result['payment'][0]['androidPay']['verificationResults']['processorResponseText']= $notificationAdditionalData['refusalReasonRaw'];
        }

        if (isset($notificationAdditionalData['merchantReference'])) {
            $result['payment'][0]['androidPay']['paymentGatewayData']['gatewayMerchantId']= $notificationAdditionalData['merchantReference'];
        }

        $result['payment'][0]['androidPay']['cardType'] = 'CREDIT';
        $result['payment'][0]['androidPay']['paymentGatewayData']['gatewayName'] = $method;
        $result['payment'][0]['androidPay']['paymentGatewayData']['gatewayTransactionId'] = $order->getPayment()->getCcTransId() ? $order->getPayment()->getCcTransId() : '';
        return $result;
    }

    private function handleAdyenApplePay($result, $notificationAdditionalData, $notification, $order)
    {
        unset($result['payment'][0]['creditCard']);

        if ($notificationAdditionalData['expiryDate']) {
            $ExpireDate = explode("/", $notificationAdditionalData['expiryDate']);
        }

        if (isset($notificationAdditionalData['cardHolderName'])) {
            $result['payment'][0]['applePay']['nameOnCard'] = $notificationAdditionalData['cardHolderName'];
        }

        if (isset($notificationAdditionalData['cardPaymentMethod'])) {
            $result['payment'][0]['applePay']['cardBrand'] = $notificationAdditionalData['cardPaymentMethod'];
        }

        if (isset($notificationAdditionalData['cardBin'])) {
            $result['payment'][0]['applePay']['bin'] = $notificationAdditionalData['cardBin'];
        }

        if (isset($notificationAdditionalData['cardIssuingCountry'])) {
            $result['payment'][0]['applePay']['countryOfIssuance'] = $notificationAdditionalData['cardIssuingCountry'];
        }

        if (isset($notificationAdditionalData['cvcResult'])) {
            $result['payment'][0]['applePay']['verificationResults']['cvvResult'] = $notificationAdditionalData['cvcResult'];
        }

        if (isset($notificationAdditionalData['authCode'])) {
            $result['payment'][0]['applePay']['verificationResults']['authorizationCode'] = $notificationAdditionalData['authCode'];
        }

        if (isset($notificationAdditionalData['avsResult'])) {
            $result['payment'][0]['applePay']['verificationResults']['avsFullResult'] = $notificationAdditionalData['avsResult'];
        }

        if (isset($ExpireDate[0])) {
            $result['payment'][0]['applePay']['expirationMonth'] = $ExpireDate[0];
        }

        if (isset($ExpireDate[1])) {
            $result['payment'][0]['applePay']['expirationYear'] = $ExpireDate[1];
        }

        if (isset($notificationAdditionalData['cardSummary'])) {
            $result['payment'][0]['applePay']['lastFourDigits'] = $notificationAdditionalData['cardSummary'];
        }

        $result['payment'][0]['applePay']['cardType'] = 'CREDIT';
        $result['payment'][0]['applePay']['paymentGatewayData']['gatewayName'] = $method;
        $result['payment'][0]['applePay']['paymentGatewayData']['gatewayTransactionId'] = $order->getPayment()->getCcTransId() ? $order->getPayment()->getCcTransId() : '';

        return $result;
    }

}
