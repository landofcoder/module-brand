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
namespace Ves\Brand\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Group Collection
     */
    protected $_groupCollection;

    /** @var \Magento\Store\Model\StoreManagerInterface */
    protected $_storeManager;

    /**
     * Brand config node per website
     *
     * @var array
     */
    protected $_config = [];

    /**
     * Template filter factory
     *
     * @var \Magento\Catalog\Model\Template\Filter\Factory
     */
    protected $_templateFilterFactory;

    /**
     * @var \Magento\Cms\Model\Template\FilterProvider
     */
    protected $_filterProvider;

    protected $_request;

    protected $_moduleList;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Ves\Brand\Model\Group $groupCollection,
        \Magento\Cms\Model\Template\FilterProvider $filterProvider,
        \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attributeFactory,
        \Magento\Framework\Module\ModuleListInterface $moduleList
        ) {
        parent::__construct($context);
        $this->_filterProvider = $filterProvider;
        $this->_storeManager = $storeManager;
        $this->_groupCollection = $groupCollection;
        $this->_request            = $context->getRequest();
        $this->_attributeFactory = $attributeFactory;
        $this->_moduleList      = $moduleList;
    }

    public function checkModuleInstalled($moduleName){
        return $this->_moduleList->has($moduleName);
    }
    
    public function getGroupList(){
        $result = array();
        $collection = $this->_groupCollection->getCollection()
        ->addFieldToFilter('status', '1');
        foreach ($collection as $brandGroup) {
            $result[$brandGroup->getId()] = $brandGroup->getName();
        }
        return $result;
    }

    public function getAttributeOptions(){
        $attribute_code = \Ves\Brand\Model\Brand::ATTRIBUTE_CODE;
        $attributeInfo=$this->_attributeFactory->getCollection()
               ->addFieldToFilter('attribute_code',['eq'=>$attribute_code])
               ->getFirstItem();
        
        $attribute_id = $attributeInfo->getAttributeId();
        
        $model = $this->_groupCollection;
		$connection = $model->getResource()->getConnection();
		$select = $connection->select()->from(
			["main_table" => $model->getResource()->getTable('eav_attribute_option')], 'option_id'
		)
		->joinLeft(
            ['eav_option_value'=>$model->getResource()->getTable('eav_attribute_option_value')],
			"main_table.option_id = eav_option_value.option_id and eav_option_value.store_id=0",
			["option_value" => "eav_option_value.value"]
        )->where('attribute_id = '.$attribute_id);
		$options = $connection->fetchAll($select);
		$return_options = [0=> __("-- Please Select --")];
		if($options){
			foreach($options as $_option){
				$return_options[$_option['option_id']] = $_option["option_value"];
			}
		}
		return $return_options;
    }

    public function getAttributeValueOptions(){
        $model = $this->_attributeFactory->setEntityTypeId(
            \Magento\Catalog\Model\Product::ENTITY
		);
		$attribute_code = \Ves\Brand\Model\Brand::ATTRIBUTE_CODE;
        $model->loadByCode(\Magento\Catalog\Model\Product::ENTITY, $attribute_code);
        $options = $model->getOptions();
        $return = [0=> __("-- Please Select --")];
        if($options){
            foreach($options as $option){
                if($id = (int)$option->getValue()){
                    $return[$id] = $option->getLabel();
                }
            }
        }
        return $return;
    }
    
    public function getStoreBrandCode() {
        return \Ves\Brand\Model\Brand::ATTRIBUTE_CODE;
    }

    /**
     * Return brand config value by key and store
     *
     * @param string $key
     * @param \Magento\Store\Model\Store|int|string $store
     * @return string|null
     */
    public function getConfig($key, $store = null)
    {
        $store = $this->_storeManager->getStore($store);
        $websiteId = $store->getWebsiteId();

        $result = $this->scopeConfig->getValue(
            'vesbrand/'.$key,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store);
        return $result;
    }

    public function filter($str)
    {
        $html = $this->_filterProvider->getPageFilter()->filter($str);
        return $html;
    }

    public function getSearchFormUrl(){
        $url        = $this->_storeManager->getStore()->getBaseUrl();
        $url_prefix = $this->getConfig('general_settings/url_prefix');
        $url_suffix = $this->getConfig('general_settings/url_suffix');
        $urlPrefix  = '';
        if ($url_prefix) {
            $urlPrefix = $url_prefix . '/';
        }
        return $url . $urlPrefix . 'search';
    }
    public function getSearchKey(){
        return $this->_request->getParam('s');
    }

    public function getBrandUrl($url_key = "")
    {
        $url = $this->_storeManager->getStore()->getBaseUrl();
        $route = $this->getConfig('general_settings/route');
        $url_suffix = $this->getConfig('general_settings/url_suffix');
        if($url_key){
            return $url.$url_key.$url_suffix;
        }else {
            return $url.$route.$url_suffix;
        }
    }

}