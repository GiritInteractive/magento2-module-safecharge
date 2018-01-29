<?php

namespace Girit\Safecharge\Observer\Sales\Order\Void;

use Girit\Safecharge\Model\Payment;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;

/**
 * Girit Safecharge sales order void observer.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Register implements ObserverInterface
{
    /**
     * @param Observer $observer
     *
     * @return Register
     */
    public function execute(Observer $observer)
    {
        /** @var OrderPayment $payment */
        $payment = $observer->getPayment();

        if ($payment->getMethod() !== Payment::METHOD_CODE) {
            return $this;
        }

        /** @var Order $order */
        $order = $payment->getOrder();

        $order->setStatus(Payment::SC_VOIDED);

        return $this;
    }
}
