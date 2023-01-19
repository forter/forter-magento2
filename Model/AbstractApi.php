<?php

namespace Forter\Forter\Model;

use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPrepere;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl as ClientInterface;
use Magento\Framework\Event\ManagerInterface;

/**
 * Class AbstractApi
 * @package Forter\Forter\Model
 */
class AbstractApi
{
    /**
     *
     */
    public const ERROR_ENDPOINT = 'https://api.forter-secure.com/errors/';

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
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @method __construct
     * @param  PaymentPrepere   $paymentPrepere
     * @param  Session          $checkoutSession
     * @param  ClientInterface  $clientInterface
     * @param  ForterConfig     $forterConfig
     * @param  ManagerInterface $eventManager
     * @param ObjectManagerInterface $objectManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        PaymentPrepere $paymentPrepere,
        Session $checkoutSession,
        ClientInterface $clientInterface,
        ForterConfig $forterConfig,
        ResourceConnection $resource,
        ManagerInterface $eventManager
    ) {
        $this->paymentPrepere = $paymentPrepere;
        $this->checkoutSession = $checkoutSession;
        $this->clientInterface = $clientInterface;
        $this->forterConfig = $forterConfig;
        $this->resource = $resource;
        $this->eventManager = $eventManager;
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
                $this->setCurlOptions(strlen($data ?? ''), $tries);
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

        $url = "https://api.forter-secure.com/v2/status/" . $order->getIncrementId();
        $this->sendApiRequest($url, json_encode($this->json));
    }

    /**
     * @method triggerRecommendationEvents
     * @param  object                         $response
     * @param  object                         $order
     * @param  string                         $timing (pre / post / cron)
     * @return AbstractApi
     */
    public function triggerRecommendationEvents($response, $order, $timing = null)
    {
        if (($recommendations = $this->forterConfig->getRecommendationsFromResponse($response))) {
            foreach ($recommendations as $recommendation) {
                if (!$recommendation || !is_string($recommendation)) {
                    continue;
                }

                $eventName = 'forter_recommendation_' . strtolower(preg_replace('/\s+/', '_', $recommendation));
                $eventData = [
                    'recommendation' => $recommendation,
                    'forter_response' => $response,
                    'order' => $order,
                    'timing' => $timing
                ];

                $this->eventManager->dispatch($eventName, $eventData);

                $eventData['order_increment_id'] = $order->getIncrementId(); //Log only order increment ID instead of the whole object.
                $this->forterConfig->log('[Recommendation Event Triggered] ' . $eventName, "debug", ['event_data' => $eventData]);
            }
        }

        return $this;
    }
}
