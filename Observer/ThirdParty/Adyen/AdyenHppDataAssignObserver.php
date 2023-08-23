<?php

namespace Forter\Forter\Observer\ThirdParty\Adyen;

use Adyen\Payment\Helper\StateData;
use Adyen\Service\Validator\DataArrayValidator;
use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AdyenHppDataAssignObserver extends AbstractDataAssignObserver
{
    const BRAND_CODE = 'brand_code';
    const DF_VALUE = 'df_value';
    const GUEST_EMAIL = 'guestEmail';
    const FORTER_STATE_DATA = 'forterData';
    const STATE_DATA = 'stateData';
    const RETURN_URL = 'returnUrl';
    const RECURRING_PROCESSING_MODEL = 'recurringProcessingModel';

    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    private static $approvedAdditionalDataKeys = [
        self::BRAND_CODE,
        self::DF_VALUE,
        self::GUEST_EMAIL,
        self::STATE_DATA,
        self::RETURN_URL,
        self::RECURRING_PROCESSING_MODEL
    ];

    /** @var StateData */
    private $stateData;

    /**
     * AdyenHppDataAssignObserver constructor.
     * @param StateData $stateData
     */
    public function __construct(
        StateData $stateData,
    ) {
        $this->stateData = $stateData;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $paymentInfo = $this->readPaymentModelArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $additionalData = DataArrayValidator::getArrayOnlyWithApprovedKeys(
            $additionalData,
            self::$approvedAdditionalDataKeys
        );

        if (!empty($additionalData[self::STATE_DATA])) {
            $stateData = json_decode($additionalData[self::STATE_DATA], true);
            $paymentInfo->setAdditionalInformation(self::FORTER_STATE_DATA, $stateData);
        }
    }
}
