<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Tests;

use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Db\FederatedInvite;
use OCA\Contacts\Db\FederatedInviteMapper;
use OCA\Contacts\Service\FederatedInvitesService;
use OCA\Contacts\Service\SocialApiService;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class FederatedInvitesServiceTest extends TestCase {

	private AddressHandler&MockObject $addressHandler;
	private IAppConfig&MockObject $appConfig;
	private ITimeFactory&MockObject $timeFactory;
	private IURLGenerator&MockObject $urlGenerator;
	private IUserManager&MockObject $userManager;
	private IUserSession&MockObject $userSession;
	private FederatedInviteMapper&MockObject $federatedInviteMapper;
	private LoggerInterface&MockObject $logger;
	private SocialApiService&MockObject $socialApiService;

	private FederatedInvitesService $federatedInvitesService;

	protected function setUp(): void {
		parent::setUp();

		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->federatedInviteMapper = $this->createMock(FederatedInviteMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->socialApiService = $this->createMock(SocialApiService::class);

		$this->federatedInvitesService = new FederatedInvitesService(
			$this->addressHandler,
			$this->appConfig,
			$this->timeFactory,
			$this->urlGenerator,
			$this->userManager,
			$this->userSession,
			$this->federatedInviteMapper,
			$this->logger,
			$this->socialApiService,
		);
	}

	public function testInviteAccepted(): void {
		$token = 'token';
		$userId = 'userId';
		$invite = new FederatedInvite();
		$invite->setCreatedAt(1);
		$invite->setUserId($userId);
		$invite->setToken($token);

		$this->federatedInviteMapper->expects(self::once())
			->method('findByToken')
			->with($token)
			->willReturn($invite);

		$this->federatedInviteMapper->expects(self::once())
			->method('update')
			->willReturnArgument(0);

		$recipientProvider = 'http://127.0.0.1';
		$recipientId = 'remote';
		$recipientEmail = 'remote@example.org';
		$recipientName = 'Remote Remoteson';

		$this->addressHandler->expects(self::once())
			->method('removeProtocolFromUrl')
			->with($recipientProvider)
			->willReturn('127.0.0.1');
		$this->socialApiService->expects(self::once())
			->method('createContact')
			->with('remote@127.0.0.1', $recipientEmail, $recipientName, $userId)
			->willReturn(['UID' => 'contact-uid']);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn($userId);
		$user->method('getEMailAddress')
			->willReturn('email');
		$user->method('getDisplayName')
			->willReturn('displayName');

		$this->userManager->expects(self::once())
			->method('get')
			->with($userId)
			->willReturn($user);

		$response = ['userID' => $userId, 'email' => 'email', 'name' => 'displayName'];
		$json = new JSONResponse($response, Http::STATUS_OK);

		$this->assertEquals($json, $this->federatedInvitesService->inviteAccepted($recipientProvider, $token, $recipientId, $recipientEmail, $recipientName));
	}

	public function testInviteAcceptedFailsWhenContactCreationFails(): void {
		$token = 'token';
		$userId = 'userId';
		$invite = new FederatedInvite();
		$invite->setCreatedAt(1);
		$invite->setUserId($userId);
		$invite->setToken($token);

		$this->federatedInviteMapper->expects(self::once())
			->method('findByToken')
			->with($token)
			->willReturn($invite);
		$this->federatedInviteMapper->expects(self::never())
			->method('update');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($userId);
		$user->method('getEMailAddress')->willReturn('email');
		$user->method('getDisplayName')->willReturn('displayName');
		$this->userManager->expects(self::once())
			->method('get')
			->with($userId)
			->willReturn($user);

		$this->addressHandler->expects(self::once())
			->method('removeProtocolFromUrl')
			->with('http://127.0.0.1')
			->willReturn('127.0.0.1');
		$this->socialApiService->expects(self::once())
			->method('createContact')
			->with('remote@127.0.0.1', 'remote@example.org', 'Remote Remoteson', $userId)
			->willReturn(null);

		$response = $this->federatedInvitesService->inviteAccepted(
			'http://127.0.0.1',
			$token,
			'remote',
			'remote@example.org',
			'Remote Remoteson',
		);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
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
