<?php

namespace Forter\Forter\Test\Integration;

use Magento\Config\Model\Config;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Class OrderPlacementTest
 */
class OrderPlacementTestCheckmoCronAuth extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Config
     */
    private $config;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->registry = $this->objectManager->get(Registry::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->config = $this->objectManager->get(Config::class);

        $this->saveConfigSettings();
        $this->loadCustomerApproveDataFixture();
    }

    private function saveConfigSettings()
    {
        require __DIR__ . '/fixtures/settings/forter_settings.php';

        $configValues = [
            'forter/settings/enabled' => '1',
            'forter/settings/site_id' => $forterSettings['site_id'],
            'forter/settings/secret_key' => $forterSettings['secret_key'],
            'forter/settings/api_version' => $forterSettings['api_version'],
            'forter/advanced_settings_cc_listener/class_id_identifier' => null,
            'forter/immediate_post_pre_decision/pre_post_select' => '3',
            'forter/immediate_post_pre_decision/decline_cron' => '2',
            'forter/immediate_post_pre_decision/not_review_cron' => '1',
            'forter/immediate_post_pre_decision/approve_cron' => '2'
        ];

        foreach ($configValues as $path => $value) {
            $this->config->setDataByPath($path, $value);
        }

        $this->config->save();
    }

    /**
     * @magentoDataFixture loadShippingDataFixture
     * @magentoDataFixture loadPaymentDataFixture
     * @magentoDataFixture loadProductDataFixture
     */
    public function testOrderApproveCheckmoPaymentCronAuthorization()
    {
        $this->rollbackDb = false;

        $customer = $this->customerRepository->get('approve@forter.com');
        $shippingAddress = $this->registry->registry('shipping_address');
        $paymentMethod = $this->registry->registry('checkmo_payment_method');

        // Load product by SKU
        $productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $product = $productRepository->get('simple-product-forter');

        // Create a quote
        $quote = $this->objectManager->create(\Magento\Quote\Model\Quote::class);
        $quote->setStoreId(1)
            ->setCurrency()
            ->assignCustomer($customer);

        $quote->addProduct($product, 1); // Adding one unit of the product

        // Set billing and shipping addresses
        $quote->getBillingAddress()->addData($shippingAddress->getData());
        $quote->getShippingAddress()->addData($shippingAddress->getData());

        // Set shipping method
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('flatrate_flatrate'); // Replace with your desired shipping method

        // Set dummy checkout session to avoid error in PaymentDataImportObserver
        $checkoutSession = $this->objectManager->get(\Magento\Checkout\Model\Session::class);
        $checkoutSession->setQuoteId($quote->getId());

        // Set payment method
        $quote->getPayment()->setQuote($quote);
        $quote->getPayment()->importData(['method' => $paymentMethod->getCode()]);

        // Collect totals and save the quote
        $quote->collectTotals()->save();

        // Convert the quote to an order
        $quoteManagement = $this->objectManager->create(\Magento\Quote\Api\CartManagementInterface::class);
        $orderId = $quoteManagement->placeOrder($quote->getId());

        //Executing Cron
        $cron = $this->objectManager->create(\Forter\Forter\Cron\PostDecisionActions::class);
        $cron->execute();

        //Load queue collection
        $queueCollection = $this->objectManager->create(\Forter\Forter\Model\ResourceModel\Queue\Collection::class);
        $queueCollection->setOrder('sync_id', 'DESC')->setPageSize(1)->setCurPage(1);
        $orderQueue = $queueCollection->getFirstItem();

        //Load the order
        $order = $this->objectManager->create(\Magento\Sales\Model\Order::class);
        $order->load($orderId);

        // Verify the order placement
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($customer->getId(), $order->getCustomerId());
        $this->assertEquals($paymentMethod->getCode(), $order->getPayment()->getMethod());

        $syncFlag = $orderQueue->getData('sync_flag');
        $this->assertEquals('1', $syncFlag);

        $forterStatus = $order->getData('forter_status');
        $this->assertEquals('approve', $forterStatus);

        //Create Invoice
        $this->createInvoice($order);
        $order->load($orderId);
        $this->assertEquals('pending', $order->getStatus());

        //Create Shipment
        $this->createShipment($order);
        $order->load($orderId);
        $this->assertEquals('complete', $order->getStatus());

        //create Credit Memo
        $this->createCreditMemo($order);
        $order->load($orderId);
        $this->assertEquals('closed', $order->getStatus());
    }

    private function createCreditMemo(Order $order)
    {
        $creditmemoFactory = $this->objectManager->create(\Magento\Sales\Model\Order\CreditmemoFactory::class);
        $creditmemo = $creditmemoFactory->createByOrder($order);
        $creditmemoService = $this->objectManager->create(\Magento\Sales\Model\Service\CreditmemoService::class);
        $creditmemoService->refund($creditmemo, false, false);
    }

    private function createInvoice(Order $order)
    {
        if (!$order->canInvoice()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Cannot create an invoice.'));
        }

        $invoice = $this->objectManager->create(\Magento\Sales\Model\Service\InvoiceService::class)->prepareInvoice($order);
        // $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
        $invoice->register();
        $invoice->save();

        $transactionSave = $this->objectManager->create(\Magento\Framework\DB\Transaction::class);
        $transactionSave->addObject($invoice)
            ->addObject($order)
            ->save();
    }

    private function createShipment(Order $order)
    {
        if (!$order->canShip()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Cannot create a shipment.'));
        }

        $convertOrder = $this->objectManager->create(\Magento\Sales\Model\Convert\Order::class);
        $shipment = $convertOrder->toShipment($order);

        foreach ($order->getAllItems() as $orderItem) {
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }

            $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($orderItem->getQtyToShip());
            $shipment->addItem($shipmentItem);
        }

        if (empty($shipment->getAllItems())) {
            throw new \Magento\Framework\Exception\LocalizedException(__('We cannot create an empty shipment.'));
        }

        $shipment->register();
        $shipment->getExtensionAttributes()->setSourceCode('default'); // Set a default source code
        $shipment->getOrder()->setIsInProcess(true);

        // Add tracking information
        $carrierCode = 'osf_integration_test';
        $trackingNumber = 'TRACK123456';

        $track = $this->objectManager->create(\Magento\Sales\Model\Order\Shipment\Track::class);
        $track->setCarrierCode($carrierCode)
            ->setTitle('OSF_INTEGRATION_TEST')
            ->setTrackNumber($trackingNumber);

        $shipment->addTrack($track);

        $transactionSave = $this->objectManager->create(\Magento\Framework\DB\Transaction::class);
        $transactionSave->addObject($shipment)
            ->addObject($shipment->getOrder())
            ->save();
    }

    public static function loadPaymentDataFixture()
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(\Magento\Eav\Model\Config::class)->clear();
        require __DIR__ . '/fixtures/payment/payment_forter.php';
    }

    public static function loadProductDataFixture()
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(\Magento\Eav\Model\Config::class)->clear();
        require __DIR__ . '/fixtures/product/simple.php';
    }

    public static function loadShippingDataFixture()
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(\Magento\Eav\Model\Config::class)->clear();
        require __DIR__ . '/fixtures/shipping/shipping_data_fixture.php';
    }

    public static function loadCustomerApproveDataFixture()
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(\Magento\Eav\Model\Config::class)->clear();
        require __DIR__ . '/fixtures/customer/customer_approve.php';
    }
}
