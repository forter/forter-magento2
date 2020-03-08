<?php
/**
 * @author Zach Vaknin | Girit interactive
 * @copyright Copyright (c) 2019 Forter
 * @package Forter_Forter
 */

namespace Forter\Forter\Model\ResourceModel;

/**
 * Class Queue
 * @package Forter\Forter\Model\ResourceModel
 */
class Queue extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('forter_send_queue', 'sync_id');
    }
}
