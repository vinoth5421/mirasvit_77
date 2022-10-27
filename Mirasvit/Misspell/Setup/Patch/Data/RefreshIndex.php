<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-search-ultimate
 * @version   2.0.77
 * @copyright Copyright (C) 2022 Mirasvit (https://mirasvit.com/)
 */


namespace Mirasvit\Misspell\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\DB\Adapter\AdapterInterface;

class RefreshIndex implements DataPatchInterface
{
    private $setup;
    private $resource;

    public function __construct(
        ModuleDataSetupInterface $setup,
        ResourceConnection $resource
    ) {
        $this->setup = $setup;
        $this->resource = $resource;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        //this script does not overwrite existing data
        $this->setup->getConnection()->startSetup();
        $installer = $this->setup;

        $tableName = $installer->getTable('mst_misspell_index');
        $indexName = $this->resource->getIdxName(
            'mst_misspell_index',
            'trigram',
            AdapterInterface::INDEX_TYPE_FULLTEXT
        );

        $indices = $installer->getConnection()->getIndexList($tableName);

        if (!key_exists($indexName, $indices)
            || $indices[$indexName]['type'] != AdapterInterface::INDEX_TYPE_FULLTEXT) {
            $installer->getConnection()->dropIndex($tableName, $indexName);

            $installer->getConnection()->addIndex(
                $tableName,
                $indexName,
                'trigram',
                AdapterInterface::INDEX_TYPE_FULLTEXT
            );
        }

        $this->setup->getConnection()->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies() {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases() {
        return [];
    }
}
