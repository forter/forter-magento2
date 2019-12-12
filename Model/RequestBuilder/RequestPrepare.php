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
 * Class RequestPrepare
 * @package Forter\Forter\Model\RequestBuilder
 */
class RequestPrepare
{

    /**
     * @var CategoryFactory
     */
    private $wishlistProvider;

    /**
     * RequestPrepare constructor.
     * @param CategoryFactory $categoryFactory
     */
    public function __construct(
        CategoryFactory $categoryFactory
    ) {
        $this->categoryFactory = $categoryFactory;
    }

    /**
     * @param $remoteIp
     * @return array
     */
    public function getConnectionInformation($remoteIp)
    {
        $headers = getallheaders();
        return [
            "customerIP" => $this->getIpFromOrder($remoteIp, $headers),
            "userAgent" => (is_array($headers) && array_key_exists("User-Agent", $headers)) ? $headers['User-Agent'] : null,
            "forterTokenCookie" => null,
            "merchantDeviceIdentifier" => null,
            "fullHeaders" => json_encode($headers)
        ];
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
            "currency" => $order->getOrderCurrency()->getCurrencyCode()
        ];
    }

    /**
     * @param $order
     * @return array
     */
    public function getAdditionalIdentifiers($order)
    {
        $store = $order->getStore();
        $payment = $order->getPayment();

        return [
            'additionalOrderId' => $order->getRealOrderId(),
            'paymentGatewayId' => $payment ? strval($payment->getTransactionId()) : "",
            'merchant' => [
                'merchantId' => $store->getId(),
                'merchantDomain' => $store->getUrl(),
                'merchantName' => $store->getName()
            ]
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
                        "currency" => $order->getOrderCurrency()->getCurrencyCode()
                    ],
                    "value" => [
                        "amountLocalCurrency" => strval($item->getPrice()),
                        "currency" => $order->getOrderCurrency()->getCurrencyCode()
                    ],
                    "productId" => $item->getProductId(),
                    "name" => $item->getName(),
                    "type" => $item->getData("is_virtual") ? "NON_TANGIBLE" : "TANGIBLE",
                    "quantity" => (double)$item->getQtyOrdered(),
                    "category" => $categories
                ],
                "itemSpecificData" => [
                    "physicalGoods" => [
                        "wrapAsGift" => $item->getData("gift_message_available") ? true : false
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
            "couponCodeUsed" => $order->getCouponCode(),
            "couponDiscountAmount" => [
                "amountLocalCurrency" => strval($order->getDiscountAmount()),
                "currency" => $order->getOrderCurrency()->getCurrencyCode()
            ],
            "discountType" => $order->getDiscountDescription() ? $order->getDiscountDescription() : null
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

    /**
     * @param $remoteIp
     * @param $headers
     * @return false|mixed|string
     */
    private function getIpFromOrder($remoteIp, $headers)
    {
        $xForwardedFor = array_key_exists('X-Forwarded-For', $headers) ? $headers['X-Forwarded-For'] : '';
        $hasXForwardedFor = $xForwardedFor && strlen($xForwardedFor) > 0;
        // x-forwarded-for is a string that is formatted like "clientIp, proxyIp1, proxyIp2"
        // incase it exists take it else for for remoteIp
        if ($hasXForwardedFor) {
            $indexOfComa = strpos($xForwardedFor, ",");
            if ($indexOfComa === false) {
                return $xForwardedFor;
            }
            return substr($xForwardedFor, 0, $indexOfComa);
        }
        return $remoteIp;
    }
}
