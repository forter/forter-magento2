<?php

namespace Forter\Forter\Model\Config\Source;

class DecisionOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Redirect Success Page, Cancel the order, prevent email sending')],
            ['value' => '2', 'label' => __('Send user back to Checkout page with error')]
        ];
    }
}
