<?php

namespace Forter\Forter\Model\Config\Source;

class PrePostSelect implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Auth pre paymernt')],
            ['value' => '2', 'label' => __('Auth post paymernt')]
        ];
    }
}
