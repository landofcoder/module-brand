<?php
/**
 * Venustheme
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Venustheme.com license that is
 * available through the world-wide-web at this URL:
 * http://www.venustheme.com/license-agreement.html
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category   Venustheme
 * @package    Ves_Brand
 * @copyright  Copyright (c) 2014 Venustheme (http://www.venustheme.com/)
 * @license    http://www.venustheme.com/LICENSE-1.0.html
 */
namespace Ves\Brand\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\DB\Ddl\Table;


class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        if (version_compare($context->getVersion(), '1.0.1', '<')) {
            /**
             * Create table 'ves_brand_product'
             */
            $table = $installer->getConnection()->newTable(
                $installer->getTable('ves_brand_product')
            )->addColumn(
                'brand_id',
                Table::TYPE_INTEGER,
                null,
                ['nullable' => false, 'primary' => true],
                'Brand ID'
            )->addColumn(
                'product_id',
                Table::TYPE_SMALLINT,
                null,
                ['unsigned' => true, 'nullable' => false, 'primary' => true],
                'Product ID'
            )->addColumn(
                'position',
                Table::TYPE_INTEGER,
                11,
                ['nullable' => true],
                'Position'
            )->setComment(
                'Ves Brand To Product Linkage Table'
            );
            $installer->getConnection()->createTable($table);
        }

        if (version_compare($context->getVersion(), '1.0.2', '<')) {
            $installer->getConnection()->addColumn(
                $installer->getTable('ves_brand'),
                'featured',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    'nullable' => true,
                    'default' => 0,
                    'comment' => 'featured',
                    'after' => 'status'
                ]
            );
        }

        if (version_compare($context->getVersion(), '1.0.4', '<')) {
            $this->addBrandIdForeignKey($installer);
            $this->addProductIdForeignKey($installer);
        }
        $installer->endSetup();
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $installer
     * @return void
     */
    protected function addBrandIdForeignKey($installer)
    {
        $table = $installer->getTable('ves_brand_product');
        $installer->getConnection()->modifyColumn(
            $table,
            'brand_id',
            [
                'type' => Table::TYPE_INTEGER,
                'length' => 10,
                'unsigned' => true,
                'nullable' => false,
                'comment' => 'Brand ID'
            ]
        );

        $installer->getConnection()->addForeignKey(
            $installer->getFkName('ves_brand_product', 'brand_id', 'ves_brand', 'brand_id'),
            'ves_brand_product',
            'brand_id',
            $installer->getTable('ves_brand'),
            'brand_id',
            Table::ACTION_CASCADE
        );
    }

    /**
     * @param \Magento\Framework\Setup\SchemaSetupInterface $installer
     * @return void
     */
    protected function addProductIdForeignKey($installer)
    {
        $table = $installer->getTable('ves_brand_product');
        $installer->getConnection()->modifyColumn(
            $table,
            'product_id',
            [
                'type' => Table::TYPE_INTEGER,
                'length' => 10,
                'unsigned' => true,
                'nullable' => false,
                'comment' => 'Product ID'
            ]
        );

        $installer->getConnection()->addForeignKey(
            $installer->getFkName('ves_brand_product', 'product_id', 'catalog_product_entity', 'entity_id'),
            'ves_brand_product',
            'product_id',
            $installer->getTable('catalog_product_entity'),
            'entity_id',
            Table::ACTION_CASCADE
        );
    }
}
