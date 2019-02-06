<?php

namespace Safecharge\Safecharge\Controller\Payment\Redirect;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
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
 * Safecharge Safecharge redirect pending controller.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Pending extends Action
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
        Onepage $onepageCheckout
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
    }

    /**
     * @return ResultInterface
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function execute()
    {
        if ($this->moduleConfig->isDebugEnabled() === true) {
            $this->safechargeLogger->debug(
                'Redirect Pending Response: '
                . json_encode($this->getRequest()->getParams())
            );
        }

        try {
            $response = $this->getRequest()->getParams();

            if (!isset($response['Status']) || in_array(strtolower($response['Status']), ['declined', 'error'])) {
                $order->setState(Order::STATE_PAYMENT_REVIEW)->setStatus(Order::STATE_PAYMENT_REVIEW)->save();
                throw new \Exception(__('Your payment failed.'));
            }

            $result = $this->placeOrder();
            if ($result->getSuccess() !== true) {
                throw new PaymentException(__($result->getErrorMessage()));
            }

            /** @var Order $order */
            $order = $this->orderFactory->create()->load($result->getOrderId());

            /** @var OrderPayment $payment */
            $orderPayment = $order->getPayment();

            $orderPayment->setAdditionalInformation(
                Payment::TRANSACTION_ID,
                $response['TransactionID']
            );

            if (isset($response['AuthCode']) && $response['AuthCode']) {
                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_AUTH_CODE_KEY,
                    $response['AuthCode']
                );
            }

            if (isset($response['payment_method']) && $response['payment_method']) {
                $orderPayment->setAdditionalInformation(
                    Payment::TRANSACTION_EXTERNAL_PAYMENT_METHOD,
                    $response['payment_method']
                );
            }
            $orderPayment->setTransactionAdditionalInfo(
                Transaction::RAW_DETAILS,
                $response
            );

            if (strtolower($response['Status']) === 'pending') {
                $orderPayment
                    ->setIsTransactionPending(true)
                    ->setIsTransactionClosed(0)
                    ->setTransactionId($response['TransactionID']);
                $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);
            } elseif (in_array(strtolower($response['Status']), ['approved', 'success'])) {
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
            }

            $orderPayment->save();
            $order->save();
        } catch (PaymentException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'));
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/onepage/success/'));

        return $resultRedirect;
    }

    /**
     * Place order.
     *
     * @return DataObject
     */
    private function placeOrder()
    {
        $result = $this->dataObjectFactory->create();

        try {
            /**
             * Current workaround depends on Onepage checkout model defect
             * Method Onepage::getCheckoutMethod performs setCheckoutMethod
             */
            $this->onepageCheckout->getCheckoutMethod();

            $orderId = $this->cartManagement->placeOrder($this->getQuoteId());

            $result
                ->setData('success', true)
                ->setData('order_id', $orderId);

            $this->_eventManager->dispatch(
                'safecharge_place_order',
                [
                    'result' => $result,
                    'action' => $this,
                ]
            );
        } catch (\Exception $exception) {
            $result
                ->setData('error', true)
                ->setData(
                    'error_message',
                    __('An error occurred on the server. Please try to place the order again.')
                );
        }

        return $result;
    }

    /**
     * @return int
     * @throws PaymentException
     */
    private function getQuoteId()
    {
        $quoteId = (int)$this->getRequest()->getParam('quote');

        if ((int)$this->checkoutSession->getQuoteId() === $quoteId) {
            return $quoteId;
        }

        throw new PaymentException(
            __('Session has expired, order has been not placed.')
        );
    }
}