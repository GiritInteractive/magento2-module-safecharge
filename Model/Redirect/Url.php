<?php

namespace Safecharge\Safecharge\Model\Redirect;

use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Quote\Model\Quote;
use Safecharge\Safecharge\Model\Config as ModuleConfig;
use Safecharge\Safecharge\Model\Payment;

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
     * Url constructor.
     *
     * @param ModuleConfig    $moduleConfig
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        ModuleConfig $moduleConfig,
        CheckoutSession $checkoutSession
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->moduleConfig->getEndpoint() . '?' . http_build_query($this->prepareParams());
    }

    /**
     * @return string
     */
    public function getPostData()
    {
        return [
            "url" => $this->moduleConfig->getEndpoint(),
            "params" => $this->prepareParams()
        ];
    }

    /**
     * @return array
     */
    protected function prepareParams()
    {
        if ($this->moduleConfig->getPaymentSolution() === Payment::SOLUTION_INTERNAL) {
            return '';
        }

        /** @var Quote $quote */
        $quote = $this->checkoutSession->getQuote();

        $quotePayment = $quote->getPayment();

        $shipping = 0;
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress !== null) {
            $shipping = $shippingAddress->getBaseShippingAmount();
        }

        $reservedOrderId = $quotePayment->getAdditionalInformation(Payment::TRANSACTION_ORDER_ID) ?: $this->moduleConfig->getReservedOrderId();

        $queryParams = [
            'merchant_id' => $this->moduleConfig->getMerchantId(),
            'merchant_site_id' => $this->moduleConfig->getMerchantSiteId(),
            'customField1' => $this->moduleConfig->getSourcePlatformField(),
            'total_amount' => round($quote->getBaseGrandTotal(), 2),
            'discount' => round($quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount(), 2),
            'shipping' => round($shipping, 2),
            'currency' => $quote->getBaseCurrencyCode(),
            'user_token_id' => $quote->getCustomerId(),
            'time_stamp' => date('YmdHis'),
            'version' => '4.0.0',
            'success_url' => $this->moduleConfig->getRedirectSuccessUrl(),
            'pending_url' => $this->moduleConfig->getRedirectPendingUrl(),
            'error_url' => $this->moduleConfig->getRedirectErrorUrl(),
            'back_url' => $this->moduleConfig->getBackUrl(),
            'notify_url' => $this->moduleConfig->getDmnUrl($reservedOrderId),
            'merchant_unique_id' => $reservedOrderId,
            'ipAddress' => $quote->getRemoteIp(),
            'encoding' => 'UTF-8',
        ];

        if (($billing = $quote->getBillingAddress()) && $billing !== null) {
            $billingAddress = [
                'first_name' => $billing->getFirstname(),
                'last_name' => $billing->getLastname(),
                'address' => is_array($billing->getStreet()) ? implode(' ', $billing->getStreet()) : '',
                'cell' => '',
                'phone' => $billing->getTelephone(),
                'zip' => $billing->getPostcode(),
                'city' => $billing->getCity(),
                'country' => $billing->getCountryId(),
                'state' => $billing->getRegionCode(),
                'email' => $billing->getEmail(),
            ];
            $queryParams = array_merge($queryParams, $billingAddress);
        }

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

        return $queryParams;
    }
}
