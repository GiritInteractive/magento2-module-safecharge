<?php

namespace Girit\Safecharge\Model\ResourceModel\RequestLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Girit Safecharge request log collection model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Collection extends AbstractCollection
{
    /**
     * Resource model construct that should be used for object initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();

        $this->_init(
            \Girit\Safecharge\Model\RequestLog::class,
            \Girit\Safecharge\Model\ResourceModel\RequestLog::class
        );
    }
}
