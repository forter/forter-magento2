<?php
namespace Forter\Forter\Model\ResourceModel;

class Entity extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('forter_entity', 'entity_id');
    }
}
