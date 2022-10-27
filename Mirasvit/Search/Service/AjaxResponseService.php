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

namespace Mirasvit\Search\Service;

use Magento\Catalog\Model\Layer;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\View\Layout\Element;
use Magento\Framework\View\Result\Page;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AjaxResponseService
{
    private $resolver;

    private $resultRawFactory;

    private $request;

    private $moduleManager;

    public function __construct(
        Layer\Resolver $resolver,
        RawFactory $resultRawFactory,
        Http $request,
        ModuleManager $moduleManager
    ) {
        $this->resolver                = $resolver;
        $this->resultRawFactory        = $resultRawFactory;
        $this->request                 = $request;
        $this->moduleManager           = $moduleManager;
    }

    /**
     * @param Page $page
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function getAjaxResponse(Page $page)
    {
        $data = $this->buildDataInstantMode($page);
        $data = $this->prepareAjaxData($data);

        return $this->createResponse($data);
    }

    /**
     * @param string[] $data
     *
     * @return string
     */
    protected function prepareAjaxData($data)
    {
        $map = [
            '&amp;'                  => '&',
            '?isAjax=1&'             => '?',
            '?isAjax=1'              => '',
            '&isAjax=1'              => '',
            '?isAjax=true&'          => '?',
            '?isAjax=true'           => '',
            '&isAjax=true'           => '',
        ];

        foreach ($map as $search => $replace) {
            $data = str_replace($search, $replace, $data);
        }

        return $data;
    }

    /**
     * @param string $data
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    protected function createResponse($data)
    {
        $response = $this->resultRawFactory->create()
            ->setHeader('Content-type', 'text/plain')
            ->setContents(\Zend_Json::encode($data));

        return $response;
    }

    private function buildDataInstantMode(Page $page)
    {
        $layout              = $page->getLayout();
        $productsHtml        = $this->getProductsHtml($page);
        $productsCount       = $this->getProductsCount();
        $leftNavHtml         = $this->getBlockHtml($page, 'catalog.leftnav', 'catalogsearch.leftnav');
        
        $categoryViewData = '';
        $children         = $layout->getChildNames('category.view.container');
        foreach ($children as $child) {
            $categoryViewData .= $layout->renderElement($child);
        }

        $categoryViewData     = '<div class="category-view">' . $categoryViewData . '</div>';

        $data = [
            'products'         => $productsHtml,
            'products_count'   => $productsCount,
            'leftnav'          => $leftNavHtml,
            'categoryViewData' => $categoryViewData
        ];

        try {
            $sidebarTag   = $layout->getElementProperty('div.sidebar.additional', Element::CONTAINER_OPT_HTML_TAG);
            $sidebarClass = $layout->getElementProperty('div.sidebar.additional', Element::CONTAINER_OPT_HTML_CLASS);

            if (method_exists($layout, 'renderNonCachedElement')) {
                $sidebarAdditional = $layout->renderNonCachedElement('div.sidebar.additional');
            } else {
                $sidebarAdditional = '';
            }

            $data['sidebarAdditional']         = $sidebarAdditional;
            $data['sidebarAdditionalSelector'] = $sidebarTag . '.' . str_replace(' ', '.', $sidebarClass);
        } catch (\Exception $e) {
        }

        if ($this->moduleManager->isEnabled('Lof_AjaxScroll')) {
            $data['products'] .= $layout->createBlock('Lof\AjaxScroll\Block\Init')
                ->setTemplate('Lof_AjaxScroll::init.phtml')->toHtml();
            $data['products'] .= $layout->createBlock('Lof\AjaxScroll\Block\Init')
                ->setTemplate('Lof_AjaxScroll::scripts.phtml')->toHtml();
            $data['products'] .= "<script>window.ias.nextUrl = window.ias.getNextUrl();</script>";
        }

        if ($this->moduleManager->isEnabled('Mirasvit_Scroll')) {
            $data['products'] .= $layout->createBlock('Mirasvit\Scroll\Block\Scroll')
                ->setTemplate('Mirasvit_Scroll::scroll.phtml')->toHtml();
        }

        return $data;
    }

    private function getProductsHtml(Page $page)
    {
        if (in_array($this->request->getFullActionName(), ['brand_brand_view', 'all_products_page_index_index'])) {
            $productsHtml = $this->getBlockHtml($page, 'category.products.list');
        } else {
            $productsHtml = $this->getBlockHtml($page, 'category.products', 'search.result');
        }

        return $productsHtml;
    }

    private function getProductsCount()
    {
        $productCollection = $this->resolver->get()->getProductCollection();

        return $productCollection ? $productCollection->getSize() : 0;
    }

    /**
     * @param Page   $page
     * @param string $blockName
     * @param string $fallbackBlockName
     *
     * @return string
     */
    private function getBlockHtml(Page $page, $blockName, $fallbackBlockName = '')
    {
        $block = $this->getBlock($page, $blockName, $fallbackBlockName);

        return $block ? $block->toHtml() : '';
    }

    /**
     * @param Page   $page
     * @param string $blockName
     * @param string $fallbackBlockName
     *
     * @return \Magento\Framework\View\Element\BlockInterface|null
     */
    private function getBlock(Page $page, $blockName, $fallbackBlockName = '')
    {
        $layout = $page->getLayout();
        $block  = $layout->getBlock($blockName);

        if (!$block && $fallbackBlockName) {
            $block = $layout->getBlock($fallbackBlockName);
        }

        return $block ? $block : null;
    }
}
