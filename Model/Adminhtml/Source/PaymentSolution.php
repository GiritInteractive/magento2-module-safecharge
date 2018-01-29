<?php

namespace Girit\Safecharge\Model\Adminhtml\Source;

use Girit\Safecharge\Model\Payment;
use Magento\Framework\Option\ArrayInterface;

/**
 * Girit Safecharge payment solution source model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class PaymentSolution implements ArrayInterface
{
    /**
     * Possible actions on order place.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Payment::SOLUTION_INTERNAL,
                'label' => __('Built In Form'),
            ],
            [
                'value' => Payment::SOLUTION_EXTERNAL,
                'label' => __('Redirect'),
            ],
        ];
    }
}
