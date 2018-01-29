<?php

namespace Girit\Safecharge\Model;

use Girit\Safecharge\Lib\Http\Client\Curl;
use Girit\Safecharge\Model\Logger as SafechargeLogger;
use Girit\Safecharge\Model\Response\Factory as ResponseFactory;
use Magento\Framework\Exception\PaymentException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;

/**
 * Girit Safecharge abstract request model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
abstract class AbstractRequest extends AbstractApi
{
    /**
     * Payment gateway endpoints.
     */
    const LIVE_ENDPOINT = 'https://secure.safecharge.com/ppp/';
    const TEST_ENDPOINT = 'https://ppp-test.safecharge.com/ppp/';

    /**
     * Payment gateway methods.
     */
    const GET_SESSION_TOKEN_METHOD = 'getSessionToken';
    const PAYMENT_CC_METHOD = 'paymentCC';
    const PAYMENT_SETTLE_METHOD = 'settleTransaction';
    const PAYMENT_CARD_TOKENIZATION_METHOD = 'cardTokenization';
    const PAYMENT_USER_PAYMENT_OPTION_METHOD = 'addUPOCreditCardByTempToken';
    const PAYMENT_DYNAMIC_3D_METHOD = 'dynamic3D';
    const PAYMENT_PAYMENT_3D_METHOD = 'payment3D';
    const CREATE_USER_METHOD = 'createUser';
    const GET_USER_DETAILS_METHOD = 'getUserDetails';
    const PAYMENT_REFUND_METHOD = 'refundTransaction';
    const PAYMENT_VOID_METHOD = 'voidTransaction';
    const OPEN_ORDER_METHOD = 'openOrder';

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var ResponseInterface
     */
    protected $responseFactory;

    /**
     * @var int
     */
    protected $requestId;

    /**
     * Object constructor.
     *
     * @param Logger          $safechargeLogger
     * @param Config          $config
     * @param Curl            $curl
     * @param ResponseFactory $responseFactory
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        ResponseFactory $responseFactory
    ) {
        parent::__construct(
            $safechargeLogger,
            $config
        );

        $this->curl = $curl;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws PaymentException
     */
    public function process()
    {
        $this->sendRequest();

        return $this
            ->getResponseHandler()
            ->process();
    }

    /**
     * @return int
     */
    protected function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function initRequest()
    {
        if ($this->requestId === null) {
            $requestLog = $this->safechargeLogger->createRequest(
                [
                    'request' => [
                        'Type' => 'POST',
                    ],
                ]
            );
            $this->requestId = $requestLog->getId();
        }
    }

    /**
     * Return full endpoint to particular method for request call.
     *
     * @return string
     */
    protected function getEndpoint()
    {
        $endpoint = self::LIVE_ENDPOINT;
        if ($this->config->isTestModeEnabled() === true) {
            $endpoint = self::TEST_ENDPOINT;
        }
        $endpoint .= 'api/v1/';

        $method = $this->getRequestMethod();

        return $endpoint . $method . '.do';
    }

    /**
     * Return method for request call.
     *
     * @return string
     */
    abstract protected function getRequestMethod();

    /**
     * Return request headers.
     *
     * @return array
     */
    protected function getHeaders()
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Return request params.
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getParams()
    {
        $this->initRequest();

        $params = [
            'merchantId' => $this->config->getMerchantId(),
            'merchantSiteId' => $this->config->getMerchantSiteId(),
            'clientRequestId' => (string)$this->getRequestId(),
            'timeStamp' => date('YmdHis'),
        ];

        return $params;
    }

    /**
     * @return array
     * @throws PaymentException
     */
    protected function prepareParams()
    {
        $params = $this->getParams();

        $checksumKeys = $this->getChecksumKeys();
        if (empty($checksumKeys)) {
            return $params;
        }

        $concat = '';
        foreach ($checksumKeys as $checksumKey) {
            if (!isset($params[$checksumKey])) {
                throw new PaymentException(
                    __(
                        'Required key %1 for checksum calculation is missing.',
                        $checksumKey
                    )
                );
            }

            if (is_array($params[$checksumKey])) {
                foreach ($params[$checksumKey] as $subKey => $subVal) {
                    $concat .= $subVal;
                }
            } else {
                $concat .= $params[$checksumKey];
            }
        }

        $concat .= $this->config->getMerchantSecretKey();
        $concat = utf8_encode($concat);
        $params['checksum'] = hash('sha256', $concat);

        return $params;
    }

    /**
     * Return keys required to calculate checksum. Keys order is relevant.
     *
     * @return array
     */
    protected function getChecksumKeys()
    {
        return [
            'merchantId',
            'merchantSiteId',
            'clientRequestId',
            'timeStamp',
        ];
    }

    /**
     * @return AbstractRequest
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function sendRequest()
    {
        $endpoint = $this->getEndpoint();
        $headers = $this->getHeaders();
        $params = $this->prepareParams();

        $this->curl->setHeaders($headers);

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            [
                'method' => $this->getRequestMethod(),
                'request' => [
                    'Endpoint' => $endpoint,
                    'Type' => 'POST',
                    'Headers' => $headers,
                    'Body' => $params,
                ],
            ]
        );

        $this->curl->post($endpoint, $params);

        return $this;
    }

    /**
     * Return response handler type.
     *
     * @return string
     */
    abstract protected function getResponseHandlerType();

    /**
     * Return proper response handler.
     *
     * @return ResponseInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getResponseHandler()
    {
        $responseHandler = $this->responseFactory->create(
            $this->getResponseHandlerType(),
            $this->getRequestId(),
            $this->curl
        );

        return $responseHandler;
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    protected function getOrderData(Order $order)
    {
        /** @var OrderAddressInterface $billing */
        $billing = $order->getBillingAddress();

        $orderData = [
            'userTokenId' => $order->getCustomerId() ?: $order->getCustomerEmail(),
            'clientUniqueId' => $order->getIncrementId(),
            'currency' => $order->getBaseCurrencyCode(),
            'amountDetails' => [
                'totalShipping' => (float)$order->getBaseShippingAmount(),
                'totalHandling' => (float)0,
                'totalDiscount' => (float)abs($order->getBaseDiscountAmount()),
                'totalTax' => (float)$order->getBaseTaxAmount(),
            ],
            'items' => [],
            'deviceDetails' => [
                'deviceType' => 'DESKTOP',
                'ipAddress' => $order->getRemoteIp(),
            ],
        ];

        if ($billing !== null) {
            $orderData['billingAddress'] = [
                'firstName' => $billing->getFirstname(),
                'lastName' => $billing->getLastname(),
                'address' => is_array($billing->getStreet())
                    ? implode(' ', $billing->getStreet())
                    : '',
                'cell' => '',
                'phone' => $billing->getTelephone(),
                'zip' => $billing->getPostcode(),
                'city' => $billing->getCity(),
                'country' => $billing->getCountryId(),
                'state' => $billing->getRegionCode(),
                'email' => $billing->getEmail(),
            ];
        }

        // Add items details.
        $orderItems = $order->getAllVisibleItems();
        foreach ($orderItems as $orderItem) {
            $price = (float)$orderItem->getBasePrice();
            if (!$price) {
                continue;
            }

            $orderData['items'][] = [
                'name' => $orderItem->getName(),
                'price' => $price,
                'quantity' => (int)$orderItem->getQtyOrdered(),
            ];
        }

        return $orderData;
    }
}
