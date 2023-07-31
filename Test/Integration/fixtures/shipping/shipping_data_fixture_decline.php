<?php

use Magento\Customer\Model\Address;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$shippingAddress = $objectManager->create(Address::class);
$shippingAddress->setFirstname('John Decline')
    ->setLastname('Doe')
    ->setCountryId('US')
    ->setRegionId(12) // Region ID for California
    ->setCity('Los Angeles')
    ->setPostcode('90001')
    ->setStreet(['123 Main St'])
    ->setTelephone('555-555-5555');

$registry = $objectManager->get(\Magento\Framework\Registry::class);
$registry->register('shipping_address_decline', $shippingAddress);
