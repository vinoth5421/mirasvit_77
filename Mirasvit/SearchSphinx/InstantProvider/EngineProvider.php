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

namespace Mirasvit\SearchSphinx\InstantProvider;

use Mirasvit\SearchSphinx\SphinxQL\SphinxQL;
use Mirasvit\SearchSphinx\SphinxQL\Connection;
use Mirasvit\SearchSphinx\SphinxQL\Expression as QLExpression;
use Mirasvit\SearchSphinx\SphinxQL\Stemming\En;
use Mirasvit\SearchSphinx\SphinxQL\Stemming\Nl;
use Mirasvit\SearchAutocomplete\InstantProvider\InstantProvider;

class EngineProvider extends InstantProvider
{
    private $query          = [];

    private $activeFilters  = [];

    private $applyFilter    = false;

    private $filtersToApply = [];

    private $searchTerms = [];

    public function getResults(string $indexIdentifier): array
    {
        $sphinxQL = new SphinxQL($this->getConnection());
        $metaQL = new SphinxQL($this->getConnection());
        $fields = $this->configProvider->getIndexAttributes($indexIdentifier);

            $response = $sphinxQL
                ->select(['id','LENGTH(autocomplete) AS autocomplete_strlen','*' ,new QLExpression('weight()')])
                ->from($this->configProvider->getIndexName($indexIdentifier))
                ->match('*', new QLExpression($this->getSearchQuery()))
                ->where('autocomplete_strlen', '>', 0)
                ->limit(0, $this->getLimit($indexIdentifier))
                ->option('max_matches', 1000000)
                ->option('field_weights', $fields)
                ->option('ranker', new QLExpression("expr('sum(1/min_hit_pos*user_weight
                    + word_count*user_weight + exact_hit*user_weight*1000 + lcs*user_weight) * 1000 + bm25')"))
                ->enqueue($metaQL->query('SHOW META'))
                ->enqueue()
                ->executeBatch();

            $total = $response[1][0]['Value'];
            $items = $this->mapHits($response[0]);

        return [
            'totalItems' => $total,
            'items'      => $items,
            'buckets'    => [],
        ];
    }

    private function getSearchQuery(): string
    {
        return $this->compileQuery($this->queryService->build($this->getQueryText())['built']);
    }

    private function compileQuery(array $query): string
    {
        $compiled = [];
        foreach ($query as $directive => $value) {
            switch ($directive) {
                case '$like':
                    $like = $this->compileQuery($value);
                    if ($like) {
                        $compiled[] = '(' . $like . ')';
                    }
                    break;

                case '$and':
                    $and = [];
                    foreach ($value as $item) {
                        $and[] = $this->compileQuery($item);
                    }
                    $and = array_filter($and);
                    if ($and) {
                        $compiled[] = '(' . implode(' ', $and) . ')';
                    }
                    break;

                case '$or':
                    $or = [];
                    foreach ($value as $item) {
                        $or[] = $this->compileQuery($item);
                    }
                    $or = array_filter($or);
                    $or = array_slice($or, 0, 3);
                    if ($or) {
                        $compiled[] = '(' . implode(' | ', $or) . ')';
                    }
                    break;

                case '$term':
                    $phrase = $this->escape($value['$phrase']);
                    if (strlen($phrase) == 1) {
                        if ($value['$wildcard'] == $this->configProvider::WILDCARD_DISABLED) {
                            $compiled[] = "$phrase";
                        } else {
                            $compiled[] = "$phrase*";
                        }
                        break;
                    }
                    switch ($value['$wildcard']) {
                        case $this->configProvider::WILDCARD_INFIX:
                            $compiled[] = "$phrase | *$phrase*";
                            break;
                        case $this->configProvider::WILDCARD_PREFIX:
                            $compiled[] = "$phrase | *$phrase";
                            break;
                        case $this->configProvider::WILDCARD_SUFFIX:
                            $compiled[] = "$phrase | $phrase*";
                            break;
                        case $this->configProvider::WILDCARD_DISABLED:
                            if (strpos($phrase, ' ') === false) {
                                $compiled[] = $phrase;
                            }
                            break;
                    }
                    break;
            }
        }

        return implode(' ', $compiled);
    }

    protected function escape(string $value): string
    {
        $pattern = '/(\+|&&|\|\||\/|!|\(|\)|\{|}|\[|]|\^|"|~|@|#|\*|\?|:|\\\)/';
        $replace = '\\\$1';
        $value   = preg_replace($pattern, $replace, $value);

        $strPattern = ['-'];
        $strReplace = $value === '-' ? ['-'] : ['\-'];
        $value      = str_replace($strPattern, $strReplace, $value);

        return $value;
    }

    private function mapHits($response)
    {
        $items = [];
        foreach ($response as $hit) {
            if (count($items) > 6) {
                break;
            }

            $item = [
                'name'        => null,
                'url'         => null,
                'sku'         => null,
                'image'       => null,
                'description' => null,
                'price'       => null,
                'rating'      => null,
            ];

            try {
                $item = array_merge($item, \Zend_Json::decode($hit['autocomplete']));
                $items[] = $item;
            } catch (\Exception $e) {
            }
        }

        return $items;
    }

    private function getConnection(): Connection
    {
        $connection = new \Mirasvit\SearchSphinx\SphinxQL\Connection();
        $connection->setParams($this->configProvider->getEngineConnection());

        return $connection;
    }
}
