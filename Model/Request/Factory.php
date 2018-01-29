<?php

namespace Girit\Safecharge\Model\Request;

use Girit\Safecharge\Model\AbstractRequest;
use Girit\Safecharge\Model\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;

/**
 * Girit Safecharge request factory model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Factory
{
    /**
     * Set of requests.
     *
     * @var array
     */
    private $invokableClasses = [
        AbstractRequest::GET_SESSION_TOKEN_METHOD => \Girit\Safecharge\Model\Request\Token::class,
        AbstractRequest::CREATE_USER_METHOD => \Girit\Safecharge\Model\Request\CreateUser::class,
        AbstractRequest::GET_USER_DETAILS_METHOD => \Girit\Safecharge\Model\Request\GetUserDetails::class,
        AbstractRequest::OPEN_ORDER_METHOD => \Girit\Safecharge\Model\Request\OpenOrder::class,
    ];

    /**
     * Object manager object.
     *
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * Construct
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create request model.
     *
     * @param string $method
     *
     * @return RequestInterface
     * @throws LocalizedException
     */
    public function create($method)
    {
        $className = !empty($this->invokableClasses[$method])
            ? $this->invokableClasses[$method]
            : null;

        if ($className === null) {
            throw new LocalizedException(
                __('%1 method is not supported.')
            );
        }

        $model = $this->objectManager->create($className);
        if (!$model instanceof RequestInterface) {
            throw new LocalizedException(
                __(
                    '%1 doesn\'t implement \Girit\Safecharge\Mode\RequestInterface',
                    $className
                )
            );
        }

        return $model;
    }
}
