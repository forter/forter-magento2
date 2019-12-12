<?php

namespace Forter\Forter\Model\RequestHandler;

use Exception;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory;
use Magento\Sales\Model\Service\InvoiceService;

/**
 * Class Approve
 * @package Forter\Forter\Model\RequestHandler
 */
class Approve
{
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
        CollectionFactory $invoiceCollectionFactory,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository
    )
    {
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
            if ($order) {
                $invoices = $this->_invoiceCollectionFactory->create()
                    ->addAttributeToFilter('order_id', ['eq' => $order->getId()]);

                $invoices->getSelect()->limit(1);

                if ((int)$invoices->count() !== 0) {
                    $invoices = $invoices->getFirstItem();
                    $invoice = $this->_invoiceRepository->get($invoices->getId());
                    return $invoice;
                }

                if (!$order->canInvoice()) {
                    return null;
                }

                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->getOrder()->setCustomerNoteNotify(false);
                $invoice->getOrder()->setIsInProcess(true);
                $order->addStatusHistoryComment(__('Invoice Has been Created by Forter'), false);
                $transactionSave = $this->_transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                $transactionSave->save();

                return $invoice;
            }
        } catch (Exception $e) {
            throw new LocalizedException(
                __($e->getMessage())
            );
        }
    }
}
