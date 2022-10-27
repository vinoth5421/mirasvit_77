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

namespace Mirasvit\SearchAutocomplete\Controller\Cart;

// use Magento\Checkout\Controller\Cart\Add as GenericCartAdd;
use Magento\Catalog\Controller\Product\View\ViewInterface;
use Magento\Checkout\Model\Cart as CheckoutCart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Add extends Action implements ViewInterface
{
    private $cart;
    private $session;
    private $product;
    private $productRepository;

    public function __construct(
        CheckoutCart $cart,
        CheckoutSession $session,
        ProductRepositoryInterface $productRepository,
        Context $context
    ) {
        parent::__construct($context);
        $this->cart                 = $cart;
        $this->session              = $session;
        $this->productRepository    = $productRepository;
    }

    public function execute()
    {
        $success = false;
        $message = (string) NoSuchEntityException::singleField('product_id', $this->getRequest()->getParam('id'))->getMessage();

        if ($this->_initProduct()) {
            try {
                $cart = $this->cart->addProduct($this->product,['qty' => 1]);
                $cart->save();
                $message = __('We can\'t add this item to your shopping cart right now.');
            } catch (\Exception $e) {
                $message = $e->getMessage();
            }


            if ($this->session->getLastAddedProductId() == $this->product->getId()) {
                $success = true;
                $message = __('Product was successfully added to the cart');
            }
        } else {
            $this->messageManager->addErrorMessage((string) $message);
            $resultRedirect = $this->resultRedirectFactory->create();

            return $resultRedirect->setPath($this->_redirect->getRefererUrl());
        }

        $this->_eventManager->dispatch(
            'checkout_cart_add_product_complete',
            ['product' => $this->product, 'request' => $this->getRequest(), 'response' => $this->getResponse()]
        );

        if ($this->getRequest()->isAjax()) {
            $response = $this->getResponse();
            return $response->representJson(\Zend_Json::encode(
                [
                    'success' => $success,
                    'message' => $message
                ]
            ));
        }

        if ($success) {
            $this->messageManager->addSuccessMessage((string) $message);
        } else {
            $this->messageManager->addErrorMessage((string) $message);
        }

        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setPath($success? $this->getCartUrl() : $this->product->getProductUrl());
    }

    private function getCartUrl(): string
    {
        return (string)$this->_url->getUrl('checkout/cart', ['_secure' => true]);
    }

    private function _initProduct()
    {
        $productId = (int)$this->getRequest()->getParam('id');
        if ($productId) {
            $storeId = $this->_objectManager->get(
                \Magento\Store\Model\StoreManagerInterface::class
            )->getStore()->getId();
            try {
                $this->product = $this->productRepository->getById($productId, false, $storeId);
                return true;
            } catch (NoSuchEntityException $e) {
                return false;
            }
        }
        return false;
    }
}
