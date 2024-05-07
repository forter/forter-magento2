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
use Forter\Forter\Model\ThirdParty\Stripe\StripePayment;

/**
 * Class Payment
 * @package Forter\Forter\Model\RequestBuilder
 */
class Payment
{
    /**
     * @var PaymentMethods
     */
    protected $paymentMethods;

    /**
     * @var CustomerPreper
     */
    protected $customerPreper;

    protected $stripePayment;

    /**
     * Payment constructor.
     * @param PaymentMethods $paymentMethods
     * @param CustomerPreper $customerPreper
     */
    public function __construct(
        PaymentMethods $paymentMethods,
        CustomerPreper $customerPreper,
        StripePayment $stripePayment
    ) {
        $this->paymentMethods = $paymentMethods;
        $this->customerPreper = $customerPreper;
        $this->stripePayment = $stripePayment;
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
        if (strpos($payment_method, 'paypal') !== false && $payment_method != 'adyen_paypal') {
            $paymentData["paypal"] = $this->paymentMethods->getPaypalDetails($payment);
        } elseif (strpos($payment_method, 'paybright') !== false) {
            $paymentData["installmentService"] = $this->paymentMethods->getPaybrightDetails($order, $payment);
        } elseif (!is_null($payment->getData('cc_type')) && strpos($payment->getData('cc_type'), 'klarna_account') !== false) {
            $paymentData["installmentService"] = $this->paymentMethods->getAdyenKlarnaDetails($order, $payment);
        } else {
            if (strpos($payment_method, 'adyen') !== false) {
                if ($payment->getCcType() == 'googlepay' || $payment->getAdditionalInformation('brand_code') == 'googlepay' || $payment_method == 'adyen_googlepay') {
                    $cardDetails = $this->paymentMethods->getAdyenGooglePayDetails($payment, $order);
                } else {
                    $cardDetails = $this->paymentMethods->getAdyenDetails($payment);
                }
            } elseif (strpos($payment_method, 'authorizenet') !== false) {
                $cardDetails = $this->paymentMethods->getAuthorizeNetDetails($payment);
            } elseif (strpos($payment_method, 'braintree') !== false) {
                $cardDetails = $this->paymentMethods->getBraintreeDetails($payment);
            } elseif (strpos($payment_method, 'mercadopago') !== false) {
                $cardDetails = $this->paymentMethods->getMercadopagoDetails($payment);
            } elseif (strpos($payment_method, 'stripe') !== false) {
                $cardDetails = $this->paymentMethods->getStripePaymentDetails($payment, $this->stripePayment->getPaymentData($order));
            } else {
                $cardDetails = $this->paymentMethods->preferCcDetails($payment);
            }

            if (array_key_exists("expirationMonth", $cardDetails ?? []) || array_key_exists("expirationYear", $cardDetails ?? []) || array_key_exists("lastFourDigits", $cardDetails ?? [])) {
                $paymentData["creditCard"] = $cardDetails;
            }

            if ($payment->getCcType() == 'googlepay' || $payment->getAdditionalInformation('brand_code') == 'googlepay' || $payment_method == 'adyen_googlepay') {
                $paymentData['androidPay'] = $cardDetails;
                unset($paymentData["creditCard"]);
            }

            if ($payment->getCcType() == 'applepay' || $payment->getAdditionalInformation('brand_code') == 'applepay' || $payment_method == 'adyen_applepay') {
                $paymentData['applePay'] = $cardDetails;
                unset($paymentData["creditCard"]);
            }
            if (strpos($payment->getMethod(), 'stripe_payments') !== false) {
                $stripeData = json_decode($payment->getAdditionalInformation('stripeChargeData') ?? '');
                if ($stripeData && isset($stripeData->payment_method)) {
                    $paymentData['tokenizedCard'] = $cardDetails;
                    unset($paymentData["creditCard"]);
                }

                if ($stripeData && isset($stripeData->payment_method_details)
                    && isset($stripeData->payment_method_details->card)
                    && isset($stripeData->payment_method_details->card->wallet)
                    && isset($stripeData->payment_method_details->card->wallet->type)
                    && $stripeData->payment_method_details->card->wallet->type === 'google_pay') {

                    $paymentData['androidPay'] = $cardDetails;
                    unset($paymentData["tokenizedCard"]);
                    unset($paymentData["creditCard"]);
                }

                if ($stripeData && isset($stripeData->payment_method_details)
                    && isset($stripeData->payment_method_details->card)
                    && isset($stripeData->payment_method_details->card->wallet)
                    && isset($stripeData->payment_method_details->card->wallet->type)
                    && $stripeData->payment_method_details->card->wallet->type === 'apple_pay') {

                    $paymentData['applePay'] = $cardDetails;
                    unset($paymentData["tokenizedCard"]);
                    unset($paymentData["creditCard"]);
                }
            }

            // Attempt to set tokenized card information if available
            if (!isset($paymentData["creditCard"]) && $payment->getAdditionalInformation('forter_cc_token') && $payment->getAdditionalInformation('forter_cc_bin')) {
                $paymentData["tokenizedCard"] = [
                    'bin'   => $payment->getAdditionalInformation('forter_cc_bin'),
                    'token' => $payment->getAdditionalInformation('forter_cc_token')
                ];
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
