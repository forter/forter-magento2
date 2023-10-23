<?php 

namespace Forter\Forter\Model\Order;

class Recommendation {

    protected const VERIFICATION_REQUIRED_3DS_CHALLENGE = "VERIFICATION_REQUIRED_3DS_CHALLENGE";
    protected const REQUEST_SCA_EXEMPTION_LOW_VALUE = "REQUEST_SCA_EXEMPTION_LOW_VALUE";
    protected const REQUEST_SCA_EXEMPTION_TRA = "REQUEST_SCA_EXEMPTION_TRA";
    protected const REQUEST_SCA_EXCLUSION_MOTO = "REQUEST_SCA_EXCLUSION_MOTO";
    protected const REQUEST_SCA_EXEMPTION_CORP = "REQUEST_SCA_EXEMPTION_CORP";

    public static function isVerificationRequired3dsChallenge(  $order ) {

        $forterResponse = $order->getForterResponse();

        if ($forterResponse !== null) {
            $response = json_decode($forterResponse, true);

            if (isset($response['recommendations']) && is_array($response['recommendations'])) {
                foreach( $response['recommendations'] as $recommendation ) {
                    if ( $recommendation == self::VERIFICATION_REQUIRED_3DS_CHALLENGE ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

}