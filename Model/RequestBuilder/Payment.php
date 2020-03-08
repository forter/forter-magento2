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

/**
 * Class Payment
 * @package Forter\Forter\Model\RequestBuilder
 */
class Payment
{

  /**
   * Payment constructor.
   * @param Subscriber $subscriber
   */
    public function __construct(
        PaymentMethods $paymentMethods,
        CustomerPreper $customerPreper
    ) {
        $this->paymentMethods = $paymentMethods;
        $this->customerPreper = $customerPreper;
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

        $paymentData = [];
        $payment_method = $payment->getMethod();
        // If paypal:
        if (strpos($payment_method, 'paypal') !== false) {
            $paymentData["paypal"] = $this->paymentMethods->getPaypalDetails($payment);
        } else {
            switch ($payment_method) {
              case 'authorizenet_directpost':
              case 'authorizenet_acceptjs':
                $cardDetails = $this->paymentMethods->getAuthorizeNetDetails($payment);
                break;
              case 'braintree':
                $cardDetails = $this->paymentMethods->getBraintreeDetails($payment);
                break;
              default:
                $cardDetails = $this->paymentMethods->preferCcDetails($payment);
          }
            if (array_key_exists("expirationMonth", $cardDetails) || array_key_exists("expirationYear", $cardDetails) || array_key_exists("lastFourDigits", $cardDetails)) {
                $paymentData["creditCard"] = $cardDetails;
            }
        }

        $paymentData["billingDetails"] = $this->customerPreper->getBillingDetails($billingAddress);
        $paymentData["paymentMethodNickname"] = $payment->getMethod();
        $paymentData["amount"] = [
            "amountLocalCurrency" => strval($order->getGrandTotal()),
            "currency" => $order->getOrderCurrency()->getCurrencyCode()
        ];

        return [$paymentData];
    }
}
