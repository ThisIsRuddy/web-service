<?php

namespace Ruddy\WebService\Model;

use Ruddy\WebService\Api\WebServiceRepositoryInterface;
use Zend\Log\Writer\Stream;
use Zend\Log\Logger;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnectionFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableTypeInstance;
use Magento\Catalog\Model\ProductRepository as ModelProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class WebServiceRepository
 * @package Ruddy\WebService\Model
 */
class WebServiceRepository implements WebServiceRepositoryInterface{
    /**
     * @var Logger
     */
    protected $_logger;
    /**
     * @var JsonFactory
     */
    protected $_jsonFactory;
    /**
     * @var ResourceConnectionFactory
     */
    protected $_resourceConnection;
    /**
     * @var ProductCollectionFactory
     */
    protected $_productCollection;
    /**
     * @var CategoryFactory
     */
    protected $_category;
    /**
     * @var ModelProductRepository
     */
    protected $_modelProductRepository;
    /**
     * @var ConfigurableTypeInstance
     */
    protected $_configurableType;
    /**
     * WebServiceRepository constructor.
     *
     * @param JsonFactory $_jsonFactory
     * @param ResourceConnectionFactory $_resourceConnection
     * @param ProductCollectionFactory $_productCollection
     * @param CategoryFactory $_category
     * @param ModelProductRepository $_modelProductRepository
     * @param ConfigurableTypeInstance $_configurableType
     */
    public function __construct(
        JsonFactory $_jsonFactory,
        ResourceConnectionFactory $_resourceConnection,
        ProductCollectionFactory $_productCollection,
        CategoryFactory $_category,
        ModelProductRepository $_modelProductRepository,
        ConfigurableTypeInstance $_configurableType
    ){
        $writer = new Stream(BP . '/var/log/WebServiceRepository.log');
        $this->_logger = new Logger();
        $this->_logger ->addWriter($writer);

        $this->_jsonFactory = $_jsonFactory;
        $this->_resourceConnection = $_resourceConnection;
        $this->_productCollection = $_productCollection;
        $this->_category = $_category;
        $this->_modelProductRepository = $_modelProductRepository;
        $this->_configurableType = $_configurableType;
    }
    /**
     * @return int
     */
    public function getCatalogProductCount(){
        return $this->_productCollection->create()->getSize();
    }
    /**
     * @param int $categoryId
     * @return int
     */
    public function getCategoryProductCount($categoryId){
        $size = 0;
        $category = $this->_category->create()->load($categoryId);
        if (isset($category) && !empty($category)) {
            $size = $category->getProductCollection()->getSize();
        }
        return $size;
    }
    /**
     * @param string $sku
     * @return string
     */
    public function getConfigurableAttributes($sku){
        $product = $this->_modelProductRepository->get($sku);
        return (isset($product) && !empty($product)) ?
            [$this->_configurableType->getConfigurableAttributesAsArray($product)] :
            ["error" => "No product found for $sku"];
    }
    /**
     * @param string $sku
     * @return string
     */
    public function getUsedProductAttributes($sku){
        $product = $this->_modelProductRepository->get($sku);
        return (isset($product) && !empty($product)) ?
            [$this->_configurableType->getUsedProductAttributes($product)] :
            ["error" => "No product found for $sku"];
    }
    /**
     * @param string $sku
     * @return string
     */
    public function getConfigurableVariations($sku){
        $results = [];
        $configurable = $this->getProductBySku($sku);
        if(!isset($configurable) || empty($configurable)) return [$results['errors']['crit'] = 'Unable to find configurable product.'];

        $skus = $this->getProductSkusByIds($this->_configurableType->getUsedProducts($configurable), $results);
        if(!isset($skus) || empty($skus)) return [$results['errors']['crit'] = "Unable to find any simple products using the variation ids retrieved from the configurable product sku '$sku'."];
        
        if(isset($results['errors']['warn'])) $results['success']['warn'][] = "'" . count($results['errors']['warn']) . "' variation(s) could not be found, these skus may not exist. Reindex suggested.";
        return [$results];
    }
    /**
     * @param string $sku
     * @param mixed $variations
     * @return string
     */
    public function setConfigurableVariations($sku, $variations){
        $results = [];
        $configurable = $this->getProductBySku($sku);
        if(!isset($configurable) || empty($configurable)) return [$results['errors']['crit'] = 'Unable to find configurable product.'];

        $ids = $this->getProductIdsBySkus($variations, $results);
        if(!isset($ids) || empty($ids)) return [$results['errors']['crit'] = 'Unable to find any simple products using the variation skus supplied.'];

        $configurable->getExtensionAttributes()->setConfigurableProductLinks($ids);
        $configurable->save();

        $results['success']['assignedVariations'] = $this->getConfigurableVariations($sku);
        if(isset($results['errors']['warn'])) $results['success']['warn'][] = "'" . count($results['errors']['warn']) . "' simple variation(s) had errors, see warn errors.";

        return [$results];
    }
    private function getProductBySku($sku){
        $product = null;
        try {
            $product = $this->_modelProductRepository->get($sku);
        }catch(NoSuchEntityException $e){
            $this->_logger->warn("Unable to find product with sku '$sku'.'");
        }
        return $product;
    }
    private function getProductIdsBySkus($skus, &$results){
        $ids = null;
        foreach($skus as $s_sku){
            try {
                $product = $this->_modelProductRepository->get($s_sku);
                $ids[] = $product->getId();
            }catch(NoSuchEntityException $e) {
                $results['errors']['warn'][] = "Unable to find simple product for sku: '$s_sku'";
                $this->_logger->warn("Unable to find product with sku '$s_sku'.'");
            }
        }
        return $ids;
    }
    private function getProductSkusByIds($ids, &$results){
        $skus = null;
        foreach($ids as $s_id => $val){
            try {
                $product = $this->_modelProductRepository->getById($s_id);
                $skus[] = $product->getSku();
            }catch(NoSuchEntityException $e) {
                $results['errors']['warn'][] = "Unable to find simple product for id: '$s_id'";
                $this->_logger->warn("Unable to find product with id '$s_id'.'");
            }
        }
        return $skus;
    }

}