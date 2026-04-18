<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Contacts\Listener;

use OC\Core\AppInfo\ConfigLexicon;
use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\ConfigLexicon as ContactsConfigLexicon;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\OCM\Events\LocalOCMDiscoveryEvent;
use OCP\OCM\Events\ResourceTypeRegisterEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/** @template-implements IEventListener<Event> */
class OcmDiscoveryListener implements IEventListener {
	public function __construct(
		private IAppConfig $appConfig,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * This handler validates invite accept dialog configuration
	 * for OCM discovery events.
	 *
	 * @param Event $event an event of type LocalOCMDiscoveryEvent or ResourceTypeRegisterEvent
	 * @return void
	 */
	public function handle(Event $event): void {
		if (!$this->isOcmDiscoveryEvent($event)) {
			return;
		}

		if (!$this->appConfig->getValueBool(Application::APP_ID, ContactsConfigLexicon::OCM_INVITES_ENABLED)) {
			return;
		}

		$inviteAcceptDialog = trim($this->appConfig->getValueString('core', ConfigLexicon::OCM_INVITE_ACCEPT_DIALOG));
		if ($inviteAcceptDialog === '') {
			$this->logger->warning('OCM invites are enabled but invite accept dialog route is empty', [
				'app' => Application::APP_ID,
				'routeConfigKey' => ConfigLexicon::OCM_INVITE_ACCEPT_DIALOG,
			]);
			return;
		}

		try {
			$this->urlGenerator->linkToRouteAbsolute($inviteAcceptDialog);
		} catch (Throwable $e) {
			$this->logger->warning('OCM invites are enabled but invite accept dialog route cannot be resolved', [
				'app' => Application::APP_ID,
				'route' => $inviteAcceptDialog,
				'exception' => $e,
			]);
			return;
		}
	}

	private function isOcmDiscoveryEvent(Event $event): bool {
		return $event instanceof ResourceTypeRegisterEvent
			|| $event instanceof LocalOCMDiscoveryEvent;
	}
}
