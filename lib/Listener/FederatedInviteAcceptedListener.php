<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Listener;

use Exception;
use OCA\CloudFederationAPI\Events\FederatedInviteAcceptedEvent;
use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Service\SocialApiService;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Listens to the federated invite accepted event.
 * Catching the event should lead to the creation of the new remote contact
 * from the invite, in the inviter's address book.
 *
 * @template-implements IEventListener<FederatedInviteAcceptedEvent>
 */
class FederatedInviteAcceptedListener implements IEventListener {

	public function __construct(
		private AddressHandler $addressHandler,
		private SocialApiService $socialApiService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Handles the FederatedInviteAcceptedEvent dispatched by the server when an
	 * invite has been accepted. The accepted invitation is enclosed in the event.
	 * Creates and saves a new contact in the address book of the sender of the
	 * invitation. There is no user session at this point.
	 */
	public function handle(Event $event): void {
		if (!($event instanceof FederatedInviteAcceptedEvent)) {
			return;
		}

		$invitation = $event->getInvitation();
		$userId = $invitation->getUserId();
		$cloudId = $invitation->getRecipientUserId() . '@' . $this->addressHandler->removeProtocolFromUrl($invitation->getRecipientProvider());

		$token = (string)$invitation->getToken();
		$tokenSuffix = strlen($token) >= 4 ? substr($token, -4) : '****';
		$this->logger->info('Received invite-accepted event for user {userId} tokenSuffix={tokenSuffix}', [
			'app' => Application::APP_ID,
			'userId' => $userId,
			'tokenSuffix' => $tokenSuffix,
		]);

		try {
			$newContact = $this->socialApiService->createFederatedContact(
				$cloudId,
				$invitation->getRecipientEmail(),
				$invitation->getRecipientName(),
				$userId,
			);
			if (isset($newContact)) {
				$this->logger->info('Created new contact with UID: ' . $newContact['UID'] . " for user with UID: $userId", ['app' => Application::APP_ID]);
			}
		} catch (Exception $e) {
			$this->logger->error('An unexpected error occurred creating a new contact.', [
				'app' => Application::APP_ID,
				'exception' => $e,
				'userId' => $userId,
				'cloudId' => $cloudId,
			]);
		}
	}
}
