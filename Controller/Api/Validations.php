<?php
namespace Forter\Forter\Controller\Api;

/**
 * Class Validations
 * @package Forter\Forter\Controller\Api
 */
class Validations extends \Magento\Framework\App\Action\Action
{
    const XML_FORTER_SECRET_KEY = "forter_settings_secret_key";
    const XML_FORTER_SITE_ID = "forter_settings_site_id";
    const FORTER_RESPONSE_DECLINE = 'decline';
    const FORTER_RESPONSE_PENDING = 'resending';
    const FORTER_RESPONSE_APPROVE = 'approve';
    const FORTER_RESPONSE_NOT_REVIEWED = 'not reviewed';
    const FORTER_RESPONSE_PENDING_APPROVE = 'pending';


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
            $siteId = $request->getHeader("X-Forter-SiteID");
            $key = $request->getHeader("X-Forter-Token");
            $hash = $request->getHeader("X-Forter-Signature");
            $postData = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents("php://input");

            if ($hash != $this->calculateHash($siteId, $key, $postData)) {
                throw new Exception("Forter: Invalid call");
            }

            if ($siteId != $this->getSiteId()) {
                throw new Exception("Forter: Invalid call");
            }

            $jsonRequest = json_decode($postData);
            if (is_null($jsonRequest)) {
                throw new Exception("Forter: Invalid call");
            }

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

            if (!$order->getForterStatus()) {
                throw new Exception("Forter: Order status does not allow action.[id={$orderId}, status={$order->getForterStatus()}");
            }

            // handle action
            $this->handleAutoCaptureCallback($jsonRequest->action, $jsonRequest->reason, $order);

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

    /**
     * @param $siteId
     * @param $token
     * @param $body
     * @return string
     */
    public function calculateHash($siteId, $token, $body)
    {
        $secert = $this->getSecretKey();
        return hash('sha256', $secert . $token . $siteId . $body);
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getSecretKey($storeId = null)
    {
        $secretKey = $this->scopeConfig->getValue(self::XML_FORTER_SECRET_KEY, $storeId);

        return $secretKey;
    }

    /**
     * @param null $storeId
     * @return mixed
     */
    public function getSiteId($storeId = null)
    {
        $siteId = $this->scopeConfig->getValue(self::XML_FORTER_SITE_ID, $storeId);

        return $siteId;
    }

    /**
     * @param $forter_action
     * @param $forter_message
     * @param $order
     */
    public function handleAutoCaptureCallback($forter_action, $forter_message, $order)
    {
        if ($forter_action == "decline") {
            $this->handleResponseDecline($order, $forter_message);
        } elseif ($forter_action == "approve") {
            $this->handleResponseApprove($order, $forter_message);
        } elseif ($forter_action == "not reviewed") {
            $this->handleResponseNotReviewed($order, $forter_message);
        } else {
            throw new Exception("Forter: Unsupported action from Forter");
        }
    }


    /**
     * @param $order
     * @param $message
     */
    public function handleResponseDecline($order, $message)
    {
        //will be updated soon according to M2 structure
    }

    /**
     * @param $order
     * @param $message
     */
    public function handleResponseApprove($order, $message)
    {
        //will be updated soon according to M2 structure
    }

    /**
     * @param $order
     * @param $message
     */
    public function handleResponseNotReviewed($order, $message)
    {
        $order->setForterStatus(self::FORTER_STATUS_NOT_REVIEWED);
        $order->addStatusHistoryComment("Forter: {$message}", false);
        $order->save();
    }
}