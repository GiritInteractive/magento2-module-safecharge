<?php

namespace Girit\Safecharge\Model\Request;

use Girit\Safecharge\Lib\Http\Client\Curl;
use Girit\Safecharge\Model\AbstractRequest;
use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\Config;
use Girit\Safecharge\Model\Logger as SafechargeLogger;
use Girit\Safecharge\Model\RequestInterface;
use Girit\Safecharge\Model\Response\Factory as ResponseFactory;
use Magento\Framework\Exception\PaymentException;
use Girit\Safecharge\Model\Request\Factory as RequestFactory;

/**
 * Girit Safecharge open order request model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class OpenOrder extends AbstractRequest implements RequestInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var array
     */
    protected $orderData;

    /**
     * OpenOrder constructor.
     *
     * @param SafechargeLogger $safechargeLogger
     * @param Config           $config
     * @param Curl             $curl
     * @param ResponseFactory  $responseFactory
     * @param Factory          $requestFactory
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        ResponseFactory $responseFactory,
        RequestFactory $requestFactory
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $curl,
            $responseFactory
        );

        $this->requestFactory = $requestFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return self::OPEN_ORDER_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::OPEN_ORDER_HANDLER;
    }

    /**
     * @param array $orderData
     *
     * @return OpenOrder
     */
    public function setOrderData(array $orderData)
    {
        $this->orderData = $orderData;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     * @throws \Magento\Framework\Exception\PaymentException
     */
    protected function getParams()
    {
        if ($this->orderData === null) {
            throw new PaymentException(__('Order data has been not set.'));
        }

        $tokenRequest = $this->requestFactory
            ->create(AbstractRequest::GET_SESSION_TOKEN_METHOD);
        $tokenResponse = $tokenRequest->process();

        $params = array_merge_recursive(
            [
                'sessionToken' => $tokenResponse->getToken(),
            ],
            $this->orderData,
            parent::getParams()
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
