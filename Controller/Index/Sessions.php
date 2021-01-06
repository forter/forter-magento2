<?php
namespace Forter\Forter\Controller\Index;

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
     * @param \Magento\Framework\App\Action\Context
     * @param \Magento\Framework\View\Result\PageFactory
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        PageFactory $pageFactory
    ) {
        $this->_pageFactory = $pageFactory;
        $this->customerSession = $customerSession;
        return parent::__construct($context);
    }

    /**
     *
     */
    public function execute()
    {
        $post = $this->getRequest()->getPostValue();
        $this->customerSession->setForterToken($post['token']);
    }
}
