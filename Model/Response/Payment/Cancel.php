<?php

namespace Girit\Safecharge\Model\Response\Payment;

use Girit\Safecharge\Model\Response\AbstractPayment;
use Girit\Safecharge\Model\ResponseInterface;

/**
 * Girit Safecharge payment void response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Cancel extends AbstractPayment implements ResponseInterface
{
    /**
     * @var int
     */
    protected $transactionId;

    /**
     * @var string
     */
    protected $authCode;

    /**
     * @return Cancel
     */
    protected function processResponseData()
    {
        $body = $this->getBody();

        $this->transactionId = $body['transactionId'];
        $this->authCode = $body['authCode'];

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    protected function getRequestStatus()
    {
        if (parent::getRequestStatus() === false) {
            return false;
        }

        $body = $this->getBody();
        if (strtolower($body['transactionStatus']) === 'error') {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @return string
     */
    public function getAuthCode()
    {
        return $this->authCode;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return array_merge_recursive(
            parent::getRequiredResponseDataKeys(),
            [
                'transactionId',
                'authCode',
                'transactionStatus',
            ]
        );
    }

    /**
     * @return bool|string
     */
    protected function getErrorReason()
    {
        $body = $this->getBody();
        if (!empty($body['gwErrorReason'])) {
            return $body['gwErrorReason'];
        }

        return parent::getErrorReason();
    }
}
