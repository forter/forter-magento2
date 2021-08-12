<?php
namespace Forter\Forter\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Sendmail
{
    const EMAIL_TEMPLATE = 'forter/sendmail_on_decline/email_template';

    const EMAIL_SERVICE_ENABLE = 'forter/sendmail_on_decline/enabled';

    const SENDER_EMAIL = 'forter/sendmail_on_decline/sender';

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param StoreManagerInterface $storeManager
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param LoggerInterface $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Send Mail
     * @param  Order $order
     * @return $this
     *
     * @throws LocalizedException
     * @throws MailException
     */
    public function sendMail(Order $order)
    {
        $storeId = $order->getStoreId();

        if (!$this->getConfigValue(self::EMAIL_SERVICE_ENABLE, $storeId)) {
            return;
        }

        $this->inlineTranslation->suspend();

        $transport = $this->transportBuilder->setTemplateIdentifier(
            $this->getConfigValue(self::EMAIL_TEMPLATE, $storeId)
        )->setTemplateOptions([
            'area' => Area::AREA_FRONTEND,
            'store' => $storeId
        ])->setTemplateVars([
            'message_1' => 'CUSTOM MESSAGE STR 1',
            'message_2' => 'custom message str 2',
            'store' => $storeId
        ])->setFromByScope(
            $this->getConfigValue(self::SENDER_EMAIL, $storeId),
            $storeId
        )->addTo(
            $order->getCustomerEmail()
        )->getTransport();

        try {
            $transport->sendMessage();
        } catch (\Exception $exception) {
            $this->logger->critical($exception->getMessage());
        }

        $this->inlineTranslation->resume();

        return $this;
    }

    /**
     * Return store configuration value
     *
     * @param string $path
     * @param int $storeId
     * @return mixed
     */
    protected function getConfigValue($path, $storeId)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
