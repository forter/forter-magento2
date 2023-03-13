<?php

namespace Forter\Forter\Model\RmaFactory;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Rma\Model\ResourceModel\Rma\CollectionFactory as RmaCollectionFactory;

class ForterRmaCollectionFactory
{

    protected $rmaCollectionFactory;

    /**
     * @param ModuleManager $moduleManager
     */
    public function __construct(ModuleManager $moduleManager)
    {
        if ($moduleManager->isEnabled('Magento_Rma') && class_exists(RmaCollectionFactory::class)) {
            $objectManager = ObjectManager::getInstance();
            $this->rmaCollectionFactory = $objectManager->get(RmaCollectionFactory::class);
        }
    }

    /**
     * @param $orderId
     * @return null
     */
    public function getForterRmaCollection($orderId)
    {
        if ($this->rmaCollectionFactory) {
            return $this->rmaCollectionFactory->create()->addFieldToFilter('order_id', $orderId)->setOrder('date_requested', 'DESC');
        }
        return null;
    }
}
