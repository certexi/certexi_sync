<?php

// SPDX-FileCopyrightText: 2026 Red Sustentable Internacional S.A. de C.V.
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace OCA\CertexiSync\Migration;

use Closure;
use OCA\CertexiSync\Service\Journal;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000100Date20261116000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if ($schema->hasTable(Journal::TABLE)) {
			return null;
		}
		$table = $schema->createTable(Journal::TABLE);
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
		$table->addColumn('table_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
		$table->addColumn('row_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
		$table->addColumn('op', Types::SMALLINT, ['notnull' => true, 'unsigned' => true]);
		$table->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['table_id', 'id'], 'cxsync_table_seq');
		$table->addIndex(['created_at'], 'cxsync_created');
		return $schema;
	}
}
