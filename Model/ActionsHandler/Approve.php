<?php

namespace Forter\Forter\Model\ActionsHandler;

use Exception;
use Forter\Forter\Model\AbstractApi;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;

/**
 * Class Approve
 * @package Forter\Forter\Model\ActionsHandler
 */
class Approve
{
    /**
      * @var AbstractApi
      */
    private $abstractApi;
    /**
     * @var CollectionFactory
     */
    private $_invoiceCollectionFactory;
    /**
     * @var InvoiceService
     */
    private $_invoiceService;
    /**
     * @var TransactionFactory
     */
    private $_transactionFactory;
    /**
     * @var InvoiceRepositoryInterface
     */
    private $_invoiceRepository;
    /**
     * @var OrderRepositoryInterface
     */
    private $_orderRepository;

    /**
     * @param CollectionFactory $invoiceCollectionFactory
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        AbstractApi $abstractApi,
        CollectionFactory $invoiceCollectionFactory,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->abstractApi = $abstractApi;
        $this->_invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->_invoiceRepository = $invoiceRepository;
        $this->_orderRepository = $orderRepository;
    }

    /**
     * @param $order
     * @return \Magento\Sales\Api\Data\InvoiceInterface|Invoice|null
     * @throws LocalizedException
     */
    public function handleApproveImmediatly($order)
    {
        try {
            $paymentBaseAmountAuthorized = $order->getPayment()->getBaseAmountAuthorized();

            if (!$paymentBaseAmountAuthorized || !($paymentBaseAmountAuthorized > 0)) {
                return false;
            }

            if ($order) {
                $invoices = $this->_invoiceCollectionFactory->create()
                    ->addAttributeToFilter('order_id', ['eq' => $order->getId()]);

                $invoices->getSelect()->limit(1);

                if ((int)$invoices->count() !== 0) {
                    $invoices = $invoices->getFirstItem();
                    $invoice = $this->_invoiceRepository->get($invoices->getId());
                    $order->addStatusHistoryComment(__('Forter: Invoice Already Created for This Order'), false);
                    return $invoice;
                }

                if (!$order->canInvoice()) {
                    $order->setCustomerNoteNotify(false);
                    $order->setIsInProcess(true);
                    $order->addStatusHistoryComment(__('Forter: Magento Failed to Create Invoice. Order Cannot Be Invoiced'), false);
                    return null;
                }

                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->getOrder()->setCustomerNoteNotify(false);
                $invoice->getOrder()->setIsInProcess(true);
                $order->addStatusHistoryComment(__('Forter: Invoice Has been Created'), false);
                $transactionSave = $this->_transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                $transactionSave->save();

                return $invoice;
            }
        } catch (Exception $e) {
            $order->addStatusHistoryComment(__('Forter: Magento Failed to Create Invoice. Internal Error'), false);
            $this->abstractApi->reportToForterOnCatch($e);
            throw new \Exception($e->getMessage());
        }
    }
}
