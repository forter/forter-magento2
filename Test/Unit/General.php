<?php

namespace Forter\Forter\Test\Unit;

class General extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $mockBuilder = new ForterMockBuilder();

        $cookieManagerInterfaceMock = $mockBuilder->buildCookieManagerInterfaceMock();

        $this->orderMock = $mockBuilder->buildOrderMock();

        $this->basicinfo = $objectManager->getObject(
            \Forter\Forter\Model\RequestBuilder\BasicInfo::class,
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

        $connectionInformation = $this->basicinfo->getConnectionInformation($this->orderMock->getRemoteIp(), $headers);

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
