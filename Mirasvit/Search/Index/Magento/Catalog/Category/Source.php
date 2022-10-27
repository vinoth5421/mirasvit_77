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



namespace Mirasvit\Search\Index\Magento\Catalog\Category;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Option\ArrayInterface;

class Source implements ArrayInterface
{
    /**
     * @var CategoryCollectionFactory
     */
    private $collectionFactory;

    /**
     * Source constructor.
     * @param CategoryCollectionFactory $collectionFactory
     */
    public function __construct(
        CategoryCollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect(['name'])
            ->addFieldToFilter('is_active', 1);

        foreach ($collection as $category) {
            $options[] = [
                'value' => $category->getId(),
                'label' => str_repeat(' . ', (int) $category->getLevel()) .' '. $category->getData('name') .'[store ID: ' . $category->getStoreId() .']',
            ];
        }

        return $options;
    }
}
