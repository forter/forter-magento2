<?php

namespace Forter\Forter\Helper;

use Forter\Forter\Model\Entity as ForterEntity;
use Forter\Forter\Model\EntityFactory as ForterEntityFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;

class EntityHelper
{
    const FORTER_STATUS_WAITING = "waiting_for_data";
    const FORTER_STATUS_PRE_POST_VALIDATION = "pre_post_validation";
    const FORTER_STATUS_COMPLETE = "complete";
    const FORTER_STATUS_NEW = "new";

    /**
     * @var ForterEntity
     */
    protected $forterEntity;

    /**
     * @var ForterEntityFactory
     */
    protected $forterEntityFactory;

    /**
     * @var DateTime
     */
    private $dateTime;

    public function __construct(
        ForterEntity $forterEntity,
        ForterEntityFactory $forterEntityFactory,
        DateTime $dateTime
    ) {
        $this->forterEntity = $forterEntity;
        $this->forterEntityFactory = $forterEntityFactory;
        $this->dateTime = $dateTime;
    }

    public function createForterEntity($order, $storeId, $validationType)
    {
        $currentTime = $this->dateTime->gmtDate();
        return $this->forterEntity
            ->setStoreId($storeId)
            ->setOrderIncrementId($order->getIncrementId())
            ->setPaymentMethod($order->getPayment()->getMethod())
            ->setValidationType($validationType)
            ->save();
    }

    public function getForterEntityByIncrementId($incrementId, $statuses = [])
    {
        if (!$incrementId) {
            return null;
        }

        $collection = $this->forterEntityFactory
            ->create()
            ->getCollection()
            ->addFieldToFilter('order_increment_id', $incrementId)
            ->setOrder('created_at', 'DESC');

        if (!empty($statuses)) {
            $collection->addFieldToFilter('status', ['in' => $statuses]);
        }

        return $collection->getFirstItem();
    }

    public function getForterEntitiesPreSync()
    {
        return $this->forterEntityFactory
            ->create()
            ->getCollection()
            ->addFieldToFilter('status', ['in' => [self::FORTER_STATUS_WAITING, self::FORTER_STATUS_NEW, self::FORTER_STATUS_PRE_POST_VALIDATION]])
            ->addFieldToFilter('sync_flag', 0)
            ->addFieldToFilter('retries', ['lt' => 10])
            ->addFieldToFilter(
                'updated_at',
                [
                    'from' => date('Y-m-d H:i:s', strtotime('-7 days'))
                ]
            )->addFieldToFilter(
                'updated_at',
                [
                    'to' => date('Y-m-d H:i:s')
                ]
            );
    }

    public function getForterEntitiesPostDecisionAction()
    {
        return $this->forterEntityFactory
            ->create()
            ->getCollection()
            ->addFieldToFilter('status', ['in' => [self::FORTER_STATUS_COMPLETE]])
            ->addFieldToFilter('sync_flag', 1)
            ->addFieldToFilter('post_decision_actions_flag', 0)
            ->addFieldToFilter('entity_type', 'order');
    }

    public function updateForterEntity($forterEntity, $order, $forterResponse, $message)
    {
        $forterStatus = $forterResponse->status ?? '';

        if ($forterResponse->status != 'success') {
            $forterStatus = 'error';
        }

        if (!$order->getData('isPreAndPost')) {
            if ($forterResponse->status === 'success' && ($forterResponse->action === 'approve' || $forterResponse->action === 'decline')) {
                $forterEntity->setStatus(self::FORTER_STATUS_COMPLETE);
            }
        } else {
            $forterEntity->setStatus(self::FORTER_STATUS_PRE_POST_VALIDATION);
        }

        if ($forterResponse) {
            $forterEntity->setForterResponse(json_encode($forterResponse));
        }

        if ($forterResponse && isset($forterResponse->action)) {
            $forterEntity->setForterAction($forterResponse->action);
        }

        if ($message) {
            if (!is_string($message)) {
                $message = json_encode($message);
                $forterEntity->setAdditionalInformation($message);
            } else {
                $forterEntity->setAdditionalInformation($message);
            }
        }
        if ($forterResponse && isset($forterResponse->reasonCode)) {
            $forterEntity->setForterReason($forterResponse->reasonCode);
        }

        $forterEntity->setForterStatus($forterStatus);
        $forterEntity->save();
    }
}
