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
use Magento\Framework\Exception\LocalizedException;
use Zero\Redirects\Model\Log;
use Zero\Redirects\Model\Redirector;

/**
 * A row's action (POST, form key checked by the admin): `redirect` with a
 * target makes the 301; `ignore` and `reopen` change its status. Back to
 * the list as it was filtered.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_Redirects::log';

    public function __construct(
        Context $context,
        private readonly Log $log,
        private readonly Redirector $redirector
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $request = $this->getRequest();
        $logId = (int) $request->getParam('id');
        $back = array_filter([
            'status' => (string) $request->getParam('back_status'),
            'q' => (string) $request->getParam('back_q'),
            'p' => (int) $request->getParam('back_p') > 1 ? (string) (int) $request->getParam('back_p') : '',
        ], static fn ($v) => $v !== '');
        try {
            switch ((string) $request->getParam('do')) {
                case 'redirect':
                    $this->redirector->redirect($logId, (string) $request->getParam('target'));
                    $this->messageManager->addSuccessMessage(__('Redirected with a 301.'));
                    break;
                case 'ignore':
                    $this->log->setStatus($logId, Log::IGNORED);
                    $this->messageManager->addSuccessMessage(__('Ignored.'));
                    break;
                case 'reopen':
                    $this->log->setStatus($logId, Log::OPEN);
                    $this->messageManager->addSuccessMessage(__('Back in the open 404s.'));
                    break;
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('zero_redirects/log/index', $back);
    }
}
