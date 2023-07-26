<?php

/**
 * Forter Payments For Magento 2
 * https://www.Forter.com/
 *
 * @category Forter
 * @package  Forter_Forter
 * @author   Girit-Interactive (https://www.girit-tech.com/)
 */

namespace Forter\Forter\Model;

use Forter\Forter\Logger\Logger\DebugLogger;
use Forter\Forter\Logger\Logger\ErrorLogger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Forter Forter config model.
 */
class Config
{
    /**
     *
     */
    public const MODULE_NAME = 'Forter_Forter';

    public const VERIFICATION_RESULTS_DEFAULT_FIELDS = [
        "authorizationCode",
        "authorizationPolicy",
        "avsFullResult",
        "avsNameResult",
        "avsStreetResult",
        "avsZipResult",
        "cvvResult",
        "cavvResult",
        "eciValue",
        "processorResponseCode",
        "processorResponseText"
    ];

    public const RECOMENDATION_KEYS_MESSAGES_MAP = [
        "ROUTING_RECOMMENDED" => "Re-route",
        "ID_VERIFICATION_REQUIRED" => "ID Verification",
        "MONITOR_POTENTIAL_COUPON_ABUSE" => "Coupon Abuse",
        "MONITOR_POTENTIAL_SELLER_COLLUSION" => "Seller Collusion",
        "MONITOR_POTENTIAL_REFUND_ABUSE" => "Refund Abuse",
        "DECLINE_CHANEL_ABUSER" => "Channel Abuse",
        "BORDERLINE" => "Borderline",
    ];

    /**
     * Scope config object.
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * Store manager object.
     *
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var DebugLogger
     */
    private $forterDebugLogger;

    /**
     * @var ErrorLogger
     */
    private $forterErrorLogger;

    /**
     * @var array|null
     */
    private $verificationResultMap = null;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @method __construct
     * @param  ScopeConfigInterface     $scopeConfig
     * @param  StoreManagerInterface    $storeManager
     * @param  EncryptorInterface       $encryptor
     * @param  LoggerInterface          $logger
     * @param  ModuleListInterface      $moduleList
     * @param  UrlInterface             $urlBuilder
     * @param  DebugLogger              $forterDebugLogger
     * @param  ErrorLogger              $forterErrorLogger
     * @param  ProductMetadataInterface $productMetadata
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        LoggerInterface $logger,
        ModuleListInterface $moduleList,
        UrlInterface $urlBuilder,
        DebugLogger $forterDebugLogger,
        ErrorLogger $forterErrorLogger,
        ProductMetadataInterface $productMetadata
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
        $this->moduleList = $moduleList;
        $this->urlBuilder = $urlBuilder;
        $this->forterDebugLogger = $forterDebugLogger;
        $this->forterErrorLogger = $forterErrorLogger;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Return config path.
     *
     * @return string
     */
    private function getConfigPath()
    {
        return sprintf('forter/');
    }

    /**
     * Return store manager.
     * @return StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * Return URL Builder
     * @return UrlInterface
     */
    public function getUrlBuilder()
    {
        return $this->urlBuilder;
    }

    /**
     * Return store id.
     *
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreId($scopeId = null)
    {
        $scopeId = ($scopeId === null) ? $this->storeManager->getStore()->getId() : $scopeId;
        return $scopeId;
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSiteId($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('settings/site_id', $scope, $scopeId);
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSecretKey($scope = null, $scopeId = null)
    {
        $secretKey = $this->getConfigValue('settings/secret_key', $scope, $scopeId);
        $decryptSecretKey = $this->encryptor->decrypt($secretKey);
        return $decryptSecretKey;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getTimeOutSettings($scope = null, $scopeId = null)
    {
        return $timeOutArray = [
            "base_connection_timeout" => $this->getConfigValue('connection_information/base_connection_timeout', $scope, $scopeId),
            "base_request_timeout" => $this->getConfigValue('connection_information/base_request_timeout', $scope, $scopeId),
            "max_connection_timeout" => $this->getConfigValue('connection_information/max_connection_timeout', $scope, $scopeId),
            "max_request_timeout" => $this->getConfigValue('connection_information/max_request_timeout', $scope, $scopeId)
        ];
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getEmailSettingsOnDecline($scope = null, $scopeId = null)
    {
        return [
          "enable" => $this->getConfigValue('sendmail_on_decline/sendmail_on_decline_enabled', $scope, $scopeId),
          "email_sender" => $this->getConfigValue('sendmail_on_decline/sender', $scope, $scopeId),
          "email_receiver" => $this->getConfigValue('sendmail_on_decline/receiver', $scope, $scopeId),
          "custom_email_template" => $this->getConfigValue('sendmail_on_decline/email_template', $scope, $scopeId)
        ];
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getApiVersion($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('settings/api_version', $scope, $scopeId);
    }

    /**
     * Return config field value.
     * @param string $fieldKey Field key.
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfigValue($fieldKey, $scope = null, $scopeId = null)
    {
        $scope = ($scope === null) ? \Magento\Store\Model\ScopeInterface::SCOPE_STORE : $scope;
        if ($scope === \Magento\Store\Model\ScopeInterface::SCOPE_STORE) {
            $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
        } elseif ($scope === \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE) {
            $scope = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES;
        }
        $scopeId = ($scopeId === null) ? $this->getStoreId() : $scopeId;

        return $this->scopeConfig->getValue(
            $this->getConfigPath() . $fieldKey,
            $scope,
            $scopeId
        );
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isEnabled($scope = null, $scopeId = null)
    {
        if (!$this->getSecretKey() || !$this->getSiteId()) {
            return false;
        }
        return (bool)$this->getConfigValue('settings/enabled', $scope, $scopeId);
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isDebugEnabled($scope = null, $scopeId = null)
    {
        return (bool)$this->getConfigValue('settings/debug_mode', $scope, $scopeId);
    }

    /**
     * Return bool value depends of that if the Desicion Contoller Enabled
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isDecisionControllerEnabled($scope = null, $scopeId = null)
    {
        return (bool)$this->getConfigValue('advanced_settings/enabled_decision_controller', $scope, $scopeId);
    }

    /**
     * Return bool value depends of that if the Pending On Hold Enabled
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isPendingOnHoldEnabled($scope = null, $scopeId = null)
    {
        return (bool)$this->getConfigValue('advanced_settings/enabled_hold_order', $scope, $scopeId);
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isSandboxMode($scope = null, $scopeId = null)
    {
        return (bool)$this->getConfigValue('settings/enhanced_data_mode', $scope, $scopeId);
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSandboxMode($scope = null, $scopeId = null)
    {
        return (bool)$this->getConfigValue('settings/sandbox_mode', $scope, $scopeId);
    }

    /**
     * @method getCurrentStore
     */
    public function getCurrentStore()
    {
        return $this->storeManager->getStore();
    }

    /**
     * @method log
     * @param mixed $message
     * @param string $type
     * @param array $data
     * @param string $prefix
     * @return $this
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function log($message, $type = "debug", $data = [], $prefix = '[Forter] ')
    {
        if ($type === 'error' || $this->isDebugEnabled()) {
            $message = $prefix . json_encode($message);
            if (!isset($data['store_id'])) {
                $data['store_id'] = $this->getStoreId();
            }
            $this->forterDebugLogger->debug($message, $data);
            if ($type === 'error') {
                $this->forterErrorLogger->debug($message, $data);
                $this->logger->error($message, $data);
            }
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getModuleVersion()
    {
        return $this->moduleList->getOne(self::MODULE_NAME)['setup_version'];
    }

    public function getMagentoFullVersion()
    {
        return "{$this->productMetadata->getName()} {$this->productMetadata->getEdition()} {$this->productMetadata->getVersion()}";
    }

    /**
     * @return mixed
     */
    public function getDeclinePre()
    {
        return $this->getConfigValue('immediate_post_pre_decision/decline_pre');
    }

    /**
     * @return mixed
     */
    public function getDeclinePost()
    {
        return $this->getConfigValue('immediate_post_pre_decision/decline_post');
    }

    /**
     * @return mixed
     */
    public function getPreThanksMsg($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('immediate_post_pre_decision/pre_thanks_msg', $scope, $scopeId);
    }

    /**
     * @return mixed
     */
    public function getPostThanksMsg($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('immediate_post_pre_decision/post_thanks_msg', $scope, $scopeId);
    }

    /**
     * @return mixed
     */
    public function isHoldingOrdersEnabled($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings/enabled_order_holding', $scope, $scopeId);
    }

    /**
     * @return mixed
     */
    public function isOrderFulfillmentEnable($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings/enabled_order_fulfillment', $scope, $scopeId);
    }

    /**
     * @return mixed
     */
    public function isOrderCreditMemoStatusEnable($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings/enable_order_creditmemo', $scope, $scopeId);
    }

    /**
     * @return mixed
     */
    public function isOrderRmaStatusEnable($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings/enable_order_returns', $scope, $scopeId);
    }

    /**
     * @return mixed
     */
    public function isOrderShippingStatusEnable($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings/enable_order_shipping', $scope, $scopeId);
    }

    /**
     * @return mixed
     */
    public function isPhoneOrderEnabled($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings/phone_order_enabled', $scope, $scopeId);
    }

    /**
     * @return mixed
     */
    public function getApprovePost()
    {
        return $this->getConfigValue('immediate_post_pre_decision/approve_post');
    }

    /**
     * @return mixed
     */
    public function getApproveCron()
    {
        return $this->getConfigValue('immediate_post_pre_decision/approve_cron');
    }

    /**
     * @return mixed
     */
    public function getDeclineCron()
    {
        return $this->getConfigValue('immediate_post_pre_decision/decline_cron');
    }

    /**
     * @return mixed
     */
    public function getNotReviewCron()
    {
        return $this->getConfigValue('immediate_post_pre_decision/not_review_cron');
    }

    /**
     * @return mixed
     */
    public function getNotReviewPost()
    {
        return $this->getConfigValue('immediate_post_pre_decision/not_review_post');
    }

    /**
     * @return bool
     */
    public function getIsPre()
    {
        $prePostSelect = $this->getConfigValue('immediate_post_pre_decision/pre_post_select');
        return ($prePostSelect == '1' ? true : false);
    }

    /**
     * @return bool
     */
    public function getIsPost()
    {
        $prePostSelect = $this->getConfigValue('immediate_post_pre_decision/pre_post_select');
        return ($prePostSelect == '2' ? true : false);
    }

    /**
     * @return bool
     */
    public function getIsPreAndPost()
    {
        $prePostSelect = $this->getConfigValue('immediate_post_pre_decision/pre_post_select');
        return ($prePostSelect == '4' ? true : false);
    }

    /**
     * @return bool
     */
    public function getIsCron()
    {
        $prePostSelect = $this->getConfigValue('immediate_post_pre_decision/pre_post_select');
        return ($prePostSelect == '3' ? true : false);
    }

    /**
     * Return boolean regarding active/disable pre-auth card observing
     *
     * @return boolean
     */
    public function isCcListenerActive($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings_cc_listener/enabled_cc_listener', $scope, $scopeId);
    }

    /**
     * Return boolean regarding observe Last4CC
     *
     * @return boolean
     */
    public function getAllowLast4CCListener($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings_cc_listener/enabled_cc_listener_last4cc', $scope, $scopeId);
    }

    /**
     * Return boolean regarding observe Bin
     *
     * @return boolean
     */
    public function getAllowBinListener($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings_cc_listener/enabled_cc_listener_bin', $scope, $scopeId);
    }

    /**
     * Return the element to observe
     *
     * @return string
     */
    public function getElementToObserve($scope = null, $scopeId = null)
    {
        return $this->getConfigValue('advanced_settings_cc_listener/class_id_identifier', $scope, $scopeId);
    }

    /**
     * @method getVerificationResultsMap
     * @return array
     */
    public function getVerificationResultsMap($scope = null, $scopeId = null)
    {
        if ($this->verificationResultMap === null) {
            $this->verificationResultMap = is_null($this->scopeConfig->getValue('forter/advanced_settings/verification_results_map', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) ? [] : json_decode($this->scopeConfig->getValue('forter/advanced_settings/verification_results_map', \Magento\Store\Model\ScopeInterface::SCOPE_STORE), true);
        }
        return $this->verificationResultMap;
    }

    /**
     * @method getVerificationResultsMethodFields
     * @param  string                             $method
     * @return array
     */
    public function getVerificationResultsMethodFields($method)
    {
        $fields = self::VERIFICATION_RESULTS_DEFAULT_FIELDS;
        if ($method) {
            $map = $this->getVerificationResultsMap();
            if (isset($map[$method])) {
                foreach ((array)$map[$method] as $key => $value) {
                    if (!in_array($key, $fields)) {
                        $fields[] = $key;
                    }
                }
            }
        }
        return $fields;
    }

    /**
     * @method getVerificationResultsMapping
     * @param  string                        $method
     * @param  string                        $key
     * @return string
     */
    public function getVerificationResultsMapping($method, $key)
    {
        if ($method && $key) {
            $map = $this->getVerificationResultsMap();
            if (isset($map[$method]) && !empty($map[$method][$key])) {
                return (string)$map[$method][$key];
            }
            return $key;
        }
    }

    /**
     * @param $addresses
     * @return array|bool
     */
    public function getAddressInAccount($addresses)
    {
        if (!isset($addresses) || !$addresses) {
            return [];
        }

        foreach ($addresses as $address) {
            $street = $address->getStreet();
            $customerAddress['address1'] = $street[0] . "";
            $customerAddress['city'] = $address->getCity() . "";
            $customerAddress['country'] = $address->getCountryId() . "";
            $customerAddress['address2'] = (isset($street[1]) ? $street[1] : "");
            $customerAddress['zip'] = $address->getPostcode() . "";
            $customerAddress['region'] = (string)$address->getRegionId() . "";
            $customerAddress['company'] = $address->getCompany() . "";

            $addressInAccount[] = $customerAddress;
        }

        return (isset($addressInAccount)) ? $addressInAccount : null;
    }

    /**
     * @param $order
     * @param $message
     */
    public function addCommentToOrder($order, $message)
    {
        $order->addStatusHistoryComment('Forter: ' . $message)
          ->setIsCustomerNotified(false)
          ->setEntityName('order');

        $order->save();
    }

    /**
     * @param $type
     * @return mixed|string
     */
    public function getPrePostDecisionMsg($type, $scope = null, $scopeId = null)
    {
        $result = $this->getConfigValue('immediate_post_pre_decision/' . $type, $scope, $scopeId);
        switch ($type) {
            case 'pre_post_select':
                if ($result == '1') {
                    return 'Before Payment Action (pre-authorization)';
                } elseif ($result == '2') {
                    return 'After Payment Action (post-authorization)';
                } elseif ($result == '3') {
                    return 'by Cron';
                } elseif ($result == '4') {
                    return 'Before & After Payment Action (pre/post-authorization)';
                }
                return $result;

            case 'decline_pre':
                if ($result == '0') {
                    $result = 'Do nothing';
                } elseif ($result == '1') {
                    $result = 'Show Payment Error to User (stay in checkout page with error message)';
                }
                return $result;

            case 'decline_post':
                if ($result == '3') {
                    $result = 'Do nothing';
                } elseif ($result == '1') {
                    $result = 'Cancel Order, Stop Confirmation Email, Void or Refund Payment (CRON)';
                } elseif ($result == '2') {
                    $result = 'Set Order to Payment Review State and Stop Confirmation Email';
                }
                return $result;

            case 'approve_post':
                if ($result == '1') {
                    $result = 'Create Invoice and Capture Payments (CRON)';
                } elseif ($result == '2') {
                    $result = 'Do Nothing';
                }
                return $result;

            case 'not_review_post':
                if ($result == '1') {
                    $result = 'Create Invoice and Capture Payments (CRON)';
                } elseif ($result == '2') {
                    $result = 'Do Nothing';
                }
                return $result;
            case 'approve_cron':
                if ($result == '1') {
                    $result = 'Create Invoice and Capture Payments (CRON)';
                } elseif ($result == '2') {
                    $result = 'Do Nothing';
                }
                return $result;
            case 'decline_cron':
                if ($result == '1') {
                    $result = 'Cancel Order, Void or Refund Payment (CRON)';
                } elseif ($result == '2') {
                    $result = 'Set Order to Payment Review State';
                } elseif ($result == '3') {
                    $result = 'Do nothing';
                }
                return $result;
              case 'not_review_cron':
                if ($result == '1') {
                    $result = 'Create Invoice and Capture Payments';
                } elseif ($result == '2') {
                    $result = 'Do nothing';
                }
                return $result;
            default:
                return $result;
        }
    }


    /**
     * Convert Forter recommendation key to a human readable message (by internal map).
     * @method getRecommendationMessageByKey
     * @param  string                        $recommendationKey
     * @return string
     */
    public function getRecommendationMessageByKey($recommendationKey)
    {
        if (!$recommendationKey || !is_string($recommendationKey)) {
            return '';
        }
        if (isset(self::RECOMENDATION_KEYS_MESSAGES_MAP[$recommendationKey])) {
            return __(self::RECOMENDATION_KEYS_MESSAGES_MAP[$recommendationKey]);
        }
        return __($recommendationKey);
    }

    /**
     * @method getRecommendationsFromResponse
     * @param  object                         $forterResponse
     * @return string[]
     */
    public function getRecommendationsFromResponse($forterResponse)
    {
        if (
            is_object($forterResponse) &&
            !empty($forterResponse->recommendations) &&
            is_array($forterResponse->recommendations)
        ) {
            return $forterResponse->recommendations;
        }
        return [];
    }

    /**
     * @method getResponseRecommendationsNote
     * @param  object                         $forterResponse
     * @param  string                         $recommendationsHeading ('recommendations')
     * @param  object                         $prefix (space)
     * @return string
     */
    public function getResponseRecommendationsNote($forterResponse, $recommendationsHeading = 'recommendations', $prefix = ' ')
    {
        if (($recommendations = $this->getRecommendationsFromResponse($forterResponse))) {
            $recommendations = implode(
                ', ',
                array_map(function ($recommendation) {
                    return self::getRecommendationMessageByKey($recommendation);
                }, $recommendations)
            );
            $recommendationsHeading = $recommendationsHeading ? __('%1: ', $recommendationsHeading) : '';
            return $prefix . __('(%1%2)', $recommendationsHeading, $recommendations);
        }
        return '';
    }
}
