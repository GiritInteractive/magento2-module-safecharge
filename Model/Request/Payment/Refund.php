<?php

namespace Girit\Safecharge\Model\Request\Payment;

use Girit\Safecharge\Lib\Http\Client\Curl;
use Girit\Safecharge\Model\AbstractRequest;
use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\Config;
use Girit\Safecharge\Model\Logger as SafechargeLogger;
use Girit\Safecharge\Model\Payment;
use Girit\Safecharge\Model\Request\AbstractPayment;
use Girit\Safecharge\Model\Request\Factory as RequestFactory;
use Girit\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Girit\Safecharge\Model\RequestInterface;
use Girit\Safecharge\Model\Response\Factory as ResponseFactory;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction as OrderTransaction;

/**
 * Girit Safecharge refund payment request model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Refund extends AbstractPayment implements RequestInterface
{
    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * Refund constructor.
     *
     * @param SafechargeLogger               $safechargeLogger
     * @param Config                         $config
     * @param Curl                           $curl
     * @param RequestFactory                 $requestFactory
     * @param Factory                        $paymentRequestFactory
     * @param ResponseFactory                $responseFactory
     * @param OrderPayment                   $orderPayment
     * @param TransactionRepositoryInterface $transactionRepository
     * @param float                          $amount
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        RequestFactory $requestFactory,
        PaymentRequestFactory $paymentRequestFactory,
        ResponseFactory $responseFactory,
        OrderPayment $orderPayment,
        TransactionRepositoryInterface $transactionRepository,
        $amount = 0.0
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $requestFactory,
            $paymentRequestFactory,
            $responseFactory,
            $orderPayment,
            $amount
        );

        $this->transactionRepository = $transactionRepository;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_REFUND_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_REFUND_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\PaymentException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        /** @var OrderPayment $orderPayment */
        $orderPayment = $this->orderPayment;

        /** @var Order $order */
        $order = $orderPayment->getOrder();

        /** @var int|null $transactionId */
        $transactionId = $orderPayment->getRefundTransactionId();
        if ($transactionId === null) {
            throw new PaymentException(
                __('Invoice transaction id has been not provided.')
            );
        }

        /** @var OrderTransaction $transaction */
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionId,
            $orderPayment->getId(),
            $order->getId()
        );

        $authCode = null;
        if ($transaction === false) {
            $authCode = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_AUTH_CODE_KEY);
        } else {
            $transactionDetails = $transaction->getAdditionalInformation(OrderTransaction::RAW_DETAILS);

            if (empty($transactionDetails['authCode'])) {
                $authCode = $orderPayment->getAdditionalInformation(Payment::TRANSACTION_AUTH_CODE_KEY);
            } else {
                $authCode = $transactionDetails['authCode'];
            }
        }

        if ($authCode === null) {
            throw new PaymentException(
                __('Transaction does not contain authorization code.')
            );
        }

        $params = [
            'clientUniqueId' => $order->getIncrementId(),
            'currency' => $order->getBaseCurrencyCode(),
            'amount' => (float)$this->amount,
            'relatedTransactionId' => $transactionId,
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
