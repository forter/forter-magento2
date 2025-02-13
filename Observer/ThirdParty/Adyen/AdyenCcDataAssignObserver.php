<?php

namespace Forter\Forter\Observer\ThirdParty\Adyen;

use Magento\Framework\Event\Observer;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class AdyenCcDataAssignObserver extends AbstractDataAssignObserver
{
    const EXPIRATION_DATE = 'expiryDate';
    const LAST_4 = 'cardSummary';
    const CARD_BRAND = 'paymentMethod';
    const ADDITIONAL_DATA = 'additionalData';

    const FORTER_STATE_DATA = 'forterData';

    /**
     * Approved root level keys from additional data array
     *
     * @var array
     */
    private static $approvedAdditionalDataKeys = [
        self::EXPIRATION_DATE,
        self::LAST_4,
        self::CARD_BRAND
    ];

    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        ModuleManager $moduleManager
    ) {
        $this->moduleManager = $moduleManager;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->moduleManager->isEnabled('Adyen_Payment')) {
            return null;
        }

        $data = $this->readDataArgument($observer);
        $paymentInfo = $this->readPaymentModelArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $additionalData = \Adyen\Payment\Helper\Util\DataArrayValidator::getArrayOnlyWithApprovedKeys(
            $additionalData,
            self::$approvedAdditionalDataKeys
        );
        $paymentInfo->unsAdditionalInformation(self::FORTER_STATE_DATA);
        $paymentInfo->setAdditionalInformation(self::ADDITIONAL_DATA, $additionalData);
    }
}
