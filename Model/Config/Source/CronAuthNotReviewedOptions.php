<?php

namespace Forter\Forter\Model\Config\Source;

/**
 * Class PostDecisionOptions
 * @package Forter\Forter\Model\Config\Source
 */
class CronAuthNotReviewedOptions implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
          ['value' => '1', 'label' => __('Create Invoice and Capture Payments')],
          ['value' => '2', 'label' => __('Do Nothing')]
        ];
    }
}
