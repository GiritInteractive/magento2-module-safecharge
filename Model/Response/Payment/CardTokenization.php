<?php

namespace Girit\Safecharge\Model\Response\Payment;

use Girit\Safecharge\Model\Response\AbstractPayment;
use Girit\Safecharge\Model\ResponseInterface;

/**
 * Girit Safecharge payment card tokenization response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class CardTokenization extends AbstractPayment implements ResponseInterface
{
    /**
     * @var string
     */
    protected $ccTempToken;

    /**
     * @return CardTokenization
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->ccTempToken = $body['ccTempToken'];

        return $this;
    }

    /**
     * @return int
     */
    public function getCcTempToken()
    {
        return $this->ccTempToken;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return array_merge_recursive(
            parent::getRequiredResponseDataKeys(),
            [
                'isVerified',
                'ccTempToken',
            ]
        );
    }
}
