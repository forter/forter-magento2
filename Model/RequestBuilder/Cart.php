<?php
/**
 * Forter Payments For Magento 2
 * https://www.Forter.com/
 *
 * @category Forter
 * @package  Forter_Forter
 * @author   Girit-Interactive (https://www.girit-tech.com/)
 */

namespace Forter\Forter\Model\RequestBuilder;

use Magento\Catalog\Model\CategoryFactory;

/**
 * Class Cart
 * @package Forter\Forter\Model\RequestBuilder
 */
class Cart
{

    /**
     * Cart constructor.
     * @param CategoryFactory $categoryFactory
     */
    public function __construct(
        CategoryFactory $categoryFactory
    ) {
        $this->categoryFactory = $categoryFactory;
    }

    /**
     * @param $order
     * @return array
     */
    public function getTotalAmount($order)
    {
        return [
            "amountUSD" => null,
            "amountLocalCurrency" => strval($order->getGrandTotal()),
            "currency" => $order->getOrderCurrency()->getCurrencyCode() . ""
        ];
    }

    /**
     * @param $order
     * @return array
     */
    public function generateCartItems($order)
    {
        $totalDiscount = 0;
        $cartItems = [];

        foreach ($order->getAllItems() as $item) {

            //Category generation
            $product = $item->getProduct();
            $categories = $this->getProductCategories($item->getProduct());
            $totalDiscount += $item->getDiscountAmount();
            $itemIds[] = $item->getProductId();

            // Each item is added to items list twice - once as parent as once as a child. Only add the parents to the cart items
            if ($item->getParentItem() && in_array($item->getParentItem()->getProductId(), $itemIds)) {
                continue;
            }

            $cartItems[] = [
                "basicItemData" => [
                    "price" => [
                        "amountLocalCurrency" => strval($item->getPrice()),
                        "currency" => $order->getOrderCurrency()->getCurrencyCode() . ""
                    ],
                    "value" => [
                        "amountLocalCurrency" => strval($item->getPrice()),
                        "currency" => $order->getOrderCurrency()->getCurrencyCode() . ""
                    ],
                    "productId" => $item->getProductId(),
                    "name" => $item->getName() . "",
                    "type" => $item->getData("is_virtual") ? "NON_TANGIBLE" : "TANGIBLE",
                    "quantity" => (double)$item->getQtyOrdered() ,
                    "category" => $categories
                ],
                "itemSpecificData" => [
                    "physicalGoods" => [
                        "wrapAsGift" => $item->getData("gift_message_available") ? true : false
                    ]
                ],
                "beneficiaries" => [
                    "personalDetails" => [ // recipient info located in the product_options object of item data
                        "firstName" => $item->getInfoBuyRequest("giftcard_recipient_name") . "",
                        "lastName" => "",
                        "email" => $item->getInfoBuyRequest("giftcard_recipient_email") . ""
                    ]
                ]
            ];
        }
        return $cartItems;
    }

    /**
     * @param $order
     * @return array|null
     */
    public function getTotalDiscount($order)
    {
        if (!$order->getCouponCode()) {
            return null;
        }

        return [
            "couponCodeUsed" => $order->getCouponCode() . "",
            "couponDiscountAmount" => [
                "amountLocalCurrency" => strval($order->getDiscountAmount()),
                "currency" => $order->getOrderCurrency()->getCurrencyCode() . ""
            ],
            "discountType" => $order->getDiscountDescription() ? $order->getDiscountDescription() : ""
        ];
    }

    /**
     * @param $product
     * @return array|string|null
     */
    private function getProductCategories($product)
    {
        $categories = [];

        if (!$product) {
            return null;
        }

        $categoryIds = $product->getCategoryIds();
        if ($categoryIds) {
            return null;
        }

        foreach ($categoryIds as $categoryId) {
            $category = $this->categoryFactory->create()->load($categoryId);
            // Is main category
            if ($category && $category->getLevel() == 2) {
                $categories[] = $category->getName();
            }
        }

        $categories = implode("/", $categories);
        return $categories;
    }
}
