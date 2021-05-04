<?php

namespace Forter\Forter\Test\Unit\RequestBuilder;

use Forter\Forter\Model\RequestBuilder\BasicInfo;
use Forter\Forter\Test\Unit\DataPrefer\ConstList;
use Forter\Forter\Test\Unit\DataPrefer\ForterMockBuilder as ForterMock;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

class General extends TestCase
{
    protected function setUp()
    {
        $objectManager = new ObjectManager($this);
        $mockBuilder = new ForterMock();

        $cookieManagerInterfaceMock = $mockBuilder->buildCookieManagerInterfaceMock();

        $this->orderMock = $mockBuilder->buildOrderMock();

        $this->basicinfo = $objectManager->getObject(
            BasicInfo::class,
            [
                "cookieManager" => $cookieManagerInterfaceMock
            ]
        );
    }

    public function testGenerateConnectionInformation()
    {
        $headers = [
          "User-Agent" => ConstList::USER_AGENT,
        ];

        $connectionInformation = $this->basicinfo->getConnectionInformation($this->orderMock->getRemoteIp());

        $this->assertEquals($connectionInformation, [
          "customerIP" => ConstList::ORDER_REMOTE_IP,
          "userAgent" => ConstList::USER_AGENT,
          "forterTokenCookie" => ConstList::FORTER_COOKIE,
          "merchantDeviceIdentifier" => null,
          "fullHeaders" => json_encode($headers)
        ]);
    }

    public function testGetIpFromOrder()
    {
        $headers = getallheaders();
        $ipFromOrder = $this->basicinfo->getIpFromOrder($this->orderMock->getRemoteIp(), $headers);
        $this->assertEquals(
            $ipFromOrder,
            ConstList::ORDER_REMOTE_IP
        );
    }

    public function testGenerateAdditionalIdentifiers()
    {
        $additionalIdentifiers = $this->basicinfo->getAdditionalIdentifiers($this->orderMock);
        $this->assertEquals(
            $additionalIdentifiers,
            [
            "additionalOrderId" => ConstList::ORDER_REAL_ORDER_ID,
                "paymentGatewayId" => ConstList::PAYMENT_TRANSACTIONID,
                "merchant" => [
                    "merchantId" => ConstList::STORE_ID,
                    "merchantDomain" => ConstList::STORE_URL,
                    "merchantName" => ConstList::STORE_NAME
                ]
            ]
        );
    }
}
