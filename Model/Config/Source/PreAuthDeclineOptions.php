<?php

namespace Forter\Forter\Model\Config\Source;

/**
 * Class PreAuthDeclineOptions
 * @package Forter\Forter\Model\Config\Source
 */
class PreAuthDeclineOptions implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '0', 'label' => __('Do Nothing')],
            ['value' => '1', 'label' => __('Payment exception (stay in checkout page with error message)')]
        ];
    }
}
