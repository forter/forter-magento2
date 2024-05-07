<?php

namespace Forter\Forter\Model\ThirdParty\Stripe;

use Forter\Forter\Model\ForterLogger;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;

class StripePayment
{

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var ForterLogger
     */
    protected $forterLogger;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManagerInterface;

    public function __construct(
        Registry               $registry,
        ForterLogger           $forterLogger,
        ObjectManagerInterface $objectManagerInterface
    ) {
        $this->registry = $registry;
        $this->forterLogger = $forterLogger;
        $this->objectManagerInterface = $objectManagerInterface;
    }

    public function getPaymentData(\Magento\Sales\Model\Order $order)
    {
        try {
            if ($order->isEmpty()) {
                return false;
            }

            $paymentIntentID = $order->getPayment()->getLastTransId();

            if (empty($paymentIntentID)) {
                return false;
            }

            if (class_exists('\Stripe\PaymentIntent') == false) {
                return false;
            }

            $customCurlClient = false;

            if (class_exists(\Stripe\HttpClient\CurlClient::class) &&
                is_callable([\Stripe\ApiRequestor::class, 'setHttpClient'])
            ) {
                $curl = new \Stripe\HttpClient\CurlClient();
                $curl->setTimeout(2);
                $curl->setConnectTimeout(1);
                \Stripe\ApiRequestor::setHttpClient($curl);

                $customCurlClient = true;
            }

            if (class_exists('StripeIntegration\Payments\Model\Config')) {
                $this->objectManagerInterface->get('StripeIntegration\Payments\Model\Config')->getStripeClient();
            }

            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentID);

            if ($customCurlClient == true) {
                \Stripe\ApiRequestor::setHttpClient(null);
            }

            if (is_object($paymentIntent) && is_object($paymentIntent->charges) && is_array($paymentIntent->charges->data)) {
                $paymentData = array_pop($paymentIntent->charges->data);

                if (is_object($paymentData) == false) {
                    return false;
                }

                return $paymentData;
            }
        } catch (\Exception $e) {
            $this->forterLogger->forterConfig->log('No payment data was found on Stripe API for the current order ' . $e->getMessage(),'error');
        }
        return false;
    }
}
