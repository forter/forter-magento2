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

use Forter\Forter\Model\Config as ForterConfig;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Newsletter\Model\Subscriber;
use Magento\Review\Model\Review;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\Controller\WishlistProviderInterface;

/**
 * Class Customer
 * @package Forter\Forter\Model\RequestBuilder
 */
class Customer
{
    /**
     *
     */
    const SHIPPING_METHOD_PREFIX = "Select Shipping Method - ";

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var WishlistProviderInterface
     */
    private $wishlistProvider;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var Review
     */
    private $review;
    /**
     * @var Subscriber
     */
    private $subscriber;
    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * Customer constructor.
     * @param OrderFactory $orderFactory
     * @param Review $review
     * @param Session $session
     * @param StoreManagerInterface $storeManager
     * @param Subscriber $subscriber
     * @param Config $forterConfig
     */
    public function __construct(
        OrderFactory $orderFactory,
        Review $review,
        Session $session,
        WishlistProviderInterface $wishlistProvider,
        StoreManagerInterface $storeManager,
        Subscriber $subscriber,
        CustomerRepositoryInterface $customerRepository,
        ForterConfig $forterConfig
    ) {
        $this->session = $session;
        $this->wishlistProvider = $wishlistProvider;
        $this->orderFactory = $orderFactory;
        $this->review = $review;
        $this->storeManager = $storeManager;
        $this->subscriber = $subscriber;
        $this->customerRepository = $customerRepository;
        $this->forterConfig = $forterConfig;
    }

    /**
     * @param $order
     * @return array
     */
    public function getPrimaryDeliveryDetails($order)
    {
        $shippingMethod = $order->getShippingMethod();
        if ($shippingMethod == 'instore_pickup') {
            $deliveryMethod = "store pickup";
        } else {
            $deliveryMethod = substr(str_replace($this::SHIPPING_METHOD_PREFIX, "", $order->getShippingDescription()), 0, 45);
        }

        return [
            "deliveryType" => $order->getShippingMethod() ? "PHYSICAL" : "DIGITAL",
            "deliveryMethod" => $deliveryMethod,
            "deliveryPrice" => [
                "amountLocalCurrency" => strval($order->getShippingAmount()),
                "currency" => $order->getOrderCurrency()->getCurrencyCode() . ""
            ]
        ];
    }

    /**
     * @param $order
     * @return array
     */
    public function getPrimaryRecipient($order)
    {
        $shippingAddress = $order->getShippingAddress();
        $shippingMethod = $order->getShippingMethod();
        if ($shippingMethod == 'instore_pickup') {
            $billingAddress = $shippingAddress;
        } else {
            $billingAddress = $order->getBillingAddress();
        }
        $primaryRecipient = [];
        if ($shippingAddress) {
            $personalDetails = [
                "firstName" => $shippingAddress->getFirstname() . "",
                "lastName" => $shippingAddress->getLastname() . "",
                "email" => $shippingAddress->getEmail() . ""
            ];
            if ($shippingAddress->getTelephone()) {
                $phone = [
                    [
                        "phone" => $shippingAddress->getTelephone() . ""
                    ]
                ];
            }
            $primaryRecipient["address"] = $this->getAddressData($shippingAddress);
        } else {
            if ($billingAddress->getTelephone()) {
                $phone = [
                    [
                        "phone" => $billingAddress->getTelephone() . ""
                    ]
                ];
            }
            $personalDetails = [
                "firstName" => $billingAddress->getFirstName() . "",
                "lastName" => $billingAddress->getLastName() . ""
            ];
        }
        $primaryRecipient["personalDetails"] = $personalDetails;

        if (isset($phone)) {
            $primaryRecipient["phone"] = $phone;
        }

        return $primaryRecipient;
    }

    /**
     * @param $order
     * @return array
     */
    public function getAccountOwnerInfo($order)
    {
        $customer = $this->getCustomer($order);

        // customer not logged in
        if (!$customer) {
            $billingAddress = $order->getBillingAddress();
            return [
                "firstName" => $billingAddress->getFirstname() . "",
                "lastName" => $billingAddress->getLastname() . "",
                "email" => $billingAddress->getEmail() . ""
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
            "firstName" => $customer->getFirstname() . "",
            "lastName" => $customer->getLastname() . "",
            "email" => $customer->getEmail() . "",
            "accountId" => $customer->getId(),
            "created" => strtotime($customer->getCreatedAt()),
            "pastOrdersCount" => $ordersCount,
            "pastOrdersSum" => $ordersSum
        ];
    }

    /**
     * @param null $order
     * @param null $savedcustomer
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
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

        if ($this->storeManager->getStore()) {
            $storeId = $this->storeManager->getStore()->getId();
        } else {
            $storeId = $order->getStore()->getId();
        }

        $reviews_count = $this->getCustomerReviewsCount($customerId, $storeId);

        if (!$this->forterConfig->getIsCron()) {
            $currentUserWishlist = $this->wishlistProvider->getWishlist();
            $wishlistItemsCount = $currentUserWishlist ? count($currentUserWishlist->getItemCollection()) : 0;
        } else {
            $wishlistItemsCount = 0;
        }

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

    /**
     * @param $order
     * @return \Magento\Customer\Api\Data\CustomerInterface|null
     */
    private function getCustomer($order)
    {
        if (!is_null($this->session) &&
            method_exists($this->session, "getCustomerData") &&
            $this->session->getCustomerData()) {
            return $this->session->getCustomerData();
        } elseif ($order->getCustomerId()) {
            // If can't get customer from session - for example in cases of order send failure
            return $this->customerRepository->getById($order->getCustomerId());
        } else {
            return null;
        }
    }

    /**
     * @param $address
     * @return array|null
     */
    public function getAddressData($address)
    {
        if (!$address) {
            return null;
        }
        $street_address = $address->getStreet();
        $address_1 = (!is_null($street_address) && array_key_exists('0', $street_address)) ? $street_address['0'] : null;
        $address_2 = (!is_null($street_address) && array_key_exists('1', $street_address)) ? $street_address['1'] : null;

        return [
            "address1" => $address_1 . "",
            "address2" => $address_2 . "",
            "zip" => $address->getPostCode() . "",
            "city" => $address->getCity() . "",
            "region" => (string)$address->getRegion() . "",
            "country" => $address->getCountryId() . "",
            "company" => $address->getCompany() . "",
            "savedData" => [
                "usedSavedData" => $address->getCustomerAddressId() != null,
                "choseToSaveData" => false  // Default value because this field is required and is not easy enough to get.
            ]
        ];
    }

    /**
     * @param $customerId
     * @param $storeId
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getCustomerReviewsCount($customerId, $storeId)
    {
        $reviews_count = $this->review->getResourceCollection()
            ->addStoreFilter($storeId)
            ->addCustomerFilter($customerId)
            ->count();
        return $reviews_count;
    }

    /**
     * @param $billingAddress
     * @return array|null
     */
    public function getBillingDetails($billingAddress)
    {
        $billingDetails = [];
        $billingDetails["personalDetails"] = [
          "firstName" => $billingAddress->getFirstName() . "",
          "lastName" => $billingAddress->getLastName() . "",
          "email" => $billingAddress->getEmail() . ""
        ];

        if ($billingAddress) {
            $billingDetails["address"] = $this->getAddressData($billingAddress);

            if ($billingAddress->getTelephone()) {
                $billingDetails["phone"] = [
                  [
                      "phone" => $billingAddress->getTelephone() . ""
                  ]
                ];
            }
        }

        return $billingDetails;
    }
}
