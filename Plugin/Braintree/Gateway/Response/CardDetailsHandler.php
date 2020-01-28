<?php

namespace Forter\Forter\Plugin\Braintree\Gateway\Response;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Magento\Braintree\Gateway\Response\CardDetailsHandler as OrigCardDetailsHandler;
use Magento\Braintree\Gateway\SubjectReader;
use Magento\Framework\Encryption\Encryptor;

class CardDetailsHandler
{

    /**
     * A plugin that wraps the 'Magento\Braintree\Gateway\Response\CardDetailsHandler' class.
     * It's purpose is to extract the cc bin from the gateway request and save it on the order object in order to send it to Forter later.
     */

    public function __construct(
        Config $forterConfig,
        SubjectReader $subjectReader,
        Encryptor $crypt,
        AbstractApi $abstractApi
    ) {
        $this->forterConfig = $forterConfig;
        $this->subjectReader = $subjectReader;
        $this->abstractApi = $abstractApi;
        $this->crypt = $crypt;
    }

    public function beforeHandle(OrigCardDetailsHandler $cardDetailsHandler, array $handlingSubject, array $response)
    {
        if (!$this->forterConfig->isEnabled()) {
            return false;
        }

        try {
            $paymentDO = $this->subjectReader->readPayment($handlingSubject);
            $transaction = $this->subjectReader->readTransaction($response);

            $payment = $paymentDO->getPayment();

            $creditCard = $transaction->creditCard;

            $cc_bin = $creditCard['bin'];
            $country_of_issuance = $creditCard['countryOfIssuance'];
            $name_on_card = $creditCard['cardholderName'];

            $payment->setAdditionalInformation('forter_cc_bin', $cc_bin);
            $payment->setAdditionalInformation('forter_cc_owner', $name_on_card);
            if ($country_of_issuance != null && $country_of_issuance != 'Unknown') {
                $payment->setAdditionalInformation("forter_cc_country", $country_of_issuance);
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }
}
