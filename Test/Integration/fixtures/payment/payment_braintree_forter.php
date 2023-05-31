<?php

use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Model\StoreConfigResolver;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Framework\App\Config\Storage\WriterInterface;

$objectManager = Bootstrap::getObjectManager();

// Braintree Adapter Configuration
$config = $objectManager->create(Config::class);
$storeConfigResolver = $objectManager->create(StoreConfigResolver::class);
$logger = $objectManager->get(\Psr\Log\LoggerInterface::class);

$adapter = new BraintreeAdapter($config, $storeConfigResolver, $logger);

// Set your sandbox credentials
$adapter->environment('sandbox');
$adapter->merchantId('t6zvx2js3gt4jj85');
$adapter->publicKey('t6v2y24h7twb9zyp');
$adapter->privateKey('754181df1e828fb25be93e6548e4bcf5');

$objectManager->addSharedInstance($adapter, BraintreeAdapter::class);

//// Set Braintree payment method as active
$configWriter = $objectManager->get(WriterInterface::class);
$configWriter->save('payment/braintree/active', 1);

// Braintree Payment Method Configuration
$paymentHelper = $objectManager->get(PaymentHelper::class);
$paymentMethodInstance = $paymentHelper->getMethodInstance('braintree');
$paymentMethodInstance->setStore(0);

$registry = $objectManager->get(Registry::class);
$registry->unregister('braintree');
$registry->register('braintree', $paymentMethodInstance);

