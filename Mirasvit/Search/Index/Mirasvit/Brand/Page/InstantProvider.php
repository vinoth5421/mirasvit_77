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

namespace Mirasvit\Search\Index\Mirasvit\Brand\Page;

use Magento\Framework\App\ObjectManager;
use Mirasvit\Search\Index\AbstractInstantProvider;

class InstantProvider extends AbstractInstantProvider
{
    public function getItems(int $storeId, int $limit): array
    {
        $items = [];

        foreach ($this->getCollection($limit) as $model) {
            $items[] = $this->mapItem($model, $storeId);
        }

        return $items;
    }

    /**
     * @param object $model
     */
    private function mapItem($model, int $storeId): array
    {
        return [
            'name' => $model->getBrandTitle(),
            'url'  => $this->getBrandUrl($model, $storeId),
        ];
    }

    public function getSize(int $storeId): int
    {
        return $this->getCollection(0)->getSize();
    }

    public function map(array $documentData, int $storeId): array
    {
        if (!class_exists('Mirasvit\Brand\Model\Brand')) {
            return [];
        }

        foreach ($documentData as $entityId => $itm) {
            $om = ObjectManager::getInstance();
            $entity = $om->create('Mirasvit\Brand\Model\BrandPage')->load($entityId);
            $map = $this->mapItem($entity, $storeId);
            $documentData[$entityId][self::INSTANT_KEY] = $map;
        }

        return $documentData;
    }

    private function getBrandUrl(object $brand, int $storeId): string
    {
        $brandUrlService = ObjectManager::getInstance()->create('\Mirasvit\Brand\Service\BrandUrlService');
        return $brandUrlService->getBrandUrl($brand, $storeId);
    }
}
