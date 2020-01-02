<?php

namespace Forter\Forter\Test\Unit;

class Customer extends \PHPUnit\Framework\TestCase
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

        $this->customerPrepere = $objectManager->getObject(
            \Forter\Forter\Model\RequestBuilder\Customer::class,
            [
                "session" => $sessionMock,
                "orderFactory" => $orderFactoryMock,
                "review" => $reviewMock,
                "subscriber" => $subscriberMock
            ]
        );
    }

    public function testGetCustomerAccountData()
    {
        $this->orderMock->method("getCustomerIsGuest")->willReturn(true);
        $this->assertEquals(
            $this->customerPrepere->getCustomerAccountData($this->orderMock, $this->customerMock),
            [
              "status" => ConstList::SUSPENDED,
              "customerEngagement" => [
                'wishlist' => [
                  "inUse" => false,
                  "itemInListCount" => 0
                ],
                'reviewsWritten' => [
                  "inUse" => true,
                  "itemInListCount" => 3
                ],
                'newsletters' => [
                  "inUse" => false
                ]
              ]
            ]
        );
    }

    public function testGetPrimaryRecipient()
    {
        $primaryRecipient = $this->customerPrepere->getPrimaryRecipient($this->orderMock);

        $this->assertEquals(
            $primaryRecipient,
            [
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
                            ]
        );
    }

    public function testGetAccountOwnerInfo()
    {
        $orderFactoryMock = $this->getMockBuilder(\Magento\Sales\Model\OrderFactory::class)
        ->disableOriginalConstructor()
        ->setMethods(["getAllIds", "addFieldToFilter", "getCollection", "create", "getTotalCount"])
        ->getMock();

        $orderFactoryMock->method("getTotalCount")->willReturn(0);
        $orderFactoryMock->method("getAllIds")->willReturn([]);
        $orderFactoryMock->method("addFieldToFilter")->willReturn($orderFactoryMock);
        $orderFactoryMock->method("getCollection")->willReturn($orderFactoryMock);
        $orderFactoryMock->method("create")->willReturn($orderFactoryMock);

        $accountOwnerInfo = $this->customerPrepere->getAccountOwnerInfo($this->orderMock, $orderFactoryMock);

        $this->assertEquals($accountOwnerInfo, [
        "firstName" => ConstList::CUSTOMER_FIRST_NAME,
        "lastName" => ConstList::CUSTOMER_LAST_NAME,
        "email" => ConstList::CUSTOMER_EMAIL,
        "accountId" => ConstList::CUSTOMER_ID,
        "created" => strtotime(ConstList::CUSTOMER_CREATION_DATE),
        "pastOrdersCount" => 0,
        "pastOrdersSum" => 0
      ]);
    }

    public function testGetPrimaryDeliveryDetails()
    {
        $primaryDeliveryDetails = $this->customerPrepere->getPrimaryDeliveryDetails($this->orderMock);
        $this->assertEquals($primaryDeliveryDetails, [
          "deliveryType" => ConstList::SHIPPINGMETHODTYPE,
          "deliveryMethod" => ConstList::SHIPPINGMETHODNAME,
          "deliveryPrice" => [
            "amountLocalCurrency" => ConstList::SHIPPINGAMOUNT,
            "currency" => ConstList::CURRENCYCODE
          ]
        ]);
    }
}
