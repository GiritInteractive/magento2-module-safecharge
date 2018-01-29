<?php

namespace Girit\Safecharge\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;
use Girit\Safecharge\Model\Payment;

/**
 * Girit Safecharge mode source model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Mode implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $optionArray = [];
        foreach ($this->toArray() as $value => $label) {
            $optionArray[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $optionArray;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            Payment::MODE_LIVE => __('Live'),
            Payment::MODE_SANDBOX => __('Sandbox'),
        ];
    }
}
