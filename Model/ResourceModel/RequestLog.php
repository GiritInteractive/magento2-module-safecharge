<?php

namespace Girit\Safecharge\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Girit Safecharge request log resource model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class RequestLog extends AbstractDb
{
    /**
     * Resource model construct that should be used for object initialization.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('girit_safecharge_api_request_log_grid', 'request_id');
    }
}
