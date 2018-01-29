<?php

namespace Girit\Safecharge\Model\Logger;

use Magento\Framework\Logger\Handler\Base;

/**
 * Girit Safecharge logger handler model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Handler extends Base
{
    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/girit_safecharge.log';
}
