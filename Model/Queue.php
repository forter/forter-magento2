<?php
/**
 * @author Zach Vaknin | Girit interactive
 * @copyright Copyright (c) 2019 Forter
 * @package Forter_Forter
 */

namespace Forter\Forter\Model;

class Queue extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('Forter\Forter\Model\ResourceModel\Queue');
    }

}
