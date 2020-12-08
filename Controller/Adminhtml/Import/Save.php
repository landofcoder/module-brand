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
namespace Ves\Brand\Controller\Adminhtml\Import;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $_filesystem;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Ves\Setup\Helper\Import
     */
    protected $_vesImport;

    /**
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    protected $_configResource;

    /**
    * CSV Processor
    *
    * @var \Magento\Framework\File\Csv
    */
    protected $csvProcessor;

    /**
    * productFactory
    *
    * @var \Magento\Catalog\Model\ProductFactory
    */
    protected $productFactory;
    /**
     * @param \Magento\Backend\App\Action\Context                          $context           
     * @param \Magento\Framework\View\Result\PageFactory                   $resultPageFactory        
     * @param \Magento\Framework\Filesystem                                $filesystem        
     * @param \Magento\Store\Model\StoreManagerInterface                   $storeManager      
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $scopeConfig       
     * @param \Magento\Framework\App\ResourceConnection                    $resource          
     * @param \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configResource    
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configResource,
        \Magento\Catalog\Model\Product\Media\Config $mediaConfig,
        \Magento\Framework\File\Csv $csvProcessor,
        \Magento\Catalog\Model\ProductFactory $productFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->_filesystem       = $filesystem;
        $this->_storeManager     = $storeManager;
        $this->_scopeConfig      = $scopeConfig;
        $this->_configResource   = $configResource;
        $this->_resource         = $resource;
        $this->mediaConfig       = $mediaConfig;
        $this->csvProcessor = $csvProcessor;
        $this->productFactory = $productFactory;
    }

    /**
     * Forward to edit
     *
     * @return \Magento\Backend\Model\View\Result\Forward
     */
    public function execute()
    {
        $data = $this->getRequest()->getParams();
        $filePath = $fileContent = '';
        try {
            $uploader = $this->_objectManager->create(
                'Magento\MediaStorage\Model\File\Uploader',
                ['fileId' => 'data_import_file']
            );

            $fileContent = '';
            if($uploader) {
                $tmpDirectory = $this->_objectManager->get('Magento\Framework\Filesystem')->getDirectoryRead(DirectoryList::TMP);
                $savePath     = $tmpDirectory->getAbsolutePath('ves/import');
                $uploader->setAllowRenameFiles(true);
                $result       = $uploader->save($savePath);
                $filePath = $tmpDirectory->getAbsolutePath('ves/import/' . $result['file']);
                $fileContent  = file_get_contents($tmpDirectory->getAbsolutePath('ves/import/' . $result['file']));
            }
        } catch (\Exception $e) {
            $this->messageManager->addError(__("Can't import data<br/> %1", $e->getMessage()));
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('*/*/');
        }
        $delimiter = $this->getRequest()->getParam('split_symbol');
        if($delimiter) {
            $importData = $this->csvProcessor->setDelimiter($delimiter)->getData($filePath);
        } else {
            $importData = $this->csvProcessor->getData($filePath);
        }
        
        $store = $this->_storeManager->getStore($data['store_id']);
        $connection = $this->_resource->getConnection();
        if(!empty($importData)) {
            try{
                
                $heading = $importData[0];
                unset($importData[0]);
                $attribute_index = array_search("additional_attributes", $heading);
                $brand_index = array_search("product_brand", $heading);
                $sku_index = array_search("sku", $heading);
                if($sku_index !== false && ($brand_index !== false || $attribute_index !== false)) {
                    $imported_counter = 0;
                   foreach($importData as $item_data) {
                        $product_sku = $item_data[$sku_index];
                        $product_sku = trim($product_sku);

                        if($product_sku) {
                            //get product id by sku
                            $product = $this->productFactory->create();
                            $product_id = $product->getIdBySku($product_sku);
                            $brand_value = "";
                            $brand_values = [];
                           if($brand_index !== false) {
                                $brand_value = $item_data[$brand_index];
                                $brand_value = trim($brand_value);
                            } elseif($attribute_index !== false) {
                                $attr_value = $item_data[$attribute_index];
                                $tmp_arr = explode(",", $attr_value);
                                if($tmp_arr) {
                                    foreach($tmp_arr as $arr_item) {
                                        $tmp_attr = explode("=", $arr_item);
                                        if($tmp_attr && $tmp_attr[0] == "product_brand") {
                                            $brand_value = isset($tmp_attr[1])?$tmp_attr[1]:"";
                                            $brand_value = replace('"','',$brand_value);
                                            $brand_value = trim($brand_value);
                                            $brand_values = explode(",",$brand_value);
                                        }
                                    }
                                }
                            }
                            if($brand_values && $product_id) {
                                //delete all old brands via product_id
                                $brand_model->deleteBrandsByProduct($product_id);
                                foreach($brand_values as $brand_value){
                                    $brand_value = trim($brand_value);
                                    if($brand_value){
                                        //get brand id by brand name
                                        $brand_model = $this->_objectManager->create('Ves\Brand\Model\Brand');
                                        $brand_model = $brand_model->loadByBrandName($brand_value);

                                        //insert products to brand
                                        if($brand_model->getId()) {
                                            $brand_model->saveProduct($product_id);
                                            $imported_counter++;
                                        }
                                    }
                                }
                                
                            }
                        }
                        
                    }

                    if($imported_counter) 
                        $this->messageManager->addSuccess(__("Import successfully"));
                    else 
                        $this->messageManager->addError(__("Can not found product or brand item to imported."));
                } else {
                    $this->messageManager->addError(__("Required there columns: sku, product_brand or additional_attributes"));
                }

                
            }catch(\Exception $e){
                $this->messageManager->addError(__("Can't import data<br/> %1", $e->getMessage()));
            }
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/');
    }
    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Ves_Brand::import_save');
    }
}
