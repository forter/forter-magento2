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
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\Controller\WishlistProviderInterface;

class RequestPrepare
{
    const SHIPPING_METHOD_PREFIX = "Select Shipping Method - ";

    public function __construct(
        CategoryFactory $categoryFactory,
        WishlistProviderInterface $wishlistProvider,
        Subscriber $subscriber,
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
        $this->categoryFactory = $categoryFactory;
        $this->wishlistProvider = $wishlistProvider;
        $this->subscriber = $subscriber;
    }

    public function getConnectionInformation($remoteIp)
    {
        $headers = getallheaders();
        $connectionInformation  = [
      "customerIP" => $this->getIpFromOrder($remoteIp, $headers),
      "userAgent" => (is_array($headers) && array_key_exists("User-Agent", $headers)) ? $headers['User-Agent'] : null,
      "forterTokenCookie" => null,
      "merchantDeviceIdentifier" => null,
      "fullHeaders" => json_encode($headers)
    ];
        return $connectionInformation;
    }

    public function getTotalAmount($order)
    {
        $totalAmount  = [
      "amountUSD" => null,
      "amountLocalCurrency" => strval($order->getGrandTotal()),
      "currency" => $order->getOrderCurrency()->getCurrencyCode()
    ];
        return $totalAmount;
    }

    public function getAdditionalIdentifiers($order)
    {
        $store = $order->getStore();
        $payment = $order->getPayment();

        $additionalIdentifiers  = [
      'additionalOrderId' => $order->getRealOrderId(),
      'paymentGatewayId' => $payment ? strval($payment->getTransactionId()) : "",
      'merchant' => [
        'merchantId' => $store->getId(),
        'merchantDomain' => $store->getUrl(),
        'merchantName' => $store->getName()
      ]
    ];

        return $additionalIdentifiers;
    }

    public function generateCartItems($order)
    {
        $totalDiscount = 0;
        $cartItems = [];

        foreach ($order->getAllItems() as $item) {

          // Each item is added to items list twice - once as parent as once as a child. Only add the parents to the cart items
            if ($item->getParentItem() && in_array($item->getParentItem()->getProductId(), $itemIds)) {
                continue;
            }

            //Category generation
            $product = $item->getProduct();
            $categories = $this->getProductCategories($item->getProduct());
            $totalDiscount += $item->getDiscountAmount();
            $itemIds[] = $item->getProductId();

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

    public function getTotalDiscount($order)
    {
        if (!$order->getCouponCode()) {
            return null;
        }

        return [
        "couponCodeUsed" => $order->getCouponCode(),
        "couponDiscountAmount" => [
            "amountLocalCurrency" => strval($order->getDiscountAmount()),
            "currency"=> $order->getOrderCurrency()->getCurrencyCode()
        ],
        "discountType" => $order->getDiscountDescription() ? $order->getDiscountDescription() : null
    ];
    }

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
