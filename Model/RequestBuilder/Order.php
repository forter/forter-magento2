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
use Forter\Forter\Model\RequestBuilder\GiftCard as GiftCardPrepere;
use Forter\Forter\Model\RequestBuilder\Payment as PaymentPrepere;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\RequestInterface;
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
     * @var GiftCardPrepere
     */
    protected $giftCardPrepere;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var CartPrepare
     */
    protected $cartPrepare;

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
     * @param GiftCardPrepere $giftCardPrepere
     */
    public function __construct(
        RequestInterface $request,
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
        ForterConfig $forterConfig,
        GiftCardPrepere $giftCardPrepere
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
        $this->request = $request;
        $this->giftCardPrepere;
    }

    /**
     * @param $order
     * @param $orderStage
     * @return array
     */
    public function buildTransaction($order, $orderStage)
    {
        $data = [
            "orderId" => strval($order->getIncrementId()),
            "orderType" => "WEB",
            "timeSentToForter" => time()*1000,
            "checkoutTime" => time(),
            "additionalIdentifiers" => $this->basicInfoPrepare->getAdditionalIdentifiers($order, $orderStage),
            "totalAmount" => $this->cartPrepare->getTotalAmount($order),
            "cartItems" => $this->cartPrepare->generateCartItems($order),
            "primaryDeliveryDetails" => $this->customerPrepere->getPrimaryDeliveryDetails($order),
            "primaryRecipient" => $this->customerPrepere->getPrimaryRecipient($order),
            "accountOwner" => $this->customerPrepere->getAccountOwnerInfo($order),
            "customerAccountData" => $this->customerPrepere->getCustomerAccountData($order),
            "totalDiscount" => $this->cartPrepare->getTotalDiscount($order),
            "payment" => $this->paymentPrepere->generatePaymentInfo($order)
        ];

        if ($this->giftCardPrepere) {
            $data['primaryRecipient']["personalDetails"] = $this->giftCardPrepere->getGiftCardPrimaryRecipient($order);
        }

        //If phone order - get forter client number (forter_web_id)
        if (($forterWebId = $this->request->getPost('forter_web_id'))) {
            $data['orderType'] = "PHONE";
            $data['phoneOrderInformation'] = [
                "customerWebId" => $forterWebId
            ];
        } else {
            //If not a phone order - add the connectionInformation:
            $data['connectionInformation'] = $order->getPayment()->getAdditionalInformation('forter_client_details');
        }

        if ($this->forterConfig->isSandboxMode()) {
            $data['additionalInformation'] = [
              'debug' => $order->debug()
            ];
        }

        return $data;
    }
}
