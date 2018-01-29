<?php

namespace Girit\Safecharge\Model\Response\Payment;

use Girit\Safecharge\Model\Response\AbstractPayment;
use Girit\Safecharge\Model\ResponseInterface;

/**
 * Girit Safecharge payment user payment option response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class UserPaymentOption extends AbstractPayment implements ResponseInterface
{
    /**
     * @var string
     */
    protected $ccToken;

    /**
     * @return UserPaymentOption
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->ccToken = $body['userPaymentOptionId'];

        return $this;
    }

    /**
     * @return int
     */
    public function getCcToken()
    {
        return $this->ccToken;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return array_merge_recursive(
            parent::getRequiredResponseDataKeys(),
            [
                'userPaymentOptionId',
            ]
        );
    }
}
