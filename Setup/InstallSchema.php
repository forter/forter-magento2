<?php
/**
 * @author Zach Vaknin | Girit interactive
 * @copyright Copyright (c) 2019 Forter
 * @package Forter_Forter
 */

namespace Forter\Forter\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        $connection = $installer->getConnection();


            $syncTable = $installer->getConnection()->newTable(
               $installer->getTable('forter_send_queue')
           )
           ->addColumn(
               'sync_id',
               \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
               null,
               ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
               'Id'
           )
           ->addColumn(
               'store_id',
               \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
               null,
               ['unsigned' => true, 'nullable' => true],
               'Store ID'
           )
           ->addColumn(
               'entity_type',
               \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
               50,
               ['nullable' => true],
               'Entity Type'
           )
           ->addColumn(
               'entity_id',
               \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
               null,
               ['unsigned' => true, 'nullable' => true],
               'Entity ID'
           )
           ->addColumn(
               'entity_body',
               \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
               null,
               ['unsigned' => true, 'nullable' => true],
               'Request Body'
           )
           ->addColumn(
               'sync_flag',
               \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
               null,
               ['nullable' => true, 'default' => '0'],
               'Sync Flag'
           )
           ->addColumn(
               'sync_date',
               \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME,
               null,
               ['nullable' => false],
               'Sync Date'
           );

        $installer->getConnection()->createTable($syncTable);
        $installer->endSetup();
   }
}
