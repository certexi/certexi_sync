<?php

// SPDX-FileCopyrightText: 2026 Red Sustentable Internacional S.A. de C.V.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OCA\CertexiSync\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;

/**
 * Append-only journal of Tables row changes: (seq, table, row, op). No cell
 * values are stored; readers fetch current rows through Tables itself.
 */
class Journal {
	public const TABLE = 'certexi_sync_journal';
	public const OP_CREATE = 1;
	public const OP_UPDATE = 2;
	public const OP_DELETE = 3;
	public const BATCH_LIMIT = 500;
	public const RETENTION_SECONDS = 30 * 24 * 3600;

	public function __construct(
		private IDBConnection $db,
		private ITimeFactory $time,
		private IAppConfig $config,
	) {
	}

	public function record(int $tableId, int $rowId, int $op): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'table_id' => $qb->createNamedParameter($tableId, IQueryBuilder::PARAM_INT),
			'row_id' => $qb->createNamedParameter($rowId, IQueryBuilder::PARAM_INT),
			'op' => $qb->createNamedParameter($op, IQueryBuilder::PARAM_INT),
			'created_at' => $qb->createNamedParameter($this->time->getTime(), IQueryBuilder::PARAM_INT),
		])->executeStatement();
	}

	/** Highest sequence number so far (0 if empty). */
	public function cursor(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('id'))->from(self::TABLE);
		$result = $qb->executeQuery();
		$max = $result->fetchOne();
		$result->closeCursor();
		return (int)($max ?? 0);
	}

	/** Entries with seq > $after are complete only if $after >= this. */
	public function prunedThrough(): int {
		return $this->config->getValueInt('certexi_sync', 'pruned_through', 0);
	}

	/**
	 * @param list<int> $tableIds
	 * @return list<array{seq: int, tableId: int, rowId: int, op: int}>
	 */
	public function after(int $after, array $tableIds, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'table_id', 'row_id', 'op')
			->from(self::TABLE)
			->where($qb->expr()->gt('id', $qb->createNamedParameter($after, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('table_id', $qb->createNamedParameter($tableIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id', 'ASC')
			->setMaxResults($limit);
		$result = $qb->executeQuery();
		$out = [];
		while ($row = $result->fetch()) {
			$out[] = [
				'seq' => (int)$row['id'],
				'tableId' => (int)$row['table_id'],
				'rowId' => (int)$row['row_id'],
				'op' => (int)$row['op'],
			];
		}
		$result->closeCursor();
		return $out;
	}

	public function prune(): void {
		$cutoff = $this->time->getTime() - self::RETENTION_SECONDS;
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('id'))->from(self::TABLE)
			->where($qb->expr()->lt('created_at', $qb->createNamedParameter($cutoff, IQueryBuilder::PARAM_INT)));
		$result = $qb->executeQuery();
		$through = (int)($result->fetchOne() ?? 0);
		$result->closeCursor();
		if ($through === 0) {
			return;
		}
		// Record the gap before deleting, so a reader never misses it.
		$this->config->setValueInt('certexi_sync', 'pruned_through', max($through, $this->prunedThrough()));
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)
			->where($qb->expr()->lte('id', $qb->createNamedParameter($through, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
