<?php

namespace Forter\Forter\Test\Unit;

class Cart extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $mockBuilder = new ForterMockBuilder();

        $this->orderMock = $mockBuilder->buildOrderMock();

        $this->cartPrepere = $objectManager->getObject(
            \Forter\Forter\Model\RequestBuilder\Cart::class
        );
    }

    public function testGetTotalAmount()
    {
        $totalAmount = $this->cartPrepere->getTotalAmount($this->orderMock);
        $this->assertEquals($totalAmount, [
            "amountUSD" => null,
            "amountLocalCurrency" => ConstList::ORDER_GRAND_TOTAL,
            "currency" => ConstList::CURRENCYCODE
        ]);
    }

    public function testGetTotalDiscount()
    {
        $totalDiscount = $this->cartPrepere->getTotalDiscount($this->orderMock);
        $this->assertEquals($totalDiscount, [
          "couponCodeUsed" => ConstList::COUPONCODE,
          "couponDiscountAmount" => [
            "amountLocalCurrency" => ConstList::DISCOUNTAMOUNT,
            "currency" => ConstList::CURRENCYCODE
          ],
          "discountType" => ConstList::DISCOUNTDESCRIPTION
        ]);
    }
}
