<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Tests;

use OCA\Contacts\Db\FederatedInvite;
use OCA\Contacts\Db\FederatedInviteMapper;
use OCA\Contacts\Service\FederatedInvitesService;
use OCA\Contacts\Service\SocialApiService;
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

		$recipientProvider = 'http://127.0.0.1';
		$recipientId = 'remote';
		$recipientEmail = 'remote@example.org';
		$recipientName = 'Remote Remoteson';
		$response = ['userID' => $userId, 'email' => 'email', 'name' => 'displayName'];
		$json = new JSONResponse($response, Http::STATUS_OK);

		$this->assertEquals($json, $this->federatedInvitesService->inviteAccepted($recipientProvider, $token, $recipientId, $recipientEmail, $recipientName));
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

	public function testSetOcmInviteBoolSettingCoversEachAllowedKey(): void {
		$this->appConfig->expects(self::exactly(count(FederatedInvitesService::OCM_INVITES_BOOL_KEYS)))
			->method('setValueBool');

		foreach (FederatedInvitesService::OCM_INVITES_BOOL_KEYS as $key) {
			$this->assertTrue(
				$this->federatedInvitesService->setOcmInviteBoolSetting($key, false),
				"Allowed key '$key' should be writable",
			);
		}
	}
}
