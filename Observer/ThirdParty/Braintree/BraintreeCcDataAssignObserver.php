<?php

declare(strict_types=1);

namespace Forter\Forter\Observer\ThirdParty\Braintree\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

class BraintreeCcDataAssignObserver extends AbstractDataAssignObserver
{
    public const CC_BIN = 'bin';
    public const CC_LAST4 = 'cc_last4';

    /**
     * @var array
     */
    protected $additionalInformationList = [
        self::CC_BIN,
        self::CC_LAST4
    ];

    /**
     * Assign additional payment card information
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (isset($additionalData[$additionalInformationKey])) {
                $paymentInfo->setAdditionalInformation(
                    $additionalInformationKey,
                    $additionalData[$additionalInformationKey]
                );
            }
        }
    }
}
