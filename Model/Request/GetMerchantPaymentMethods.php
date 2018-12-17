<?php

namespace Safecharge\Safecharge\Model\Request;

use Safecharge\Safecharge\Lib\Http\Client\Curl;
use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\AbstractResponse;
use Safecharge\Safecharge\Model\Config;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Request\Factory as RequestFactory;
use Safecharge\Safecharge\Model\RequestInterface;
use Safecharge\Safecharge\Model\Response\Factory as ResponseFactory;

/**
 * Safecharge Safecharge get merchant payment methods request model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class GetMerchantPaymentMethods extends AbstractRequest implements RequestInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

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
        return self::GET_MERCHANT_PAYMENT_METHODS_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::GET_MERCHANT_PAYMENT_METHODS_HANDLER;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    protected function getParams()
    {
        $tokenRequest = $this->requestFactory
            ->create(AbstractRequest::GET_SESSION_TOKEN_METHOD);
        $tokenResponse = $tokenRequest->process();

        $quote = $this->config->getCheckoutSession()->getQuote();
        $billing = ($quote) ? $quote->getBillingAddress() : null;
        $countryCode = ($billing) ? $billing->getCountryId() : null;

        $params = [
            'sessionToken' => $tokenResponse->getToken(),
            "currencyCode" => $quote->getBaseCurrencyCode(),
            "countryCode" => $countryCode,
            "languageCode", "eng",
        ];

        $params = array_merge_recursive(parent::getParams(), $params);

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
            'timeStamp',
        ];
    }
}
