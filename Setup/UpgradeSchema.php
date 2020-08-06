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

<<<<<<< HEAD
        $orderTable = 'sales_order';
        $orderGridTable = 'sales_order_grid';

        $setup->getConnection()
            ->addColumn(
                $setup->getTable($orderTable),
                'forter_web_id',
=======
        $quote = 'quote';
        $orderTable = 'sales_order';
        $orderGridTable = 'sales_order_grid';

        $setup->getConnection()
            ->addColumn(
                $setup->getTable($quote),
                'forter_client_webid',
>>>>>>> added field to order creation view adin site
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'visible' => true,
                    'comment' =>'Client number to track'
                ]
            );

        $setup->getConnection()
            ->addColumn(
<<<<<<< HEAD
                $setup->getTable($orderGridTable),
                'forter_web_id',
=======
                $setup->getTable($orderTable),
                'forter_client_webid',
>>>>>>> added field to order creation view adin site
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
                'forter_client_webid',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'visible' => true,
                    'comment' =>'Client number to track'
                ]
            );

        $setup->getConnection()
            ->addColumn(
                $setup->getTable($orderTable),
                'forter_client_details',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 500,
                    'visible' => true,
                    'comment' =>'Forter Client Details'
                ]
            );

        $setup->endSetup();
    }
}