<?php
namespace Forter\Forter\Controller\Api;

use Forter\Forter\Model\Config;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Forter\Forter\Model\ActionsHandler\Decline;
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
     * Validations constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     */
    public function __construct(
        Decline $decline,
        DateTime $dateTime,
        Config $forterConfig,
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
            $this->handleDecline($order);
        } elseif ($forter_action == "approve") {
            $this->handleApprove($order);
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

    /**
     * @param $order
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function handleApprove($order)
    {
        $result = $this->forterConfig->getApprovePost();
        if ($result == '1') {
            $this->setMessageToQueue($order, 'approve');
        }
    }

    /**
     * @param $order
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function handleDecline($order)
    {
        $result = $this->forterConfig->getDeclinePost();
        if ($result == '1') {
            $this->customerSession->setForterMessage($this->forterConfig->getPostThanksMsg());
            if ($order->canHold()) {
                $order->setCanSendNewEmailFlag(false);
                $this->decline->holdOrder($order);
                $this->setMessageToQueue($order, 'decline');
            }
        } elseif ($result == '2') {
            $order->setCanSendNewEmailFlag(false);
            $this->decline->markOrderPaymentReview($order);
        }
    }

    /**
     * @param $order
     * @param $type
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setMessageToQueue($order, $type)
    {
        $storeId = $order->getStore()->getId();
        $currentTime = $this->dateTime->gmtDate();
        $this->forterConfig->log('Increment ID:' . $order->getIncrementId());
        $this->queue->create()
            ->setStoreId($storeId)
            ->setEntityType('order')
            ->setIncrementId($order->getIncrementId())
            ->setEntityBody($type)
            ->setSyncDate($currentTime)
            ->save();
    }
}