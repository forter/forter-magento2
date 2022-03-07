<?php

namespace Forter\Forter\Common;
use Forter\Forter\Model\Config as ForterConfig;
const DebugMode =1;
const ProductionMode = 0;
class ForterLogger
{
    private $httpClient;
    private $LOG_ENDPOINT = 'https://api.forter-secure.com/errors';
    /**
     * @method __construct
     * @param  ForterConfig    $forterConfig
     */
    public function __construct(ForterConfig $forterConfig) {
        $this->forterConfig = $forterConfig;
        $this->httpClient = new \GuzzleHttp\Client(['base_uri' => $this->LOG_ENDPOINT]);
    }

    public function SendLog(ForterLoggerMessage $data) {
        try {
            $json = $data->ToJson();
            $requestOps = [];
            $requestOps['x-forter-siteid'] = $this->forterConfig->getSiteId();
            $requestOps['api-version'] = $this->forterConfig->getApiVersion();
            $requestOps['x-forter-extver'] = $this->forterConfig->getModuleVersion();
            $requestOps['x-forter-client'] = $this->forterConfig->getMagentoFullVersion();
            $requestOps['Accept'] = 'application/json';
            $requestOps['json'] = $json;
            $this->httpClient->requestAsync('post', '/',$requestOps);
        } catch (\Exception $e) {
            $this->forterConfig->log('Error:' . $e->getMessage());
        }
    }
}
