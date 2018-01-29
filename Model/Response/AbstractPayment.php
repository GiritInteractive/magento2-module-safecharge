<?php

namespace Girit\Safecharge\Model\Response;

use Girit\Safecharge\Lib\Http\Client\Curl;
use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\Config;
use Girit\Safecharge\Model\Logger as SafechargeLogger;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Order\Payment\Transaction as OrderTransaction;

/**
 * Girit Safecharge abstract payment response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
abstract class AbstractPayment extends AbstractResponse
{
    /**
     * @var OrderPayment
     */
    protected $orderPayment;

    /**
     * AbstractPayment constructor.
     *
     * @param SafechargeLogger  $safechargeLogger
     * @param Config            $config
     * @param int               $requestId
     * @param Curl              $curl
     * @param OrderPayment|null $orderPayment
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config,
        $requestId,
        Curl $curl,
        OrderPayment $orderPayment
    ) {
        parent::__construct(
            $safechargeLogger,
            $config,
            $requestId,
            $curl
        );

        $this->orderPayment = $orderPayment;
    }

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $this
            ->processResponseData()
            ->updateTransaction();

        return $this;
    }

    /**
     * @return AbstractPayment
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function updateTransaction()
    {
        $body = $this->getBody();
        $transactionKeys = $this->getRequiredResponseDataKeys();

        $transactionInformation = [];
        foreach ($transactionKeys as $transactionKey) {
            if (!isset($body[$transactionKey])) {
                continue;
            }

            $transactionInformation[$transactionKey] = $body[$transactionKey];
        }
        ksort($transactionInformation);

        $this->orderPayment->setTransactionAdditionalInfo(
            OrderTransaction::RAW_DETAILS,
            $transactionInformation
        );

        return $this;
    }
}
