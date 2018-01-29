<?php

namespace Girit\Safecharge\Model\Response\Payment;

use Girit\Safecharge\Model\Payment;
use Girit\Safecharge\Model\Response\AbstractPayment;
use Girit\Safecharge\Model\ResponseInterface;

/**
 * Girit Safecharge payment settle response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Settle extends AbstractPayment implements ResponseInterface
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
     * @return Settle
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
     * @return Settle
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function updateTransaction()
    {
        parent::updateTransaction();

        if ($this->config->getPaymentAction() === Payment::ACTION_AUTHORIZE_CAPTURE) {
            $this->orderPayment->setAdditionalInformation(
                Payment::TRANSACTION_AUTH_CODE_KEY,
                $this->getAuthCode()
            );
            $this->orderPayment->setAdditionalInformation(
                Payment::TRANSACTION_ID,
                $this->getTransactionId()
            );
        }

        $this->orderPayment
            ->setParentTransactionId($this->orderPayment->getTransactionId())
            ->setTransactionId($this->getTransactionId())
            ->setIsTransactionClosed(1);

        return $this;
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
}
