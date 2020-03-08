<?php
/**
 * @author Zach Vaknin | Girit interactive
 * @copyright Copyright (c) 2019 Forter
 * @package Forter_Forter
 */

namespace Forter\Forter\Model\ResourceModel\Queue;

/**
 * Class Collection
 * @package Forter\Forter\Model\ResourceModel\Queue
 */
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init('Forter\Forter\Model\Queue', 'Forter\Forter\Model\ResourceModel\Queue');
    }
}
