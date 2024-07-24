<?php

namespace Forter\Forter\Plugin\Thirdparty\Adyen\PaymentInformation;

use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data as AdyenHelper;
use Adyen\Payment\Helper\Requests as RequestsHelper;
use Forter\Forter\Model\Config as ForterConfig;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ObjectManager;
use Magento\Quote\Api\CartRepositoryInterface;

class PaymentInformationManagement
{
    protected $forterConfig;

    public function __construct(
        Config                   $config,
        AdyenHelper              $adyenHelper,
        RequestsHelper           $requestsHelper,
        CustomerSession          $customerSession,
        ForterConfig             $forterConfig,
        ?CartRepositoryInterface $cartRepository = null
    ) {
        $this->configHelper = $config;
        $this->adyenHelper = $adyenHelper;
        $this->requestsHelper = $requestsHelper;
        $this->customerSession = $customerSession;
        $this->forterConfig = $forterConfig;
        $this->cartRepository = $cartRepository
            ?? ObjectManager::getInstance()->get(CartRepositoryInterface::class);
    }

    public function beforeSavePaymentInformation(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
                                                             $cartId,
        \Magento\Quote\Api\Data\PaymentInterface             $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface             $billingAddress = null
    ) {
        if ($paymentMethod->getMethod() !== 'adyen_cc_vault') {
            return [$cartId, $paymentMethod, $billingAddress];
        }

        $isPreAuth = $this->isPreAuth($paymentMethod->getMethod());
        if ($isPreAuth && $cartId !== null) {
            $cardDetails = $this->getRecurringCardDetails($cartId, $paymentMethod);
            if ($cardDetails) {
                $this->processCardDetails($paymentMethod, $cardDetails);
            }
        }

        return [$cartId, $paymentMethod, $billingAddress];
    }

    protected function isPreAuth($method)
    {
        $methodSetting = $this->forterConfig->getMappedPrePos($method);
        return $methodSetting && in_array($methodSetting, ['pre', 'prepost']) ||
            !$methodSetting && ($this->forterConfig->getIsPre() || $this->forterConfig->getIsPreAndPost());
    }

    protected function getRecurringCardDetails($cartId, $paymentMethod)
    {
        $quote = $this->cartRepository->getActive($cartId);
        $storeId = $quote->getStoreId();
        $shopperReference = $quote->getBillingAddress()->getCustomerId();
        $request = [
            "merchantAccount" => $this->configHelper->getAdyenAbstractConfigData('merchant_account', $storeId),
            "shopperReference" => $this->adyenHelper->padShopperReference($shopperReference),
            "recurring" => ['contract' => "RECURRING"],
        ];

        $client = $this->adyenHelper->initializeAdyenClient($storeId);
        $service = $this->adyenHelper->createAdyenRecurringService($client);
        return $service->listRecurringDetails($request)["details"] ?? null;
    }

    protected function processCardDetails($paymentMethod, $cardDetails)
    {
        $stateData = json_decode($paymentMethod->getAdditionalData()['stateData'] ?? '{}');
        $recurringDetailReference = $stateData->paymentMethod->storedPaymentMethodId ?? null;

        if (!$recurringDetailReference) {
            return;
        }

        foreach ($cardDetails as $card) {
            if (array_key_exists("RecurringDetail", $card)) {
                $recurringDetail = $card["RecurringDetail"];
                if ($recurringDetail["recurringDetailReference"] === $recurringDetailReference) {
                    // Retrieve the desired information from the card
                    $existingAdditionalData = $paymentMethod->getAdditionalData();
                    if (is_array($existingAdditionalData) && array_key_exists("additionalData", $recurringDetail) && array_key_exists("cardBin", $recurringDetail["additionalData"])) {
                        $existingAdditionalData['cardBin'] = $recurringDetail["additionalData"]["cardBin"];
                    }
                    if (array_key_exists('card', $recurringDetail)) {
                        if (array_key_exists('number', $recurringDetail['card'])) {
                            $existingAdditionalData['cardSummary'] = $recurringDetail['card']['number'];
                        }
                        if (array_key_exists('expiryYear', $recurringDetail['card']) && array_key_exists('expiryMonth', $recurringDetail['card'])) {
                            $existingAdditionalData['expiryDate'] = $recurringDetail['card']['expiryMonth'] . '/' . $recurringDetail['card']['expiryYear'];
                        }
                    }
                    $paymentMethod->setAdditionalData($existingAdditionalData);
                    break;
                }
            }
        }
    }
}
