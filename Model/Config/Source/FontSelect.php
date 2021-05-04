<?php

namespace Forter\Forter\Model\Config\Source;

class FontSelect implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'Roboto', 'label' => __('Roboto')],
            ['value' => 'Old+Times+American', 'label' => __('Old Times American')],
            ['value' => 'Courier+Prime', 'label' => __('Courier Prime')],
            ['value' => 'Average', 'label' => __('Average')],
            ['value' => 'Palanquin', 'label' => __('Palanquin')],
            ['value' => 'Cormorant+Garamond', 'label' => __('Cormorant Garamond')],
            ['value' => 'EB+Garamond', 'label' => __('EB Garamond')],
            ['value' => 'Comic+Sans+MS', 'label' => __('Comic Sans MS')],
            ['value' => 'Comic+Neue', 'label' => __('Comic Neue')],
            ['value' => 'Impact', 'label' => __('Impact')],
        ];
    }
}
