<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\QueueFactory;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class SendQueue
 * @package Forter\Forter\Cron
 */
class SendQueue
{
    /**
     *
     */
    const MAPPER_LOCATION = 'https://dev-file-dump.fra1.digitaloceanspaces.com/mapper.json';
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var ForterLogger
     */
    private $forterLogger;

    public function __construct(
        Config $config,
        AbstractApi $abstractApi,
        ForterLogger $forterLogger
    ) {
        $this->forterConfig = $config;
        $this->abstractApi = $abstractApi;
        $this->forterLogger = $forterLogger;
    }

    /**
     * Process items in Queue
     */
    public function execute()
    {
        try {
                $message = new ForterLoggerMessage($order->getStoreId(),  $order->getIncrementId(), 'CRON Validation');
                $message->metaData->order = $order;
                $message->proccessItem = $item;
                $this->forterLogger->SendLog($message);
            }
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    /**
     * this method will handle the pre payment and validation state
     *
     * @param $order holde the order model
     * @param $item hold the item from the que that can get from at rest of the models such as order and more (use as ref incase this method override it will be in use)
     * @return void
     */
    private function handlePreSyncMethod($order, $item)
    {
        try {
            $data = $this->requestBuilderOrder->buildTransaction($order, 'AFTER_PAYMENT_ACTION');
            $paymentMethod = $data['payment'][0]['paymentMethodNickname'];

            if ($paymentMethod == 'adyen_cc') {
                if (!isset($data['payment'][0]['creditCard'])) {
                    return false;
                }
                $creditCard = $data['payment'][0]['creditCard'];
                if (!array_key_exists('expirationMonth', $creditCard) || !array_key_exists('expirationYear', $creditCard) || !array_key_exists('lastFourDigits', $creditCard)) {
                    return false;
                }
            }

            $url = self::VALIDATION_API_ENDPOINT . $order->getIncrementId();

            $response = $this->abstractApi->sendApiRequest($url, json_encode($data));
            $responseArray = json_decode($response);

            $this->abstractApi->sendOrderStatus($order);

            $order->setForterResponse($response);

            if ($responseArray->status != 'success' || !isset($responseArray->action)) {
                $order->setForterStatus('error');
                $order->save();
                return true;
            }

            $this->handleForterResponse($order, $responseArray->action);
            $order->addStatusHistoryComment(__('Forter (cron) Decision: %1', $responseArray->action));
            $order->setForterStatus($responseArray->action);
            $order->save();

            $message = new ForterLoggerMessage($order->getStoreId(),  $order->getIncrementId(), 'Forter CRON Decision');
            $message->metaData->order = $order;
            $message->metaData->forterStatus = $responseArray->action;
            $this->forterLogger->SendLog($message);

            return $responseArray->status ? true : false;
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

}
