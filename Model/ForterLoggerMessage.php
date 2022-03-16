<?php
namespace Forter\Forter\Model;
/***
 * class ForterLoggerMessage
 * @package Forter\Forter\Model\ForterLoggerMessage
 */
class ForterLoggerMessage {
    public $storeId;
    public $orderId;
    public $transactionId = '';
    public $message;
    public \StdClass $metaData;
    public function __construct(string $storeId, string $orderId, string $message ) {
        $this->storeId = $storeId;
        $this->message = $message;
        $this->orderId = $orderId;
        $this->metaData = new \StdClass();
    }

    public function ToJson() {
        return json_encode($this);
    }
}