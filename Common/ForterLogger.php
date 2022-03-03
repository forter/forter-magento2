<?php

namespace Forter\Forter\Common;
const DebugMode =1;
const ProductionMode = 0;
class ForterLogger
{
    private $LOG_ENDPOINT = 'https://api.forter-secure.com/errors/';
    private static $instance = null;
    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new ForterLogger();
        }

        return self::$instance;
    }

    public function SendLog(ForterLoggerMessage $data) {
        $json = $data->ToJson();
        new \GuzzleHttp\Psr7\Request('POST', $this->LOG_ENDPOINT, ['body' => $json]);
    }
}
