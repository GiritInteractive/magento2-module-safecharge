<?php

namespace Girit\Safecharge\Controller\Payment;

use Magento\Framework\App\Action\Action;

/**
 * Girit Safecharge payment authenticate controller.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Authenticate extends Action
{
    public function execute()
    {
        /** @var \Magento\Framework\View\Layout $layout */
        $layout = $this->_view->getLayout();

        $block = $layout->createBlock(\Girit\Safecharge\Block\Payment\Authenticate\Form::class);
        $block->setTemplate('Girit_Safecharge::payment/authenticate/form.phtml');

        $this->getResponse()->setBody($block->toHtml());
    }
}
