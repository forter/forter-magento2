<?php
namespace Forter\Forter\Controller\Callback;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\QueueFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Validations
 * @package Forter\Forter\Controller\Api
 */
class Validations extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{

    /**
      * @var AbstractApi
      */
    protected $abstractApi;
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
     * @var
     */
    protected $scopeConfig;

    /**
     * @var
     */
    protected $jsonResultFactory;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $url;

    /**
     * Validations constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Forter\Forter\Model\Config $forterConfig
     * @param \Forter\Forter\Model\QueueFactory $queue
     * @param \Magento\Framework\UrlInterface $url
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
     */
    public function __construct(
        AbstractApi $abstractApi,
        ScopeConfigInterface $scopeConfig,
        Decline $decline,
        DateTime $dateTime,
        Config $forterConfig,
        QueueFactory $queue,
        UrlInterface $url,
        LoggerInterface $logger,
        Session $customerSession,
        Context $context,
        PageFactory $pageFactory,
        OrderRepositoryInterface $orderRepository,
        JsonFactory $jsonResultFactory
    ) {
        $this->url = $url;
        $this->queue = $queue;
        $this->decline = $decline;
        $this->dateTime = $dateTime;
        $this->scopeConfig = $scopeConfig;
        $this->_pageFactory = $pageFactory;
        $this->forterConfig = $forterConfig;
        $this->orderRepository = $orderRepository;
        $this->customerSession = $customerSession;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->abstractApi = $abstractApi;
        return parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        if (!$this->forterConfig->isEnabled() || !$this->forterConfig->isDecisionControllerEnabled()) {
            return;
        }

        $request = $this->getRequest();
        $method = $request->getMethod();

        if (!$method == "POST") {
            $norouteUrl = $this->url->getUrl('noroute');
            $this->getResponse()->setRedirect($norouteUrl);
        }

        try {
            $siteId = $request->getHeader("X-Forter-SiteID");
            $key = $request->getHeader("X-Forter-Token");
            $hash = $request->getHeader("X-Forter-Signature");

            if ($siteId != $this->forterConfig->getSiteId()) {
                throw new \Exception("Forter: Site Id Validation Faild");
            }

            $requestParams = $request->getParams();
            $bodyRawParams = json_decode($request->getContent(), true);
            $params = array_merge($requestParams, $bodyRawParams);

            if ($hash != $this->calculateHash($siteId, $key, $params)) {
                //throw new \Exception("Forter: Incorrect Hashing");
            }

            $jsonRequest = $params;
            if (is_null($jsonRequest)) {
                throw new \Exception("Forter: Call body is empty");
            }

            $orderId = $request->getParam('order_id');

            $order = $this->getOrder($orderId);

            if (!$order) {
                throw new \Exception("Forter: Unknown order");
            }

            $order->setForterResponse($request->getContent());
            $order->setForterStatus($jsonRequest['action']);
            $order->save();

            $this->handlePostDecisionCallback($jsonRequest['action'], $order);
        } catch (Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }

        return;
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
    public function calculateHash($siteId, $token, $params)
    {
        $body = "";
        $paramAmount =  sizeof($params);
        $counter = 1;
        foreach ($params as $param => $value) {
            if ($paramAmount == $counter) {
                $body .= $param . "=" . $value;
            } else {
                $body .= $param . "=" . $value . "&";
            }
            $counter++;
        }

        $secert = $this->forterConfig->getSecretKey();
        return hash('sha256', $secert . $token . $siteId . $body);
    }

    /**
     * @param $forterDecision
     * @param $order
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function handlePostDecisionCallback($forterDecision, $order)
    {
        if ($forterDecision == "decline") {
            $this->handleDecline($order);
        } elseif ($forterDecision == 'approve') {
            $this->handleApprove($order);
        } elseif ($forterDecision == "not reviewed") {
            $this->handleNotReviewed($order);
        } else {
            throw new \Exception("Forter: Unsupported action from Forter");
        }
    }

    /**
     * @param $order
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
