<?php

namespace Girit\Safecharge\Controller\Payment;

use Girit\Safecharge\Model\Config as ModuleConfig;
use Girit\Safecharge\Model\Logger as SafechargeLogger;
use Girit\Safecharge\Model\Redirect\Url as RedirectUrlBuilder;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;

/**
 * Girit Safecharge payment redirect controller.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Redirect extends Action
{
    /**
     * @var RedirectUrlBuilder
     */
    private $redirectUrlBuilder;

    /**
     * @var SafechargeLogger
     */
    private $safechargeLogger;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * Redirect constructor.
     *
     * @param Context            $context
     * @param RedirectUrlBuilder $redirectUrlBuilder
     * @param SafechargeLogger   $safechargeLogger
     * @param ModuleConfig       $moduleConfig
     */
    public function __construct(
        Context $context,
        RedirectUrlBuilder $redirectUrlBuilder,
        SafechargeLogger $safechargeLogger,
        ModuleConfig $moduleConfig
    ) {
        parent::__construct($context);

        $this->redirectUrlBuilder = $redirectUrlBuilder;
        $this->safechargeLogger = $safechargeLogger;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @return ResponseInterface
     */
    public function execute()
    {
        $url = $this->redirectUrlBuilder->getUrl();

        if ($this->moduleConfig->isDebugEnabled() === true) {
            $this->safechargeLogger->debug('Redirect URL: ' . $url);
        }

        return $this->getResponse()->setBody($url);
    }
}
