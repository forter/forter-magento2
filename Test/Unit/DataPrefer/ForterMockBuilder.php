<?php

namespace Forter\Forter\Test\Unit\DataPrefer;

class ForterMockBuilder extends \PHPUnit\Framework\TestCase
{
    public function buildCustomerMock()
    {
        $customerMock = $this->getMockBuilder(\Magento\Customer\Model\Customer::class)
        ->disableOriginalConstructor()
        ->setMethods(["isCustomerLocked", "getAddressById", "getFirstname", "getLastname", "getEmail", "getId", "getCreatedAt"])
        ->getMock();
        $customerMock->method("isCustomerLocked")->willReturn(ConstList::CUSTOMER_LOCKED);
        $customerMock->method("getAddressById")->willReturn(ConstList::CUSTOMER_ADDRESS_BY_ID);
        $customerMock->method("getFirstname")->willReturn(ConstList::CUSTOMER_FIRST_NAME);
        $customerMock->method("getLastname")->willReturn(ConstList::CUSTOMER_LAST_NAME);
        $customerMock->method("getEmail")->willReturn(ConstList::CUSTOMER_EMAIL);
        $customerMock->method("getId")->willReturn(ConstList::CUSTOMER_ID);
        $customerMock->method("getCreatedAt")->willReturn(ConstList::CUSTOMER_CREATION_DATE);

        return $customerMock;
    }

    /**
     * Gets partners model mock
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\Magento\Framework\HTTP\Client\Curl
     */
    public function getCurlMock($methods)
    {
        return $this->createPartialMock(\Magento\Framework\HTTP\Client\Curl::class, $methods, []);
    }

    public function buildReviewMock()
    {
        $resourceCollectionMock = $this->getMockBuilder(\Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection::class)
            ->disableOriginalConstructor()
            ->setMethods(["addStoreFilter", "addCustomerFilter", "count"])
            ->getMock();
        $resourceCollectionMock->method('addStoreFilter')->willReturn($resourceCollectionMock);
        $resourceCollectionMock->method('addCustomerFilter')->willReturn($resourceCollectionMock);
        $resourceCollectionMock->method('count')->willReturn(ConstList::REVIEW_COUNT);

        $reviewMock = $this->getMockBuilder(\Magento\Review\Model\Review::class)
            ->disableOriginalConstructor()
            ->setMethods(["getResourceCollection"])
            ->getMock();
        $reviewMock->method('getResourceCollection')->willReturn($resourceCollectionMock);

        return $reviewMock;
    }

    private function buildShippingAddressMock()
    {
        $shippingAddressMock = $this->getMockBuilder(\Magento\Sales\Model\Order\Address::class)
            ->disableOriginalConstructor()
            ->getMock();
        $shippingAddressMock->method('getStreet')->willReturn(ConstList::SHIPPING_ADDRESS_STREET);
        $shippingAddressMock->method('getPostCode')->willReturn(ConstList::SHIPPING_ADDRESS_POSTCODE);
        $shippingAddressMock->method('getCity')->willReturn(ConstList::SHIPPING_ADDRESS_CITY);
        $shippingAddressMock->method('getRegion')->willReturn(ConstList::SHIPPING_ADDRESS_REGION);
        $shippingAddressMock->method('getCountryId')->willReturn(ConstList::SHIPPING_ADDRESS_COUNTRYID);
        $shippingAddressMock->method('getVatIsValid')->willReturn(ConstList::SHIPPING_ADDRESS_VATISVALID);
        $shippingAddressMock->method('getTelephone')->willReturn(ConstList::SHIPPING_ADDRESS_TELEPHONE);
        $shippingAddressMock->method('getId')->willReturn(ConstList::SHIPPING_ADDRESS_ID);
        $shippingAddressMock->method("getFirstName")->willReturn(ConstList::CUSTOMER_FIRST_NAME);
        $shippingAddressMock->method("getLastName")->willReturn(ConstList::CUSTOMER_LAST_NAME);
        $shippingAddressMock->method("getName")->willReturn(ConstList::CUSTOMER_NAME);
        $shippingAddressMock->method("getMiddleName")->willReturn(ConstList::CUSTOMER_MIDDLE_NAME);
        $shippingAddressMock->method("getPrefix")->willReturn(ConstList::CUSTOMER_PREFIX);
        $shippingAddressMock->method("getSuffix")->willReturn(ConstList::CUSTOMER_SUFFIX);
        $shippingAddressMock->method("getEmail")->willReturn(ConstList::CUSTOMER_EMAIL);
        $shippingAddressMock->method("getCompany")->willReturn(ConstList::SHIPPING_ADDRESS_COMPANY);

        return $shippingAddressMock;
    }

    private function buildStoreMock()
    {
        $storeMock = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->getMock();
        $storeMock->method("getName")->willReturn(ConstList::STORE_NAME);
        $storeMock->method("getId")->willReturn(ConstList::STORE_ID);
        $storeMock->method("getUrl")->willReturn(ConstList::STORE_URL);

        return $storeMock;
    }

    public function buildForterConfigMock()
    {
        $storeMock = $this->getMockBuilder(\Forter\Forter\Model\Config::class)
            ->disableOriginalConstructor()
            ->setMethods(["getStoreId","isEnabled","getTimeOutSettings","getSiteId","getApiVersion","getModuleVersion","getSecretKey","log"])
            ->getMock();

        $storeMock->method("getStoreId")->willReturn(ConstList::STORE_ID);
        $storeMock->method("isEnabled")->willReturn(true);
        $storeMock->method("getTimeOutSettings")->willReturn(ConstList::TIME_OUT_SETTINGS);
        $storeMock->method("getSiteId")->willReturn(ConstList::SITE_ID);
        $storeMock->method("getApiVersion")->willReturn(ConstList::API_ID);
        $storeMock->method("getModuleVersion")->willReturn(ConstList::MODULE_ID);
        $storeMock->method("getModuleVersion")->willReturn(ConstList::SECRET_KEY);
        $storeMock->method("log")->willReturn(true);

        return $storeMock;
    }

    private function buildPaymentMock()
    {
        $paymentMock = $this->getMockBuilder(\Magento\Braintree\Block\Payment::class)
            ->disableOriginalConstructor()
            ->setMethods(["getTransactionId", "getCcOwner", "getCcType", "getCcLast4", "getCcExpMonth",
                "getCcExpYear", "getEcheckBankName", "getMethod", "getCcTransId", "getCcNumberEnc"])
            ->getMock();
        $paymentMock->method("getTransactionId")->willReturn(ConstList::PAYMENT_TRANSACTIONID);
        $paymentMock->method("getCcOwner")->willReturn(ConstList::PAYMENT_CCOWNER);
        $paymentMock->method("getCcType")->willReturn(ConstList::PAYMENT_CCTYPE);
        $paymentMock->method("getCcLast4")->willReturn(ConstList::PAYMENT_CCLAST4);
        $paymentMock->method("getCcExpMonth")->willReturn(ConstList::PAYMENT_CCEXPMONTH);
        $paymentMock->method("getCcExpYear")->willReturn(ConstList::PAYMENT_CCEXPYEAR);
        $paymentMock->method("getEcheckBankName")->willReturn(ConstList::PAYMENT_ECHECKBANKNAME);
        $paymentMock->setAdditionalInformation([
            "processorAuthorizationCode" => ConstList::PAYMENT_PROCESSOR_AUTHORIZATION_CODE,
            "processorResponseCode" => ConstList::PAYMENT_PROCESSOR_RESPONSE_CODE,
            "processorResponseText" => ConstList::PAYMENT_PROCESSOR_RESPONSE_TEXT,
            "avsStreetAddressResponseCode" => ConstList::PAYMENT_AVS_STREET_ADDRESS_RESPONSE_CODE,
            "avsPostalCodeResponseCode" => ConstList::PAYMENT_AVS_POSTAL_CODE_RESPONSE_CODE
        ]);
        $paymentMock->method("getMethod")->willReturn(ConstList::PAYMENT_METHOD);
        $paymentMock->method("getCcTransId")->willReturn(ConstList::PAYMENT_CC_TRANS_ID);

        return $paymentMock;
    }

    private function buildOrderCurrencyMock()
    {
        $orderCurrencyMock = $this->getMockBuilder(\Magento\Directory\Model\Currency::class)
            ->disableOriginalConstructor()
            ->setMethods(["getCurrencyCode"])
            ->getMock();

        $orderCurrencyMock->method("getCurrencyCode")->willReturn(ConstList::CURRENCYCODE);

        return $orderCurrencyMock;
    }

    public function buildSessionMock($customerMock)
    {
        $sessionMock = $this->getMockBuilder(\Magento\Customer\Model\Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $sessionMock->method('getCustomer')->willReturn($customerMock);
        $sessionMock->method('getCustomerData')->willReturn($customerMock);

        return $sessionMock;
    }

    public function buildSubscriberMock()
    {
        $subscriberMock = $this->getMockBuilder(\Magento\Newsletter\Model\Subscriber::class)
            ->disableOriginalConstructor()
            ->setMethods(["loadByCustomerId", "isSubscribed"])
            ->getMock();
        $subscriberMock->method("loadByCustomerId")->willReturn($subscriberMock);
        $subscriberMock->method("isSubscribed")->willReturn(false);

        return $subscriberMock;
    }

    public function buildCookieManagerInterfaceMock()
    {
        $cookieManagerInterfaceMock = $this->getMockBuilder(\Magento\Framework\Stdlib\CookieManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cookieManagerInterfaceMock->method('getCookie')->willReturn(ConstList::FORTER_COOKIE);

        return $cookieManagerInterfaceMock;
    }

    public function buildOrderFactoryMock()
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

        return $orderFactoryMock;
    }

    public function buildOrderMock()
    {
        $orderMock = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderMock->method("getStore")->willReturn($this->buildStoreMock());
        $orderMock->method("getPayment")->willReturn($this->buildPaymentMock());
        $orderMock->method("getOrderCurrency")->willReturn($this->buildOrderCurrencyMock());
        $orderMock->method("getRealOrderId")->willReturn(ConstList::ORDER_REAL_ORDER_ID);
        $orderMock->method("getRemoteIp")->willReturn(ConstList::ORDER_REMOTE_IP);
        $orderMock->method("getBillingAddress")->willReturn($this->buildShippingAddressMock());
        $orderMock->method("getShippingAddress")->willReturn($this->buildShippingAddressMock());
        $orderMock->method("getCustomerFirstName")->willReturn(ConstList::CUSTOMER_FIRST_NAME);
        $orderMock->method("getCustomerLastName")->willReturn(ConstList::CUSTOMER_LAST_NAME);
        $orderMock->method("getCustomerName")->willReturn(ConstList::CUSTOMER_NAME);
        $orderMock->method("getCustomerMiddleName")->willReturn(ConstList::CUSTOMER_MIDDLE_NAME);
        $orderMock->method("getCustomerPrefix")->willReturn(ConstList::CUSTOMER_PREFIX);
        $orderMock->method("getCustomerSuffix")->willReturn(ConstList::CUSTOMER_SUFFIX);
        $orderMock->method("getCustomerEmail")->willReturn(ConstList::CUSTOMER_EMAIL);
        $orderMock->method("getGrandTotal")->willReturn(ConstList::ORDER_GRAND_TOTAL);
        $orderMock->method("getShippingMethod")->willReturn(ConstList::SHIPPINGMETHODTYPE);
        $orderMock->method("getShippingDescription")->willReturn(ConstList::SHIPPINGMETHODNAME);
        $orderMock->method("getShippingAmount")->willReturn(ConstList::SHIPPINGAMOUNT);
        $orderMock->method("getCouponCode")->willReturn(ConstList::COUPONCODE);
        $orderMock->method("getDiscountAmount")->willReturn(ConstList::DISCOUNTAMOUNT);
        $orderMock->method("getDiscountDescription")->willReturn(ConstList::DISCOUNTDESCRIPTION);

        $items = [
            new \Magento\Framework\DataObject(
                [
                  'id' => ConstList::PRODUCT_ID,
                  'name' => ConstList::PRODUCT_NAME,
                  'qty_ordered' => ConstList::PRODUCT_QTY_ORDERED,
                  'price' => ConstList::PRODUCT_PRICE,
                  'is_virtual' => ConstList::PRODUCT_IS_VIRTUAL,
                  'gift_message_available' => ConstList::PRODUCT_GIFT_MESSAGE_AVAILABLE
                ]
            )
        ];

        $orderMock->expects($this->once())->method('getAllItems')->will($this->returnValue($items));

        return $orderMock;
    }
}
