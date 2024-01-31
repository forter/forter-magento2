<?php

namespace Forter\Forter\Model\Config\Source;

/**
 * Class PrePostSelect
 * @package Forter\Forter\Model\Config\Source
 */
class PrePostSelect implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('Before Payment Action (pre-authorization)')],
            ['value' => '2', 'label' => __('After Payment Action (post-authorization)')],
            ['value' => '4', 'label' => __('Before & After Payment Action (pre/post-authorization)')]
        ];
    }
}
