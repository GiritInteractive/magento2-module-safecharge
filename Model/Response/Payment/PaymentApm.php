<?php

namespace Safecharge\Safecharge\Model\Response\Payment;

use Safecharge\Safecharge\Model\Response\AbstractPayment;
use Safecharge\Safecharge\Model\ResponseInterface;

/**
 * Safecharge Safecharge paymentAPM response model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class PaymentApm extends AbstractPayment implements ResponseInterface
{

    /**
     * @var array
     */
    protected $redirectUrl = "";

    /**
     * @return PaymentApm
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->redirectURL = (string) $body['redirectURL'];

        return $this;
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'redirectURL',
        ];
    }
}
