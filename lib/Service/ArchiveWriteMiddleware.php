<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);

namespace OCA\CertexiSync\Service;

use OCA\Tables\Controller\Api1Controller;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Service\PermissionsService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use OCP\Server;

final class ArchivedWriteDenied extends \RuntimeException {}

final class ArchiveWriteMiddleware extends Middleware {
    private ?string $lock = null;

    public function __construct(private IRequest $request, private IUserSession $session, private ILockingProvider $locks, private \OCP\App\IAppManager $apps) {}

    public function beforeController($controller, $methodName) {
        // FlowApp sends edits and archive stamps through Tables API v1.
        if (!($controller instanceof Api1Controller) || $methodName !== 'updateRow') {
            return;
        }
        // Forms 1.1.1 already guards this request with the same exclusive lock.
        if ($this->apps->isEnabledForUser('certexi_forms') &&
            class_exists(\OCA\CertexiForms\Service\ArchiveWriteMiddleware::class)) {
            return;
        }
        $actor = $this->session->getUser()?->getUID();
        $rowId = (int)$this->request->getParam('rowId');
        if ($actor === null || $rowId <= 0) {
            throw new ArchivedWriteDenied();
        }
        $key = 'certexi/tables/rows/' . $rowId;
        $this->locks->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE);
        $this->lock = $key;
        try {
            $tableId = Server::get(Row2Mapper::class)->getTableIdForRow($rowId);
            $viewId = (int)$this->request->getParam('viewId');
            $permissions = Server::get(PermissionsService::class);
            $archive = Server::get(ArchiveState::class);
            if ($tableId === null || ($viewId
                ? !$archive->canReadViewRow($tableId, $viewId, $rowId)
                : !$permissions->canReadRowsByElementId($tableId, 'table', $actor))) {
                throw new ArchivedWriteDenied();
            }
            // Table managers keep the existing table-scoped recovery workflow.
            if ($archive->isArchived($tableId, $rowId) && ($viewId ||
                !$permissions->canManageTableById($tableId, $actor) ||
                !$archive->isRestore($tableId, $this->request->getParam('data')))) {
                throw new ArchivedWriteDenied();
            }
        } catch (\Throwable $error) {
            $this->unlock();
            throw $error;
        }
    }

    private function unlock(): void {
        if ($this->lock !== null) {
            $this->locks->releaseLock($this->lock, ILockingProvider::LOCK_EXCLUSIVE);
            $this->lock = null;
        }
    }

    public function afterController($controller, $methodName, Response $response): Response {
        $this->unlock();
        return $response;
    }

    public function afterException($controller, $methodName, \Exception $exception) {
        $this->unlock();
        if ($exception instanceof ArchivedWriteDenied) {
            return new DataResponse(['message' => 'Record is read-only'], 403);
        }
        throw $exception;
    }
}
