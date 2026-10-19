<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Redirects\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Zero\Redirects\Model\Config;

/**
 * The 404 Log page's switch (POST, form key checked by the admin): logging
 * on or off for the whole store (the default scope of "Log 404s"; a store
 * view can still differ in Stores > Configuration).
 */
class Toggle extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_Redirects::log';

    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly WriterInterface $configWriter,
        private readonly ReinitableConfigInterface $appConfig,
        private readonly TypeListInterface $cacheTypeList
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $on = (string) $this->getRequest()->getParam('logging') === '1';
        $this->configWriter->save(Config::ENABLED, $on ? '1' : '0');
        $this->cacheTypeList->cleanType('config');
        $this->appConfig->reinit();
        $this->messageManager->addSuccessMessage($on ? __('404 logging is on.') : __('404 logging is off.'));

        return $this->resultRedirectFactory->create()->setPath('zero_redirects/log/index');
    }
}
