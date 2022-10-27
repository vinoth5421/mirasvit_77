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



namespace Mirasvit\SearchLanding\Block;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Mirasvit\SearchLanding\Api\Data\PageInterface;

class View extends Template
{
    private $registry;
    private $page;

    public function __construct(
        Registry $registry,
        Template\Context $context
    ) {
        $this->registry = $registry;
        $this->page = $this->registry->registry('search_landing_page');

        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    protected function _prepareLayout()
    {
        /** @var PageInterface $page */
        $this->pageConfig->getTitle()->set($this->page->getTitle());
        $this->pageConfig->setKeywords($this->page->getMetaKeywords());
        $this->pageConfig->setDescription($this->page->getMetaDescription());

        return parent::_prepareLayout();
    }

    public function getDescription(): ?string
    {
        return $this->page->getDescription();
    }
}
