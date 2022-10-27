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



namespace Mirasvit\Search\Setup\Patch\Data;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Eav\Setup\EavSetupFactory;

class DataPatch1013 implements DataPatchInterface, PatchVersionInterface
{
    private $setup;
    private $configWriter;

    public function __construct(
        ModuleDataSetupInterface $setup,
        WriterInterface $configWriter
    ) {
        $this->setup = $setup;
        $this->configWriter = $configWriter;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [DataPatch1011::class];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '1.0.13';
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->setup->startSetup();
        $setup = $this->setup;

        $currentDBName = $setup->getConnection()->fetchRow('SELECT DATABASE() as current_db;')['current_db'];
        $this->configWriter->save('catalog/search/elasticsearch7_index_prefix', $currentDBName);
        $this->configWriter->save('catalog/search/elasticsearch6_index_prefix', $currentDBName);
        $this->configWriter->save('catalog/search/elasticsearch5_index_prefix', $currentDBName);

        $this->setup->endSetup();
    }
}
