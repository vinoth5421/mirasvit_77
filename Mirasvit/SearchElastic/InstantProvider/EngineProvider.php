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

namespace Mirasvit\SearchElastic\InstantProvider;

use Elasticsearch\Client;
use Mirasvit\SearchAutocomplete\InstantProvider\InstantProvider;

class   EngineProvider extends InstantProvider
{
    private $query          = [];

    private $activeFilters  = [];

    private $applyFilter    = false;

    private $filtersToApply = [];

    private $searchTerms = [];

    private $buckets = [];

    public function getResults(string $indexIdentifier): array
    {
        $this->query = [
            'index' => $this->configProvider->getIndexName($indexIdentifier),
            'body'  => [
                'from'          => $this->getFrom($indexIdentifier),
                'size'          => $this->getLimit($indexIdentifier),
                'stored_fields' => [
                    '_id',
                    '_score',
                    '_source',
                ],
                'sort'          => [
                    [
                        '_score' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
                'query'         => [
                    'bool' => [
                        'minimum_should_match' => 1,
                    ],
                ],
            ],
            'track_total_hits' => true,
        ];

        $this->setMustCondition($indexIdentifier);
        $this->setShouldCondition($indexIdentifier);

        if ($indexIdentifier === 'catalogsearch_fulltext') {
            $this->setBuckets();
        }

        try {
            $rawResponse = $this->getClient()->search($this->query);
        } catch (\Exception $e) {
            $correctedQuery = $this->suggest();
            if ($correctedQuery && $correctedQuery != $this->getQueryText()) {
                $this->setQueryText($correctedQuery);
                $this->getResults($indexIdentifier);
            } else {
                return [
                    'totalItems' => 0,
                    'items'      => [],
                    'buckets'    => [],
                ];
            }
        }

        if ($this->configProvider->getEngine() == 'elasticsearch6') {
            $totalItems = (int)$rawResponse['hits']['total'];
        } else {
            $totalItems = (int)$rawResponse['hits']['total']['value'];
        }

        $correctedQuery = $this->suggest();
        if ($totalItems < 1 && $correctedQuery && $correctedQuery != $this->getQueryText()) {
            $this->setQueryText($correctedQuery);
            return $this->getResults($indexIdentifier);
        }

        $items = [];

        foreach ($rawResponse['hits']['hits'] as $data) {
            if (!isset($data['_source']['_instant'])) {
                continue;
            }

            if (!$data['_source']['_instant']) {
                continue;
            }

            $items[] = $data['_source']['_instant'];
        }

        $buckets = [];

        if (isset($rawResponse['aggregations'])) {
            foreach ($rawResponse['aggregations'] as $code => $data) {
                $bucketData = $this->configProvider->getBucketOptionsData($code, $data['buckets']);
                if (empty($bucketData)) {
                    continue;
                }
                if (in_array($code, $this->filtersToApply)) {
                    continue;
                }

                $buckets[$code] = $bucketData;
                $this->buckets[$code] = $bucketData;
            }

            if (!empty($this->filtersToApply)) {
                foreach (array_diff(array_keys($this->buckets), array_keys($buckets)) as $bucketCode) {
                    if (!in_array($bucketCode, $this->filtersToApply)) {
                        unset($this->buckets[$bucketCode]);
                    }
                }
            } else {
                $this->buckets = $buckets;
            }
        }

        if (!empty($this->getActiveFilters()) && $this->applyFilter == false) {
            $this->applyFilter = true;
            foreach ($this->getActiveFilters() as $filterKey => $value) {
                $this->filtersToApply[] = $filterKey;

                $result = $this->getResults($indexIdentifier);
                $buckets = $this->prepareBuckets($buckets);
                foreach ($result['buckets'] as $bucketKey => $bucket) {
                    if (in_array($bucketKey, $this->filtersToApply)) {
                        continue;
                    }

                    $this->buckets[$bucketKey] = $bucket;

                }

                $totalItems = $result['totalItems'];
                $items      = $result['items'];
            }
        }

        return [
            'totalItems' => count($items) > 0 ? $totalItems : 0,
            'items'      => $items,
            'buckets'    => $this->buckets,
        ];
    }

    private function getActiveFilters(): array
    {
        if (empty($this->activeFilters)) {
            $this->activeFilters = $this->configProvider->getActiveFilters();
        }

        if (!empty($this->filtersToApply)) {
            return array_intersect_key($this->activeFilters, array_flip($this->filtersToApply));
        }

        return $this->activeFilters;
    }

    private function setMustCondition(string $indexIdentifier): void
    {
        if ($indexIdentifier === 'catalogsearch_fulltext') {
            $this->query['body']['query']['bool']['must'][] = [
                'terms' => [
                    'visibility' => ['3', '4'],
                ],
            ];
            if ($this->applyFilter) {
                foreach ($this->getActiveFilters() as $filterCode => $filterValue) {
                    if ($filterCode == 'price') {
                        $priceFilter = [];
                        foreach ($filterValue as $value) {
                            $priceFilter['bool']['should'][] = [
                                'range' => [
                                    'price_0_1' => json_decode($value, true),
                                ],
                            ];
                        }

                        $this->query['body']['query']['bool']['must'] = array_merge($this->query['body']['query']['bool']['must'], [$priceFilter]);
                    } else {
                        $termStatement = is_array($filterValue)? 'terms':'term';
                        $this->query['body']['query']['bool']['must'][] = [
                            $termStatement => [
                                $filterCode => $filterValue,
                            ],
                        ];
                    }
                }
            }
        }
    }

    private function setShouldCondition(string $indexIdentifier): void
    {
        $fields          = $this->configProvider->getIndexFields($indexIdentifier);
        $fields['_misc'] = 1;

        $searchQuery = $this->queryService->build($this->getQueryText());
        $selectQuery   = [];
        $queryValue = $searchQuery['query'];

        if (!isset($selectQuery['bool'])) {
            $selectQuery['bool'] = ['must' => []];
        }

        if (!$this->isKeyExists($selectQuery['bool']['must'], 'query_string')) {
            $preparedFields = [];

            $this->compileQuery($searchQuery['built']);           

            $wildcardExceptions = $searchQuery['wildcardExceptions'];

            if (empty($wildcardExceptions)) {
                $processedQuery = $queryValue;
            } else {
                $processedQuery = $this->escape(preg_replace('~\b('. implode('|', $wildcardExceptions) .')\b~', " $1 ", $queryValue));
            }

            $terms = preg_split('~\s~', $processedQuery);

            foreach ($fields as $field => $boost) {
                $preparedFields[] = $field .'^'. $boost;

                $selectQuery['bool']['should'][]['terms'] = [
                    $field => $terms,
                    'boost' => $boost,
                ];

                $selectQuery['bool']['should'][]['match_phrase'] = [
                    $field => [
                        'query' => $processedQuery,
                        'boost' => (string) $boost * 2,
                    ]
                ];

                $selectQuery['bool']['should'][]['wildcard'][$field] = [
                    'value' => $processedQuery,
                    'boost' => (string) $boost * 1.5,
                ];

                $selectQuery['bool']['should'][]['wildcard'][$field] = [
                    'value' => $processedQuery.'*',
                    'boost' => (string) $boost * 1.2,
                ];

                $selectQuery['bool']['should'][]['wildcard'][$field] = [
                    'value' => '*'.$processedQuery,
                    'boost' => (string) $boost * 1.2,
                ];

                $selectQuery['bool']['should'][]['wildcard'][$field] = [
                    'value' => '*'. $processedQuery .'*',
                    'boost' => (string) $boost * 1.1,
                ];

                foreach ($this->searchTerms as $term => $boostMultiplier) {
                    $term = (string) $term;

                    if (strlen($term) < 3) {
                        $selectQuery['bool']['should'][]['wildcard'][$field] = [
                            'value' => $term,
                            'boost' => (string) $boost * 0.75 * (float)$boostMultiplier,
                        ];
                    } else {
                        $selectQuery['bool']['should'][]['wildcard'][$field] = [
                            'value' => $term,
                            'boost' => (string) $boost * (float)$boostMultiplier,
                        ];
                    }
                }
            }
            unset($processedQuery);

            $cases = $this->getQueryStringCases($searchQuery);
            $synonyms = $searchQuery['synonyms'];

            foreach ($synonyms as $synonym) {
                $cases[] = '('. $synonym .')^1.1';
            }

            $selectQuery['bool']['must'][]['query_string'] = [
                'query'             => implode(' OR ', $cases),
                'fields'            => $preparedFields,
                'default_operator'  => strtoupper($searchQuery['matchMode']),
            ];
        }

        if (!isset($this->query['body']['query']['bool']['should'])) {
            $this->query['body']['query']['bool']['should'] = [];
        }

        $this->query['body']['query']['bool']['should'] = array_merge(
            $this->query['body']['query']['bool']['should'],
            $selectQuery['bool']['should']
        );

        if (!isset($this->query['body']['query']['bool']['must'])) {
            $this->query['body']['query']['bool']['must'] = [];
        }

        $this->query['body']['query']['bool']['must'] = array_merge(
            $this->query['body']['query']['bool']['must'],
            $selectQuery['bool']['must']
        );
    }

    private function getQueryStringCases($searchQuery):array
    {
        $cases = [];
        $queryValue = $this->escape($searchQuery['query']);
        $longTail = $searchQuery['long_tail'];
        foreach ($longTail as $key => $expression) {
            if ($queryValue == $this->escape($expression)) {
                unset($longTail[$key]);
            } else {
                $longTail[$key] = $this->escape($expression);
            }
        }

        unset($key);
        unset($expression);
        $wildcardExceptions = $searchQuery['wildcardExceptions'];

        switch ($searchQuery['wildcardMode']) {
            case $this->configProvider::WILDCARD_DISABLED:
                $cases [] = '('. $queryValue .')^4';

                if (!empty($longTail)) {
                    $cases [] = '('. implode (' OR ', $longTail)  .')^4';
                }

                break;
            case $this->configProvider::WILDCARD_PREFIX:
                $cases [] = '('. $queryValue .')^4';

                if (empty($wildcardExceptions)) {
                    $processedQuery = $queryValue;
                } else {
                    $processedQuery = preg_replace('~\b('. implode('|', $wildcardExceptions) .')\b~', " $1 ", $queryValue);
                }

                $cases [] = '(*'. $processedQuery .')^3';
                unset($processedQuery);

                if (!empty($longTail)) {
                    foreach ($longTail as $expression) {
                    $cases [] = '(*'. $expression .')^3';
                    }
                }

                break;
            case $this->configProvider::WILDCARD_SUFFIX:
                $cases [] = '('. $queryValue .')^4';

                if (empty($wildcardExceptions)) {
                    $processedQuery = $queryValue;
                } else {
                    $processedQuery = preg_replace('~\b('. implode('|', $wildcardExceptions) .')\b~', " $1 ", $queryValue);
                }

                $cases [] = '('. $processedQuery .'*)^3';
                unset($processedQuery);

                if (!empty($longTail)) {
                    foreach ($longTail as $expression) {
                    $cases [] = '('. $expression .'*)^3';
                    }
                }

                break;
            case $this->configProvider::WILDCARD_INFIX:
                $cases [] = '('. $queryValue .')^4';

                if (empty($wildcardExceptions)) {
                    $processedQuery = $queryValue;
                } else {
                    $processedQuery = preg_replace('~\b('. implode('|', $wildcardExceptions) .')\b~', " $1 ", $queryValue);
                }

                $cases [] = '(*'. $processedQuery .' OR '. $processedQuery .'*)^3';
                $processedQuery = preg_replace('~[\s]+~', '* *', $queryValue);

                if (!empty($wildcardExceptions)) {
                    $processedQuery = preg_replace('~\b('. implode('|', $wildcardExceptions) .')\b~', " $1 ", $processedQuery);
                }

                $cases [] = '('. $processedQuery .')^2';
                $cases [] = '(*'. $processedQuery .'*)';

                if (!empty($longTail)) {
                    foreach ($longTail as $expression) {
                    $cases [] = '(*'. $expression .' OR '. $expression .'*)^3';
                    $processedExpression = preg_replace('~[\s]+~', '* *', $expression);
                    $cases [] = '('. $processedExpression .')^2';
                    $cases [] = '(*'. $processedExpression .'*)';
                    }
                }

                break;
        }

        return $cases;
    }

    private function isKeyExists(array $array, string $keySearch): bool
    {
        if (array_key_exists($keySearch, $array)) {
            return true;
        } else {
            foreach ($array as $key => $item) {
                if (is_array($item) && $this->isKeyExists($item, $keySearch)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function setBuckets(): void
    {
        foreach ($this->getBuckets() as $fieldName) {
            if ($fieldName == 'price') {
                $this->query['body']['aggregations'][$fieldName] = ['terms' => ['field' => 'price_0_1', 'size' => 500]];
            } else {
                $this->query['body']['aggregations'][$fieldName] = ['terms' => ['field' => $fieldName, 'size' => 500]];
            }
        }
    }

    private function compileQuery(array $query): string
    {
        $compiled = [];
        foreach ($query as $directive => $value) {
            switch ($directive) {
                case '$like':
                    $compiled[] = '(' . $this->compileQuery($value) . ')';
                    break;

                case '$and':
                    $and = [];
                    foreach ($value as $item) {
                        $and[] = $this->compileQuery($item);
                    }
                    $compiled[] = '(' . implode(' AND ', $and) . ')';
                    break;

                case '$or':
                    $or = [];
                    foreach ($value as $item) {
                        $or[] = $this->compileQuery($item);
                    }
                    $compiled[] = '(' . implode(' OR ', $or) . ')';
                    break;

                case '$term':
                    $phrase = $this->escape($value['$phrase']);
                    switch ($value['$wildcard']) {
                        case $this->configProvider::WILDCARD_INFIX:
                            $compiled[] = "$phrase OR *$phrase*";
                            $this->searchTerms[$phrase] = 1;
                            $this->searchTerms["*$phrase*"] = 0.3;
                            break;
                        case $this->configProvider::WILDCARD_PREFIX:
                            $compiled[] = "$phrase OR *$phrase";
                            $this->searchTerms[$phrase] = 1;
                            $this->searchTerms["*$phrase"] = 0.5;
                            break;
                        case $this->configProvider::WILDCARD_SUFFIX:
                            $compiled[] = "$phrase OR $phrase*";
                            $this->searchTerms[$phrase] = 1;
                            $this->searchTerms["$phrase*"] = 0.5;
                            break;
                        case $this->configProvider::WILDCARD_DISABLED:
                            $compiled[] = $phrase;
                            $this->searchTerms[$phrase] = 1;
                            break;
                    }
                    break;
            }
        }

        return implode(' OR ', $compiled);
    }

    private function prepareBuckets($buckets): array
    {
        foreach ($buckets as $key => $bucket) {
            foreach ($bucket['buckets'] as $optionKey => $option) {
                $buckets[$key]['buckets'][$optionKey]['count'] = 0;
            }
        }

        return $buckets;
    }

    private function getClient(): Client
    {
        return \Elasticsearch\ClientBuilder::fromConfig($this->configProvider->getEngineConnection(), true);
    }

    public function suggest(): ?string
    {
        if (!in_array('mst_misspell_index', $this->configProvider->getIndexes())) {
            return null;
        }

        $query = preg_split('/[\s]+/', $this->getQueryText());
        $response   = [];

        if (!is_array($query)) {
            $query = [$query];
        }

        try {
            foreach ($query as $term) {
                $result = $this->getClient()->search($this->prepareTermSuggestQuery($term));
                $processedResponse = $this->processResponse($result);

                if (empty($processedResponse)) {
                    $result = $this->getClient()->search($this->preparePhraseSuggestQuery($term));
                    $processedResponse = $this->processResponse($result);
                }

                $response[] = $processedResponse;
            }
        } catch (\Exception $e) {}

        $response = array_filter($response);
        $response = array_unique($response);

        if (empty($response)) {
            return null;
        }

        return implode(' ', $response);
    }

    private function prepareTermSuggestQuery(string $query): array
    {
        return [
            'index' => $this->getIndexName(),
            'body'  => [
                'suggest' => [
                    'suggestion' => [
                        'text'       => $query,
                        'term' => [
                            'field'            => 'keyword',
                            'size'             => 1,
                            'prefix_length'    => 0,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function preparePhraseSuggestQuery(string $query): array
    {
        return [
            'index' => $this->getIndexName(),
            'body'  => [
                'suggest' => [
                    'text'       => $query,
                    'suggestion' => [
                        'phrase' => [
                            'field'            => 'keyword.trigram',
                            'size'             => 1,
                            'gram_size'        => 3,
                            'max_errors'       => 100,
                            'direct_generator' => [
                                [
                                    'field'        => 'keyword.trigram',
                                    'suggest_mode' => 'always',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getIndexName(): string
    {
        return $this->configProvider->getIndexName('mst_misspell_index');
    }

    private function processResponse(array $response): ?string
    {
        $result = null;
        if (isset($response['suggest']['suggestion'][0]['options'][0]['text'])) {
            $result = $response['suggest']['suggestion'][0]['options'][0]['text'];
        } else if (isset($response['suggest']['suggestion'][0]['text'])) {
            $result = $response['suggest']['suggestion'][0]['text'];
        }

        return $result;
    }
}
