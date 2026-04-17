<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Psr\Log\LoggerInterface;

/**
 * Adds a partial UNIQUE index that prevents two open invites for the same
 * (user_id, recipient_email) pair, defending against TOCTOU races between
 * the application-level claim and the database write.
 *
 * Postgres and SQLite support partial indexes natively. MySQL/MariaDB do
 * not, so the migration logs an INFO line and relies on the application
 * guard in FederatedInviteMapper::claimInviteForEmail().
 */
class Version8006Date20260417120100 extends SimpleMigrationStep {
	private const INDEX_NAME = 'fed_inv_open_email_uniq';

	public function __construct(
		private IDBConnection $connection,
		private LoggerInterface $logger,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$provider = $this->connection->getDatabaseProvider();
		if ($provider !== IDBConnection::PLATFORM_POSTGRES && $provider !== IDBConnection::PLATFORM_SQLITE) {
			$output->info(sprintf(
				'Skipping partial unique index %s on %s; application-level guard remains in effect.',
				self::INDEX_NAME,
				$provider,
			));
			$this->logger->info(
				'Skipped partial unique index for federated_invites on database provider {provider}.',
				['app' => 'contacts', 'provider' => $provider],
			);
			return null;
		}
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$provider = $this->connection->getDatabaseProvider();
		if ($provider !== IDBConnection::PLATFORM_POSTGRES && $provider !== IDBConnection::PLATFORM_SQLITE) {
			return;
		}

		$predicate = $provider === IDBConnection::PLATFORM_POSTGRES
			? 'recipient_email IS NOT NULL AND accepted = false'
			: 'recipient_email IS NOT NULL AND accepted = 0';

		$sql = sprintf(
			'CREATE UNIQUE INDEX IF NOT EXISTS %s ON %sfederated_invites (user_id, recipient_email) WHERE %s',
			self::INDEX_NAME,
			'*PREFIX*',
			$predicate,
		);

		try {
			$this->connection->executeStatement($sql);
		} catch (\Throwable $e) {
			$this->logger->warning(
				'Failed to create partial unique index for federated_invites: {message}',
				['app' => 'contacts', 'message' => $e->getMessage(), 'exception' => $e],
			);
		}
	}
}
