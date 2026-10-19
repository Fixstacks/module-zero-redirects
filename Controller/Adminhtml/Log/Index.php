<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Zero\Redirects\Model\Importer;

/**
 * Marketing > SEO & Search > 404 Log; the 404s buffered since the last cron
 * run come in first (and up to 20 suggestions), so the list is current.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_Redirects::log';

    public function __construct(
        Context $context,
        private readonly Importer $importer
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $this->importer->import(20);
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Zero_Redirects::log');
        $page->getConfig()->getTitle()->prepend(__('404 Log'));

        return $page;
    }
}
