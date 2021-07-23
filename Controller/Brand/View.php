<?php
/**
 * Venustheme
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the Venustheme.com license that is
 * available through the world-wide-web at this URL:
 * http://www.venustheme.com/license-agreement.html
 * 
 * DISCLAIMER
 * 
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 * 
 * @category   Venustheme
 * @package    Ves_Brand
 * @copyright  Copyright (c) 2014 Venustheme (http://www.venustheme.com/)
 * @license    http://www.venustheme.com/LICENSE-1.0.html
 */
namespace Ves\Brand\Controller\Brand;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class View extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\LayoutFactory
     */
    protected $resultLayoutFactory;

    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Framework\App\ResponseInterface
     */
    protected $_response;

    /**
     * @var \Magento\Framework\Controller\Result\RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \Ves\Brand\Model\Brand
     */
    protected $_brandModel;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;


    /**
     * @var \Magento\Framework\Controller\Result\ForwardFactory
     */
    protected $resultForwardFactory;

    /**
     * @var \Ves\Brand\Helper\Data
     */
    protected $_brandHelper;

    /**
     * @param  Context $context,
     * @param  \Magento\Store\Model\StoreManager $storeManager
     * @param  \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param  \Ves\Brand\Model\Brand $brandModel
     * @param  \Magento\Framework\Registry $coreRegistry,
     * @param  \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory
     * @param  \Ves\Brand\Helper\Data $brandHelper
     * @param  JsonFactory $resultJsonFactory $brandHelper
     * @param \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory
     */
    public function __construct(
        Context $context,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Ves\Brand\Model\Brand $brandModel,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\Controller\Result\ForwardFactory $resultForwardFactory,
        \Ves\Brand\Helper\Data $brandHelper,
        JsonFactory $resultJsonFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory
        ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->_brandModel = $brandModel;
        $this->_coreRegistry = $coreRegistry;
        $this->resultForwardFactory = $resultForwardFactory;
        $this->_brandHelper = $brandHelper;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
    }

    public function _initBrand()
    {
        $brandId = (int)$this->getRequest()->getParam('brand_id', false);
        if (!$brandId) {
            return false;
        }
        try{
            $brand = $this->_brandModel->load($brandId);
        } catch (NoSuchEntityException $e) {
            return false;
        }
        $this->_coreRegistry->register('current_brand', $brand);
        return $brand;
    }

    /**
     * Default customer account page
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        if(!$this->_brandHelper->getConfig('general_settings/enable')){
            return $this->resultForwardFactory->create()->forward('noroute');
        }

        $brand = $this->_initBrand();
        if ($brand) {
            /** @var \Magento\Framework\View\Result\Page $resultPage */
            $page = $this->resultPageFactory->create();
            // apply custom layout (page) template once the blocks are generated
            if ($brand->getPageLayout()) {
                $page->getConfig()->setPageLayout($brand->getPageLayout());
            }
            $page->addHandle(['type' => 'VES_BRAND_'.$brand->getId()]);
            if (($layoutUpdate = $brand->getLayoutUpdateXml()) && trim($layoutUpdate)!='') {
                $page->addUpdate($layoutUpdate);
            }

            /*$collectionSize = $brand->getProductCollection()->getSize();
            if($collectionSize){
                $page->addHandle(['type' => 'vesbrand_brand_layered']);
            }*/
            $page->getConfig()->addBodyClass('page-products')
                    ->addBodyClass('brand-' . $brand->getUrlKey());

            if($this->getRequest()->getParam('ajax') == 1){
                $this->getRequest()->getQuery()->set('ajax', null);
                $requestUri = $this->getRequest()->getRequestUri();
                $requestUri = preg_replace('/(\?|&)ajax=1/', '', $requestUri);
                $this->getRequest()->setRequestUri($requestUri);
                $resultLayout = $this->resultLayoutFactory->create();
                $brandProducts = $resultLayout->getLayout()->getBlock('brand.products');
                $productsBlockHtml = $brandProducts?$brandProducts->toHtml():"";
                $leftnav = $resultLayout->getLayout()->getBlock('catalog.leftnav');
                $leftNavBlockHtml = $leftnav?$leftnav->toHtml():"";
                return $this->_resultJsonFactory->create()->setData(['success' => true, 'html' => [
                    'products_list' => $productsBlockHtml,
                    'filters' => $leftNavBlockHtml
                ]]);
            }
            return $page;
        }elseif (!$this->getResponse()->isRedirect()) {
            return $this->resultForwardFactory->create()->forward('noroute');
        }
    }
}