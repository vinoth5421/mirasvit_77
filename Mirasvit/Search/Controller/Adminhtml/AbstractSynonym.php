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



namespace Mirasvit\Search\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Mirasvit\Search\Api\Data\StopwordInterface;
use Mirasvit\Search\Repository\StopwordRepository;
use Mirasvit\Search\Service\SynonymService;
use Magento\Framework\Filesystem\DirectoryList;

abstract class AbstractSynonym extends Action
{
    protected $stopwordRepository;

    protected $synonymService;

    protected $directoryList;

    protected $context;

    public function __construct(
        StopwordRepository $stopwordRepository,
        SynonymService $synonymService,
        DirectoryList $directoryList,
        Context $context
    ) {
        $this->stopwordRepository = $stopwordRepository;
        $this->synonymService     = $synonymService;
        $this->directoryList      = $directoryList;
        $this->context            = $context;

        parent::__construct($context);
    }

    /**
     * @param \Magento\Backend\Model\View\Result\Page $resultPage
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    protected function initPage($resultPage)
    {
        $resultPage->setActiveMenu('Mirasvit_Search::search');

        $resultPage->getConfig()->getTitle()->prepend((string)__('Manage Synonyms'));

        return $resultPage;
    }

    /**
     * {@inheritdoc}
     */
    protected function _isAllowed()
    {
        return $this->context->getAuthorization()->isAllowed('Magento_Search::synonyms');
    }
}
