<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\QueueFactory;

class SendQueue
{
    const ORDER_FULFILLMENT_STATUS_ENDPOINT = "https://api.forter-secure.com/v2/status/";

    /**
     * @param  AbstractApi     $abstractApi
     * @param  ForterConfig forterConfig
     */
    public function __construct(
        AbstractApi $abstractApi,
        QueueFactory $forterQueue
    ) {
        $this->abstractApi = $abstractApi;
        $this->forterQueue = $forterQueue;
    }

    /**
     * Send to forter items in Queue
     * @return boolval
     */
    public function execute()
    {
        $items = $this->forterQueue
      ->create()
      ->getCollection()
      ->addFieldToFilter('sync_flag', '0');

        $items
       ->setPageSize(3)->setCurPage(1);

        foreach ($items as $item) {
            $url = $this->getUrl($item);
            $data = $item->getData('entity_body');
            $response = $this->abstractApi->sendApiRequest($url, $data);

            if ($response) {
                $item->setSyncFlag('1');
                $item->save();
            }
        }

        return true;
    }

    /**
     * Return endpoint base on item type
     * @param  Forter\Forter\Model\QueueFactory
     * @return string
     */
    private function getUrl($item)
    {
        if ($item->getEntityType() == 'order_fulfillment_status') {
            return self::ORDER_FULFILLMENT_STATUS_ENDPOINT . $item->getEntityId();
        }
    }
}
