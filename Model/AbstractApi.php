<?php

namespace Forter\Forter\Model;

use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPrepere;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl as ClientInterface;
use Magento\Framework\ObjectManagerInterface;


/**
 * Class AbstractApi
 * @package Forter\Forter\Model
 */
class AbstractApi
{
    /**
     *
     */
    const ERROR_ENDPOINT = 'https://api.forter-secure.com/errors/';

    /**
     * Payment Prefer
     *
     * @var PaymentPrepere
     */
    private $paymentPrepere;

    /**
     * @var ClientInterface
     */
    private $checkoutSession;

    /**
     * @var ClientInterface
     */
    private $clientInterface;

    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    public $json;

    /**
     * @var ResourceConnection
     */
    protected $resource;


    /**
     * @method __construct
     * @param PaymentPrepere $paymentPrepere
     * @param Session $checkoutSession
     * @param ClientInterface $clientInterface
     * @param ForterConfig $forterConfig
     * @param ObjectManagerInterface $objectManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        PaymentPrepere $paymentPrepere,
        Session $checkoutSession,
        ClientInterface $clientInterface,
        ForterConfig $forterConfig,
        ObjectManagerInterface $objectManager,
        ResourceConnection $resource
    ) {
        $this->paymentPrepere = $paymentPrepere;
        $this->checkoutSession = $checkoutSession;
        $this->clientInterface = $clientInterface;
        $this->forterConfig = $forterConfig;
        $this->objectManager = $objectManager;
        $this->resource = $resource;
    }

    /**
     * @param $url
     * @param $data
     * @return string
     */
    public function sendApiRequest($url, $data, $type = 'post')
    {
        try {
            $tries = 1;

            do {
                $tries++;
                $timeOutStatus = $this->calcTimeOut($tries);
                $this->setCurlOptions(strlen($data ?? '') , $tries);
                $this->forterConfig->log('[Forter Request attempt number] ' . $tries, "debug");
                $this->forterConfig->log('[Forter Request Url] ' . $url, "debug");
                $this->forterConfig->log('[Forter Request Body] ' . $data, "debug");

                try {
                    if ($type == 'post') {
                        $this->clientInterface->post($url, $data);
                    } elseif ($type == 'get') {
                        $this->clientInterface->get($url);
                    }
                    $response = $this->clientInterface->getBody();
                    $this->forterConfig->log('[Forter Response Body] ' . $response, "debug");
                    $this->forterConfig->log('[Forter Response Header] ', "debug", [$this->clientInterface->getHeaders()]);

                    $response = json_decode($response);

                    if (isset($response->status) || isset($response->forterDecision)) {
                        return json_encode($response);
                    }
                } catch (\Exception $e) {
                    $this->forterConfig->log('[Exception] ' . $e->getMessage() . "\n" . $e->getTraceAsString(), "error");
                }
            } while ($timeOutStatus);

            return json_encode([
                "status" => "failed",
                "message" => "maximum retries reached, please see logs"
            ]);
        } catch (\Exception $e) {
            $this->forterConfig->log('Error:' . $e->getMessage());
            return json_encode([
                "status" => "failed",
                "message" => "an error occurred, please see logs"
            ]);
        }
    }

    /**
     * @param $bodyLen
     * @param $tries
     */
    private function setCurlOptions($bodyLen, $tries)
    {

      /* Curl Headers */
        $this->clientInterface->addHeader('x-forter-siteid', $this->forterConfig->getSiteId());
        $this->clientInterface->addHeader('api-version', $this->forterConfig->getApiVersion());
        $this->clientInterface->addHeader('x-forter-extver', $this->forterConfig->getModuleVersion());
        $this->clientInterface->addHeader('x-forter-client', $this->forterConfig->getMagentoFullVersion());
        $this->clientInterface->addHeader('Content-Type', 'application/json');
        $this->clientInterface->addHeader('Content-Length', $bodyLen);
        /* Curl Options */
        $this->clientInterface->setOption(CURLOPT_USERNAME, $this->forterConfig->getSecretKey());
        $this->clientInterface->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->clientInterface->setOption(CURLOPT_SSL_VERIFYPEER, true);
        $this->clientInterface->setOption(CURLOPT_SSL_VERIFYHOST, 2);
    }

    /**
     * @param $tries
     * @return bool
     */
    private function calcTimeOut($tries)
    {
        $timeOutSettingsArray = $this->forterConfig->getTimeOutSettings();

        $connect_timeout_ms = min(
            ($tries * $timeOutSettingsArray['base_connection_timeout']),
            $timeOutSettingsArray['max_connection_timeout']
        );

        $total_timeout_ms = min(
            ($tries * $timeOutSettingsArray['base_request_timeout']),
            $timeOutSettingsArray['max_request_timeout']
        );

        $this->clientInterface->setOption(CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout_ms);
        $this->clientInterface->setOption(CURLOPT_TIMEOUT_MS, $total_timeout_ms);

        if ($connect_timeout_ms >= $timeOutSettingsArray['max_connection_timeout']) {
            if ($total_timeout_ms >= $timeOutSettingsArray['max_request_timeout']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $e
     */
    public function reportToForterOnCatch($e)
    {
        $url = self::ERROR_ENDPOINT;
        $orderId = $this->checkoutSession->getQuote()->getReservedOrderId();

        $this->json = [
        "orderID" => $orderId,
        "exception" => [
         "message" => [
           "message" => $e->getMessage(),
           "fileName" => $e->getFile(),
           "lineNumber"=> $e->getLine(),
           "name" => get_class($e),
           "stack" => $e->getTrace()
         ],
         "debugInfo" => ""
        ]
        ];
        $this->json = json_encode($this->json);
        $this->forterConfig->log($this->json, "error");
        $this->sendApiRequest($url, $this->json);
    }

    public function getUpdatedStatusEnum($order)
    {
        $orderState = $order->getState();
        if ($orderState == "complete") {
            $orderState = "COMPLETED";
        } elseif ($orderState == "processing") {
            $orderState = "PROCESSING";
        } elseif ($orderState == "canceled") {
            $orderState = "CANCELED_BY_MERCHANT";
        } else {
            $orderState = "PROCESSING";
        }
        return $orderState;
    }

    public function sendOrderStatus($order)
    {
        $this->json = [
        "orderId" => $order->getIncrementId(),
        "eventTime" => time(),
        "updatedStatus" => $this->getUpdatedStatusEnum($order),
        "payment" => $this->paymentPrepere->generatePaymentInfo($order)
      ];

        $this->addAdditionalPaymentData($order);

        $url = "https://api.forter-secure.com/v2/status/" . $order->getIncrementId();
        $this->sendApiRequest($url, json_encode($this->json));
    }

    /**
     * @param $paymentMethod
     * @return void
     */
    public function addAdditionalPaymentData($order)
    {

        $paymentMethod = $order->getPayment()->getMethod();
        switch ($paymentMethod) {
            case 'worldpay_apm':
            case 'worldpay_cc':
                $this->worldpayAdditionalInformation($paymentMethod);
                break;
            case 'adyen_cc':
            case 'adyen_hpp':
                $this->adyenAdditionalPaymentData($order);
                break;
            case 'checkoutcom_card_payment' :
                $this->checkoutcomAdditionalPaymentData();
                break;
            default;
                break;
        }
    }

    /**
     * @param $method
     * @return void
     */
    public function worldpayAdditionalInformation($method)
    {
        try {
            if (!$this->forterConfig->isEnabled()) {
                return;
            }

            if ($method == 'worldpay_cc' || $method == 'worldpay_apm') {
                $connection = $this->resource->getConnection();
                $tableName = $this->resource->getTableName('worldpay_payment');
                $select_sql = "Select * FROM " . $tableName . " Where order_id=" . $this->json['orderId'];
                $dataSet = $connection->fetchAll($select_sql);
                $worldPayPayment = $dataSet[0];
                if (isset($worldPayPayment['card_number'])) {
                    $this->json['payment'][0]['creditCard']['bin'] = substr($worldPayPayment['card_number'], 0, 6);
                    $this->json['payment'][0]['creditCard']['lastFourDigits'] = substr($worldPayPayment['card_number'], -4);
                }

                if (isset($worldPayPayment['payment_type'])) {
                    $this->json['payment'][0]['creditCard']['cardBrand'] = $worldPayPayment['payment_type'];
                }

                if (isset($worldPayPayment['cvc_result'])) {
                    $this->json['payment'][0]['creditCard']['verificationResults']['cvvResult'] = $worldPayPayment['cvc_result'];
                }

                if (isset($worldPayPayment['avs_result'])) {
                    $this->json['payment'][0]['creditCard']['verificationResults']['avsFullResult'] = $worldPayPayment['avs_result'];
                }
            }

        } catch (\Exception $e) {
            $this->reportToForterOnCatch($e);
        }
    }

    /**
     * @param $order
     * @return void
     */
    public function adyenAdditionalPaymentData($order)
    {
        try {
            $logArray = [];
            $this->forterConfig->log('Forter Adyen Module integration start');
            $logArray[1] = 'Forter Adyen Module integration start';
            if (!$this->forterConfig->isEnabled()) {
                return;
            }

            $this->notificationFactory = $this->objectManager->create(\Adyen\Payment\Model\ResourceModel\Notification\CollectionFactory::class);

            $notifications = $this->notificationFactory->create();

            $notifications->addFilter('merchant_reference', $this->json['orderId'], 'eq');
            $notifications->addFilter('event_code', 'AUTHORISATION', 'eq');
            $notification = $notifications->getFirstItem();

            if ($notifications->getSize() < 1) {
                $logArray[0] = 'Forter Adyen Module:' . $this->json['orderId'] . ' No AUTHORISATION result was found for this user';
                $this->forterConfig->log('Forter Adyen Module:' . $this->json['orderId'] . ' No AUTHORISATION result was found for this user');
                $this->json['additionalInformation']['adyen_debug'] = $logArray;
                return;
            }

            $method = $order->getPayment()->getMethod();
            $payment = $order->getPayment();
            $this->forterConfig->log('Forter Adyen Module:' . $this->json['orderId'] . ', Payment method is:' . $method);
            $logArray[2] = 'Forter Adyen Module:' . $this->json['orderId'] . ', Payment method is:' . $method;

            if ($method == 'adyen_cc') {
                $logArray[3] = 'Forter Adyen Module:' . $this->json['orderId'] . ', Entered adyen_cc method';
                $this->forterConfig->log('Forter Adyen Module:' . $this->json['orderId'] . ', Entered adyen_cc method');
                $notificationAdditionalData = unserialize($notification->getAdditionalData());

                if ($notificationAdditionalData['expiryDate']) {
                    $ExpireDate = explode("/", $notificationAdditionalData['expiryDate']);
                }

                if (isset($notificationAdditionalData['cardHolderName'])) {
                    $this->json['payment'][0]['creditCard']['nameOnCard'] = $notificationAdditionalData['cardHolderName'];
                } else {
                    $this->json['payment'][0]['creditCard']['nameOnCard'] = '';
                }

                if (isset($notificationAdditionalData['cardPaymentMethod'])) {
                    $this->json['payment'][0]['creditCard']['cardBrand'] = $notificationAdditionalData['cardPaymentMethod'];
                }

                if (isset($notificationAdditionalData['cardBin'])) {
                    $this->json['payment'][0]['creditCard']['bin'] = $notificationAdditionalData['cardBin'];
                }

                if (isset($notificationAdditionalData['cardIssuingCountry'])) {
                    $this->json['payment'][0]['creditCard']['countryOfIssuance'] = $notificationAdditionalData['cardIssuingCountry'];
                }

                if (isset($notificationAdditionalData['cvcResult'])) {
                    $this->json['payment'][0]['creditCard']['verificationResults']['cvvResult'] = $notificationAdditionalData['cvcResult'];
                }

                if (isset($notificationAdditionalData['authCode'])) {
                    $this->json['payment'][0]['creditCard']['verificationResults']['authorizationCode'] = $notificationAdditionalData['authCode'];
                }

                if (isset($notificationAdditionalData['avsResult'])) {
                    $this->json['payment'][0]['creditCard']['verificationResults']['avsFullResult'] = $notificationAdditionalData['avsResult'];
                }

                if (isset($ExpireDate[0])) {
                    $this->json['payment'][0]['creditCard']['expirationMonth'] = $ExpireDate[0];
                }

                if (isset($ExpireDate[1])) {
                    $this->json['payment'][0]['creditCard']['expirationYear'] = $ExpireDate[1];
                }

                if (isset($notificationAdditionalData['cardSummary'])) {
                    $this->json['payment'][0]['creditCard']['lastFourDigits'] = $notificationAdditionalData['cardSummary'];
                }
            } elseif ($method == 'adyen_hpp' && (strpos($payment->getData('cc_type'), 'klarna_account') == false)) {
                $logArray[3] = 'Forter Adyen Module:' . $this->json['orderId'] . ', Entered adyen_hpp method';
                $this->forterConfig->log('Forter Adyen Module:' . $this->json['orderId'] . ', Entered adyen_hpp method');
                $notificationAdditionalData = unserialize($notification->getAdditionalData());

                if (isset($notificationAdditionalData['paypalPayerId'])) {
                    $this->json['payment'][0]['paypal']['payerId']= $notificationAdditionalData['paypalPayerId'];
                }

                if (isset($notificationAdditionalData['paypalEmail'])) {
                    $this->json['payment'][0]['paypal']['payerEmail']= $notificationAdditionalData['paypalEmail'];
                }

                if (isset($notificationAdditionalData['paypalAddressStatus'])) {
                    $this->json['payment'][0]['paypal']['payerAddressStatus']= $notificationAdditionalData['paypalAddressStatus'];
                }

                if (isset($notificationAdditionalData['paypalPayerStatus'])) {
                    $this->json['payment'][0]['paypal']['payerStatus']= $notificationAdditionalData['paypalPayerStatus'];
                }

                if (isset($notificationAdditionalData['paypalPaymentStatus'])) {
                    $this->json['payment'][0]['paypal']['paymentStatus']= $notificationAdditionalData['paypalPaymentStatus'];
                } elseif (isset($notificationAdditionalData['paypalPayerStatus'])) {
                    $this->json['payment'][0]['paypal']['paymentStatus']= $notificationAdditionalData['paypalPayerStatus'];
                }

                if (isset($notificationAdditionalData['paypalProtectionEligibility'])) {
                    $this->json['payment'][0]['paypal']['protectionEligibility']= $notificationAdditionalData['paypalProtectionEligibility'];
                }

                if (isset($notificationAdditionalData['paypalPayerResidenceCountry'])) {
                    $this->json['payment'][0]['paypal']['payerAccountCountry']= $notificationAdditionalData['paypalPayerResidenceCountry'];
                }

                if (isset($notificationAdditionalData['paypalCorrelationId'])) {
                    $this->json['payment'][0]['paypal']['correlationId']= $notificationAdditionalData['paypalCorrelationId'];
                }

                if (isset($notificationAdditionalData['paypalExpressCheckoutToken'])) {
                    $this->json['payment'][0]['paypal']['checkoutToken']= $notificationAdditionalData['paypalExpressCheckoutToken'];
                }

                $this->json['payment'][0]['paypal']['paymentGatewayData']['gatewayName'] = 'adyen_hpp';
                $this->json['payment'][0]['paypal']['paymentMethod'] = $notification->getPaymentMethod() ? $notification->getPaymentMethod() : '';
                $this->json['payment'][0]['paypal']['paymentGatewayData']['gatewayTransactionId'] = $order->getPayment()->getCcTransId() ? $order->getPayment()->getCcTransId() : '';
                $this->json['payment'][0]['paypal']['fullPaypalResponsePayload'] = $notificationAdditionalData ? $notificationAdditionalData : '';
            }
            $logArray[4] = $this->json['payment'];
            $logArray[5] = json_encode('Forter Adyen Module integration end');
            $this->forterConfig->log('Forter Adyen Module integration end');

            $this->json['additionalInformation']['adyen_debug'] = $logArray;
        } catch (\Exception $e) {
            $this->reportToForterOnCatch($e);
        }
    }

    public function checkoutcomAdditionalPaymentData()
    {
        try {
            if (!$this->forterConfig->isEnabled()) {
                return;
            }
            if ($this->json['payment'][0]['paymentMethodNickname'] != 'checkoutcom_card_payment') {
                return;
            }

            $this->checkoutComCollection = $this->objectManager->create(\CheckoutCom\Magento2\Model\ResourceModel\WebhookEntity\Collection::class);

            $collection = $this->checkoutComCollection;
            $collection->addFilter('order_id', $this->json['orderId'], 'eq');
            $collection->addFilter('event_type', 'payment_approved', 'eq');

            if ($collection->getSize() < 1) {
                return;
            }

            $paymentCheckoutCom = $collection->getLastItem();
            $paymentCheckoutCom = $paymentCheckoutCom->getEventData();
            $paymentCheckoutCom = json_decode($paymentCheckoutCom, true);
            if (isset($paymentCheckoutCom['data']['source']['name'])) {
                $this->json['payment'][0]['creditCard']['nameOnCard'] = $paymentCheckoutCom['data']['source']['name'];
            } else {
                $this->json['payment'][0]['creditCard']['nameOnCard'] = '';
            }
            if (isset($paymentCheckoutCom['data']['source']['scheme'])) {
                $this->json['payment'][0]['creditCard']['cardBrand'] = $paymentCheckoutCom['data']['source']['scheme'];
            }
            if (isset($paymentCheckoutCom['data']['source']['bin'])) {
                $this->json['payment'][0]['creditCard']['bin'] = $paymentCheckoutCom['data']['source']['bin'];
            }
            if (isset($paymentCheckoutCom['data']['source']['issuer_country'])) {
                $this->json['payment'][0]['creditCard']['countryOfIssuance'] = $paymentCheckoutCom['data']['source']['issuer_country'];
            }
            if (isset($paymentCheckoutCom['data']['source']['issuer'])) {
                $this->json['payment'][0]['creditCard']['cardBank'] = $paymentCheckoutCom['data']['source']['issuer'];
            }
            if (isset($paymentCheckoutCom['data']['source']['cvv_check'])) {
                $this->json['payment'][0]['creditCard']['verificationResults']['cvvResult'] = $paymentCheckoutCom['data']['source']['cvv_check'];
            }
            if (isset($paymentCheckoutCom['data']['auth_code'])) {
                $this->json['payment'][0]['creditCard']['verificationResults']['authorizationCode'] = $paymentCheckoutCom['data']['auth_code'];
            }
            if (isset($paymentCheckoutCom['data']['response_code'])) {
                $this->json['payment'][0]['creditCard']['verificationResults']['processorResponseCode'] = $paymentCheckoutCom['data']['response_code'];
            }
            if (isset($paymentCheckoutCom['data']['response_summary'])) {
                $this->json['payment'][0]['creditCard']['verificationResults']['processorResponseText'] = $paymentCheckoutCom['data']['response_summary'];
            }
            if (isset($paymentCheckoutCom['data']['source']['avs_check'])) {
                $this->json['payment'][0]['creditCard']['verificationResults']['avsFullResult'] = $paymentCheckoutCom['data']['source']['avs_check'];
            }
            if (isset($paymentCheckoutCom['data']['source']['avs_check'])) {
                $this->json['payment'][0]['creditCard']['verificationResults']['avsFullResult'] = $paymentCheckoutCom['data']['source']['avs_check'];
            }
            if (isset($paymentCheckoutCom['data']['metadata']['methodId'])) {
                $this->json['payment'][0]['creditCard']['paymentGatewayData']['gatewayName'] = $paymentCheckoutCom['data']['metadata']['methodId'];
            }
            if (isset($paymentCheckoutCom['data']['processing']['acquirer_transaction_id'])) {
                $this->json['payment'][0]['creditCard']['paymentGatewayData']['gatewayTransactionId'] = $paymentCheckoutCom['data']['processing']['acquirer_transaction_id'];
            }
            if (isset($paymentCheckoutCom['data']['source']['expiry_month'])) {
                $expiryMonth = strval($paymentCheckoutCom['data']['source']['expiry_month']);
                $this->json['payment'][0]['creditCard']['expirationMonth'] = strlen($expiryMonth) == 1 ? '0' . $expiryMonth : $expiryMonth;
            }
            if (isset($paymentCheckoutCom['data']['source']['expiry_year'])) {
                $this->json['payment'][0]['creditCard']['expirationYear'] = strval($paymentCheckoutCom['data']['source']['expiry_year']);
            }
            if (isset($paymentCheckoutCom['data']['source']['last_4'])) {
                $this->json['payment'][0]['creditCard']['lastFourDigits'] = $paymentCheckoutCom['data']['source']['last_4'];
            }
            if ($paymentCheckoutCom) {
                $this->json['payment'][0]['creditCard']['fullResponsePayload'] = $paymentCheckoutCom;
            }
            return;
        } catch (Exception $e) {
            $this->reportToForterOnCatch($e);
        }
        return;
    }
}
