<?php

namespace Forter\Forter\Ui\Component\Listing\Column;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Forter\Forter\Model\Config as ForterConfig;
use Forter\Forter\Helper\EntityHelper;

/**
 * Class ForterStatus
 * @package Forter\Forter\Ui\Component\Listing\Column
 */
class ForterStatus extends Column
{
    /**
     * @var ForterConfig
     */
    private $forterConfig;

    /**
     * @var OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteria;

    /**
     * @var EntityHelper
     */
    protected $entityHelper;

    /**
     * ForterStatus constructor.
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $criteria
     * @param ForterConfig $forterConfig
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $criteria,
        ForterConfig $forterConfig,
        EntityHelper $entityHelper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_orderRepository = $orderRepository;
        $this->_searchCriteria = $criteria;
        $this->forterConfig = $forterConfig;
        $this->entityHelper = $entityHelper;
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $order = $this->_orderRepository->get($item["entity_id"]);
                $forterEntity = $this->entityHelper->getForterEntityByIncrementId($order->getIncrementId());
                $columnData = $forterEntity->getForterStatus();
                if (($forterResponse = $forterEntity->getForterResponse())) {
                    $forterResponse = json_decode((string) $forterResponse);
                    $columnData .= $this->forterConfig->getResponseRecommendationsNote($forterResponse, false);
                }
                $item[$this->getData('name')] = $columnData;
            }
        }
        return $dataSource;
    }
}
