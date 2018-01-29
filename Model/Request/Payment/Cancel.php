<?php

namespace Girit\Safecharge\Model\Request\Payment;

use Girit\Safecharge\Model\AbstractRequest;
use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\Payment;
use Girit\Safecharge\Model\Request\AbstractPayment;
use Girit\Safecharge\Model\RequestInterface;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction as OrderTransaction;

/**
 * Girit Safecharge void payment request model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Cancel extends AbstractPayment implements RequestInterface
{
    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_VOID_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_VOID_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $this->orderPayment;

        /** @var Order $order */
        $order = $orderPayment->getOrder();

        /** @var OrderTransaction $transaction */
        $transaction = $orderPayment->getAuthorizationTransaction();
        $transactionDetails = $transaction->getAdditionalInformation(OrderTransaction::RAW_DETAILS);

        $authCode = null;
        if (empty($transactionDetails['authCode'])) {
            $authCode = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_AUTH_CODE_KEY);
        } else {
            $authCode = $transactionDetails['authCode'];
        }

        if ($authCode === null) {
            throw new PaymentException(
                __('Transaction does not contain authorization code.')
            );
        }

        $params = [
            'clientUniqueId' => $order->getIncrementId(),
            'currency' => $order->getBaseCurrencyCode(),
            'amount' => (float)$order->getBaseGrandTotal(),
            'relatedTransactionId' => $transaction->getTxnId(),
            'authCode' => $authCode,
            'comment' => '',
            'urlDetails' => [
                'notificationUrl' => '',
            ],
        ];

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            [
                'increment_id' => $order->getIncrementId(),
            ]
        );

        return $params;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getChecksumKeys()
    {
        return [
            'merchantId',
            'merchantSiteId',
            'clientRequestId',
            'clientUniqueId',
            'amount',
            'currency',
            'relatedTransactionId',
            'authCode',
            'comment',
            'urlDetails',
            'timeStamp',
        ];
    }
}
