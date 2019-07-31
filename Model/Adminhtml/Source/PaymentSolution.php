<?php

namespace Safecharge\Safecharge\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;
use Safecharge\Safecharge\Model\Payment;

/**
 * Safecharge Safecharge payment solution source model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
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
                'label' => __('SafeCharge API'),
            ],
            [
                'value' => Payment::SOLUTION_EXTERNAL,
                'label' => __('Hosted payment page'),
            ],
        ];
    }
}
