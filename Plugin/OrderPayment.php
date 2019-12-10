<?php
namespace Forter\Forter\Plugin;

use Magento\Framework\App\ResponseFactory;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order\Payment;

/**
 * Class CustomerAfterSave
 * @package [Vendor_Name]\[Module_Name]\Plugin
 */
class OrderPayment
{
    public function __construct(
        ResponseFactory $responseFactory,
        UrlInterface $url
    ) {
        $this->responseFactory = $responseFactory;
        $this->url = $url;
    }

    /**
     * @param CustomerRepository $subject
     * @param $savedCustomer
     * @return mixed
     */
    public function aroundPlace(
        Payment $subject,
        callable $proceed
    ) {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/test123123.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $redirectionUrl = $this->url->getUrl('checkout/cart/index');
        $this->responseFactory->create()->setRedirect($redirectionUrl)->sendResponse();
        die;

        $logger->info('before payment');
        $result = $proceed();
        $logger->info('after payment');
        return $result;
    }
}
