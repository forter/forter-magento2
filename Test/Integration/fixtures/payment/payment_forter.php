<?php

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Registry;
use Magento\Payment\Model\MethodInterface;

$objectManager = Bootstrap::getObjectManager();

$paymentMethodInstance = $objectManager->create(\Magento\OfflinePayments\Model\Checkmo::class);

$registry = $objectManager->get(Registry::class);
$registry->unregister('checkmo_payment_method');
$registry->register('checkmo_payment_method', $paymentMethodInstance);
