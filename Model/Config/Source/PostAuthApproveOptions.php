<?php

namespace Forter\Forter\Model\Config\Source;

/**
 * Class CaptureInvoiceOptions
 * @package Forter\Forter\Model\Config\Source
 */
class PostAuthApproveOptions implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Create Invoice and Capture Payments (CRON)')],
            ['value' => '2', 'label' => __('Create Invoice and Capture Payments (IMMEDIATELY)')],
            ['value' => '3', 'label' => __('Do Nothing')]
        ];
    }
}
