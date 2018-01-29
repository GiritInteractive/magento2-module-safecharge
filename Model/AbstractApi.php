<?php

namespace Girit\Safecharge\Model;

use Girit\Safecharge\Model\Logger as SafechargeLogger;

/**
 * Girit Safecharge abstract api model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
abstract class AbstractApi
{
    /**
     * @var SafechargeLogger
     */
    protected $safechargeLogger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * Object initialization.
     *
     * @param SafechargeLogger $safechargeLogger
     * @param Config           $config
     */
    public function __construct(
        SafechargeLogger $safechargeLogger,
        Config $config
    ) {
        $this->safechargeLogger = $safechargeLogger;
        $this->config = $config;
    }
}
