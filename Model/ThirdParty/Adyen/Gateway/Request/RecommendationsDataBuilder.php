<?php

namespace Forter\Forter\Model\ThirdParty\Adyen\Gateway\Request;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RecommendationsDataBuilder implements BuilderInterface
{
    protected const VERIFICATION_REQUIRED_3DS_CHALLENGE = "VERIFICATION_REQUIRED_3DS_CHALLENGE";
    protected const REQUEST_SCA_EXEMPTION_LOW_VALUE = "REQUEST_SCA_EXEMPTION_LOW_VALUE";
    protected const REQUEST_SCA_EXEMPTION_TRA = "REQUEST_SCA_EXEMPTION_TRA";
    protected const REQUEST_SCA_EXCLUSION_MOTO = "REQUEST_SCA_EXCLUSION_MOTO";
    protected const REQUEST_SCA_EXEMPTION_CORP = "REQUEST_SCA_EXEMPTION_CORP";

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Add shopper data into request
     *
     * @param array $buildSubject
     * @return array|null
     */
    public function build(array $buildSubject): ?array
    {
        $request = [];

        $paymentDataObject = \Magento\Payment\Gateway\Helper\SubjectReader::readPayment($buildSubject);

        if ($paymentDataObject instanceof PaymentDataObjectInterface) {
            $forterPreAuth = $this->isForterPreAuth() === '1' || $this->isForterPreAuth() === '4' ? true : false;

            if ($forterPreAuth) {
                $payment = $paymentDataObject->getPayment();

                // Ensuring payment method is adyen_cc before proceeding
                if ($payment && $payment->getMethod() === "adyen_cc") {
                    $forterResponse = $payment->getOrder()->getForterResponse();

                    if ($forterResponse !== null) {
                        $response = json_decode($forterResponse, true);

                        if (isset($response['recommendations']) && is_array($response['recommendations'])) {
                            array_walk_recursive($response['recommendations'], function ($value) use (&$request) {
                                switch ($value) {
                                    case self::VERIFICATION_REQUIRED_3DS_CHALLENGE:
                                        $request['body']["threeDS2RequestData"]["threeDSRequestorChallengeInd"] = "04";
                                        $request['body']["authenticationData"]["attemptAuthentication"] = "always";
                                        $request['body']["authenticationData"]["threeDSRequestData"]["nativeThreeDS"] = "preferred";
                                        break;
                                    case self::REQUEST_SCA_EXEMPTION_CORP:
                                        $request['body']["additionalData"]["scaExemption"] = "secureCorporate";
                                        break;
                                    case self::REQUEST_SCA_EXEMPTION_LOW_VALUE:
                                        $request['body']["additionalData"]["scaExemption"] = "lowValue";
                                        break;
                                    case self::REQUEST_SCA_EXEMPTION_TRA:
                                        $request['body']["additionalData"]["scaExemption"] = "transactionRiskAnalysis";
                                        break;
                                }
                            });
                            return $request;
                        }
                    }
                }
            }
        }
        return $request;
    }

    protected function isForterPreAuth()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/pre_post_select');
    }
}
