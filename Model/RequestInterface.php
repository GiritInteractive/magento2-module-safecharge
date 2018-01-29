<?php

namespace Girit\Safecharge\Model;

/**
 * Girit Safecharge request interface.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
interface RequestInterface
{
    /**
     * Process current request type.
     *
     * @return RequestInterface
     * @throws \Magento\Framework\Exception\PaymentException
     */
    public function process();
}
