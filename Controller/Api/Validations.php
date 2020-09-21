<?php
namespace Forter\Forter\Controller\Api;

/**
 * Class Validations
 * @package Forter\Forter\Controller\Api
 */
class Validations extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_pageFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var
     */
    private $logger;

    /**
     * Validations constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\View\Result\PageFactory $pageFactory)
    {
        $this->_pageFactory = $pageFactory;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        return parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $success = true;
        $reason = null;
        try {
            // validate call from forter
            $request = $this->getRequest();
            $siteid = $request->getHeader("X-Forter-SiteID");
            $key = $request->getHeader("X-Forter-Token");
            $hash = $request->getHeader("X-Forter-Signature");
            $postData = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");

//            if ($hash != $this->_getHelper()->calculateHash($siteid, $key, $post_data)) {
//                throw new Exception("Forter: Invalid call");
//            }

//            if ($siteid != $this->_getHelper()->getSiteID()) {
//                throw new Exception("Forter: Invalid call");
//            }

            $json_request = json_decode($postData);
//            if (is_null($json_request)) {
//                throw new Exception("Forter: Invalid call");
//            }

            // load order
            $orderId = $request->getParam('order_id');
            $order = $this->getOrder($orderId);

            // validate order
            if (!$order->getId()) {
                throw new Exception("Forter: Unknown order_id {$orderId}");
            }

            if (!$order->getForterSent()) {
                throw new Exception("Forter: Order was never sent to Forter [id={$orderId}]");
            }

//            if (!in_array($order->getForterStatus(), array(Forter_Extension_Helper_Data::FORTER_STATUS_PENDING, Forter_Extension_Helper_Data::FORTER_STATUS_PENDING_APPROVE))) {
//                throw new Exception("Forter: Order status does not allow action.[id={$order_id}, status={$order->getForterStatus()}");
//            }

            // handle action
//            $this->_getHelper()->handleAutoCaptureCallback($json_request->action, $json_request->reason, $order);

        } catch (Exception $e) {
            $this->logger->critical('Error message', ['exception' => $e]);

            $success = false;
            $reason = $e->getMessage();
        }

        // build response
        $response = array_filter(array("action" => ($success ? "success" : "failure"), 'reason' => $reason));

        return json_encode($response);
    }

    /**
     * Return order entity by id
     * @param $id
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrder($id)
    {
        return $this->orderRepository->get($id);
    }
}