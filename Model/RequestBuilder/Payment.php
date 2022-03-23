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

use Forter\Forter\Model\RequestBuilder\Customer as CustomerPreper;
use Forter\Forter\Model\RequestBuilder\Payment\PaymentMethods;
use Forter\Forter\Model\Mapper\PaymentHandler;
use Forter\Forter\Model\Mapper\PaymentTypes\AdyenPayment;
use Forter\Forter\Model\Mapper\PaymentTypes\AuthorizeNetPayment;
use Forter\Forter\Model\Mapper\PaymentTypes\GeneralPayment;
use Forter\Forter\Model\Mapper\PaymentTypes\BrainTreePayment;
use Forter\Forter\Model\Mapper\PaymentTypes\MercadopagoPayment;
use Forter\Forter\Model\Mapper\PaymentTypes\PaybrightPayment;
use Forter\Forter\Model\Mapper\PaymentTypes\PaypalPayment;
use Forter\Forter\Model\Mapper\Utils;
use Forter\Forter\Model\Config as ForterConfig;
/**
 * Class Payment
 * @package Forter\Forter\Model\RequestBuilder
 */
class Payment
{

    private PaymentMethods $paymentMethods;
    private CustomerPreper $customerPreper;
    private Utils $utilsMapping;
    private ForterConfig $config;
    /**
     * Payment constructor.
     * @param Subscriber $subscriber
     */
    public function __construct(
        PaymentMethods $paymentMethods,
        CustomerPreper $customerPreper,
        Utils $utilsMapping,
        ForterConfig $config,
        ForterLogger $forterLogger
    ) {
        $this->paymentMethods = $paymentMethods;
        $this->customerPreper = $customerPreper;
        $this->utilsMapping = $utilsMapping;
        $this->config = $config;
    }

    /**
     * @param $order
     * @return array
     */
    public function generatePaymentInfo($order)
    {
        $billingAddress = $order->getBillingAddress();
        $payment = $order->getPayment();

        if (!$payment) {
            return [];
        }

        $payment_method = $payment->getMethod();
        $paymentData = [];

        $this->utilsMapping->log($this->config->isDebugEnabled(), 'Start Payment Fetching Info'.$payment_method, $order->getStoreId(),  $order->getIncrementId());
        try {
            $paymentData = $this->processPaymentByType($payment_method, $order, $payment);
        } catch (\Exception $e) {
            $this->utilsMapping->log($this->config->isDebugEnabled(), $e->getMessage(), $order->getStoreId(),  $order->getIncrementId());
            $paymentData = $this->fallBackPaymentHandler($payment_method, $order, $payment);
        }

        $paymentData["billingDetails"] = $this->customerPreper->getBillingDetails($billingAddress);
        $paymentData["paymentMethodNickname"] = $payment->getMethod();
        $paymentData["amount"] = [
            "amountLocalCurrency" => strval($order->getGrandTotal()),
            "currency" => $order->getOrderCurrency()->getCurrencyCode()
        ];
        $this->utilsMapping->log($this->config->isDebugEnabled(), 'End Payment Fetching info'.$payment_method, $order->getStoreId(),  $order->getIncrementId());


        return [$paymentData];
    }
    private function processPaymentByType(string $payment_method, $order, $payment)
    {
        $paymentData = [];
        $paymentHandler = new PaymentHandler();
        if (strpos($payment_method, 'paypal') !== false) {
            $paymentHandler->setPayment(new PaypalPayment(), $this->config, $this->utilsMapping);
            $paymentHandler->setMapper(null, $order->getStoreId(),  $order->getIncrementId());
            $paymentData["paypal"] = $paymentHandler->process($order, $payment);
        } elseif (strpos($payment_method, 'paybright') !== false) {
            $paymentHandler->setPayment(new PaybrightPayment(), $this->config, $this->utilsMapping);
            $paymentHandler->setMapper(null, $order->getStoreId(),  $order->getIncrementId());
            $paymentData["installmentService"] = $paymentHandler->process($order, $payment);
        } else {
            if (strpos($payment_method, 'adyen') !== false) {
                $paymentHandler->setPayment(new AdyenPayment(), $this->config, $this->utilsMapping);
                $paymentHandler->setMapper(null, $order->getStoreId(),  $order->getIncrementId());
                if (strpos($payment->getData('cc_type'), 'klarna_account') !== false) {
                    $paymentData["installmentService"] = $paymentHandler->installmentService($order, $payment);
                } else {
                    $cardDetails = $paymentHandler->process($order, $payment);
                }
            } elseif (strpos($payment_method, 'authorizenet') !== false) {
                $paymentHandler->setPayment(new AuthorizeNetPayment(), $this->config, $this->utilsMapping);
                $paymentHandler->setMapper(null, $order->getStoreId(),  $order->getIncrementId());
                $cardDetails = $paymentHandler->process($order, $payment);
            } elseif (strpos($payment_method, 'braintree') !== false) {
                $paymentHandler->setPayment(new BrainTreePayment(), $this->config, $this->utilsMapping);
                $paymentHandler->setMapper(null, $order->getStoreId(),  $order->getIncrementId());
                $cardDetails = $paymentHandler->process($order, $payment);
            } elseif (strpos($payment_method, 'mercadopago') !== false) {
                $paymentHandler->setPayment(new MercadopagoPayment(), $this->config, $this->utilsMapping);
                $paymentHandler->setMapper(null, $order->getStoreId(),  $order->getIncrementId());
                $cardDetails = $paymentHandler->process($order, $payment);
            } else {
                $paymentHandler->setPayment(new GeneralPayment(), $this->config, $this->utilsMapping);
                $paymentHandler->setMapper(null, $order->getStoreId(),  $order->getIncrementId());
                $cardDetails = $paymentHandler->process($order, $payment);
            }

            if (array_key_exists("expirationMonth", $cardDetails) || array_key_exists("expirationYear", $cardDetails) || array_key_exists("lastFourDigits", $cardDetails)) {
                $paymentData["creditCard"] = $cardDetails;
            }
        }
        $metaData = new \stdClass();
        $metaData->payment = $paymentData;
        $this->utilsMapping->log($this->config->isDebugEnabled(), 'Payment info'.$payment_method, $order->getStoreId(),  $order->getIncrementId(),$metaData);

        return $paymentData;
    }

    private function fallBackPaymentHandler(string $payment_method, $order, $payment)
    {
        $paymentData = [];
        // If paypal:
        if (strpos($payment_method, 'paypal') !== false) {
            $paymentData["paypal"] = $this->paymentMethods->getPaypalDetails($payment);
        } elseif (strpos($payment_method, 'paybright') !== false) {
            $paymentData["installmentService"] = $this->paymentMethods->getPaybrightDetails($order, $payment);
        } elseif (strpos($payment->getData('cc_type'), 'klarna_account') !== false) {
            $paymentData["installmentService"] = $this->paymentMethods->getAdyenKlarnaDetails($order, $payment);
        } else {
            if (strpos($payment_method, 'adyen') !== false) {
                $cardDetails = $this->paymentMethods->getAdyenDetails($payment);
            } elseif (strpos($payment_method, 'authorizenet') !== false) {
                $cardDetails = $this->paymentMethods->getAuthorizeNetDetails($payment);
            } elseif (strpos($payment_method, 'braintree') !== false) {
                $cardDetails = $this->paymentMethods->getBraintreeDetails($payment);
            } elseif (strpos($payment_method, 'mercadopago') !== false) {
                $cardDetails = $this->paymentMethods->getMercadopagoDetails($payment);
            } else {
                $cardDetails = $this->paymentMethods->preferCcDetails($payment);
            }

            if (array_key_exists("expirationMonth", $cardDetails) || array_key_exists("expirationYear", $cardDetails) || array_key_exists("lastFourDigits", $cardDetails)) {
                $paymentData["creditCard"] = $cardDetails;
            }
        }
        return $paymentData;
    }
}
