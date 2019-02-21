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
        $totalTax = 0;
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress !== null) {
            $shipping = $shippingAddress->getBaseShippingAmount();
            $totalTax = $shippingAddress->getBaseTaxAmount();
        }

        $reservedOrderId = $quotePayment->getAdditionalInformation(Payment::TRANSACTION_ORDER_ID) ?: $this->moduleConfig->getReservedOrderId();

        $queryParams = [
            'merchant_id' => $this->moduleConfig->getMerchantId(),
            'merchant_site_id' => $this->moduleConfig->getMerchantSiteId(),
            'customField1' => $this->moduleConfig->getSourcePlatformField(),
            'total_amount' => (float)$quote->getBaseGrandTotal(),
            'discount' => (float)abs($quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount()),
            'shipping' => (float)$shipping,
            'total_tax' => ($totalTax && $quote->getBaseSubtotalWithDiscount()) ? (float)round(($totalTax / $quote->getBaseSubtotalWithDiscount()), 4) : (float)$totalTax,
            'currency' => $quote->getBaseCurrencyCode(),
            'user_token_id' => $quote->getCustomerId(),
            'time_stamp' => date('YmdHis'),
            'version' => '4.0.0',
            'success_url' => $this->moduleConfig->getCallbackSuccessUrl(),
            'pending_url' => $this->moduleConfig->getCallbackPendingUrl(),
            'error_url' => $this->moduleConfig->getCallbackErrorUrl(),
            'back_url' => $this->moduleConfig->getBackUrl(),
            'notify_url' => $this->moduleConfig->getCallbackDmnUrl($reservedOrderId),
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

        $numberOfItems = 0;
        $i = 1;

        $quoteItems = $quote->getAllVisibleItems();
        foreach ($quoteItems as $quoteItem) {
            if (!($price = $quoteItem->getBasePrice())) {
                continue;
            }
            $queryParams['item_name_' . $i] = $quoteItem->getName();
            $queryParams['item_amount_' . $i] = round($price, 2);
            $queryParams['item_quantity_' . $i] = (int)$quoteItem->getQty();
            $numberOfItems++;
            $i++;
        }

        $queryParams['numberofitems'] = $numberOfItems;

        $queryParams['checksum'] = hash('sha256', utf8_encode($this->moduleConfig->getMerchantSecretKey() . implode("", $queryParams)));

        return $queryParams;
    }
}
