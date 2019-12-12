<?php
/**
* Forter Payments For Magento 2
* https://www.Forter.com/
*
* @category Forter
* @package  Forter_Forter
* @author   Girit-Interactive (https://www.girit-tech.com/)
*/
namespace Forter\Forter\Model;

use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Model\RequestBuilder\Customer as CustomerPreper;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPreper;
use Forter\Forter\Model\RequestBuilder\RequestPrepare;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Customer\Model\Session;
use Magento\Newsletter\Model\Subscriber;
use Magento\Review\Model\Review;
use Magento\Sales\Model\OrderFactory;
use Magento\Wishlist\Controller\WishlistProviderInterface;

/**
 * Class AuthRequestBuilder
 * @package Forter\Forter\Model
 */
class AuthRequestBuilder
{
    /**
     *
     */
    const SHIPPING_METHOD_PREFIX = "Select Shipping Method - ";
    /**
     * @var RequestPrepare
     */
    private $requestPrepare;
    /**
     * @var CustomerPreper
     */
    private $customerPreper;
    /**
     * @var PaymentPreper
     */
    private $paymentPreper;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var CategoryFactory
     */
    private $categoryFactory;
    /**
     * @var Session
     */
    private $session;
    /**
     * @var Review
     */
    private $review;
    /**
     * @var WishlistProviderInterface
     */
    private $wishlistProvider;
    /**
     * @var Subscriber
     */
    private $subscriber;
    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * AuthRequestBuilder constructor.
     * @param RequestPrepare $requestPrepare
     * @param CustomerPreper $customerPreper
     * @param PaymentPreper $paymentPreper
     * @param OrderFactory $orderFactory
     * @param CategoryFactory $categoryFactory
     * @param Session $session
     * @param Review $review
     * @param WishlistProviderInterface $wishlistProvider
     * @param Subscriber $subscriber
     * @param Config $forterConfig
     */
    public function __construct(
        RequestPrepare $requestPrepare,
        CustomerPreper $customerPreper,
        PaymentPreper $paymentPreper,
        OrderFactory $orderFactory,
        CategoryFactory $categoryFactory,
        Session $session,
        Review $review,
        WishlistProviderInterface $wishlistProvider,
        Subscriber $subscriber,
        ForterConfig $forterConfig
    ) {
        $this->requestPrepare = $requestPrepare;
        $this->customerPreper = $customerPreper;
        $this->paymentPreper = $paymentPreper;
        $this->orderFactory = $orderFactory;
        $this->categoryFactory = $categoryFactory;
        $this->session = $session;
        $this->review = $review;
        $this->wishlistProvider = $wishlistProvider;
        $this->subscriber = $subscriber;
        $this->forterConfig = $forterConfig;
    }

    /**
     * @param $order
     * @return array
     */
    public function buildTransaction($order)
    {
        $data = [
        "orderId" => strval($order->getIncrementId()),
        "orderType" => "WEB",
        "timeSentToForter" => time()*1000,
        "checkoutTime" => time(),
        "additionalIdentifiers" => $this->requestPrepare->getAdditionalIdentifiers($order),
        "connectionInformation" => $this->requestPrepare->getConnectionInformation($order->getRemoteIp()),
        "totalAmount" => $this->requestPrepare->getTotalAmount($order),
        "cartItems" => $this->requestPrepare->generateCartItems($order),
        "primaryDeliveryDetails" => $this->customerPreper->getPrimaryDeliveryDetails($order),
        "primaryRecipient" => $this->customerPreper->getPrimaryRecipient($order),
        "accountOwner" => $this->customerPreper->getAccountOwnerInfo($order),
        "customerAccountData" => $this->customerPreper->getCustomerAccountData($order, null),
        "totalDiscount" => $this->requestPrepare->getTotalDiscount($order),
        "payment" => $this->paymentPreper->generatePaymentInfo($order)
      ];

        if ($this->forterConfig->isSandboxMode()) {
            $data['additionalInformation'] = [
              'debug' => $order->debug()
          ];
        }
        return $data;
    }
}
