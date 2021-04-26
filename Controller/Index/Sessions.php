<?php
namespace Forter\Forter\Controller\Index;

use Forter\Forter\Model\AbstractApi;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Validations
 * @package Forter\Forter\Controller\Api
 */
class Sessions extends \Magento\Framework\App\Action\Action
{
    /**
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     * @param \Magento\Framework\App\Action\Context
     * @param \Magento\Framework\View\Result\PageFactory
     */
    public function __construct(
        AbstractApi $abstractApi,
        Context $context,
        Session $customerSession,
        PageFactory $pageFactory
    ) {
        $this->_pageFactory = $pageFactory;
        $this->customerSession = $customerSession;
        $this->abstractApi = $abstractApi;
        return parent::__construct($context);
    }

    /**
     *
     */
    public function execute()
    {
        try {
            $forterToken = $this->getRequest()->getHeader('Forter-Token');
            if ($forterToken) {
                $this->customerSession->setForterToken($forterToken);
            }

            $bin = $this->getRequest()->getHeader('bin');
            $this->customerSession->setForterBin($bin);

            $last4cc = $this->getRequest()->getHeader('last4cc');
            $this->customerSession->setForterLast4cc($last4cc);
        } catch (Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
