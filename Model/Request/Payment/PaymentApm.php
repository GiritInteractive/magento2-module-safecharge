<?php

namespace Safecharge\Safecharge\Model\Request\Payment;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\AbstractPayment;
use Safecharge\Safecharge\Model\Request\Factory as PaymentFactory;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Safecharge\Safecharge\Model\RequestInterface;
use Safecharge\Safecharge\Model\Response\Factory as ResponseFactory;

/**
 * Safecharge Safecharge paymentAPM request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class PaymentApm extends AbstractPayment implements RequestInterface
{

    /**
     * @var string|null
     */
    protected $paymentMethod;

    /**
     * Cc constructor.
     *
     * @param SafechargeLogger                $safechargeLogger
     * @param Config                          $config
     * @param Curl                            $curl
     * @param PaymentFactory                  $requestFactory
     * @param Factory                         $paymentRequestFactory
     * @param ResponseFactory                 $responseFactory
     * @param OrderPayment|null               $orderPayment
     * @param float|null                      $amount
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        PaymentFactory $requestFactory,
        PaymentRequestFactory $paymentRequestFactory,
        ResponseFactory $responseFactory,
        $orderPayment,
        $amount
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
    }

    /**
     * @return $this
     */
    public function setPaymentMethod($paymentMethod)
    {
        $this->paymentMethod = trim((string)$paymentMethod);
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::PAYMENT_PAYMENT_APM_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_PAYMENT_APM_HANDLER;
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

        $tokenRequest = $this->requestFactory
            ->create(AbstractRequest::GET_SESSION_TOKEN_METHOD);
        $tokenResponse = $tokenRequest->process();

        $orderPayment->unsAdditionalInformation(Payment::TRANSACTION_SESSION_TOKEN);
        $orderPayment->setAdditionalInformation(
            Payment::TRANSACTION_SESSION_TOKEN,
            $tokenResponse->getToken()
        );

        $params = array_merge_recursive(
            $this->getOrderData($order),
            [
                'orderId' => $orderPayment->getAdditionalInformation(Payment::TRANSACTION_ORDER_ID),
                'sessionToken' => $tokenResponse->getToken(),
                'transactionType' => $this->getActionType(),
                'amount' => (float)$order->getGrandTotal(),
                'merchant_unique_id' => $order->getIncrementId(),
                'urlDetails' => [
                    'notificationUrl' => $this->config->getDmnUrl($order->getIncrementId(), $order->getStoreId()),
                ],
                'paymentMethod' => $this->getPaymentMethod(),
            ]
        );

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            [
                'parent_request_id' => $orderPayment->getAdditionalInformation(Payment::TRANSACTION_REQUEST_ID),
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
            'amount',
            'currency',
            'timeStamp',
        ];
    }
}
