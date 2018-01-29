<?php

namespace Girit\Safecharge\Model;

/**
 * Girit Safecharge private data keys provider model.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class PrivateDataKeysProvider
{
    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'cardNumber',
            'CVV',
        ];
    }
}
