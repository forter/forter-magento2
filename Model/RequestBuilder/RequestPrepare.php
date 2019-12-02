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
use Magento\Customer\Model\Session;
use Magento\Newsletter\Model\Subscriber;
use Magento\Review\Model\Review;
use Magento\Sales\Model\OrderFactory;
use Magento\Wishlist\Controller\WishlistProviderInterface;

class RequestPrepare
{
    const SHIPPING_METHOD_PREFIX = "Select Shipping Method - ";

    public function __construct(
      OrderFactory $orderFactory,
      CategoryFactory $categoryFactory,
      Session $session,
      Review $review,
      WishlistProviderInterface $wishlistProvider,
      Subscriber $subscriber
  ) {
        $this->orderFactory = $orderFactory;
        $this->categoryFactory = $categoryFactory;
        $this->session = $session;
        $this->review = $review;
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
                  "productIdType" => $item->getProductType(),
                  "name" => $item->getName(),
                  "type" => $item->getData("is_virtual") ? "NON_TANGIBLE" : "TANGIBLE",
                  "quantity" => (double)$item->getQtyOrdered(),
                  "category" => $categories
              ],
              "itemSpecificData" => [
                  "physicalGoods" => [
                    "wrapAsGift" => $item->getData("gift_message_available") ? true : false
                  ]
              ],
              "created" => $item->getCreatedAt() ? strtotime($item->getCreatedAt()) : null
          ];
        }
        return $cartItems;
    }

    public function getPrimaryDeliveryDetails($order)
    {
        return [
          "deliveryType" => $order->getShippingMethod() ? "PHYSICAL" : "DIGITAL",
          "deliveryMethod" => substr(str_replace($this::SHIPPING_METHOD_PREFIX, "", $order->getShippingDescription()), 0, 45),
          "deliveryPrice" => [
              "amountLocalCurrency" => strval($order->getShippingAmount()),
              "currency" => $order->getOrderCurrency()->getCurrencyCode()
          ]
      ];
    }

    public function getPrimaryRecipient($order)
    {
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();

        $primaryRecipient = [];
        if ($shippingAddress) {
            $personalDetails = [
              "firstName" => $shippingAddress->getFirstname(),
              "lastName" => $shippingAddress->getLastname(),
              "email" => $shippingAddress->getEmail()
          ];
            if ($shippingAddress->getTelephone()) {
                $phone = [
                  [
                      "phone" => $shippingAddress->getTelephone(),
                      "phoneRole" => "SHIPPING"
                  ]
              ];
            }
            $primaryRecipient["address"] = $this->getAddressData($shippingAddress);
            $primaryRecipient["address"]["addressRole"] = "SHIPPING";
        } else {
            if ($billingAddress->getTelephone()) {
                $phone = [
                  [
                      "phone" => $billingAddress->getTelephone(),
                      "phoneRole" => "BILLING"
                  ]
              ];
            }
            $personalDetails = [
              "firstName" => $billingAddress->getFirstName(),
              "lastName" => $billingAddress->getLastName(),
              "middleInitials" => $billingAddress->getMiddleName(),
              "prefix" => $billingAddress->getPrefix(),
              "suffix" => $billingAddress->getSuffix()
          ];
        }
        $primaryRecipient["personalDetails"] = $personalDetails;

        if (isset($phone)) {
            $primaryRecipient["phone"] = $phone;
        }

        return $primaryRecipient;
    }

    public function getAccountOwnerInfo($order)
    {
        $customer = $this->getCustomer($order);

        // customer not logged in
        if (!$customer) {
            $billingAddress = $order->getBillingAddress();
            return [
              "firstName" => $billingAddress->getFirstname(),
              "lastName" => $billingAddress->getLastname(),
              "email" => $billingAddress->getEmail()
          ];
        }

        //Retrieve all orders with this email address
        $totalOrders = $this->orderFactory->create()
          ->getCollection()
          ->addFieldToFilter('customer_email', $customer->getEmail());

        $ordersSum = 0;

        foreach ($totalOrders as $oldOrder) {
            $ordersSum += $oldOrder->getGrandTotal();
        }

        $ordersCount = $totalOrders->getTotalCount();

        return [
        "firstName" => $customer->getFirstname(),
        "lastName" => $customer->getLastname(),
        "email" => $customer->getEmail(),
        "accountId" => $customer->getId(),
        "created" => strtotime($customer->getCreatedAt()),
        "pastOrdersCount" => $ordersCount,
        "pastOrdersSum" => $ordersSum
      ];
    }

    public function getCustomerAccountData($order)
    {
        $isGuest = $order->getCustomerIsGuest();
        $customer = $this->session->getCustomer();
        if ($isGuest || !$customer) {
            $accountStatus = "GUEST";
        } elseif ($customer->isCustomerLocked()) {
            $accountStatus = "SUSPENDED";
        } elseif ($customer->getData("is_active") == 0) {
            $accountStatus = "CLOSED";
        } else {
            $accountStatus = "ACTIVE";
        }

        $customerId = $order->getCustomerId();
        $reviews_count = $this->getCustomerReviewsCount($customerId, $order->getStore()->getId());

        $currentUserWishlist = $this->wishlistProvider->getWishlist();
        $wishlistItemsCount = $currentUserWishlist ? count($currentUserWishlist->getItemCollection()) : 0;

        $checkSubscriber = $this->subscriber->loadByCustomerId($customerId);

        $customerEngagement = [
          "wishlist" => [
              "inUse" => $wishlistItemsCount > 0,
              "itemInListCount" => $wishlistItemsCount
          ],
          "reviewsWritten" => [
              "inUse" => ($reviews_count > 0),
              "itemInListCount" => $reviews_count
          ],
          "newsletters" => [
              "inUse" => $checkSubscriber->isSubscribed()
          ]
      ];

        return [
          "status" => $accountStatus,
          "customerEngagement" => $customerEngagement
      ];
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

    private function getAddressData($address)
    {
        if (!$address) {
            return null;
        }
        $street_address = $address->getStreet();
        $address_1 = (!is_null($street_address) && array_key_exists('0', $street_address)) ? $street_address['0'] : null;
        $address_2 = (!is_null($street_address) && array_key_exists('1', $street_address)) ? $street_address['1'] : null;

        return [
          "address1" => $address_1,
          "address2" => $address_2,
          "zip" => $address->getPostCode(),
          "city" => $address->getCity(),
          "region" => $address->getRegion(),
          "country" => $address->getCountryId(),
          "company" => $address->getCompany(),
          "suggestedCorrectAddress" => null,
          "savedData" => [
              "usedSavedData" => $address->getCustomerAddressId() != null,
              "choseToSaveData" => false  // Default value because this field is required and is not easy enough to get.
          ]
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

    private function getCustomerReviewsCount($customerId, $storeId)
    {
        $reviews_count = $this->review->getResourceCollection()
          ->addStoreFilter($storeId)
          ->addCustomerFilter($customerId)
          ->count();
        return $reviews_count;
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

    private function getCustomer($order)
    {
        if (!is_null($this->session) &&
          method_exists($this->session, "getCustomerData") &&
          $this->session->getCustomerData()) {
            return $this->session->getCustomerData();
        } elseif ($order->getCustomerId()) {
            // If can't get customer from session - for example in cases of order send failure
            return $this->customerRepositoryInterface->getById($order->getCustomerId());
        } else {
            return null;
        }
    }
}
