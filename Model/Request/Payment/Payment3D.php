<?php

namespace Girit\Safecharge\Model\Request\Payment;

use Girit\Safecharge\Lib\Http\Client\Curl;
use Girit\Safecharge\Model\AbstractRequest;
use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\Config;
use Girit\Safecharge\Model\Logger as SafechargeLogger;
use Girit\Safecharge\Model\Payment;
use Girit\Safecharge\Model\Request\AbstractPayment;
use Girit\Safecharge\Model\Request\Factory as PaymentFactory;
use Girit\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;
use Girit\Safecharge\Model\RequestInterface;
use Girit\Safecharge\Model\Response\Factory as ResponseFactory;
use Girit\Safecharge\Model\Service\CardTokenization as CardTokenizationService;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Vault\Api\PaymentTokenManagementInterface;

/**
 * Girit Safecharge 3d secure payment request model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Payment3D extends AbstractPayment implements RequestInterface
{
    /**
     * @var PaymentTokenManagementInterface
     */
    protected $paymentTokenManagement;

    /**
     * @var CardTokenizationService
     */
    protected $cardTokenizationService;

    /**
     * @var string|null
     */
    protected $userPaymentOptionId;

    /**
     * @var string|null
     */
    protected $cardCvv;

    /**
     * @var string|null
     */
    protected $paResponse;

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
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param CardTokenizationService         $cardTokenizationService
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        PaymentFactory $requestFactory,
        PaymentRequestFactory $paymentRequestFactory,
        ResponseFactory $responseFactory,
        $orderPayment,
        $amount,
        PaymentTokenManagementInterface $paymentTokenManagement,
        CardTokenizationService $cardTokenizationService
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

        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->cardTokenizationService = $cardTokenizationService;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_PAYMENT_3D_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_PAYMENT_3D_HANDLER;
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

        if ($this->userPaymentOptionId === null) {
            $this->processCardTokenization();
        }

        $params = array_merge_recursive(
            $this->getOrderData($order),
            [
                'orderId' => $orderPayment->getAdditionalInformation(Payment::TRANSACTION_ORDER_ID),
                'sessionToken' => $orderPayment->getAdditionalInformation(Payment::TRANSACTION_SESSION_TOKEN),
                'transactionType' => $this->getActionType(),
                'amount' => (float)$this->amount,
            ]
        );

        if ($this->paResponse !== null) {
            $params['paResponse'] = $this->paResponse;
        }

        if ($this->userPaymentOptionId !== null) {
            $params['userPaymentOption'] = [
                'userPaymentOptionId' => $this->userPaymentOptionId,
                'CVV' => $this->cardCvv,
            ];
        } else {
            // Add card details.
            $ccToken = $orderPayment->getAdditionalInformation(Payment::KEY_CC_TOKEN);
            if ($ccToken === null) {
                $params['cardData'] = [
                    'cardNumber' => $orderPayment->getCcNumber(),
                    'cardHolderName' => $orderPayment->getCcOwner(),
                    'expirationMonth' => $orderPayment->getCcExpMonth(),
                    'expirationYear' => $orderPayment->getCcExpYear(),
                    'CVV' => $orderPayment->getCcCid(),
                ];
            } else {
                $paymentToken = $this->paymentTokenManagement->getByPublicHash(
                    $ccToken,
                    $order->getCustomerId()
                );
                if ($paymentToken === null) {
                    throw new PaymentException(
                        __('Requested payment token does not exists.')
                    );
                }

                $params['userPaymentOption'] = [
                    'userPaymentOptionId' => $paymentToken->getGatewayToken(),
                    'CVV' => $orderPayment->getCcCid(),
                ];
            }
        }

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

    /**
     * @return Payment3D
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function processCardTokenization()
    {
        $orderPayment = $this->orderPayment;

        $ccTokenize = $orderPayment->getAdditionalInformation(Payment::KEY_CC_SAVE);
        $orderPayment->unsAdditionalInformation(Payment::KEY_CC_SAVE);

        if (!$ccTokenize) {
            return $this;
        }

        $cardPaymentToken = $this->cardTokenizationService
            ->setOrderPayment($orderPayment)
            ->processCardPaymentToken();

        $this->orderPayment->setAdditionalInformation(
            Payment::KEY_CC_TOKEN,
            $cardPaymentToken->getPublicHash()
        );

        return $this;
    }

    /**
     * @param string $userPaymentOptionId
     *
     * @return Payment3D
     */
    public function setUserPaymentOptionId($userPaymentOptionId)
    {
        $this->userPaymentOptionId = $userPaymentOptionId;

        return $this;
    }

    /**
     * @param string $cardCvv
     *
     * @return Payment3D
     */
    public function setCardCvv($cardCvv)
    {
        $this->cardCvv = $cardCvv;

        return $this;
    }

    /**
     * @param string $paResponse
     *
     * @return Payment3D
     */
    public function setPaResponse($paResponse)
    {
        $this->paResponse = $paResponse;

        return $this;
    }
}
