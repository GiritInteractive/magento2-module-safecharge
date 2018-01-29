<?php

namespace Girit\Safecharge\Model\Response;

use Girit\Safecharge\Model\AbstractResponse;
use Girit\Safecharge\Model\ResponseInterface;

/**
 * Girit Safecharge create user response model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class CreateUser extends AbstractResponse implements ResponseInterface
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
        $this->userId = $body['userId'];

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
            'userId',
        ];
    }
}
