<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\Attributes\ModifyColumn;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

#[ModifyColumn(table: 'federated_invites', name: 'accepted', description: 'Make the accepted flag NOT NULL after backfilling NULL rows to false')]
class Version8005Date20260417120000 extends SimpleMigrationStep {
	public function __construct(
		private IDBConnection $connection,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->update('federated_invites')
			->set('accepted', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->isNull('accepted'));
		$qb->executeStatement();
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('federated_invites')) {
			return null;
		}

		$table = $schema->getTable('federated_invites');
		if (!$table->hasColumn('accepted')) {
			return null;
		}

		$column = $table->getColumn('accepted');
		if ($column->getNotnull() === true) {
			return null;
		}

		$column->setNotnull(true);
		$column->setDefault(false);

		return $schema;
	}
}
