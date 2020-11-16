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
use Forter\Forter\Model\RequestBuilder\GiftCard as GiftCardPrepere;
use Forter\Forter\Model\RequestBuilder\Customer as CustomerPrepere;
use Magento\Framework\App\ObjectManager;

/**
 * Class Cart
 * @package Forter\Forter\Model\RequestBuilder
 */
class Cart
{
    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var GiftCardPrepere
     */
    private $giftCardPrepere;

    /**
     * @var CustomerPrepere
     */
    private $customerPrepere;

    /**
     * Cart constructor.
     * @param CategoryFactory $categoryFactory
     * @param GiftCardPrepere $giftCardPrepere
     * @param CustomerPrepere $customerPrepere
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        GiftCardPrepere $giftCardPrepere = null,
        CustomerPrepere $customerPrepere = null
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->giftCardPrepere = $giftCardPrepere ? $giftCardPrepere : ObjectManager::getInstance()->get(GiftCardPrepere::class);
        $this->customerPrepere = $customerPrepere ? $customerPrepere : ObjectManager::getInstance()->get(CustomerPrepere::class);
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
        $beneficiaries = $this->customerPrepere->getPrimaryRecipient($order);

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

            $singleCartItem = [
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
                "beneficiaries" => $this->giftCardPrepere->getGiftCardBeneficiaries($item) ? $this->giftCardPrepere->getGiftCardBeneficiaries($item) : [$beneficiaries]
            ];

            if ($this->giftCardPrepere->getGiftCardBeneficiaries($item)) {
                $singleCartItem['beneficiaries'] = $this->giftCardPrepere->getGiftCardBeneficiaries($item);
            }

            $cartItems[] = $singleCartItem;
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
