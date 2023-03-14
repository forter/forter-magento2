<?php

namespace Forter\Forter\Model\RmaFactory;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Module\Manager as ModuleManager;

class ForterRmaCollectionFactory
{
    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        ModuleManager $moduleManager
    ) {
        $this->moduleManager = $moduleManager;
    }

    /**
     * @param int $orderId
     * @return \Magento\Rma\Model\ResourceModel\Rma\Collection|null
     */
    public function getForterRmaCollection($orderId)
    {
        if (!$this->moduleManager->isEnabled('Magento_Rma')) {
            return null;
        }

        $objectManager = ObjectManager::getInstance();
        try {
            $rmaCollectionFactory = $objectManager->get('Magento\Rma\Model\ResourceModel\Rma\CollectionFactory');
        } catch (\Exception $e) {
            return null;
        }

        if ($rmaCollectionFactory) {
            return $rmaCollectionFactory->create()->addFieldToFilter('order_id', $orderId)->setOrder('date_requested', 'DESC');
        }

        return null;
    }
}
