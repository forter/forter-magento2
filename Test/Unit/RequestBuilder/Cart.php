<?php

namespace Forter\Forter\Test\Unit\RequestBuilder;

use Forter\Forter\Model\RequestBuilder\Cart as CartPrepare;
use Forter\Forter\Test\Unit\DataPrefer\ConstList;
use Forter\Forter\Test\Unit\DataPrefer\ForterMockBuilder as ForterMock;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class Cart extends TestCase
{
    protected function setUp()
    {
        $objectManager = new ObjectManager($this);
        $mockBuilder = new ForterMock();

        $this->orderMock = $mockBuilder->buildOrderMock();

        $this->cartPrepere = $objectManager->getObject(CartPrepare::class);
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

    public function testGenerateCartItems()
    {
        $response = $this->cartPrepere->generateCartItems($this->orderMock);
        $this->assertEquals($response, [[
          "basicItemData" => [
            "price" => [
              "amountLocalCurrency" => "36",
              "currency" => "ILS"
            ],
            "value" => [
              "amountLocalCurrency" => "36",
              "currency" => "ILS"
            ],
            "productId" => null,
            "name" =>'name_test',
            "type" => "TANGIBLE",
            "quantity" => 3,
            "category" => null
          ],
          "itemSpecificData" => [
            "physicalGoods" => [
              "wrapAsGift" => true
            ]
          ]
        ]]);
    }
}
