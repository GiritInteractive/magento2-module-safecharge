/**
 * Girit Safecharge js component.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'safecharge',
                component: 'Girit_Safecharge/js/view/payment/method-renderer/safecharge'
            }
        );

        return Component.extend({});
    }
);