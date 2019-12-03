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
use Forter\Forter\Model\RequestBuilder\RequestPrepare;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Customer\Model\Session;
use Magento\Newsletter\Model\Subscriber;
use Magento\Review\Model\Review;
use Magento\Sales\Model\OrderFactory;
use Magento\Wishlist\Controller\WishlistProviderInterface;

class AuthRequestBuilder
{
    const SHIPPING_METHOD_PREFIX = "Select Shipping Method - ";

    public function __construct(
        RequestPrepare $requestPrepare,
        OrderFactory $orderFactory,
        CategoryFactory $categoryFactory,
        Session $session,
        Review $review,
        WishlistProviderInterface $wishlistProvider,
        Subscriber $subscriber,
        ForterConfig $forterConfig
    ) {
        $this->requestPrepare = $requestPrepare;
        $this->orderFactory = $orderFactory;
        $this->categoryFactory = $categoryFactory;
        $this->session = $session;
        $this->review = $review;
        $this->wishlistProvider = $wishlistProvider;
        $this->subscriber = $subscriber;
        $this->forterConfig = $forterConfig;
    }

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
        "primaryDeliveryDetails" => $this->requestPrepare->getPrimaryDeliveryDetails($order),
        "primaryRecipient" => $this->requestPrepare->getPrimaryRecipient($order),
        "accountOwner" => $this->requestPrepare->getAccountOwnerInfo($order),
        "customerAccountData" => $this->requestPrepare->getCustomerAccountData($order, null),
        "totalDiscount" => $this->requestPrepare->getTotalDiscount($order),
        "payment" => $this->generatePaymentInfo($order)
      ];

        if ($this->forterConfig->isSandboxMode()) {
            $data['additionalInformation'] = [
              'debug' => $order->debug()
          ];
        }
        return $data;
    }

    public function generatePaymentInfo($order)
    {
        $billing_address = $order->getBillingAddress();
        $payment = $order->getPayment();

        if (!$payment) {
            return [ [] ];
        }

        //$paymentMethodInfo = $this->getSpecificPaymentMethodInfo($order);

        $paymentData = [];

        // If paypal:
        if (strpos($payment->getMethod(), 'paypal') !== false) {
            $paymentData["paypal"] = [
                  "payerId" => $payment->getAdditionalInformation("paypal_payer_id"),
                  "payerEmail" => $payment->getAdditionalInformation("paypal_payer_email"),
                  "payerStatus" => $payment->getAdditionalInformation("paypal_payer_status"),
                  "payerAddressStatus" => $payment->getAdditionalInformation("paypal_address_status"),
                  "paymentMethod" => $payment->getMethod(),
                  "paymentStatus" => $payment->getAdditionalInformation("paypal_payment_status"),
                  "protectionEligibility" => $payment->getAdditionalInformation("paypal_protection_eligibility"),
                  "correlationId" => $payment->getAdditionalInformation("paypal_correlation_id"),
                  "checkoutToken" => $payment->getAdditionalInformation("paypal_express_checkout_token"),
                  "paymentGatewayData" => [
                      "gatewayName" => $payment->getMethod(),
                      "gatewayTransactionId" => $payment->getTransactionId(),
                  ],
                  "fullPaypalResponsePayload" => $payment->getAdditionalInformation()
          ];
        }
        /*else if ($paymentMethodInfo['cc_last4']) {
            $paymentData["creditCard"] = [
                "nameOnCard" => $paymentMethodInfo['cc_owner'],
                "cardBrand" => $paymentMethodInfo['credit_card_brand'],
                "bin" => $paymentMethodInfo['cc_bin'],
                "lastFourDigits" => $paymentMethodInfo['cc_last4'],
                "expirationMonth" => $paymentMethodInfo['cc_exp_month'],
                "expirationYear" => $paymentMethodInfo['cc_exp_year'],
                "countryOfIssuance" => $payment->getData("country_of_issuance"),
                "cardBank" => $payment->getEcheckBankName(),
                "verificationResults" => [
                    "cvvResult" => array_key_exists('cvv_result_code', $paymentMethodInfo) ? $paymentMethodInfo['cvv_result_code'] : null,
                    "authorizationCode" => $paymentMethodInfo['auth_code'],
                    "processorResponseCode" => $payment->getAdditionalInformation("processorResponseCode"),
                    "processorResponseText" => $payment->getAdditionalInformation("processorResponseText"),
                    "avsStreetResult" => array_key_exists('avs_street_code', $paymentMethodInfo) ? $paymentMethodInfo['avs_street_code'] : null,
                    "avsZipResult" => array_key_exists('avs_zip_code', $paymentMethodInfo) ? $paymentMethodInfo['avs_zip_code'] : null,
                    "avsFullResult" => array_key_exists('avs_result_code', $paymentMethodInfo) ? $paymentMethodInfo['avs_result_code'] : null
                ],
                "paymentGatewayData" => [
                    "gatewayName" => $payment->getMethod(),
                    "gatewayTransactionId" => $payment->getCcTransId(),
                ],
                "fullResponsePayload" => $payment->getAdditionalInformation()
            ];
        }*/

        $billingDetails = [];
        $billingDetails["personalDetails"] = [
          "firstName" => $billing_address->getFirstName(),
          "lastName" => $billing_address->getLastName(),
          "middleInitials" => $billing_address->getMiddleName(),
          "prefix" => $billing_address->getPrefix(),
          "suffix" => $billing_address->getSuffix()
      ];

        if ($billing_address) {
            $billingDetails["address"] = $this->getAddressData($billing_address);
            $billingDetails["address"]["addressRole"] = "BILLING";

            if ($billing_address->getTelephone()) {
                $billingDetails["phone"] = [
                  [
                      "phone" => $billing_address->getTelephone(),
                      "phoneRole" => "BILLING"
                  ]
              ];
            }
        }

        $paymentData["billingDetails"] = $billingDetails;
        $paymentData["paymentMethodNickname"] = $payment->getMethod();
        $paymentData["amount"] = [
          "amountLocalCurrency" => strval($order->getGrandTotal()),
          "currency" => $order->getOrderCurrency()->getCurrencyCode()
      ];

        return [$paymentData];
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
}
