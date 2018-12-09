<?php

namespace Safecharge\Safecharge\Controller\Payment;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\PaymentException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\OrderFactory;
use Safecharge\Safecharge\Model\AbstractRequest;
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
                $params = $this->getRequest()->getParams();
                if ($this->moduleConfig->isDebugEnabled()) {
                    $this->safechargeLogger->debug(
                        'DMN Params: '
                        . json_encode($params)
                    );
                }

                $result = $this->placeOrder();
                if ($result->getSuccess() !== true) {
                    throw new PaymentException(__($result->getErrorMessage()));
                }

                /** @var Order $order */
                $order = $this->orderFactory->create()->load($result->getOrderId());

                /** @var OrderPayment $payment */
                $orderPayment = $order->getPayment();

                $response = $this->getRequest()->getParams();

                if (strtolower($response['Status']) !== 'approved') {
                    throw new PaymentException(__('Your payment failed.'));
                }

                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_ID,
                    $response['TransactionID']
                );
                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_AUTH_CODE_KEY,
                    $response['AuthCode']
                );
                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD,
                    $response['payment_method']
                );
                $orderPayment->setTransactionAdditionalInfo(
                    Transaction::RAW_DETAILS,
                    $response
                );

                $isSettled = false;
                if ($this->moduleConfig->getPaymentAction() === Payment::ACTION_AUTHORIZE_CAPTURE) {
                    $isSettled = true;

                    $request = $this->paymentRequestFactory->create(
                        AbstractRequest::PAYMENT_SETTLE_METHOD,
                        $orderPayment,
                        $order->getBaseGrandTotal()
                    );
                    $settleResponse = $request->process();
                }

                if ($isSettled) {
                    $message = $this->captureCommand->execute(
                        $orderPayment,
                        $order->getBaseGrandTotal(),
                        $order
                    );
                    $transactionType = Transaction::TYPE_CAPTURE;
                } else {
                    $message = $this->authorizeCommand->execute(
                        $orderPayment,
                        $order->getBaseGrandTotal(),
                        $order
                    );
                    $transactionType = Transaction::TYPE_AUTH;
                }

                $orderPayment
                    ->setTransactionId($response['TransactionID'])
                    ->setIsTransactionPending(false)
                    ->setIsTransactionClosed($isSettled ? 1 : 0);

                if ($transactionType === Transaction::TYPE_CAPTURE) {
                    /** @var Invoice $invoice */
                    foreach ($order->getInvoiceCollection() as $invoice) {
                        $invoice
                            ->setTransactionId($settleResponse->getTransactionId())
                            ->pay()
                            ->save();
                    }
                }

                $transaction = $orderPayment->addTransaction($transactionType);

                $message = $orderPayment->prependMessage($message);
                $orderPayment->addTransactionCommentsToOrder(
                    $transaction,
                    $message
                );

                $orderPayment->save();
                $order->save();
            } catch (PaymentException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $this->jsonResultFactory->create()
            ->setHttpResponseCode(\Magento\Framework\Webapi\Response::HTTP_OK)
            ->setData(["error" => 0, "message" => "SUCCESS"]);
    }
}
