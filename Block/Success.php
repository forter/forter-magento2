<?php
namespace Forter\Forter\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;

class Success extends \Magento\Framework\View\Element\Template
{
    /**
     * Success constructor.
     * @param CustomerSession $customerSession
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
    }

    public function getMsg()
    {
        $msg = $this->customerSession->getForterMessage();
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test11.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($msg);
        if (!$msg) {
            return false;
        }

        $this->customerSession->unsForterMessage();

        return $msg;
    }
}
