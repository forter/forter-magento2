<?php

namespace Forter\Forter\Model\Config\Source;

/**
 * Class PostDecisionOptions
 * @package Forter\Forter\Model\Config\Source
 */
class PostDecisionOptions implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Cancel + void + refund')],
            ['value' => '2', 'label' => __('Set to payment review state')],
            ['value' => '3', 'label' => __('Do nothing')]
        ];
    }
}
