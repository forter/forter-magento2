<?php

namespace Forter\Forter\Model\Config\Source;

/**
 * Class PostDecisionOptions
 * @package Forter\Forter\Model\Config\Source
 */
class PostAuthDeclineOptions implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Cancel Order, Void or Refund Payment')],
            ['value' => '2', 'label' => __('Set to Payment Review State')],
            ['value' => '3', 'label' => __('Do nothing')]
        ];
    }
}
