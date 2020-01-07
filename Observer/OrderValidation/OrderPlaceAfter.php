<?php

namespace Forter\Forter\Observer\OrderValidation;

use Forter\Forter\Model\Config;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Class PaymentPlaceEnd
 * @package Forter\Forter\Observer\OrderValidation
 */
class OrderPlaceAfter implements ObserverInterface
{

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var Config
     */
    private $forterConfig;

    /**
     * PaymentPlaceEnd constructor.
     * @param ManagerInterface $messageManager
     * @param Config $config
     */
    public function __construct(
        ManagerInterface $messageManager,
        Config $forterConfig
    ) {
        $this->messageManager = $messageManager;
        $this->forterConfig = $forterConfig;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return bool|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->forterConfig->isEnabled() || !$this->forterConfig->getIsPost()) {
            return false;
        }

        try {
            $order = $observer->getEvent()->getOrder();
            $status = $order->getForterStatus();

            if ($status == "decline") {
                $this->messageManager->getMessages(true);
                $this->messageManager->addErrorMessage($this->forterConfig->getPostThanksMsg());
            }

            return false;
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }
}
