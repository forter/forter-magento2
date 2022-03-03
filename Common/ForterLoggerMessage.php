<?php
namespace Forter\Forter\Common;
/***
 * class ForterLoggerMessage
 * @package Forter\Forter\Common\ForterLoggerMessage
 */
class ForterLoggerMessage {
    public $storeId;
    public $orderId;
    public $transactionId = '';
    public $message;
    public \StdClass $metaData = new \StdClass;
    public function __construct(string $storeId, string $orderId, string $message ) {
        $this->storeId = $storeId;
        $this->message = $message;
        $this->orderId = $orderId;
    }

    public function ToJson() {
        return json_encode($this);
    }
}