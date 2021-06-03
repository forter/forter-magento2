<?php
namespace Forter\Forter\Controller\Index;

use Forter\Forter\Model\AbstractApi;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/**
 * Class Validations
 * @package Forter\Forter\Controller\Api
 */
class Sessions extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var AbstractApi
     */
    protected $abstractApi;

    /**
     * @method __construct
     * @param  Context     $context
     * @param  AbstractApi $abstractApi
     * @param  Session     $customerSession
     */
    public function __construct(
        Context $context,
        AbstractApi $abstractApi,
        Session $customerSession
    ) {
        parent::__construct($context);
        $this->abstractApi = $abstractApi;
        $this->customerSession = $customerSession;
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

    public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
