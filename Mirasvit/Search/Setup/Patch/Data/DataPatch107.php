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
use Magento\Framework\Module\ModuleList;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Eav\Setup\EavSetupFactory;

class DataPatch107 implements DataPatchInterface, PatchVersionInterface
{
    private $setup;
    private $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $setup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->setup = $setup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [DataPatch104::class];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '1.0.7';
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

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $eavSetup->addAttribute(
            ProductAttributeInterface::ENTITY_TYPE_CODE,
            'sold_qty',
            [
                'type'                  => 'decimal',
                'label'                 => 'Sold QTY',
                'input'                 => 'text',
                'required'              => false,
                'sort_order'            => 1005,
                'global'                => ScopedAttributeInterface::SCOPE_STORE,
                'group'                 => false,
                'visible'               => false,
                'is_used_in_grid'       => false,
                'is_system'             => false,
                'is_visible_in_grid'    => false,
                'is_filterable_in_grid' => false,
            ]
        );

        $this->setup->endSetup();
    }
}
