<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Service;

use Exception;
use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\ConfigLexicon;
use OCA\Contacts\Exception\ContactExistsException;
use OCA\DAV\CardDAV\CardDavBackend;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class FederatedInvitesService {

	// The default route of the invite accept dialog
	public const OCM_INVITE_ACCEPT_DIALOG_ROUTE = '/ocm/invite-accept-dialog';
	// The default expiration period of a new invite in seconds, ie. 30 days
	private const INVITE_EXPIRATION_PERIOD_SECONDS = 2592000;

	public function __construct(
		private IAppConfig $appConfig,
		private IURLGenerator $urlGenerator,
		private IUserSession $userSession,
		private LoggerInterface $logger,
		private SocialApiService $socialApiService,
	) {
	}

	public function isOcmInvitesEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_ENABLED);
	}

	public function isOptionalMailEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_OPTIONAL_MAIL);
	}

	public function isCcSenderEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_CC_SENDER);
	}

	public function isEncodedCopyButtonEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_ENCODED_COPY_BUTTON);
	}

	public function isSsrfGuardDisabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_DISABLE_SSRF_GUARD);
	}

	/**
	 * The set of admin-toggleable OCM bool keys. Used to gate writes from the
	 * admin settings page so callers cannot persist arbitrary keys.
	 */
	public const OCM_INVITES_BOOL_KEYS = [
		ConfigLexicon::OCM_INVITES_OPTIONAL_MAIL,
		ConfigLexicon::OCM_INVITES_CC_SENDER,
		ConfigLexicon::OCM_INVITES_ENCODED_COPY_BUTTON,
		ConfigLexicon::OCM_INVITES_DISABLE_SSRF_GUARD,
	];

	/**
	 * Persist an OCM admin bool toggle. Returns true when the key is allowed.
	 */
	public function setOcmInviteBoolSetting(string $key, bool $value): bool {
		if (!in_array($key, self::OCM_INVITES_BOOL_KEYS, true)) {
			return false;
		}
		$this->appConfig->setValueBool(Application::APP_ID, $key, $value);
		return true;
	}

	/**
	 * Returns all OCM invites config flags for frontend consumption
	 */
	public function getOcmInvitesConfig(): array {
		return [
			'optionalMail' => $this->isOptionalMailEnabled(),
			'ccSender' => $this->isCcSenderEnabled(),
			'encodedCopyButton' => $this->isEncodedCopyButtonEnabled(),
		];
	}

	/**
	 * Returns the provider's server FQDN.
	 * @return string the FQDN
	 */
	public function getProviderFQDN(): string {
		$serverUrl = $this->urlGenerator->getAbsoluteURL('/');
		$parts = parse_url($serverUrl);
		if (!is_array($parts) || !isset($parts['host']) || !is_string($parts['host'])) {
			return '';
		}
		return $parts['host'];
	}

	/**
	 * Returns the expiration date.
	 * @param int $creationDate
	 * @return int the expiration date
	 */
	public function getInviteExpirationDate(int $creationDate): int {
		return $creationDate + self::INVITE_EXPIRATION_PERIOD_SECONDS;
	}

	/**
	 * Creates a new contact and adds it to the address book of the user with the specified userId or,
	 * if null, the current logged-in user.
	 *
	 * @param string cloudId
	 * @param string email
	 * @param string name
	 * @param ?string userId id of the user for which to create the new contact.
	 * If null, this is the current logged-in user.
	 *
	 * @return string the ref of the new contact in the form
	 *                'contactURI~addressBookUri'
	 * @throws ContactExistsException
	 */
	public function createNewContact(string $cloudId, string $email, string $name, ?string $userId): ?string {
		$localUserId = $userId ? $userId : $this->userSession->getUser()->getUID();
		$newContact = $this->socialApiService->createContact(
			$cloudId,
			$email,
			$name,
			$localUserId,
		);
		if (!isset($newContact)) {
			$this->logger->error('Error creating contact for user {userId} with cloud id {cloudId}.', [
				'app' => Application::APP_ID,
				'userId' => $localUserId,
				'cloudId' => $cloudId,
			]);
			return null;
		}
		$this->logger->info('Created new contact with UID: ' . $newContact['UID'] . ' for user with UID: ' . $localUserId, ['app' => Application::APP_ID]);
		$addressBookUri = CardDavBackend::PERSONAL_ADDRESSBOOK_URI;
		if (isset($newContact['ADDRESSBOOK_URI']) && is_string($newContact['ADDRESSBOOK_URI']) && $newContact['ADDRESSBOOK_URI'] !== '') {
			$addressBookUri = $newContact['ADDRESSBOOK_URI'];
		}
		$contactRef = $newContact['UID'] . '~' . $addressBookUri;
		return $contactRef;
	}
}
