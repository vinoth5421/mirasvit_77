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

namespace Mirasvit\Misspell\Model\GraphQL;

use Mirasvit\Misspell\Service\QueryService;
use Mirasvit\Misspell\Model\ConfigProvider;
use Mirasvit\Misspell\Repository\SuggestRepository;

class Suggester
{
     public function __construct(
        QueryService $queryService,
        ConfigProvider $configProvider,
        SuggestRepository $suggestRepository
    ) {
        $this->queryService         = $queryService;
        $this->configProvider       = $configProvider;
        $this->suggestRepository    = $suggestRepository;
    }

    public function suggest(): string
    {
        $result = $this->queryService->getQueryText();
        if (!empty($this->queryService->getQueryText()) && (bool) $this->queryService->getNumResults() == false) {
            if ($this->configProvider->isMisspellEnabled()) {
                $result = $this->doSpellCorrection();
            } else {
                $result = false;
            }

            if (!$result && $this->configProvider->isFallbackEnabled()) {
                $result = $this->doFallbackCorrection();
            }
        }

        return $result;
    }

    public function doSpellCorrection(): string
    {
        $query = $this->queryService->getQueryText();
        $suggest = $this->suggestRepository->suggest($query);

        if ($suggest && $suggest != $query && $suggest != $this->queryService->getMisspellText()) {
            return $suggest;
        }

        return false;
    }

    public function doFallbackCorrection(): string
    {
        $fallback = $this->queryService->fallback($this->queryService->getQueryText());

        return $fallback;
    }
}
