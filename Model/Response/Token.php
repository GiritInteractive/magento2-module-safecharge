<?php

namespace Girit\Safecharge\Model\Response;

use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\ResponseInterface;

/**
 * Girit Safecharge token response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Token extends AbstractResponse implements ResponseInterface
{
    /**
     * @var string
     */
    protected $token;

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        $this->token = $body['sessionToken'];

        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'sessionToken',
        ];
    }
}
