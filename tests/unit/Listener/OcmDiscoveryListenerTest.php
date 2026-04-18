<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Tests\Unit\Listener;

use OC\Core\AppInfo\ConfigLexicon as CoreConfigLexicon;
use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\ConfigLexicon as ContactsConfigLexicon;
use OCA\Contacts\Listener\OcmDiscoveryListener;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\OCM\Events\ResourceTypeRegisterEvent;
use OCP\OCM\IOCMProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class OcmDiscoveryListenerTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IURLGenerator&MockObject $urlGenerator;
	private LoggerInterface&MockObject $logger;

	private OcmDiscoveryListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new OcmDiscoveryListener(
			$this->appConfig,
			$this->urlGenerator,
			$this->logger,
		);
	}

	public function testHandleReturnsEarlyWhenInvitesDisabled(): void {
		$provider = $this->createMock(IOCMProvider::class);

		$this->appConfig->expects($this->once())
			->method('getValueBool')
			->with(Application::APP_ID, ContactsConfigLexicon::OCM_INVITES_ENABLED)
			->willReturn(false);
		$this->appConfig->expects($this->never())->method('getValueString');
		$this->urlGenerator->expects($this->never())->method('linkToRouteAbsolute');
		$this->logger->expects($this->never())->method('warning');

		$this->listener->handle($this->newOcmDiscoveryEvent($provider));
	}

	public function testHandleWarnsWhenDialogRouteIsMissing(): void {
		$provider = $this->createMock(IOCMProvider::class);

		$this->appConfig->expects($this->once())
			->method('getValueBool')
			->with(Application::APP_ID, ContactsConfigLexicon::OCM_INVITES_ENABLED)
			->willReturn(true);
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with('core', CoreConfigLexicon::OCM_INVITE_ACCEPT_DIALOG)
			->willReturn('   ');
		$this->urlGenerator->expects($this->never())->method('linkToRouteAbsolute');

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				$this->stringContains('invite accept dialog route is empty'),
				$this->callback(static function (array $context): bool {
					return ($context['app'] ?? null) === Application::APP_ID
						&& ($context['routeConfigKey'] ?? null) === CoreConfigLexicon::OCM_INVITE_ACCEPT_DIALOG;
				}),
			);

		$this->listener->handle($this->newOcmDiscoveryEvent($provider));
	}

	public function testHandleWarnsWhenRouteResolutionFails(): void {
		$provider = $this->createMock(IOCMProvider::class);

		$exception = new \RuntimeException('route boom');
		$this->appConfig->expects($this->once())
			->method('getValueBool')
			->with(Application::APP_ID, ContactsConfigLexicon::OCM_INVITES_ENABLED)
			->willReturn(true);
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with('core', CoreConfigLexicon::OCM_INVITE_ACCEPT_DIALOG)
			->willReturn('contacts.federatedInvites.inviteAcceptDialog');
		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('contacts.federatedInvites.inviteAcceptDialog')
			->willThrowException($exception);

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				$this->stringContains('route cannot be resolved'),
				$this->callback(static function (array $context) use ($exception): bool {
					return ($context['app'] ?? null) === Application::APP_ID
						&& ($context['route'] ?? null) === 'contacts.federatedInvites.inviteAcceptDialog'
						&& ($context['exception'] ?? null) === $exception;
				}),
			);

		$this->listener->handle($this->newOcmDiscoveryEvent($provider));
	}

	public function testHandleResolvesDialogRouteWhenConfigured(): void {
		$provider = $this->createMock(IOCMProvider::class);

		$this->appConfig->expects($this->once())
			->method('getValueBool')
			->with(Application::APP_ID, ContactsConfigLexicon::OCM_INVITES_ENABLED)
			->willReturn(true);
		$this->appConfig->expects($this->once())
			->method('getValueString')
			->with('core', CoreConfigLexicon::OCM_INVITE_ACCEPT_DIALOG)
			->willReturn('contacts.federatedInvites.inviteAcceptDialog');
		$this->urlGenerator->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('contacts.federatedInvites.inviteAcceptDialog')
			->willReturn('https://cloud.example/ocm/invite-dialog');
		$this->logger->expects($this->never())->method('warning');

		$this->listener->handle($this->newOcmDiscoveryEvent($provider));
	}

	private function newOcmDiscoveryEvent(IOCMProvider $provider): ResourceTypeRegisterEvent {
		return new ResourceTypeRegisterEvent($provider);
	}
}
