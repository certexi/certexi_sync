<?php

// SPDX-FileCopyrightText: 2026 Red Sustentable Internacional S.A. de C.V.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OCA\CertexiSync\Controller;

use OCA\CertexiSync\AppInfo\Application;
use OCA\CertexiSync\Service\Journal;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * Change feed and batch writes for Tables rows. Every read and write goes
 * through the Tables app's own services, which enforce its permissions.
 */
class ApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private Journal $journal,
		private IDBConnection $db,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	private function tables(string $service): object {
		return Server::get('OCA\Tables\Service\\' . $service);
	}

	private function canRead(int $tableId): bool {
		try {
			return $this->tables('PermissionsService')->canReadRowsByElementId($tableId, 'table', $this->userId);
		} catch (\Throwable) {
			return false;
		}
	}

	/** @param array<string|int, mixed> $data column id → value */
	private static function cells(array $data): array {
		$out = [];
		foreach ($data as $columnId => $value) {
			$out[] = ['columnId' => (int)$columnId, 'value' => $value];
		}
		return $out;
	}

	/** The current journal position; read changes after it later. */
	#[NoAdminRequired]
	public function cursor(): DataResponse {
		return new DataResponse(['cursor' => $this->journal->cursor()]);
	}

	/**
	 * Row changes after `after` for the given tables (comma-separated IDs),
	 * oldest first, collapsed to the latest state per row. Tables the user
	 * cannot read are left out. `reset` means the journal was pruned past
	 * `after`: re-read the tables and continue from `cursor`.
	 */
	#[NoAdminRequired]
	public function changes(string $tables, int $after = 0, int $limit = 500): DataResponse {
		$limit = max(1, min($limit, 2000));
		$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $tables)))));
		$readable = array_values(array_filter($ids, fn (int $t) => $this->canRead($t)));
		if ($after < $this->journal->prunedThrough()) {
			return new DataResponse(['reset' => true, 'cursor' => $this->journal->cursor(), 'readable' => $readable]);
		}
		$entries = $readable === [] ? [] : $this->journal->after($after, $readable, $limit);
		$latest = [];
		foreach ($entries as $e) {
			$latest[$e['rowId']] = $e; // later entries win
		}
		$rows = $this->tables('RowService');
		$changes = [];
		foreach ($latest as $e) {
			$row = null;
			if ($e['op'] !== Journal::OP_DELETE) {
				try {
					$row = $rows->find($e['rowId'])->jsonSerialize();
				} catch (\Throwable) {
					$row = null; // deleted since: report it as a deletion
				}
			}
			$changes[] = [
				'seq' => $e['seq'],
				'tableId' => $e['tableId'],
				'rowId' => $e['rowId'],
				'deleted' => $row === null,
				'row' => $row,
			];
		}
		$cursor = $entries === [] ? $after : $entries[count($entries) - 1]['seq'];
		return new DataResponse([
			'reset' => false,
			'cursor' => $cursor,
			'more' => count($entries) === $limit,
			'readable' => $readable,
			'changes' => $changes,
		]);
	}

	/**
	 * Apply row creates, updates and deletes to one table in a single
	 * database transaction: all succeed or none do.
	 *
	 * @param list<array<string, mixed>> $create cell maps (column id → value)
	 * @param list<array{id: int, data: array<string, mixed>}> $update
	 * @param list<int> $delete row IDs
	 */
	#[NoAdminRequired]
	public function batch(int $tableId, array $create = [], array $update = [], array $delete = []): DataResponse {
		if (count($create) + count($update) + count($delete) > Journal::BATCH_LIMIT) {
			return new DataResponse(['message' => 'At most ' . Journal::BATCH_LIMIT . ' operations per batch.'], Http::STATUS_BAD_REQUEST);
		}
		if ($this->userId === null || !$this->canRead($tableId)) {
			return new DataResponse(['message' => 'Table not found.'], Http::STATUS_NOT_FOUND);
		}
		$rows = $this->tables('RowService');
		$belongs = function (int $rowId) use ($rows, $tableId): void {
			try {
				$found = $rows->find($rowId)->getTableId();
			} catch (\Throwable) {
				throw new \InvalidArgumentException("Row $rowId was not found.");
			}
			if ($found !== $tableId) {
				throw new \InvalidArgumentException("Row $rowId is not in table $tableId.");
			}
		};
		$created = [];
		$this->db->beginTransaction();
		try {
			foreach ($create as $data) {
				$created[] = $rows->create($tableId, null, self::cells((array)$data))->getId();
			}
			foreach ($update as $item) {
				$id = (int)($item['id'] ?? 0);
				$belongs($id);
				$rows->updateSet($id, null, self::cells((array)($item['data'] ?? [])), $this->userId, $tableId);
			}
			foreach ($delete as $id) {
				$belongs((int)$id);
				$rows->delete((int)$id, null, $this->userId);
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$status = str_contains(get_class($e), 'PermissionError') ? Http::STATUS_FORBIDDEN : Http::STATUS_BAD_REQUEST;
			$this->logger->warning('certexi_sync: batch rolled back', ['exception' => $e]);
			return new DataResponse(['message' => $e->getMessage()], $status);
		}
		return new DataResponse([
			'created' => $created,
			'updated' => count($update),
			'deleted' => count($delete),
		]);
	}
}
