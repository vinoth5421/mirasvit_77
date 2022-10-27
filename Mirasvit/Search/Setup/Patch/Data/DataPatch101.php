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
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Mirasvit\Search\Repository\IndexRepository;

class DataPatch101 implements DataPatchInterface, PatchVersionInterface
{
    private $setup;

    private $indexRepository;

    public function __construct(
        ModuleDataSetupInterface $setup,
        IndexRepository $indexRepository
    ) {
        $this->setup = $setup;
        $this->indexRepository = $indexRepository;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '1.0.1';
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

        // create default index
        $this->indexRepository->save(
            $this->indexRepository->create()
                ->setIdentifier('catalogsearch_fulltext')
                ->setTitle('Products')
                ->setIsActive(true)
                ->setPosition(1)
        );

        $this->indexRepository->save(
            $this->indexRepository->create()
                ->setIdentifier('magento_catalog_category')
                ->setTitle('Categories')
                ->setIsActive(false)
                ->setPosition(2)
                ->setAttributes([
                    'name'             => 10,
                    'description'      => 5,
                    'meta_title'       => 9,
                    'meta_keywords'    => 1,
                    'meta_description' => 1,
                ])
        );

        $this->indexRepository->save(
            $this->indexRepository->create()
                ->setIdentifier('magento_cms_page')
                ->setTitle('Information')
                ->setIsActive(false)
                ->setPosition(3)
                ->setAttributes([
                    'title'            => 10,
                    'content'          => 5,
                    'content_heading'  => 9,
                    'meta_keywords'    => 1,
                    'meta_description' => 1,
                ])
        );

        $this->setup->endSetup();
    }
}
