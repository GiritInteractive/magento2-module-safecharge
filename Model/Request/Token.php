<?php

namespace Girit\Safecharge\Model\Request;

use Girit\Safecharge\Model\AbstractRequest;
use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\RequestInterface;
use Magento\Framework\Exception\PaymentException;

/**
 * Girit Safecharge token request model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Token extends AbstractRequest implements RequestInterface
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::GET_SESSION_TOKEN_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::TOKEN_HANDLER;
    }
}
