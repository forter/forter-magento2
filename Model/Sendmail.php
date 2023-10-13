<?php

namespace Forter\Forter\Model;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Sales\Model\Order;

class Sendmail
{
    const EMAIL_TEMPLATE = 'sendmail_on_decline/email_template';
    const EMAIL_SERVICE_ENABLE = 'sendmail_on_decline/sendmail_on_decline_enabled';
    const EMAIL_SENDER = 'sendmail_on_decline/sender';
    const EMAIL_RECEIVER = 'sendmail_on_decline/receiver';

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var Config
     */
    protected $forterConfig;

    /**
     * @method __construct
     * @param  TransportBuilder $transportBuilder
     * @param  StateInterface   $inlineTranslation
     * @param  Config           $forterConfig
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        Config $forterConfig
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->forterConfig = $forterConfig;
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
        if (!$this->forterConfig->isEnabled() || !$this->forterConfig->getConfigValue(self::EMAIL_SERVICE_ENABLE)) {
            return;
        }

        $this->inlineTranslation->suspend();

        try {
            $transport = $this->transportBuilder->setTemplateIdentifier(
                $this->forterConfig->getConfigValue(self::EMAIL_TEMPLATE)
            )->setTemplateOptions([
                'area' => Area::AREA_FRONTEND,
                'store' => $this->forterConfig->getStoreId()
            ])->setTemplateVars([
                'order' => $order,
                'store' => $this->forterConfig->getCurrentStore()
            ])->setFromByScope(
                $this->forterConfig->getConfigValue(self::EMAIL_SENDER),
                $this->forterConfig->getStoreId()
            )->addTo(
                $this->forterConfig->getConfigValue(self::EMAIL_RECEIVER)
            )->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->forterConfig->log($e->getMessage(), "error");
        }

        $this->inlineTranslation->resume();

        return $this;
    }
}
