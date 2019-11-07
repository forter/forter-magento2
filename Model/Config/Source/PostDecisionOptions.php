<?php

namespace Forter\Forter\Model\Config\Source;

class PostDecisionOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Cancel')],
            ['value' => '2', 'label' => __('Void')],
            ['value' => '3', 'label' => __('set to fraud state')],
            ['value' => '4', 'label' => __('Error Message (Only-immediate)')],
            ['value' => '5', 'label' => __('Prevent mail (Only-immediate)')]
        ];
    }
}
