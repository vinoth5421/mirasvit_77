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

class DataPatch104 implements DataPatchInterface, PatchVersionInterface
{
    private $setup;
    private $moduleList;
    private $blockFactory;
    private $blockRepository;

    public function __construct(
        ModuleDataSetupInterface $setup,
        ModuleList $moduleList,
        BlockInterfaceFactory $blockFactory,
        BlockRepositoryInterface $blockRepository
    ) {
        $this->setup = $setup;
        $this->moduleList = $moduleList;
        $this->blockFactory = $blockFactory;
        $this->blockRepository = $blockRepository;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [DataPatch103::class];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '1.0.4';
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

        if ($this->moduleList->has('WeltPixel_CmsBlockScheduler')) {
            throw new \Exception('Please disable WeltPixel_CmsBlockScheduler before upgrade. And enable it after upgrade');
        }

        /** @var \Magento\Cms\Api\Data\BlockInterface $block */
        $block = $this->blockFactory->create();

        $block->setIdentifier('no-results')
            ->setTitle('Search: No Results Suggestions')
            ->setContent($this->getBlockContent('no_results'))
            ->setIsActive(true);

        try {
            $this->blockRepository->save($block);
        } catch (\Exception $e) {
        }

        $this->setup->endSetup();
    }

    private function getBlockContent(string $name): string
    {
        return file_get_contents(dirname(__FILE__) . "/data/$name.html");
    }
}
