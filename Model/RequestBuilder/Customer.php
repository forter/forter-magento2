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

use Magento\Customer\Model\Session;
use Magento\Sales\Model\OrderFactory;

class Customer
{
    public function __construct(
        OrderFactory $orderFactory,
        Review $review,
        Session $session
    ) {
        $this->session = $session;
        $this->orderFactory = $orderFactory;
        $this->review = $review;
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
                      "phone" => $shippingAddress->getTelephone()
                  ]
              ];
            }
            $primaryRecipient["address"] = $this->getAddressData($shippingAddress);
        } else {
            if ($billingAddress->getTelephone()) {
                $phone = [
                  [
                      "phone" => $billingAddress->getTelephone()
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

    public function getCustomerAccountData($order = null, $savedcustomer = null)
    {
        if ($savedcustomer) {
            $isGuest = null;
            $customerId = $savedcustomer->getId();
        } else {
            $isGuest = $order->getCustomerIsGuest();
            $customer = $this->session->getCustomer();
            $customerId = $order->getCustomerId();
        }

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

        $reviews_count = $this->getCustomerReviewsCount($customerId, $this->storeManager->getStore()->getId());

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
          "savedData" => [
              "usedSavedData" => $address->getCustomerAddressId() != null,
              "choseToSaveData" => false  // Default value because this field is required and is not easy enough to get.
          ]
      ];
    }

    private function getCustomerReviewsCount($customerId, $storeId)
    {
        $reviews_count = $this->review->getResourceCollection()
          ->addStoreFilter($storeId)
          ->addCustomerFilter($customerId)
          ->count();
        return $reviews_count;
    }
}
