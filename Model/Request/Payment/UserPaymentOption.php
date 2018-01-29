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
use Girit\Safecharge\Model\Service\UserManagement;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;

/**
 * Girit Safecharge user payment option payment request model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class UserPaymentOption extends AbstractPayment implements RequestInterface
{
    /**
     * @var UserManagement
     */
    protected $userManagement;

    /**
     * UserPaymentOption constructor.
     *
     * @param SafechargeLogger  $safechargeLogger
     * @param Config            $config
     * @param Curl              $curl
     * @param RequestFactory    $requestFactory
     * @param Factory           $paymentRequestFactory
     * @param ResponseFactory   $responseFactory
     * @param UserManagement    $userManagement
     * @param OrderPayment|null $orderPayment
     * @param float             $amount
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        Curl $curl,
        RequestFactory $requestFactory,
        PaymentRequestFactory $paymentRequestFactory,
        ResponseFactory $responseFactory,
        UserManagement $userManagement,
        $orderPayment,
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

        $this->userManagement = $userManagement;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getRequestMethod()
    {
        return AbstractRequest::PAYMENT_USER_PAYMENT_OPTION_METHOD;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getResponseHandlerType()
    {
        return AbstractResponse::PAYMENT_USER_PAYMENT_OPTION_HANDLER;
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

        /** @var OrderAddressInterface $billing */
        $billing = $order->getBillingAddress();

        $tokenRequest = $this->requestFactory
            ->create(AbstractRequest::GET_SESSION_TOKEN_METHOD);
        $tokenResponse = $tokenRequest->process();

        $this->safechargeLogger->updateRequest(
            $tokenRequest->getRequestId(),
            [
                'parent_request_id' => $orderPayment
                    ->getAdditionalInformation(Payment::TRANSACTION_REQUEST_ID),
            ]
        );

        $userTokenId = $this->getUserTokenId($order->getCustomerId());

        $params = [
            'sessionToken' => $tokenResponse->getToken(),
            'userTokenId' => $order->getCustomerId(),
            'ccTempToken' => $orderPayment->getAdditionalInformation(Payment::KEY_CC_TEMP_TOKEN),
            'billingAddress' => [
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
            ],
        ];

        $params = array_merge_recursive($params, parent::getParams());

        $this->safechargeLogger->updateRequest(
            $this->getRequestId(),
            [
                'parent_request_id' => $tokenRequest->getRequestId(),
                'increment_id' => $order->getIncrementId(),
            ]
        );

        return $params;
    }

    /**
     * @param int $customerId
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getUserTokenId($customerId)
    {
        $userTokenId = $this->userManagement->getUserId($customerId);
        if ($userTokenId) {
            return $userTokenId;
        }

        /** @var OrderPayment $orderPayment */
        $orderPayment = $this->orderPayment;

        /** @var Order $order */
        $order = $orderPayment->getOrder();

        /** @var OrderAddressInterface $billing */
        $billing = $order->getBillingAddress();

        $userTokenId = $this->userManagement->createUserId([
            'userTokenId' => $customerId,
            'firstName' => $billing->getFirstname(),
            'lastName' => $billing->getLastname(),
            'address' => '',
            'state' => '',
            'city' =>'',
            'zip' =>'',
            'countryCode' => $billing->getCountryId(),
            'phone' =>'',
            'locale' => 'en_UK',
            'email' => $billing->getEmail(),
            'dateOfBirth' => '',
        ]);

        return $userTokenId;
    }
}
