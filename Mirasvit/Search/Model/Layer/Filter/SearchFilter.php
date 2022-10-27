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

use Magento\Catalog\Model\Layer;
use Magento\Framework\App\RequestInterface;
use Magento\CatalogSearch\Model\Advanced\Request\BuilderFactory as RequestBuilderFactory;
use Magento\Search\Model\SearchEngine;
use Mirasvit\Search\Model\ConfigProvider;

class SearchFilter extends Layer\Filter\AbstractFilter
{
    private $context;
    private $requestBuilderFactory;
    private $searchEngine;
    private $config;

    public function __construct(
        Layer $layer,
        Context $context,
        RequestBuilderFactory $requestBuilderFactory,
        SearchEngine $searchEngine,
        ConfigProvider $config,
        array $data = []
    ) {
        parent::__construct(
            $context->filterItemFactory,
            $context->storeManager,
            $layer,
            $context->itemDataBuilder,
            ['data' => ['attribute_model' => $this], 'layer' => $layer]
        );

        $this->_requestVar               = 'q';
        $this->context                   = $context;
        $this->requestBuilderFactory     = $requestBuilderFactory;
        $this->searchEngine              = $searchEngine;
        $this->config                    = $config;
    }

    public function apply(RequestInterface $request): self
    {
        $attributeValue = $request->getParam($this->_requestVar);
        if ((!$this->config->isSearchIn() && !$this->config->isCategorySearch()) || empty($attributeValue)) {
            return $this;
        }

        $requestBuilder = $this->requestBuilderFactory->create()
            ->bind('search_term', $attributeValue)
            ->bindDimension('scope', $this->context->storeManager->getStore()->getId())
            ->setRequestName('catalogsearch_fulltext');

        $result = $this->searchEngine->search($requestBuilder->create());
        $results = [];

        foreach ($result->getIterator() as $item) {
            $results[] = $item->getId();
        }

        if (empty($results)) {
            $results[] = -1;
        }

        $this->getLayer()->getState()->addFilter($this->_createItem($attributeValue, $this->getName(), 1));
        $this->getLayer()->getProductCollection()->addFieldToFilter($this->_requestVar, $results);

        return $this;
    }

    public function getName(): string
    {
        return (string) __('Search');
    }

    protected function _getItemsData(): array
    {
        return [];
    }
}
