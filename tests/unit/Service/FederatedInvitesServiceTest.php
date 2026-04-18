<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Tests;

use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Service\FederatedInvitesService;
use OCA\Contacts\Service\SocialApiService;
use OCA\DAV\CardDAV\CardDavBackend;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class FederatedInvitesServiceTest extends TestCase {

	private IAppConfig&MockObject $appConfig;
	private IURLGenerator&MockObject $urlGenerator;
	private IUserSession&MockObject $userSession;
	private LoggerInterface&MockObject $logger;
	private SocialApiService&MockObject $socialApiService;

	private FederatedInvitesService $federatedInvitesService;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->socialApiService = $this->createMock(SocialApiService::class);

		$this->federatedInvitesService = new FederatedInvitesService(
			$this->appConfig,
			$this->urlGenerator,
			$this->userSession,
			$this->logger,
			$this->socialApiService,
		);
	}

	public function testSetOcmInviteBoolSettingWritesAllowedKey(): void {
		$this->appConfig->expects(self::once())
			->method('setValueBool')
			->with('contacts', 'ocm_invites_optional_mail', true);

		$result = $this->federatedInvitesService->setOcmInviteBoolSetting('ocm_invites_optional_mail', true);

		$this->assertTrue($result);
	}

	public function testSetOcmInviteBoolSettingRejectsUnknownKey(): void {
		$this->appConfig->expects(self::never())
			->method('setValueBool');

		$result = $this->federatedInvitesService->setOcmInviteBoolSetting('ocm_invites_arbitrary_unknown_key', true);

		$this->assertFalse($result);
	}

	public function testIsSsrfGuardDisabledReadsConfigToggle(): void {
		$this->appConfig->expects(self::once())
			->method('getValueBool')
			->with(Application::APP_ID, 'ocm_invites_disable_ssrf_guard')
			->willReturn(true);

		$this->assertTrue($this->federatedInvitesService->isSsrfGuardDisabled());
	}

	public function testCreateNewContactUsesReturnedAddressBookUriInContactRef(): void {
		$this->socialApiService->expects(self::once())
			->method('createContact')
			->with('remote@example.org', 'remote@example.org', 'Remote User', 'sender')
			->willReturn([
				'UID' => 'new-contact-uid',
				'ADDRESSBOOK_URI' => 'work',
			]);

		$result = $this->federatedInvitesService->createNewContact(
			'remote@example.org',
			'remote@example.org',
			'Remote User',
			'sender',
		);

		$this->assertSame('new-contact-uid~work', $result);
	}

	public function testCreateNewContactFallsBackToPersonalAddressBookUri(): void {
		$this->socialApiService->expects(self::once())
			->method('createContact')
			->with('remote@example.org', 'remote@example.org', 'Remote User', 'sender')
			->willReturn([
				'UID' => 'new-contact-uid',
			]);

		$result = $this->federatedInvitesService->createNewContact(
			'remote@example.org',
			'remote@example.org',
			'Remote User',
			'sender',
		);

		$this->assertSame(
			'new-contact-uid~' . CardDavBackend::PERSONAL_ADDRESSBOOK_URI,
			$result,
		);
	}

	public function testSetOcmInviteBoolSettingCoversEachAllowedKey(): void {
		$keys = FederatedInvitesService::OCM_INVITES_BOOL_KEYS;
		$this->assertNotEmpty(
			$keys,
			'OCM_INVITES_BOOL_KEYS must not be empty; otherwise the allowlist coverage is vacuous.',
		);

		$expectedCalls = [];
		foreach ($keys as $key) {
			$expectedCalls[] = [Application::APP_ID, $key, false];
		}

		$invocation = $this->exactly(count($keys));
		$this->appConfig->expects($invocation)
			->method('setValueBool')
			->willReturnCallback(function (string $appId, string $configKey, bool $value) use (&$expectedCalls, $invocation): bool {
				$index = $invocation->numberOfInvocations() - 1;
				$this->assertSame($expectedCalls[$index][0], $appId);
				$this->assertSame($expectedCalls[$index][1], $configKey);
				$this->assertSame($expectedCalls[$index][2], $value);
				return true;
			});

		foreach ($keys as $key) {
			$this->assertTrue(
				$this->federatedInvitesService->setOcmInviteBoolSetting($key, false),
				"Allowed key '$key' should be writable",
			);
		}
	}
}
