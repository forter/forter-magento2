<?php
namespace Forter\Forter\Controller\Callback;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\QueueFactory;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Model\Order as OrderRepository;
use Psr\Log\LoggerInterface;

/**
 * Class Validations
 * @package Forter\Forter\Controller\Api
 */
class Validations extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface, CsrfAwareActionInterface
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
     * @var ForterLogger
     */
    private $forterLogger;
    /**
     * Validations constructor.
     * @method __construct
     * @param  AbstractApi          $abstractApi
     * @param  ScopeConfigInterface $scopeConfig
     * @param  Decline              $decline
     * @param  DateTime             $dateTime
     * @param  Config               $forterConfig
     * @param  QueueFactory         $queue
     * @param  UrlInterface         $url
     * @param  LoggerInterface      $logger
     * @param  Context              $context
     * @param  PageFactory          $pageFactory
     * @param  OrderRepository      $orderRepository
     * @param  JsonFactory          $jsonResultFactory
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
        Context $context,
        PageFactory $pageFactory,
        OrderRepository $orderRepository,
        JsonFactory $jsonResultFactory,
        ForterLogger $forterLogger
    ) {
        $this->url = $url;
        $this->queue = $queue;
        $this->decline = $decline;
        $this->dateTime = $dateTime;
        $this->scopeConfig = $scopeConfig;
        $this->_pageFactory = $pageFactory;
        $this->forterConfig = $forterConfig;
        $this->orderRepository = $orderRepository;
        $this->jsonResultFactory = $jsonResultFactory;
        $this->abstractApi = $abstractApi;
        $this->forterLogger = $forterLogger;
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
            $success = true;
            $reason = null;
            $siteId = $request->getHeader("X-Forter-SiteID");
            $key = $request->getHeader("X-Forter-Token");
            $hash = $request->getHeader("X-Forter-Signature");
            $bodyRawParams = json_decode($request->getContent(), true);

            if ($siteId != $this->forterConfig->getSiteId()) {
                throw new \Exception("Forter: Site Id Validation Failed");
            }

            if ($hash != $this->calculateHash($siteId, $key, $bodyRawParams)) {
                throw new \Exception("Forter: Incorrect Hashing");
            }

            if (is_null($bodyRawParams)) {
                throw new \Exception("Forter: Call body is empty");
            }

            $order = $this->getOrder($bodyRawParams['orderId']);

            if (!$order) {
                throw new \Exception("Forter: Unknown order");
            }

            $order->setForterResponse($request->getContent());
            $order->setForterStatus($bodyRawParams['action']);
            $order->save();

            $this->handlePostDecisionCallback($bodyRawParams['action'], $order);


            $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'Forter Callback Controller Decision');
            $message->metaData->order = $order->getData();
            $message->metaData->payment = $order->getPayment()->getData();
            $message->metaData->forterStatus = $bodyRawParams['action'];
            $this->forterLogger->SendLog($message);
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
            $success = false;
            $reason = $e->getMessage();
        }

        $response = array_filter(["action" => ($success ? "success" : "failure"), 'reason' => $reason]);
        $result = $this->jsonResultFactory->create();
        $result->setData($response);

        return $result;
    }

    /**
     * Return order entity by id
     * @param $id
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrder($orderIncrementId)
    {
        return $this->orderRepository->loadByIncrementId($orderIncrementId);
    }

    /**
     * @param $siteId
     * @param $token
     * @param $bodyRawParams
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function calculateHash($siteId, $token, $bodyRawParams)
    {
        $body = json_encode($bodyRawParams);
        $secret = $this->forterConfig->getSecretKey();
        return hash('sha256', $secret . $token . $siteId . $body);
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
            $this->setMessageToQueue($order, 'decline');
        } elseif ($result == '2') {
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
        $message = new ForterLoggerMessage($this->forterConfig->getSiteId(),  $order->getIncrementId(), 'Sending Message To Que');
        $message->metaData->order = $order->getData();
        $this->forterLogger->SendLog($message);
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
