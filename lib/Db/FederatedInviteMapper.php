<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<FederatedInvite>
 */
class FederatedInviteMapper extends QBMapper {
	public const TABLE_NAME = 'federated_invites';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME);
	}

	/**
	 * Returns the federated invite with the specified token
	 *
	 * @return FederatedInvite
	 */
	public function findByToken(string $token): FederatedInvite {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('federated_invites')
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token)));
		return $this->findEntity($qb);
	}

	/**
	 * Returns all open federated invites for the user with the specified user id
	 *
	 * @return array a list of FederatedInvite objects
	 */
	public function findOpenInvitesByUid(string $userId):array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('accepted', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
		return $this->findEntities($qb);
	}

	/**
	 * Returns all open federated invites for the user with the specified user id and for the specified recipient email
	 *
	 * @return array a list of FederatedInvite objects
	 */
	public function findOpenInvitesByRecipientEmail(string $userId, string $email):array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('recipient_email', $qb->createNamedParameter($email)))
			->andWhere($qb->expr()->eq('accepted', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
		return $this->findEntities($qb);
	}

	/**
	 * Returns the federated invite with the specified token for the user with the specified user id.
	 *
	 * @return FederatedInvite the matching invite
	 */
	public function findInviteByTokenAndUid(string $token, string $userId):FederatedInvite {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(self::TABLE_NAME)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
		return $this->findEntity($qb);
	}

	/**
	 * Atomically claims an unclaimed (recipient_email IS NULL) and unaccepted
	 * invite for the given email and refreshes its lifetime.
	 *
	 * Returns true when exactly one row was affected, meaning the caller now
	 * owns the (token, recipient_email) pair. Returns false when the row no
	 * longer matches the precondition (a concurrent attach already claimed
	 * the invite, the invite was accepted, or the row vanished).
	 */
	public function claimInviteForEmail(
		string $token,
		string $userId,
		string $email,
		int $createdAt,
		int $expiredAt,
	): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE_NAME)
			->set('recipient_email', $qb->createNamedParameter($email))
			->set('created_at', $qb->createNamedParameter($createdAt, IQueryBuilder::PARAM_INT))
			->set('expired_at', $qb->createNamedParameter($expiredAt, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNull('recipient_email'))
			->andWhere($qb->expr()->eq('accepted', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
		return $qb->executeStatement() === 1;
	}

	/**
	 * Best-effort revert of a previous claim made by claimInviteForEmail().
	 * Only undoes the change when the row still has the email we set and is
	 * still unaccepted, so a revert can never overwrite a successful accept
	 * race or another user's later claim.
	 *
	 * Returns true when the revert took effect.
	 */
	public function revertInviteEmail(
		string $token,
		string $userId,
		string $email,
		int $previousCreatedAt,
		?int $previousExpiredAt,
	): bool {
		$qb = $this->db->getQueryBuilder();
		$expiredParam = $previousExpiredAt === null
			? $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL)
			: $qb->createNamedParameter($previousExpiredAt, IQueryBuilder::PARAM_INT);
		$qb->update(self::TABLE_NAME)
			->set('recipient_email', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('created_at', $qb->createNamedParameter($previousCreatedAt, IQueryBuilder::PARAM_INT))
			->set('expired_at', $expiredParam)
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('recipient_email', $qb->createNamedParameter($email)))
			->andWhere($qb->expr()->eq('accepted', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)));
		return $qb->executeStatement() === 1;
	}

}
