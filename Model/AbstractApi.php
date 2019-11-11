<?php

namespace Forter\Forter\Model;

use Forter\Forter\Model\Config as ForterConfig;
use \Magento\Framework\HTTP\Client\Curl;

class AbstractApi
{
    public function __construct(
      Curl $curl,
      ForterConfig $forterConfig
    ) {
      $this->curl = $curl;
      $this->forterConfig = $forterConfig;
    }

    public function sendApiRequest($data)
    {
      try {


        $numberoftries = 1;

        do {
          $this->curl->reset();
          $this->setCurlOptions(strlen($data));
          $this->curl->post(
              $this->_yotpoConfig->getYotpoApiUrl($path),
              $data
          );

          $body = $this->_getCurlBody();
          $errorCode =  $body['error_code'];

          if($error_code != 0) {
            $numberoftries++;
            $this->calcTimeOut(); // ToDo
          }
        }
        while($numberoftries < 4);

            return $this->_prepareCurlResponseData();
      } catch (\Exception $e) {
        $this->reportToForterOnCatch(); //ToDo
      }
    }

    private function setCurlOptions($bodyLen)
    {
      $headers = $this->getCurlHeaders();
      $password = $this->configHelper->getSecretKey() . ":";

      /* if body exist */
      if ($body !== null) {
        $this->curl->setOption(CURLOPT_POST, 1);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Content-Length', $bodyLen));
      }

      /* Curl Headers */
      $this->curl->addHeader('x-forter-siteid', $this->forterConfig->getSiteID());
      $this->curl->addHeader('api-version', $this->forterConfig->getApiVersion());
      $this->curl->addHeader('x-forter-extver', $this->forterConfig->getModuleVersion());
      $this->curl->addHeader('x-forter-client', 'magento2');


      /* Curl Options */
      $this->curl->setOption(CURLOPT_USERPWD, $password);
      $this->curl->setOption(CURLOPT_CONNECTTIMEOUT_MS,$connect_timeout_ms);
      $this->curl->setOption(CURLOPT_TIMEOUT_MS,$total_timeout_ms);
      $this->curl->setOption(CURLOPT_RETURNTRANSFER,true);
      $this->curl->setOption(CURLOPT_SSL_VERIFYPEER,true);
      $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, 2);
    }

    /**
     * @return array
     */
    protected function _prepareCurlResponseData()
    {
        $responseData = [
            'status' => $this->_getCurlStatus(),
            'headers' => $this->_getCurlHeaders(),
            'body' => $this->_getCurlBody(),
        ];
        return $responseData;
    }

    /**
     * @param bool $refresh
     * @return array
     */
    protected function _getCurlBody($refresh = false)
    {
        if ($this->body === null || $refresh) {
            $this->body = json_decode($this->curl->getBody());
        }

        return $this->body;
    }

    /**
     * @param bool $refresh
     * @return array
     */
    protected function _getCurlHeaders($refresh = false)
    {
        if ($this->headers === null || $refresh) {
            $this->headers = $this->curl->getHeaders();
        }

        return $this->headers;
    }

    /**
     * @param bool $refresh
     * @return array
     */
    protected function _getCurlBody($refresh = false)
    {
        if ($this->body === null || $refresh) {
            $this->body = json_decode($this->curl->getBody());
        }

        return $this->body;
    }

    protected function calcTimeOut(){

    }

    protected function reportToForterOnCatch(){

    }
}
