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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
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
    const MODULE_NAME = 'Forter_Forter';

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
     * @method __construct
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $logger
     * @param UrlInterface $urlBuilder
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        LoggerInterface $logger,
        ModuleListInterface $moduleList,
        UrlInterface $urlBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
        $this->moduleList = $moduleList;
        $this->urlBuilder = $urlBuilder;
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
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSiteId()
    {
        return $this->getConfigValue('settings/site_id');
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getSecretKey()
    {
        $secretKey = $this->getConfigValue('settings/secret_key');
        $decryptSecretKey = $this->encryptor->decrypt($secretKey);
        return $decryptSecretKey;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getTimeOutSettings()
    {
        return $timeOutArray = [
            "base_connection_timeout" => $this->getConfigValue('connection_information/base_connection_timeout'),
            "base_request_timeout" => $this->getConfigValue('connection_information/base_request_timeout'),
            "max_connection_timeout" => $this->getConfigValue('connection_information/max_connection_timeout'),
            "max_request_timeout" => $this->getConfigValue('connection_information/max_request_timeout')
        ];
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getApiVersion()
    {
        return $this->getConfigValue('settings/api_version');
    }

    /**
     * Return config field value.
     * @param string $fieldKey Field key.
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getConfigValue($fieldKey)
    {
        return $this->scopeConfig->getValue(
            $this->getConfigPath() . $fieldKey,
            ScopeInterface::SCOPE_STORE,
            $this->getStoreId()
        );
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isEnabled()
    {
        if (!$this->getSecretKey() || !$this->getSiteId()) {
            return false;
        }
        return (bool)$this->getConfigValue('settings/enabled');
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isDebugEnabled()
    {
        return (bool)$this->getConfigValue('settings/debug_mode');
    }

    /**
     * Return bool value depends of that if payment method sandbox mode
     * is enabled or not.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isSandboxMode()
    {
        return (bool)$this->getConfigValue('settings/enhanced_data_mode');
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
        if (!$this->isDebugEnabled()) {
            return false;
        }

        $this->logger->debug($prefix . json_encode($message), $data); //REMOVE LATER
        if ($type !== 'debug' || $this->isDebugEnabled()) {
            if (!isset($data['store_id'])) {
                $data['store_id'] = $this->getStoreId();
            }
            switch ($type) {
                case 'error':
                    $this->logger->error($prefix . json_encode($message), $data);
                    break;
                case 'info':
                    $this->logger->info($prefix . json_encode($message), $data);
                    break;
                case 'debug':
                default:
                    $this->logger->debug($prefix . json_encode($message), $data);
                    break;
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

    /**
     * @return mixed
     */
    public function getDeclinePre()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/decline_pre');
    }

    /**
     * @return mixed
     */
    public function getDeclinePost()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/decline_post');
    }

    /**
     * @return mixed
     */
    public function getPreThanksMsg()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/pre_thanks_msg');
    }

    /**
     * @return mixed
     */
    public function getPostThanksMsg()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/post_thanks_msg');
    }

    /**
     * @return mixed
     */
    public function isAccountTouchpointEnabled()
    {
        return $this->scopeConfig->getValue('forter/advanced_settings/enabled_account_touchpoint');
    }

    /**
     * @return mixed
     */
    public function isOrderFulfillmentEnable()
    {
        return $this->scopeConfig->getValue('forter/advanced_settings/enabled_order_fulfillment');
    }

    /**
     * @return mixed
     */
    public function getApprovePost()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/approve_post');
    }

    /**
     * @return mixed
     */
    public function getNotReviewPost()
    {
        return $this->scopeConfig->getValue('forter/immediate_post_pre_decision/not_review_post');
    }

    /**
     * @return bool
     */
    public function getIsPost()
    {
        $prePostSelect = $this->scopeConfig->getValue('forter/immediate_post_pre_decision/pre_post_select');
        return ($prePostSelect == '2' ? true : false);
    }

    /**
     * @return bool
     */
    public function getIsCron()
    {
        $prePostSelect = $this->scopeConfig->getValue('forter/immediate_post_pre_decision/pre_post_select');
        return ($prePostSelect == '3' ? true : false);
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
          ->setEntityName('order')
          ->save();
    }

    /**
     * @param $type
     * @return mixed|string
     */
    public function getPrePostDecisionMsg($type)
    {
        $result = $this->scopeConfig->getValue('forter/immediate_post_pre_decision/' . $type);
        switch ($type) {
            case 'pre_post_select':
                return ($result == '1' ? 'Before Payment Action (pre-authorization)' : 'After Payment Action (post-authorization)');
            case 'decline_pre':
                if ($result == '0') {
                    $result = 'Do nothing';
                } elseif ($result == '1') {
                    $result = 'Payment exception (stay in checkout page with error message)';
                } elseif ($result == '2') {
                    $result = 'Deletes the order session and redirects the customet back to cart page with error message';
                }
                return $result;
            case 'decline_post':
                if ($result == '3') {
                    $result = 'Do nothing';
                } elseif ($result == '1') {
                    $result = 'Cancel Order, Void or Refund Payment';
                } elseif ($result == '2') {
                    $result = 'Set to Payment Review State';
                }
                return $result;
            case 'approve_post':
              if ($result == '1') {
                  $result = 'Create Invoice and Capture Payments (CRON)';
              } elseif ($result == '2') {
                  $result = 'Create Invoice and Capture Payments (IMMEDIATELY)';
              } elseif ($result == '3') {
                  $result = 'Do Nothing';
              }
              return $result;
            case 'not_review_post':
              if ($result == '1') {
                  $result = 'Create Invoice and Capture Payments (CRON)';
              } elseif ($result == '2') {
                  $result = 'Create Invoice and Capture Payments (IMMEDIATELY)';
              } elseif ($result == '3') {
                  $result = 'Do Nothing';
              }
              return $result;
            default:
                return $result;
        }
    }
}
