<?php

namespace Forter\Forter\Test\Unit;

use Forter\Forter\Model\RequestBuilder\BasicInfo;
use Forter\Forter\Model\RequestBuilder\Cart;
use Forter\Forter\Model\RequestBuilder\Customer;
use Forter\Forter\Model\RequestBuilder\Order as OrderPrepere;
use Forter\Forter\Model\RequestBuilder\Payment;
use Forter\Forter\Model\RequestBuilder\Payment\PaymentMethods;
use Forter\Forter\Test\Unit\DataPrefer\ForterMockBuilder as ForterMock;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class Order extends TestCase
{
    protected function setUp()
    {
        $objectManager = new ObjectManager($this);
        $mockBuilder = new ForterMock();

        $this->orderMock = $mockBuilder->buildOrderMock();

        $cookieManagerInterfaceMock = $mockBuilder->buildCookieManagerInterfaceMock();

        $this->customerMock = $mockBuilder->buildCustomerMock();
        $sessionMock = $mockBuilder->buildSessionMock($this->customerMock);
        $orderFactoryMock = $mockBuilder->buildOrderFactoryMock();
        $reviewMock = $mockBuilder->buildReviewMock();
        $subscriberMock = $mockBuilder->buildSubscriberMock();

        $this->paymentMethods = $objectManager->getObject(PaymentMethods::class);

        $this->customerPrepere = $objectManager->getObject(
            Customer::class,
            [
              "session" => $sessionMock,
              "orderFactory" => $orderFactoryMock,
              "review" => $reviewMock,
              "subscriber" => $subscriberMock
          ]
        );

        $this->paymentPrepere = $objectManager->getObject(
            Payment::class,
            [
            "customerPreper" => $this->customerPrepere,
            "paymentMethods" => $this->paymentMethods

          ]
        );

        $this->basicInfoPrepare = $objectManager->getObject(
            BasicInfo::class,
            [
                "cookieManager" => $cookieManagerInterfaceMock
            ]
        );

        $this->cartPrepere = $objectManager->getObject(Cart::class);

        $this->orderPrepere = $objectManager->getObject(
            OrderPrepere::class,
            [
              'customerPrepere' => $this->customerPrepere,
              'paymentPrepere' => $this->paymentPrepere,
              'cartPrepare' => $this->cartPrepere,
              'basicInfoPrepare' =>$this->basicInfoPrepare
            ]
        );
    }

    /**
     * @param $order
     * @return array
     */
    public function testBuildTransaction()
    {
        $response = $this->orderPrepere->buildTransaction($this->orderMock, 'AFTER_PAYMENT_ACTION');

        $this->assertEquals(isset($response['orderId']), true);
        $this->assertEquals(isset($response['orderType']), true);
        $this->assertEquals(isset($response['timeSentToForter']), true);
        $this->assertEquals(isset($response['checkoutTime']), true);
        $this->assertEquals(isset($response['additionalIdentifiers']), true);
        $this->assertEquals(isset($response['connectionInformation']), true);
        $this->assertEquals(isset($response['totalAmount']), true);
        $this->assertEquals(isset($response['cartItems']), true);
        $this->assertEquals(isset($response['primaryDeliveryDetails']), true);
        $this->assertEquals(isset($response['primaryRecipient']), true);
        $this->assertEquals(isset($response['accountOwner']), true);
        $this->assertEquals(isset($response['customerAccountData']), true);
        $this->assertEquals(isset($response['totalDiscount']), true);
        $this->assertEquals(isset($response['payment']), true);
    }
}
