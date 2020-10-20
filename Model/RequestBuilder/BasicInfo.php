<?php
/**
 * Forter Payments For Magento 2
 * https://www.Forter.com/
 *
 * @category Forter
 * @package  Forter_Forter
 * @author   Girit-Interactive (https://www.girit-tech.com/)
 */

namespace Forter\Forter\Model\RequestBuilder;

use Magento\Framework\Stdlib\CookieManagerInterface;

/**
 * Class BasicInfo
 * @package Forter\Forter\Model\RequestBuilder
 */
class BasicInfo
{
    public function __construct(
        CookieManagerInterface $cookieManager
    ) {
        $this->cookieManager = $cookieManager;
    }

    /**
     * @param $remoteIp
     * @param $headers
     * @return array
     */
    public function getConnectionInformation($remoteIp, $headers)
    {
        return [
            "customerIP" => $remoteIp ? $remoteIp : $this->getIpFromOrder($remoteIp, $headers),
            "userAgent" => (is_array($headers) && array_key_exists("User-Agent", $headers)) ? substr($headers['User-Agent'], 0, 4000) : "",
            "forterTokenCookie" => $this->cookieManager->getCookie("forterToken") . "",
            "merchantDeviceIdentifier" => null,
            "fullHeaders" => substr(json_encode($headers) . "", 0, 4000)
        ];
    }

    /**
     * @param $order
     * @param $orderStage
     * @return array
     */
    public function getAdditionalIdentifiers($order, $orderStage)
    {
        $store = $order->getStore();
        $payment = $order->getPayment();

        return [
            'additionalOrderId' => $order->getId(),
            'paymentGatewayId' => $payment ? strval($payment->getTransactionId()) : "",
            'merchant' => [
              'merchantId' => $store->getId(),
              'merchantDomain' => $store->getUrl() . "",
              'merchantName' => $store->getName() . ""
            ],
            'magentoAdditionalOrderData' => [
                'magentoOrderStage' => $orderStage
            ]
        ];
    }

    /**
     * @param $remoteIp
     * @param $headers
     * @return false|mixed|string
     */
    public function getIpFromOrder($remoteIp, $headers)
    {
        $xForwardedFor = array_key_exists('X-Forwarded-For', $headers) ? $headers['X-Forwarded-For'] : '';
        $hasXForwardedFor = $xForwardedFor && strlen($xForwardedFor) > 0;
        // x-forwarded-for is a string that is formatted like "clientIp, proxyIp1, proxyIp2"
        // incase it exists take it else for for remoteIp
        if ($hasXForwardedFor) {
            $indexOfComa = strpos($xForwardedFor, ",");
            if ($indexOfComa === false) {
                return $xForwardedFor;
            }
            return substr($xForwardedFor, 0, $indexOfComa);
        }
        return $remoteIp;
    }
}
