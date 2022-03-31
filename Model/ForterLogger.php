<?php

namespace Forter\Forter\Model;
use Forter\Forter\Model\Config as ForterConfig;
class ForterLogger
{
    private $httpClient;
    private $BASE_URL = 'https://api.forter-secure.com';
    /**
     * @method __construct
     * @param  ForterConfig    $forterConfig
     * @param  ClientInterface $clientInterface
     */
    public function __construct(ForterConfig $forterConfig) {
        $this->forterConfig = $forterConfig;
        $this->httpClient = new \GuzzleHttp\Client(['base_uri' => $this->BASE_URL]);
    }

    public function SendLog(ForterLoggerMessage $data) {
        try {
            $json = $data->ToJson();
            $requestOps = [];
            $requestOps['x-forter-siteid'] = $this->forterConfig->getSiteId();
            $requestOps['api-version'] = $this->forterConfig->getApiVersion();
            $requestOps['x-forter-extver'] = $this->forterConfig->getModuleVersion();
            $requestOps['x-forter-client'] = $this->forterConfig->getMagentoFullVersion();
            $requestOps['Content-Type'] = 'application/json';
            $requestOps['Accept'] = 'application/json';
            $requestOps['Authorization'] =['Basic '.$this->forterConfig->getSecretKey().':'];
            $requestOps['json'] = $json;
            $this->forterConfig->log('send log request: ' .$this->BASE_URL.'/magento/orderFlow/'.$this->forterConfig->getSiteId().'/'.$data->orderId);
            $response = $this->httpClient->request('post','/magento/orderFlow/'.$this->forterConfig->getSiteId().'/'.$data->orderId, $requestOps);
            $this->forterConfig->log(sprintf('send log status: %s', $response->getStatusCode()));
        } catch (\Exception $e) {
            $this->forterConfig->log('Error:' . $e->getMessage());
        }
    }
}
