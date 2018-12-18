<?php

namespace Safecharge\Safecharge\Model\Request;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Quote\Model\Quote;
use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;
use Safecharge\Safecharge\Model\RequestInterface;
use Safecharge\Safecharge\Model\Response\Factory as ResponseFactory;

/**
 * Safecharge Safecharge paymentAPM request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class PaymentApm extends AbstractRequest implements RequestInterface
{

    /**
     * @var string|null
     */
    protected $paymentMethod;

    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * OpenOrder constructor.
     *
     * @param SafechargeLogger $safechargeLogger
     * @param Config           $config
     * @param Curl             $curl
     * @param ResponseFactory  $responseFactory
     * @param Factory          $requestFactory
     * @param CheckoutSession  $checkoutSession
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory,
        CheckoutSession $checkoutSession
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory = $requestFactory;
        $this->checkoutSession = $checkoutSession;
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
        return self::PAYMENT_APM_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_APM_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {

        /** @var Quote $quote */
        $quote = $this->checkoutSession->getQuote();

        $quotePayment = $quote->getPayment();

        $tokenRequest = $this->requestFactory
            ->create(AbstractRequest::GET_SESSION_TOKEN_METHOD);
        $tokenResponse = $tokenRequest->process();

        $quotePayment->unsAdditionalInformation(Payment::TRANSACTION_SESSION_TOKEN);
        $quotePayment->setAdditionalInformation(
            Payment::TRANSACTION_SESSION_TOKEN,
            $tokenResponse->getToken()
        );

        $params = array_merge_recursive(
            $this->getQuoteData($quote),
            [
                'orderId' => $quotePayment->getAdditionalInformation(Payment::TRANSACTION_ORDER_ID),
                'sessionToken' => $tokenResponse->getToken(),
                'amount' => (float)$quote->getGrandTotal(),
                'merchant_unique_id' => $this->config->getReservedOrderId(),
                'urlDetails' => [
                    'successUrl' => $this->config->getApmSuccessUrl(),
                    'failureUrl' => $this->config->getApmErrorUrl(),
                    'pendingUrl' => $this->config->getApmPendingUrl(),
                    'backUrl' => $this->config->getBackUrl(),
                    'notificationUrl' => $this->config->getDmnUrl(),
                ],
                'paymentMethod' => $this->getPaymentMethod(),
            ]
        );

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            [
                'parent_request_id' => $quotePayment->getAdditionalInformation(Payment::TRANSACTION_REQUEST_ID),
                'increment_id' => $this->config->getReservedOrderId(),
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