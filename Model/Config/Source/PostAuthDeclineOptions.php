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
            ['value' => '1', 'label' => __('Cancel Order, Stop Confirmation Email, Void or Refund Payment (CRON)')],
            ['value' => '2', 'label' => __('Set Order to Payment Review State and Stop Confirmation Email')],
            ['value' => '3', 'label' => __('Do nothing')]
        ];
    }
}
