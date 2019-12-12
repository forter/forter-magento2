<?php

namespace Forter\Forter\Model\Config\Source;

/**
 * Class PreDecisionOptions
 * @package Forter\Forter\Model\Config\Source
 */
class PreDecisionOptions implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '0', 'label' => __('Do Nothing')],
            ['value' => '1', 'label' => __('Payment exception (stay in checkout page with error message)')],
            ['value' => '2', 'label' => __('Destroy customer session and redirect back to cart page with error message')]
        ];
    }
}
