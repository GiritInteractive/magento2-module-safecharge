<?php

namespace Safecharge\Safecharge\Model\Redirect;

use Safecharge\Safecharge\Model\AbstractRequest;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Payment;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\Exception\PaymentException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;

/**
 * Safecharge Safecharge config provider model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Url
{
    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Url constructor.
     *
     * @param ModuleConfig    $moduleConfig
     * @param CheckoutSession $checkoutSession
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        ModuleConfig $moduleConfig,
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        if ($this->moduleConfig->getPaymentSolution() === Payment::SOLUTION_INTERNAL) {
            return '';
        }

        /** @var Quote $quote */
        $quote = $this->checkoutSession->getQuote();

        $shipping = 0;
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress !== null) {
            $shipping = $shippingAddress->getBaseShippingAmount();
        }

        $url = $this->getEndpoint();

        $queryParams = [
            'merchant_id' => $this->moduleConfig->getMerchantId(),
            'merchant_site_id' => $this->moduleConfig->getMerchantSiteId(),
            'total_amount' => round($quote->getBaseGrandTotal(), 2),
            'discount' => round($quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount(), 2),
            'shipping' => round($shipping, 2),
            'currency' => $quote->getBaseCurrencyCode(),
            'user_token_id' => $quote->getCustomerId(),
            'time_stamp' => date('YmdHis'),
            'version' => '3.0.0',
            'success_url' => $this->getSuccessUrl(),
            'error_url' => $this->getErrorUrl(),
            'back_url' => $this->getBackUrl(),
        ];

        $concat = $this->moduleConfig->getMerchantSecretKey()
            . $queryParams['merchant_id']
            . $queryParams['currency']
            . $queryParams['total_amount'];

        $numberOfItems = 0;
        $i = 1;

        $quoteItems = $quote->getAllVisibleItems();
        foreach ($quoteItems as $quoteItem) {
            $price = $quoteItem->getBasePrice();
            if (!$price) {
                continue;
            }

            $queryParams['item_name_' . $i] = $quoteItem->getName();
            $queryParams['item_amount_' . $i] = round($price, 2);
            $queryParams['item_quantity_' . $i] = (int)$quoteItem->getQty();

            $numberOfItems++;

            $concat .= $queryParams['item_name_' . $i]
                . $queryParams['item_amount_' . $i]
                . $queryParams['item_quantity_' . $i];

            $i++;
        }

        $queryParams['numberofitems'] = $numberOfItems;

        $concat .= $queryParams['user_token_id']
            . $queryParams['time_stamp'];

        $concat = utf8_encode($concat);
        $queryParams['checksum'] = hash('sha256', $concat);

        $url .= '?' . http_build_query($queryParams);

        return $url;
    }

    /**
     * Return full endpoint;
     *
     * @return string
     */
    private function getEndpoint()
    {
        $endpoint = AbstractRequest::LIVE_ENDPOINT;
        if ($this->moduleConfig->isTestModeEnabled() === true) {
            $endpoint = AbstractRequest::TEST_ENDPOINT;
        }

        return $endpoint . 'purchase.do';
    }

    /**
     * @return string
     */
    private function getSuccessUrl()
    {
        $quoteId = $this->checkoutSession->getQuoteId();

        return $this->urlBuilder->getUrl(
            'safecharge/payment/redirect_success',
            ['order' => $quoteId]
        );
    }

    /**
     * @return string
     */
    private function getErrorUrl()
    {
        $quoteId = $this->checkoutSession->getQuoteId();

        return $this->urlBuilder->getUrl(
            'safecharge/payment/redirect_error',
            ['order' => $quoteId]
        );
    }

    /**
     * @return string
     */
    private function getBackUrl()
    {
        return $this->urlBuilder->getUrl('checkout/cart');
    }
}
