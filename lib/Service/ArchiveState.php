<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);

namespace OCA\CertexiSync\Service;

use OCA\Tables\Db\ColumnMapper;
use OCA\Tables\Db\Row2Mapper;
use OCA\Tables\Db\ViewMapper;
use OCA\Tables\Service\PermissionsService;
use OCP\IUserSession;

final class ArchiveState {
    public function __construct(
        private Row2Mapper $rows,
        private ColumnMapper $columns,
        private ViewMapper $views,
        private PermissionsService $permissions,
        private IUserSession $session,
    ) {}

    public function canReadViewRow(int $tableId, int $viewId, int $rowId): bool {
        $actor = $this->session->getUser()?->getUID();
        if ($actor === null || !$this->permissions->canReadRowsByElementId($viewId, 'view', $actor)) {
            return false;
        }
        $view = $this->views->find($viewId);
        return $view->getTableId() === $tableId &&
            $this->rows->getTableIdForRow($rowId) === $tableId &&
            $this->rows->isRowInViewPresent($rowId, $view, $actor);
    }

    private function markers(int $tableId): array {
        return array_filter($this->columns->findAllByTable($tableId), static fn($col) =>
            str_contains((string)$col->getDescription(), '[certexi:lifecycle]') ||
            preg_match('/^_certexi_lifecycle(?: \(\d+\))?$/D', trim($col->getTitle())));
    }

    public function isRestore(int $tableId, mixed $data): bool {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (!is_array($data) || !$data) {
            return false;
        }
        $ids = array_map(static fn($col) => $col->getId(), $this->markers($tableId));
        foreach ($data as $key => $value) {
            if (!is_int($key) || !in_array($key, $ids, true) || !in_array($value, ['', null], true)) {
                return false;
            }
        }
        return true;
    }

    public function isArchived(int $tableId, int $rowId): bool {
        $markers = $this->markers($tableId);
        if (!$markers) {
            return false;
        }
        $row = $this->rows->find($rowId, array_values($markers));
        if ($row->getTableId() !== $tableId || $row->getId() !== $rowId) {
            throw new \RuntimeException('Archive row mismatch');
        }
        foreach ($row->getData() as $cell) {
            $value = $cell['value'];
            if ($value === true || $value === 1 || (is_string($value) &&
                preg_match('/^(?:archived|true$|1$)/i', trim($value)))) {
                return true;
            }
        }
        return false;
    }
}
