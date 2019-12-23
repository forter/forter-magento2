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
use Forter\Forter\Model\RequestBuilder\BasicInfo as BasicInfoPrepere;
use Forter\Forter\Model\RequestBuilder\Cart as CartPrepere;
use Forter\Forter\Model\RequestBuilder\Customer as CustomerPrepere;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPrepere;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Customer\Model\Session;
use Magento\Newsletter\Model\Subscriber;
use Magento\Review\Model\Review;
use Magento\Sales\Model\OrderFactory;
use Magento\Wishlist\Controller\WishlistProviderInterface;

/**
 * Class Order
 * @package Forter\Forter\Model
 */
class Order
{
    /**
     *
     */
    const SHIPPING_METHOD_PREFIX = "Select Shipping Method - ";

    /**
     * @var PaymentPrepere
     */
    private $cartPrepere;
    /**
     * @var PaymentPrepere
     */
    private $basicInfoPrepare;
    /**
     * @var CustomerPrepere
     */
    private $customerPrepere;
    /**
     * @var PaymentPrepere
     */
    private $paymentPrepere;
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
     * Order constructor.
     * @param BasicInfoPrepare $basicInfoPrepare
     * @param CartPrepare  $cartPrepare
     * @param CustomerPrepere $customerPrepere
     * @param PaymentPrepere $paymentPrepere
     * @param OrderFactory $orderFactory
     * @param CategoryFactory $categoryFactory
     * @param Session $session
     * @param Review $review
     * @param WishlistProviderInterface $wishlistProvider
     * @param Subscriber $subscriber
     * @param Config $forterConfig
     */
    public function __construct(
        CartPrepere $cartPrepare,
        BasicInfoPrepere $basicInfoPrepare,
        CustomerPrepere $customerPrepere,
        PaymentPrepere $paymentPrepere,
        OrderFactory $orderFactory,
        CategoryFactory $categoryFactory,
        Session $session,
        Review $review,
        WishlistProviderInterface $wishlistProvider,
        Subscriber $subscriber,
        ForterConfig $forterConfig
    ) {
        $this->basicInfoPrepare = $basicInfoPrepare;
        $this->cartPrepare = $cartPrepare;
        $this->customerPrepere = $customerPrepere;
        $this->paymentPrepere = $paymentPrepere;
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
        "additionalIdentifiers" => $this->basicInfoPrepare->getAdditionalIdentifiers($order),
        "connectionInformation" => $this->basicInfoPrepare->getConnectionInformation($order->getRemoteIp()),
        "totalAmount" => $this->cartPrepare->getTotalAmount($order),
        "cartItems" => $this->cartPrepare->generateCartItems($order),
        "primaryDeliveryDetails" => $this->customerPrepere->getPrimaryDeliveryDetails($order),
        "primaryRecipient" => $this->customerPrepere->getPrimaryRecipient($order),
        "accountOwner" => $this->customerPrepere->getAccountOwnerInfo($order),
        "customerAccountData" => $this->customerPrepere->getCustomerAccountData($order, null),
        "totalDiscount" => $this->cartPrepare->getTotalDiscount($order),
        "payment" => $this->paymentPrepere->generatePaymentInfo($order)
      ];

        if ($this->forterConfig->isSandboxMode()) {
            $data['additionalInformation'] = [
              'debug' => $order->debug()
          ];
        }
        return $data;
    }
}
