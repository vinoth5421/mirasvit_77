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

use Mirasvit\SearchMysql\SearchAdapter\Index\IndexNameResolver;
use Mirasvit\Search\Repository\IndexRepository;
use Mirasvit\SearchSphinx\SearchAdapter\Manager;

class ConfigProvider
{
    private $manager;

    private $indexNameResolver;

    private $indexRepository;

    public function __construct(
        Manager $manager,
        IndexNameResolver $indexNameResolver,
        IndexRepository $indexRepository
    ) {
        $this->manager           = $manager;
        $this->indexNameResolver = $indexNameResolver;
        $this->indexRepository   = $indexRepository;
    }

    public function getConfig(int $storeId): array
    {
        $config = [
            'connection' => $this->manager->getSphinxConfig(),
        ];

        foreach ($this->indexRepository->getList() as $index) {
            $config[$index->getIdentifier()] = $this->indexNameResolver->getIndexNameByStoreId($index->getIdentifier(), $storeId);
        }

        return $config;
    }
}
