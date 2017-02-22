<?php

namespace Ruddy\WebService\Api;

/**
 * Interface WebServiceRepositoryInterface
 * @package Ruddy\WebService\Api
 */
interface WebServiceRepositoryInterface{
    /**
     * @return int
     */
    public function getCatalogProductCount();
    /**
     * @param int $categoryId
     * @return mixed
     */
    public function getCategoryProductCount($categoryId);
    /**
     * @param string $sku
     * @return string
     */
    public function getConfigurableAttributes($sku);
    /**
     * @param string $sku
     * @return string
     */
    public function getUsedProductAttributes($sku);
    /**
     * @param string $sku
     * @return string
     */
    public function getConfigurableVariations($sku);
    /**
     * @param string $sku
     * @param mixed $variations
     * @return string
     */
    public function setConfigurableVariations($sku, $variations);
}