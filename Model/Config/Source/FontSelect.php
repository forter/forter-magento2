<?php

namespace Forter\Forter\Model\Config\Source;

class FontSelect implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'Arial', 'label' => __('Arial')],
            ['value' => 'Roboto', 'label' => __('Roboto')],
            ['value' => 'Times New Roman', 'label' => __('Times New Roman')],
            ['value' => 'Times', 'label' => __('Times')],
            ['value' => 'Courier New', 'label' => __('Courier New')],
            ['value' => 'Courier', 'label' => __('Courier')],
            ['value' => 'Verdana', 'label' => __('Verdana')],
            ['value' => 'Georgia', 'label' => __('Georgia')],
            ['value' => 'Palatino', 'label' => __('Palatino')],
            ['value' => 'Garamond', 'label' => __('Garamond')],
            ['value' => 'Bookman', 'label' => __('Bookman')],
            ['value' => 'Comic Sans MS', 'label' => __('Comic Sans MS')],
            ['value' => 'Candara', 'label' => __('Candara')],
            ['value' => 'Arial Black', 'label' => __('Arial Black')],
            ['value' => 'Impact', 'label' => __('Impact')]
        ];
    }
}