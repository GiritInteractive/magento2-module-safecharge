<?php

namespace Girit\Safecharge\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Girit Safecharge admin request index controller.
 *
 * @category Girit
 * @package  Girit_Safecharge
 */
class Index extends Action
{
    const ADMIN_RESOURCE = 'Girit_Safecharge::sales_safecharge_request';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        $resultPage->setActiveMenu('Girit_Safecharge::sales_safecharge_request');
        $resultPage->getConfig()->getTitle()->prepend(__('Safecharge Api Requests'));

        $resultPage->addBreadcrumb(__('Safecharge'), __('Safecharge'));
        $resultPage->addBreadcrumb(__('Api Requests'), __('Api Requests'));

        return $resultPage;
    }
}
