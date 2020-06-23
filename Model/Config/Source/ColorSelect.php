<?php

namespace Forter\Forter\Model\Config\Source;

class ColorSelect implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '#FFFFFF', 'label' => __('WHITE')],
            ['value' => '#C0C0C0', 'label' => __('SILVER')],
            ['value' => '#808080', 'label' => __('GRAY')],
            ['value' => '#000000', 'label' => __('BLACK')],
            ['value' => '#FF0000', 'label' => __('RED')],
            ['value' => '#800000', 'label' => __('MAROON')],
            ['value' => '#FFFF00', 'label' => __('YELLOW')],
            ['value' => '#808000', 'label' => __('OLIVE')],
            ['value' => '#00FF00', 'label' => __('LIME')],
            ['value' => '#008000', 'label' => __('GREEN')],
            ['value' => '#00FFFF', 'label' => __('AQUA')],
            ['value' => '#008080', 'label' => __('TEAL')],
            ['value' => '#0000FF', 'label' => __('BLUE')],
            ['value' => '#000080', 'label' => __('NAVY')],
            ['value' => '#FF00FF', 'label' => __('FUCHSIA')],
            ['value' => '#800080', 'label' => __('PURPLE')]
        ];
    }
}