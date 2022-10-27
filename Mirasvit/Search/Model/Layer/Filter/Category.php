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



namespace Mirasvit\Search\Model\Layer\Filter;

use Magento\CatalogSearch\Model\Layer\Filter\Category as GenericCategory;
use Mirasvit\Search\Model\ConfigProvider;

class Category extends GenericCategory
{
    private $escaper;

    private $dataProvider;

    private $configProvider;

    /**
     * Category constructor.
     *
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Catalog\Model\Layer\Filter\DataProvider\CategoryFactory $categoryDataProviderFactory
     * @param array $data
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        ConfigProvider $configProvider,
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Framework\Escaper $escaper,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\CategoryFactory $categoryDataProviderFactory,
        array $data = []
    ) {
        $this->configProvider = $configProvider;

        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $escaper,
            $categoryDataProviderFactory,
            $data = []
        );

        $this->_requestVar = 'cat';
        $this->dataProvider = $categoryDataProviderFactory->create(['layer' => $this->getLayer()]);
    }

    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        if ($this->configProvider->isSearchIn() || $this->configProvider->isCategorySearch()) {
            $categoryId = $request->getParam($this->_requestVar) ?: $request->getParam('id');
            if (!empty($categoryId)) {
                $this->dataProvider->setCategoryId($categoryId);

                $category = $this->dataProvider->getCategory();

                $this->getLayer()->getProductCollection()->addCategoryFilter($category);

                if ($request->getParam('id') != $category->getId() && $this->dataProvider->isValid()) {
                    $this->getLayer()->getState()->addFilter($this->_createItem($category->getName(), $categoryId));
                }
            }
        } else {
            parent::apply($request);
        }

        return $this;
    }
}
