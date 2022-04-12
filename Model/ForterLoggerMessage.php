<?php
namespace Forter\Forter\Model;
/***
 * class ForterLoggerMessage
 * @package Forter\Forter\Model\ForterLoggerMessage
 */
class ForterLoggerMessage {
    public $siteId;
    public $orderId;
    public $transactionId = '';
    public $message;
    public \StdClass $metaData;
    public function __construct(string $siteId, string $orderId, string $message ) {
        $this->siteId = $siteId;
        $this->message = $message;
        $this->orderId = $orderId;
        $this->metaData = new \StdClass();
    }

    public function ToJson() {
        return json_encode($this);
    }
}
