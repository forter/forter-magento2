<?php

namespace Forter\Forter\Model\Config\Source;

/**
 * Class CaptureInvoiceOptions
 * @package Forter\Forter\Model\Config\Source
 */
class CaptureInvoiceOptions implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Capture (invoice) Cron')],
            ['value' => '2', 'label' => __('Capture (invoice) Immediate')]
        ];
    }
}
