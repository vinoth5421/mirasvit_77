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

namespace Mirasvit\SearchElastic\SearchAdapter\Query\Builder;

use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeProvider;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldType\ResolverInterface as TypeResolver;
use Magento\Elasticsearch\Model\Adapter\FieldMapperInterface;
use Magento\Elasticsearch\Model\Config;
use Magento\Elasticsearch\SearchAdapter\Query\ValueTransformerPool;
use Magento\Framework\Search\Request\QueryInterface as RequestQueryInterface;
use Mirasvit\Search\Model\ConfigProvider;
use Mirasvit\Search\Service\QueryService;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Catalog\Model\Product;

class MatchQuery extends MatchCompatibility
{
    private $queryService;

    private $fieldMapper;

    private $attributeProvider;

    private $attributeRepository;

    private $config;

    private $searchTerms = [];

    public function __construct(
        QueryService $queryService,
        FieldMapperInterface $fieldMapper,
        AttributeProvider $attributeProvider,
        TypeResolver $fieldTypeResolver,
        ValueTransformerPool $valueTransformerPool,
        AttributeRepositoryInterface $attributeRepository,
        Config $config
    ) {
        $this->queryService         = $queryService;
        $this->fieldMapper          = $fieldMapper;
        $this->attributeProvider    = $attributeProvider;
        $this->attributeRepository  = $attributeRepository;
        $this->config               = $config;

        parent::__construct($fieldMapper, $attributeProvider, $fieldTypeResolver, $valueTransformerPool, $config);
    }

    /**
     * @param string $conditionType
     */
    public function build(array $selectQuery, RequestQueryInterface $requestQuery, $conditionType): array
    {
        $this->searchTerms = [];
        $queryValue = $requestQuery->getValue();
        $searchQuery = $this->queryService->build($queryValue);
        $queryValue = $searchQuery['query'];
        $fields = [];

        foreach ($requestQuery->getMatches() as $match) {
            $attribute = false;
            try {
                $attribute = $this->attributeRepository->get(Product::ENTITY, $match['field']);
            } catch (\Exception $e) {}

            if ($attribute && in_array($attribute->getFrontendInput(), ['price', 'weight', 'date', 'datetime'])) {
                continue;
            }

            $boost = (int)($match['boost'] ?? 1);

            $resolvedField = $this->fieldMapper->getFieldName(
                $match['field'],
                ['type' => FieldMapperInterface::TYPE_QUERY]
            );

            if (in_array($resolvedField, ['links_purchased_separately'])) {
                continue;
            }

            if ($resolvedField === '_search') {
                $resolvedField = '_misc';
            }

            $fields[$resolvedField] = $boost;
        }

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

            if (isset($fields['name'])) {
                $fields['name.keyword'] = $fields['name'];
            }

            if (isset($fields['sku'])) {
                $fields['sku.keyword'] = $fields['sku'];
            }

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

        return $selectQuery;
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
            case ConfigProvider::WILDCARD_DISABLED:
                $cases [] = '('. $queryValue .')^4';

                if (!empty($longTail)) {
                    $cases [] = '('. implode (' OR ', $longTail)  .')^4';
                }

                break;
            case ConfigProvider::WILDCARD_PREFIX:
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
            case ConfigProvider::WILDCARD_SUFFIX:
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
            case ConfigProvider::WILDCARD_INFIX:
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
                        case ConfigProvider::WILDCARD_INFIX:
                            $compiled[] = "$phrase OR *$phrase*";
                            $this->searchTerms[$phrase] = 1;
                            $this->searchTerms["*$phrase*"] = 0.3;
                            break;
                        case ConfigProvider::WILDCARD_PREFIX:
                            $compiled[] = "$phrase OR *$phrase";
                            $this->searchTerms[$phrase] = 1;
                            $this->searchTerms["*$phrase"] = 0.5;
                            break;
                        case ConfigProvider::WILDCARD_SUFFIX:
                            $compiled[] = "$phrase OR $phrase*";
                            $this->searchTerms[$phrase] = 1;
                            $this->searchTerms["$phrase*"] = 0.5;
                            break;
                        case ConfigProvider::WILDCARD_DISABLED:
                            $compiled[] = $phrase;
                            $this->searchTerms[$phrase] = 1;
                            break;
                    }
                    break;
            }
        }

        return implode(' OR ', $compiled);
    }

    private function escape(string $value): string
    {
        $pattern = '/(\+|-|\/|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~|\*|\?|:|\\\)/';
        $replace = '\\\$1';

        return preg_replace($pattern, $replace, $value);
    }
}
