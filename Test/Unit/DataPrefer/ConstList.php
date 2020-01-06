<?php

namespace Forter\Forter\Test\Unit\DataPrefer;

class ConstList
{
    const FORTER_COOKIE = "7cb7ee292dff4c87a43eaefe0d495c4b_1578229007705__UDF43_9ck";

    const CUSTOMER_LOCKED = true;
    const CUSTOMER_ADDRESS_BY_ID = null;
    const CUSTOMER_FIRST_NAME = "Linus";
    const CUSTOMER_LAST_NAME = "Torvalds";
    const CUSTOMER_NAME = "Linus Torvalds";
    const CUSTOMER_MIDDLE_NAME = "Miller";
    const CUSTOMER_PREFIX = "Dr.";
    const CUSTOMER_SUFFIX = "";
    const CUSTOMER_EMAIL = "lomus@maxima.org";
    const CUSTOMER_ID = 60;
    const CUSTOMER_CREATION_DATE = "16:08:12 05/08/03";

    const SHIPPING_ADDRESS_STREET = ["Sesame street 123."];
    const SHIPPING_ADDRESS_POSTCODE = 123456;
    const SHIPPING_ADDRESS_CITY = "Petah Tikva";
    const SHIPPING_ADDRESS_REGION = "California";
    const SHIPPING_ADDRESS_COUNTRYID = "US";
    const SHIPPING_ADDRESS_VATISVALID = 1;
    const SHIPPING_ADDRESS_TELEPHONE = "1123581321";
    const SHIPPING_ADDRESS_ID = 123;
    const SHIPPING_ADDRESS_COMPANY = "Apple";
    const SHIPPING_ADDRESS_ROLE = "SHIPPING";

    const BILLING_ADDRESS_ROLE = "BILLING";

    const STORE_NAME = "fakestore";
    const STORE_ID = 1;
    const STORE_URL = "magento2.local";

    const PAYMENT_TRANSACTIONID = "abcd1234";
    const PAYMENT_CCOWNER = "Donald J. Trump";
    const PAYMENT_CCTYPE = "ZIVA";
    const PAYMENT_CCLAST4 = 1337;
    const PAYMENT_CCEXPMONTH = '09';
    const PAYMENT_CCEXPYEAR = '2012';
    const PAYMENT_ECHECKBANKNAME = "Banksy";

    const PAYMENT_PROCESSOR_AUTHORIZATION_CODE = "0xc0de";
    const PAYMENT_PROCESSOR_RESPONSE_CODE = "0xdead";
    const PAYMENT_PROCESSOR_RESPONSE_TEXT = "Approved";
    const PAYMENT_AVS_STREET_ADDRESS_RESPONSE_CODE = "Hi!";
    const PAYMENT_AVS_POSTAL_CODE_RESPONSE_CODE = "Goodevening";
    const PAYMENT_METHOD = "braintree";
    const PAYMENT_CC_TRANS_ID = "wafgmyw478t";
    const PAYMENT_CC_BIN = "411111";
    const CURRENCYCODE = "ILS";

    const ORDER_REAL_ORDER_ID = "500";
    const ORDER_REMOTE_IP = "127.0.0.1";
    const ORDER_GRAND_TOTAL = 1337;
    const ORDER_METHOD = "Elimination";
    const COUPONCODE ="GO_FORTER_GO";
    const SHIPPINGMETHODTYPE = "PHYSICAL";
    const SHIPPINGMETHODNAME = "Free Shipping";
    const SHIPPINGAMOUNT = 15;
    const DISCOUNTAMOUNT = 15;
    const DISCOUNTDESCRIPTION = 'percent';

    const GUEST = "GUEST";
    const SUSPENDED = "SUSPENDED";

    const USER_AGENT = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.79 Safari/537.36";

    const REVIEW_COUNT = 3;

    const MODULE_ID = '1.0';
    const API_ID = '2.6';
    const SITE_ID = '194d494d2557';
    const SECRET_KEY = '30a0af07fe3414b731c094690223fa74927d7e17';

    const TIME_OUT_SETTINGS  = [
      "base_connection_timeout" => '1000',
      "base_request_timeout" => '2000',
      "max_connection_timeout" => '8000',
      "max_request_timeout" => '16000'
    ];

    const PRODUCT_ID = 4;
    const PRODUCT_NAME = 'name_test';
    const PRODUCT_QTY_ORDERED = 3;
    const PRODUCT_PRICE = 36;
    const PRODUCT_IS_VIRTUAL = false;
    const PRODUCT_GIFT_MESSAGE_AVAILABLE = true;
}
