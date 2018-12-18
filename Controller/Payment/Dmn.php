<?php

namespace Safecharge\Safecharge\Controller\Payment;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Logger as SafechargeLogger;
use Safecharge\Safecharge\Model\Payment;
use Safecharge\Safecharge\Model\Request\Payment\Factory as PaymentRequestFactory;

/**
 * Safecharge Safecharge payment redirect controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Dmn extends Action
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var AuthorizeCommand
     */
    private $authorizeCommand;

    /**
     * @var CaptureCommand
     */
    private $captureCommand;

    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var PaymentRequestFactory
     */
    private $paymentRequestFactory;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Onepage
     */
    private $onepageCheckout;

    /**
     * @var JsonFactory
     */
    private $jsonResultFactory;

    /**
     * Object constructor.
     *
     * @param Context                 $context
     * @param PaymentRequestFactory   $paymentRequestFactory
     * @param OrderFactory            $orderFactory
     * @param ModuleConfig            $moduleConfig
     * @param AuthorizeCommand        $authorizeCommand
     * @param CaptureCommand          $captureCommand
     * @param SafechargeLogger        $safechargeLogger
     * @param DataObjectFactory       $dataObjectFactory
     * @param CartManagementInterface $cartManagement
     * @param CheckoutSession         $checkoutSession
     * @param Onepage                 $onepageCheckout
     * @param JsonFactory             $jsonResultFactory
     */
    public function __construct(
        Context $context,
        PaymentRequestFactory $paymentRequestFactory,
        OrderFactory $orderFactory,
        ModuleConfig $moduleConfig,
        AuthorizeCommand $authorizeCommand,
        CaptureCommand $captureCommand,
        SafechargeLogger $safechargeLogger,
        DataObjectFactory $dataObjectFactory,
        CartManagementInterface $cartManagement,
        CheckoutSession $checkoutSession,
        Onepage $onepageCheckout,
        JsonFactory $jsonResultFactory
    ) {
        parent::__construct($context);

        $this->orderFactory = $orderFactory;
        $this->moduleConfig = $moduleConfig;
        $this->authorizeCommand = $authorizeCommand;
        $this->captureCommand = $captureCommand;
        $this->safechargeLogger = $safechargeLogger;
        $this->paymentRequestFactory = $paymentRequestFactory;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->cartManagement = $cartManagement;
        $this->checkoutSession = $checkoutSession;
        $this->onepageCheckout = $onepageCheckout;
        $this->jsonResultFactory = $jsonResultFactory;
    }

    /**
     * @return JsonFactory
     */
    public function execute()
    {
        if ($this->moduleConfig->isActive()) {
            try {
                $params = array_merge(
                    $this->getRequest()->getParams(),
                    $this->getRequest()->getPostValue()
                );

                if ($this->moduleConfig->isDebugEnabled()) {
                    $this->safechargeLogger->debug(
                        'DMN Params: '
                        . json_encode($params)
                    );
                }

                $this->validateChecksum($params);

                if (isset($params["merchant_unique_id"]) && $params["merchant_unique_id"]) {
                    $orderIncrementId = $params["merchant_unique_id"];
                } elseif (isset($params["order"]) && $params["order"]) {
                    $orderIncrementId = $params["order"];
                } elseif (isset($params["orderId"]) && $params["orderId"]) {
                    $orderIncrementId = $params["orderId"];
                } else {
                    $orderIncrementId = null;
                }

                /** @var Order $order */
                $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);

                if (!($order && $order->getId())) {
                    throw new \Exception(__('Order #%1 not found!', $orderIncrementId));
                }

                /** @var OrderPayment $payment */
                $orderPayment = $order->getPayment();

                if (isset($params['Status']) && strtolower($params['Status']) !== 'approved') {
                    throw new \Exception(__('Payment Failed.'));
                }

                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_ID,
                    $params['TransactionID']
                );
                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_AUTH_CODE_KEY,
                    $params['AuthCode']
                );
                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD,
                    $params['payment_method']
                );
                $orderPayment->setTransactionAdditionalInfo(
                    Transaction::RAW_DETAILS,
                    $params
                );

                $orderPayment->save();
                $order->save();
            } catch (\Exception $e) {
                if ($this->moduleConfig->isDebugEnabled()) {
                    $this->safechargeLogger->debug('DMN Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                }
                return $this->jsonResultFactory->create()
                    ->setHttpResponseCode(500)
                    ->setData(["error" => 1, "message" => $e->getMessage()]);
            }
        }

        if ($this->moduleConfig->isDebugEnabled()) {
            $this->safechargeLogger->debug('DMN Success for order #' . $orderIncrementId);
        }

        return $this->jsonResultFactory->create()
            ->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK)
            ->setData(["error" => 0, "message" => "SUCCESS"]);
    }

    private function validateChecksum($params)
    {
        if (!isset($params["advanceResponseChecksum"])) {
            throw new \Exception(
                __('Required key advanceResponseChecksum for checksum calculation is missing.')
            );
        }
        $concat = $this->moduleConfig->getMerchantSecretKey();
        foreach (['totalAmount', 'currency', 'responseTimeStamp', 'PPP_TransactionID', 'Status', 'productId'] as $checksumKey) {
            if (!isset($params[$checksumKey])) {
                throw new \Exception(
                    __('Required key %1 for checksum calculation is missing.', $checksumKey)
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

        $checksum = hash('sha256', utf8_encode($concat));
        if ($params["advanceResponseChecksum"] !== $checksum) {
            throw new \Exception(
                __('Checksum validation failed!')
            );
        }

        return true;
    }
}
