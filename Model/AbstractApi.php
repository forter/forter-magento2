<?php

namespace Forter\Forter\Model;

use Forter\Forter\Model\Config as ForterConfig;
use Magento\Framework\HTTP\ClientInterface;

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
     * @var ClientInterface
     */
    private $clientInterface;
    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * AbstractApi constructor.
     * @param ClientInterface $clientInterface
     * @param Config $forterConfig
     */
    public function __construct(
        ClientInterface $clientInterface,
        ForterConfig $forterConfig
    ) {
        $this->clientInterface = $clientInterface;
        $this->forterConfig = $forterConfig;
    }

    /**
     * @param $url
     * @param $data
     * @return bool|false|string
     */
    public function sendApiRequest($url, $data)
    {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        try {
            $tries = 1;

            do {
                $tries++;
                $timeOutStatus = $this->calcTimeOut($tries);
                $this->setCurlOptions(strlen($data), $tries);
                $this->forterConfig->log('Request Url:' . $url);
                $this->forterConfig->log('Request Body:' . $data);
                $this->clientInterface->post($url, $data);
                $response = $this->clientInterface->getBody();
                $this->forterConfig->log('Response Body:' . $response, 'debug');
                $this->forterConfig->log('Response Header:' . json_encode($this->clientInterface->getHeaders()));
                $response = json_decode($response);

                if ($response->status == 'success') {
                    return json_encode($response);
                }

                if ($response->forterDecision == 'APPROVE') {
                    return true;
                }
            } while ($timeOutStatus);

            return false;
        } catch (\Exception $e) {
            $this->forterConfig->log('Error:' . $e->getMessage());
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
        $this->clientInterface->addHeader('x-forter-client', 'magento2');
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

        $json = [
       "orderID" => "5610495952",
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

        $response = $this->sendApiRequest($url, json_encode($json));
    }
}
