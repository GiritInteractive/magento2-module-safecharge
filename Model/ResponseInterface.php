<?php

namespace Girit\Safecharge\Model;

/**
 * Girit Safecharge response interface.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
interface ResponseInterface
{
    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\PaymentException
     */
    public function process();
}
