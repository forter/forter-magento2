<?php

namespace Forter\Forter\Model\ActionsHandler;

use Exception;
use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config as ForterConfig;
use Magento\Framework\DB\TransactionFactory;
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
     * @var ForterConfig
     */
    private $forterConfig;
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
     * @param ForterConfig $forterConfig
     * @param TransactionFactory $transactionFactory
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        AbstractApi $abstractApi,
        CollectionFactory $invoiceCollectionFactory,
        InvoiceService $invoiceService,
        ForterConfig $forterConfig,
        TransactionFactory $transactionFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->abstractApi = $abstractApi;
        $this->forterConfig = $forterConfig;
        $this->_invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
        $this->_invoiceRepository = $invoiceRepository;
        $this->_orderRepository = $orderRepository;
    }

    /**
     * @param $order
     * @return \Magento\Sales\Api\Data\InvoiceInterface|Invoice|null
     */
    public function handleApproveImmediatly($order)
    {
        try {
            if ($order->getPayment() && $this->forterConfig->isActionExcludedPaymentMethod($order->getPayment()->getMethod(), null, $order->getStoreId())) {
                return false;
            }
            $paymentBaseAmountAuthorized = $order->getPayment()->getBaseAmountAuthorized() ?? $order->getPayment()->getAmountAuthorized();

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
                    $this->forterConfig->addCommentToOrder($order, 'Forter: Invoice Already Created for This Order');
                    return $invoice;
                }

                if (!$order->canInvoice()) {
                    $order->setCustomerNoteNotify(false);
                    $order->setIsInProcess(true);
                    $this->forterConfig->addCommentToOrder($order, 'Magento failed Creating invoice');
                    return null;
                }

                $invoice = $this->_invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->getOrder()->setCustomerNoteNotify(false);
                $invoice->getOrder()->setIsInProcess(true);
                $this->forterConfig->addCommentToOrder($order, 'Invoice Has been Created');
                $transactionSave = $this->_transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                $transactionSave->save();

                return $invoice;
            }
        } catch (\Exception $e) {
            $this->forterConfig->addCommentToOrder($order, 'Forter: Magento Failed to Create Invoice. Internal Error');
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }
}
