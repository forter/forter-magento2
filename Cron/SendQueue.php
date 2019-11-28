<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\QueueFactory;

class SendQueue
{

  const ORDER_FULFILLMENT_STATUS_ENDPOINT = "https://api.forter-secure.com/v2/status/";

  public function __construct(
    AbstractApi $abstractApi,
    QueueFactory $forterQueue
  ) {
    $this->abstractApi = $abstractApi;
    $this->forterQueue = $forterQueue;
  }


	public function execute()
	{
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/cron.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);

    $items = $this->forterQueue
      ->create()
      ->getCollection()
      ->addFieldToFilter('sync_flag', '0');

    $items
       ->setPageSize(3)->setCurPage(1);

    foreach ($items as $item) {
      $url = $this->getUrl($item);
      $data = $item->getData('entity_body');
      $response = $this->abstractApi->sendApiRequest($url,$data);

      if($response){
        $item->setSyncFlag('1');
        $item->save();
      }
    }

		return $this;
  }

  private function getUrl($item){
    if($item->getEntityType() == 'order_fulfillment_status'){
      return self::ORDER_FULFILLMENT_STATUS_ENDPOINT.$item->getEntityId();
    }
  }
}
