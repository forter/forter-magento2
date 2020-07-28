<?php
namespace Forter\Forter\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Upgrades DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $orderTable = 'sales_order';
        $orderGridTable = 'sales_order_grid';

        $setup->getConnection()
            ->addColumn(
                $setup->getTable($orderTable),
                'forter_web_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'visible' => true,
                    'comment' =>'Client number to track'
                ]
            );

        $setup->getConnection()
            ->addColumn(
                $setup->getTable($orderGridTable),
                'forter_web_id',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'visible' => true,
                    'comment' =>'Client number to track'
                ]
            );

        $setup->endSetup();
    }
}