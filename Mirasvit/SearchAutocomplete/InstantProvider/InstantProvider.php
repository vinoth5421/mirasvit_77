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

namespace Mirasvit\SearchAutocomplete\InstantProvider;

if (php_sapi_name() == 'cli') {
    return;
}
$configFile = dirname(__DIR__, 4) . '/etc/instant.json';

if (stripos(__DIR__, 'vendor') !== false) {
    $configFile = dirname(__DIR__, 6) . '/app/etc/instant.json';
}

if (!file_exists($configFile)) {
    return;
}
$config = \Zend_Json::decode(file_get_contents($configFile));

if (!isset($config['-1/instant']) || $config['-1/instant'] == false) {
    return;
}

use Mirasvit\Core\Service\CompatibilityService;
use Mirasvit\Search\Api\Data\QueryConfigProviderInterface;
use Mirasvit\Search\Service\QueryService;

class InstantProvider
{
    protected $queryService;

    protected $configProvider;

    protected $queryText;

    public function __construct(
        QueryService                 $queryService,
        QueryConfigProviderInterface $configProvider
    ) {
        $this->queryService   = $queryService;
        $this->configProvider = $configProvider;
    }

    public function process(): ?string
    {
        if (empty($this->getQueryText())) {
            return null;
        }

        $this->configProvider->setStoreId($this->getStoreId());

        $engineProviders = [
            'elasticsearch5' => new \Mirasvit\SearchElastic\InstantProvider\EngineProvider($this->queryService, $this->configProvider),
            'elasticsearch6' => new \Mirasvit\SearchElastic\InstantProvider\EngineProvider($this->queryService, $this->configProvider),
            'elasticsearch7' => new \Mirasvit\SearchElastic\InstantProvider\EngineProvider($this->queryService, $this->configProvider),
            'sphinx'         => new \Mirasvit\SearchSphinx\InstantProvider\EngineProvider($this->queryService, $this->configProvider),
        ];

        $searchEngine = $this->configProvider->getEngine();

        if (!isset($engineProviders[$searchEngine])) {
            return null;
        }

        $indexesResult = [];
        $totalItems    = 0;
        $indexes       = $this->configProvider->getIndexes();
        foreach ($indexes as $indexIdentifier) {
            if ($indexIdentifier == 'mst_misspell_index') {
                continue;
            }

            $results = $engineProviders[$searchEngine]->getResults($indexIdentifier);
            $buckets = [];
            if ($this->configProvider->getLayeredNavigationPosition() == 'filters_top') {
                if (isset($results['buckets']['category_ids'])) {
                    $buckets = ['category_ids' => $results['buckets']['category_ids']];
                }
            } elseif ($this->configProvider->getLayeredNavigationPosition() == 'filters_sidebar') {
                $buckets = $results['buckets'];
            }

            foreach($results['items'] as $key => $item) {
                if (isset($item['price']) && isset($item['price'][$this->getCurrency()])) {
                    $results['items'][$key]['price'] = $item['price'][$this->getCurrency()];
                }
            }

            $indexesResult[] = [
                'identifier'   => $indexIdentifier == 'catalogsearch_fulltext' ? 'magento_catalog_product' : $indexIdentifier,
                'isShowTotals' => true,
                'position'     => $this->configProvider->getIndexPosition($indexIdentifier),
                'title'        => $this->configProvider->getIndexTitle($indexIdentifier),
                'totalItems'   => $results['totalItems'],
                'items'        => $results['items'],
                'buckets'      => $buckets,
                'pages'        => $this->getPaginationData($results['totalItems']),
            ];

            $totalItems += $results['totalItems'];
        }

        $queryText = $this->getQueryText();
        $result    = [
            'query'      => $this->getQueryText(),
            'totalItems' => $totalItems,
            'indexes'    => $indexesResult,
            'noResults'  => $totalItems === 0,
            'textEmpty'  => sprintf($this->configProvider->getTextEmpty(), $queryText),
            'textAll'    => sprintf($this->configProvider->getTextAll(), $totalItems),
            'urlAll'     => $this->configProvider->getUrlAll() . $queryText,
        ];

        return json_encode($result);
    }

    protected function getQueryText(): string
    {
        if (empty($this->queryText)) {
            $this->queryText = (string)$this->getParam('q');
        }

        return $this->queryText;
    }

    protected function setQueryText(string $queryText): void
    {
        $this->queryText = $queryText;
    }

    protected function getPageNum(): int
    {
        return (int)$this->getParam('p');
    }

    protected function getBuckets(): array
    {
        return $this->configProvider->getAvailableBuckets();
    }

    protected function getFrom(string $indexIdentifier): int
    {
        $from = 0;
        if ($indexIdentifier == $this->getActiveIndex()) {
            $from = ($this->getPageNum() - 1) * $this->getLimit($indexIdentifier);
        }

        return $from;
    }

    protected function getLimit(string $indexIdentifier): int
    {
        return $this->getParam('limit') != null
            ? (int)$this->getParam('limit')
            : $this->configProvider->getLimit($indexIdentifier);
    }

    protected function getCurrency(): string
    {
        return (string) $this->getParam('currency');
    }

    protected function escape(string $value): string
    {
        $pattern = '/(\+|-|\/|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~|\*|\?|:|\\\)/';
        $replace = '\\\$1';

        return preg_replace($pattern, $replace, $value);
    }

    protected function getStoreId(): int
    {
        return (int)$this->getParam('store_id');
    }

    private function getParam(string $param)
    {
        if (filter_input(INPUT_GET, $param) != null) {
            return filter_input(INPUT_GET, $param);
        }

        return null;
    }

    private function getActiveIndex(): string
    {
        return $this->getParam('index') != null
            ? (string)$this->getParam('index')
            : 'magento_catalog_product';
    }

    private function getPaginationData(int $resultsQTY)
    {
        $pagesQty = ceil($resultsQTY / $this->configProvider->getProductsPerPage());
        if ($pagesQty == 1) {
            return [];
        }
        $pages       = [];
        $currentPage = $this->getPageNum();
        for ($i = 1; $i <= $pagesQty; $i++) {
            $pages[$i] = ['isActive' => ($i == $currentPage ? true : false), 'label' => $i];
        }

        return $pages;
    }
}

$configProvider = new ConfigProvider($config);
$queryService   = new QueryService($configProvider);
$provider       = new InstantProvider($queryService, $configProvider);
$html           = $provider->process();
/** mp comment start */
if (!CompatibilityService::isMarketplace()) {
    if ($html) {
        // @codingStandardsIgnoreStart
        echo $html;
        exit;
        // @codingStandardsIgnoreEnd
    }
}
/** mp comment end */
