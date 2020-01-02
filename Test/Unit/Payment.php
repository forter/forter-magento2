<?php

namespace Forter\Forter\Test\Unit;

class Payment extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $mockBuilder = new ForterMockBuilder();
        $this->customerMock = $mockBuilder->buildCustomerMock();
        $sessionMock = $mockBuilder->buildSessionMock($this->customerMock);
        $orderFactoryMock = $mockBuilder->buildOrderFactoryMock();
        $reviewMock = $mockBuilder->buildReviewMock();
        $subscriberMock = $mockBuilder->buildSubscriberMock();

        $this->orderMock = $mockBuilder->buildOrderMock();

        $this->paymentMethods = $objectManager->getObject(
            \Forter\Forter\Model\RequestBuilder\Payment\PaymentMethods::class
        );

        $this->customerPrepere = $objectManager->getObject(
            \Forter\Forter\Model\RequestBuilder\Customer::class,
            [
              "session" => $sessionMock,
              "orderFactory" => $orderFactoryMock,
              "review" => $reviewMock,
              "subscriber" => $subscriberMock
          ]
        );

        $this->paymentPrepere = $objectManager->getObject(
            \Forter\Forter\Model\RequestBuilder\Payment::class,
            [
            "customerPreper" => $this->customerPrepere,
            "paymentMethods" => $this->paymentMethods

          ]
        );
    }

    public function testGeneratePaymentInfo()
    {
        $paymentInfo = $this->paymentPrepere->generatePaymentInfo($this->orderMock);
        $this->assertEquals(
            $paymentInfo[0],
            [
          "creditCard" => [
            "nameOnCard" => ConstList::PAYMENT_CCOWNER,
            "cardBrand" => ConstList::PAYMENT_CCTYPE,
            "bin" => null,
            "lastFourDigits" => ConstList::PAYMENT_CCLAST4,
            "expirationMonth" => ConstList::PAYMENT_CCEXPMONTH,
            "expirationYear" => ConstList::PAYMENT_CCEXPYEAR,
            "countryOfIssuance" => null,
            "cardBank" => ConstList::PAYMENT_ECHECKBANKNAME,
            "verificationResults" => [
              "cvvResult" => null,
              "authorizationCode" => ConstList::PAYMENT_PROCESSOR_AUTHORIZATION_CODE,
              "processorResponseCode" => ConstList::PAYMENT_PROCESSOR_RESPONSE_CODE,
              "processorResponseText" => ConstList::PAYMENT_PROCESSOR_RESPONSE_TEXT,
              "avsStreetResult" => ConstList::PAYMENT_AVS_STREET_ADDRESS_RESPONSE_CODE,
              "avsZipResult" => ConstList::PAYMENT_AVS_POSTAL_CODE_RESPONSE_CODE,
              "avsFullResult" => null
            ],
            "paymentGatewayData" => [
              "gatewayName" => ConstList::PAYMENT_METHOD,
              "gatewayTransactionId" => ConstList::PAYMENT_CC_TRANS_ID
            ],
            "fullResponsePayload" => [
                "processorAuthorizationCode" => ConstList::PAYMENT_PROCESSOR_AUTHORIZATION_CODE,
                "processorResponseCode" => ConstList::PAYMENT_PROCESSOR_RESPONSE_CODE,
                "processorResponseText" => ConstList::PAYMENT_PROCESSOR_RESPONSE_TEXT,
                "avsStreetAddressResponseCode" => ConstList::PAYMENT_AVS_STREET_ADDRESS_RESPONSE_CODE,
                "avsPostalCodeResponseCode" => ConstList::PAYMENT_AVS_POSTAL_CODE_RESPONSE_CODE
            ]
          ],
          "billingDetails" => [
            "personalDetails" => [
              "firstName" => ConstList::CUSTOMER_FIRST_NAME,
              "lastName" => ConstList::CUSTOMER_LAST_NAME,
              "email" => ConstList::CUSTOMER_EMAIL
            ],
            "address" => [
              "address1" => ConstList::SHIPPING_ADDRESS_STREET[0],
                "address2" => null,
                "zip" => ConstList::SHIPPING_ADDRESS_POSTCODE,
                "city" => ConstList::SHIPPING_ADDRESS_CITY,
                "region" => ConstList::SHIPPING_ADDRESS_REGION,
                "country" => ConstList::SHIPPING_ADDRESS_COUNTRYID,
                "company" => ConstList::SHIPPING_ADDRESS_COMPANY,
                "savedData" => [
                  "usedSavedData" => false,
                  "choseToSaveData" => false
                ]
              ],
              "phone" => [
                [
                  "phone" => ConstList::SHIPPING_ADDRESS_TELEPHONE
                ]
              ]
            ],
            "paymentMethodNickname" => ConstList::PAYMENT_METHOD,
            "amount" => [
              "amountLocalCurrency" => ConstList::ORDER_GRAND_TOTAL,
              "currency" => ConstList::CURRENCYCODE
            ]
          ]
        );
    }
}
