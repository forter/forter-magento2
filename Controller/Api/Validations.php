<?php
namespace Forter\Forter\Controller\Api;

use Forter\Forter\Model\Config;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Forter\Forter\Model\ActionsHandler\Decline as Decline;
use Forter\Forter\Model\ActionsHandler\Approve as Approve;
use Magento\Customer\Model\Session as CustomerSession;
use Forter\Forter\Model\QueueFactory as ForterQueueFactory;

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
     * @var Config
     */
    protected $forterConfig;

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
    protected $logger;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var ForterQueueFactory
     */
    protected $queue;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var Decline
     */
    protected $decline;

    /**
     * @var Approve
     */
    protected $approve;

    /**
     * Validations constructor.
     * @param Decline $decline
     * @param DateTime $dateTime
     * @param Config $forterConfig
     * @param Approve $approve
     * @param ForterQueueFactory $queue
     * @param \Psr\Log\LoggerInterface $logger
     * @param CustomerSession $customerSession
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Decline $decline,
        DateTime $dateTime,
        Config $forterConfig,
        Approve $approve,
        ForterQueueFactory $queue,
        \Psr\Log\LoggerInterface $logger,
        CustomerSession $customerSession,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository)
    {
        $this->queue = $queue;
        $this->logger = $logger;
        $this->decline = $decline;
        $this->approve = $approve;
        $this->dateTime = $dateTime;
        $this->_pageFactory = $pageFactory;
        $this->forterConfig = $forterConfig;
        $this->orderRepository = $orderRepository;
        $this->customerSession = $customerSession;
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
            $this->handleAutoCaptureCallback($jsonRequest->action, $order);

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
    public function handleAutoCaptureCallback($forter_action, $order)
    {
        if ($forter_action == "decline") {
            $this->decline->handlePostTransactionDescision($order);
        } elseif ($forter_action == "approve") {
            $this->approve->handleApprove($order);
        } elseif ($forter_action == "not reviewed") {
            $this->handleNotReviewed($order);
        } else {
            throw new Exception("Forter: Unsupported action from Forter");
        }
    }

    /**
     * @param $order
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function handleNotReviewed($order)
    {
        $result = $this->forterConfig->getNotReviewPost();
        if ($result == '1') {
            $this->setMessageToQueue($order, 'approve');
        }
    }
}