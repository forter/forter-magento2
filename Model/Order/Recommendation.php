<?php

namespace Forter\Forter\Model\Order;

use Forter\Forter\Model\EntityFactory as ForterEntityFactory;
use Forter\Forter\Helper\EntityHelper;
class Recommendation
{
    protected const VERIFICATION_REQUIRED_3DS_CHALLENGE = "VERIFICATION_REQUIRED_3DS_CHALLENGE";
    protected const REQUEST_SCA_EXEMPTION_LOW_VALUE = "REQUEST_SCA_EXEMPTION_LOW_VALUE";
    protected const REQUEST_SCA_EXEMPTION_TRA = "REQUEST_SCA_EXEMPTION_TRA";
    protected const REQUEST_SCA_EXCLUSION_MOTO = "REQUEST_SCA_EXCLUSION_MOTO";
    protected const REQUEST_SCA_EXEMPTION_CORP = "REQUEST_SCA_EXEMPTION_CORP";

    protected $forterEntityFactory;

    /**
     * @var EntityHelper
     */
    protected $entityHelper;

    public function __construct(
        EntityHelper $entityHelper
    ) {
        $this->entityHelper = $entityHelper;
    }

    public function isVerificationRequired3dsChallenge($order)
    {
        $forterEntity = $this->entityHelper->getForterEntityByIncrementId($order->getIncrementId());

        if (!$forterEntity) {
            return;
        }
        $forterResponse = $forterEntity->getForterResponse();

        if ($forterResponse !== null) {
            $response = json_decode($forterResponse, true);

            if (isset($response['recommendations']) && is_array($response['recommendations'])) {
                foreach ($response['recommendations'] as $recommendation) {
                    if ($recommendation == self::VERIFICATION_REQUIRED_3DS_CHALLENGE) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
