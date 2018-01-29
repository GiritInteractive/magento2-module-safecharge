<?php

namespace Girit\Safecharge\Model\Response;

use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\ResponseInterface;

/**
 * Girit Safecharge open order response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class OpenOrder extends AbstractResponse implements ResponseInterface
{
    /**
     * @var string
     */
    protected $orderId;

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        $this->orderId = $body['orderId'];

        return $this;
    }

    /**
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'orderId',
        ];
    }
}
