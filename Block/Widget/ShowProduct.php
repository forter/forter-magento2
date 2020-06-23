<?php
/**
 * Copyright © 2019 Magenest. All rights reserved.
 * See COPYING.txt for license details.
 *
 * Magenest_magento233_dev extension
 * NOTICE OF LICENSE
 *
 * @category Magenest
 * @package Magenest_magento233_dev
 */

namespace Magenest\CustomWidget\Block\Widget;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Data\Helper\PostHelper;
use Magento\Framework\Url\Helper\Data;
use Magento\Widget\Block\BlockInterface;

class ShowProduct extends ListProduct implements BlockInterface
{
    protected $_productFactory;
    public function __construct(Context $context,
                                PostHelper $postDataHelper,
                                Resolver $layerResolver,
                                ProductFactory $productFactory,
                                CategoryRepositoryInterface $categoryRepository,
                                Data $urlHelper, array $data = [])
    {
        $this->_productFactory = $productFactory;
        parent::__construct($context, $postDataHelper, $layerResolver,
            $categoryRepository, $urlHelper, $data);
        $this->setTemplate("Magenest_CustomWidget::widget/product.phtml");
    }

    public function getProductInformation(){
        $productId = $this->getProduct_id();
        if ($productId){
            $productId = str_replace('product/','',$productId);
        }
        $product = $this->_productFactory->create()->load($productId);
        return $product;
    }

}