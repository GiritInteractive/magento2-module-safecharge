<?php

namespace Girit\Safecharge\Controller\Payment\Redirect;

use Girit\Safecharge\Model\Config as ModuleConfig;
use Girit\Safecharge\Model\Logger as SafechargeLogger;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Girit Safecharge payment place controller.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Error extends Action
{
    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * Error constructor.
     *
     * @param Context          $context
     * @param SafechargeLogger $safechargeLogger
     * @param ModuleConfig     $moduleConfig
     */
    public function __construct(
        Context $context,
        SafechargeLogger $safechargeLogger,
        ModuleConfig $moduleConfig
    ) {
        parent::__construct($context);

        $this->safechargeLogger = $safechargeLogger;
        $this->moduleConfig = $moduleConfig;
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
                'Redirect Error Response: '
                . var_export($this->getRequest()->getParams(), true)
            );
        }

        $this->messageManager->addErrorMessage(
            __('Your payment failed.')
        );

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart'));

        return $resultRedirect;
    }
}
