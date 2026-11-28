<?php
// SPDX-License-Identifier: AGPL-3.0-only
declare(strict_types=1);
namespace OCA\CertexiSync\Migration;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000300Date20261128000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();
        if ($schema->hasTable('cxsync_booking_events')) return null;
        $t = $schema->createTable('cxsync_booking_events');
        $t->addColumn('calendar_id', Types::BIGINT, ['notnull'=>true]);
        $t->addColumn('operation_id', Types::STRING, ['notnull'=>true,'length'=>36]);
        $t->addColumn('revision', Types::BIGINT, ['notnull'=>true]);
        $t->addColumn('etag', Types::STRING, ['notnull'=>true,'length'=>128]);
        $t->setPrimaryKey(['calendar_id','operation_id']);
        return $schema;
    }
}
