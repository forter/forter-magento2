<?php

namespace Forter\Forter\Model;

use Forter\Forter\Model\Config as ForterConfig;
use \Magento\Framework\HTTP\ClientInterface;

class AbstractApi
{
    const ERROR_ENDPOINT = 'https://api.forter-secure.com/errors/';

    public function __construct(
      ClientInterface $clientInterface,
      ForterConfig $forterConfig
    ) {
      $this->clientInterface = $clientInterface;
      $this->forterConfig = $forterConfig;
    }

    public function sendApiRequest($url,$data)
    {
      try {
        $tries = 1;

        do{
          $tries++;
          $timeOutStatus = $this->calcTimeOut($tries);
          $this->setCurlOptions(strlen($data),$tries);
          $this->clientInterface->post($url,$data);
          $response = $this->clientInterface->getBody();
          $response = json_decode($response);

          if($response->status == 'success'){
            return true;
          }

        } while($timeOutStatus);

        return false;


      } catch (\Exception $e) {
        //$this->reportToForterOnCatch($e); TODO log instead of send otherwise endless loops
      }
    }

    private function setCurlOptions($bodyLen,$tries)
    {

      /* Curl Headers */
      $this->clientInterface->addHeader('x-forter-siteid', $this->forterConfig->getSiteId());
      $this->clientInterface->addHeader('api-version', $this->forterConfig->getApiVersion());
      $this->clientInterface->addHeader('x-forter-extver', $this->forterConfig->getModuleVersion());
      $this->clientInterface->addHeader('x-forter-client', 'magento2');
      $this->clientInterface->addHeader('Content-Type', 'application/json');
      $this->clientInterface->addHeader('Content-Length', $bodyLen);

      /* Curl Options */
      $this->clientInterface->setOption(CURLOPT_USERNAME,$this->forterConfig->getSecretKey());
      $this->clientInterface->setOption(CURLOPT_RETURNTRANSFER,true);
      $this->clientInterface->setOption(CURLOPT_SSL_VERIFYPEER,true);
      $this->clientInterface->setOption(CURLOPT_SSL_VERIFYHOST, 2);
    }

    private function calcTimeOut($tries){
      $timeOutSettingsArray = $this->forterConfig->getTimeOutSettings();

      $connect_timeout_ms = min(
        ($tries * $timeOutSettingsArray['base_connection_timeout']),
        $timeOutSettingsArray['max_connection_timeout']
      );

      $total_timeout_ms = min(
        ($tries * $timeOutSettingsArray['base_request_timeout']),
        $timeOutSettingsArray['max_request_timeout']
      );

      $this->clientInterface->setOption(CURLOPT_CONNECTTIMEOUT_MS,$connect_timeout_ms);
      $this->clientInterface->setOption(CURLOPT_TIMEOUT_MS,$total_timeout_ms);

      if($connect_timeout_ms >= $timeOutSettingsArray['max_connection_timeout']){
        if($total_timeout_ms >= $timeOutSettingsArray['max_request_timeout']){
         return false;
       }
      }

      return true;
   }

   public function reportToForterOnCatch($e){
     ini_set('memory_limit','-1');
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

     $response = $this->sendApiRequest($url,json_encode($json));
   }
}
