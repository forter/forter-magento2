<?php
namespace Forter\Forter\Model;

class Entity extends \Magento\Framework\Model\AbstractModel
{
    protected function _construct()
    {
        $this->_init('Forter\Forter\Model\ResourceModel\Entity');
    }
}
