<?php
namespace Forter\Forter\Model\ResourceModel\Entity;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Forter\Forter\Model\Entity', 'Forter\Forter\Model\ResourceModel\Entity');
    }
}
