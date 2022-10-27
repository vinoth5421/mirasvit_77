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


declare(strict_types=1);

namespace Mirasvit\Search\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\Search\Model\ConfigProvider;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Search\Model\QueryFactory;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;

class CategorySearch extends Template
{
    protected $storeManager;

    protected $config;

    protected $localeFormat;

    protected $searchHelper;

    protected $layerResolver;

    public function __construct(
        Context $context,
        ConfigProvider $config,
        SearchHelper $searchHelper,
        LayerResolver $layerResolver
    ) {
        $this->storeManager     = $context->getStoreManager();
        $this->config           = $config;
        $this->searchHelper     = $searchHelper;
        $this->layerResolver    = $layerResolver;

        parent::__construct($context);
    }

    public function getJsConfig(): array
    {
        return [
            'isActive'                  => $this->config->isCategorySearch(),
            'minSearchLength'           => $this->searchHelper->getMinQueryLength(),
            'minProductsQtyToDisplay'   => $this->config->getMinProductsQtyToDisplay(),
            'delay'                     => 300,
        ];
    }

    public function getQueryText(): string
    {
        return (string) $this->searchHelper->getEscapedQueryText();
    }

    public function getCollectionSize(): int
    {
        return $this->layerResolver->get()->getProductCollection()->getSize();
    }

    public function getIsVisibleCategorySearch():bool
    {
        return $this->getCollectionSize() > $this->config->getMinProductsQtyToDisplay() || strlen($this->getQueryText()) > $this->searchHelper->getMinQueryLength();
    }
}
