<?php

namespace Girit\Safecharge\Model\Response;

use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\ResponseInterface;

/**
 * Girit Safecharge get user details response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class GetUserDetails extends AbstractResponse implements ResponseInterface
{
    /**
     * @var string
     */
    protected $userId;

    /**
     * @return AbstractResponse
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function process()
    {
        parent::process();

        $body = $this->getBody();
        $this->userId = $body['userDetails']['userId'];

        return $this;
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return array
     */
    protected function getRequiredResponseDataKeys()
    {
        return [
            'userDetails',
        ];
    }
}
