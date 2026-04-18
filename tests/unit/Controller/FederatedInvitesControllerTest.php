<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Controller;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OC\App\CompareVersion;
use OCA\Contacts\Db\FederatedInvite;
use OCA\Contacts\Db\FederatedInviteMapper;
use OCA\Contacts\Service\FederatedInvitesService;
use OCA\Contacts\Service\GroupSharingService;
use OCA\Contacts\Service\SocialApiService;
use OCA\Contacts\WayfProvider;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IInitialState;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Contacts\IManager;
use OCP\Defaults;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\OCM\IOCMDiscoveryService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class FederatedInvitesControllerTest extends TestCase {
	private const UID = 'alice';
	private const OTHER_UID = 'bob';
	private const TOKEN = 'token-1234';

	private FederatedInvitesController $controller;

	private IRequest|MockObject $request;
	private AddressHandler|MockObject $addressHandler;
	private CardDavBackend|MockObject $cardDavBackend;
	private Defaults|MockObject $defaults;
	private FederatedInviteMapper|MockObject $mapper;
	private FederatedInvitesService|MockObject $invitesService;
	private IAppManager|MockObject $appManager;
	private IClientService|MockObject $httpClient;
	private IConfig|MockObject $config;
	private IInitialState|MockObject $initialState;
	private IFactory|MockObject $languageFactory;
	private IManager|MockObject $contactsManager;
	private IMailer|MockObject $mailer;
	private IOCMDiscoveryService|MockObject $discovery;
	private IUserSession|MockObject $userSession;
	private WayfProvider|MockObject $wayfProvider;
	private SocialApiService|MockObject $socialApi;
	private ITimeFactory|MockObject $timeFactory;
	private CompareVersion|MockObject $compareVersion;
	private GroupSharingService|MockObject $groupSharingService;
	private IL10N|MockObject $l10n;
	private IURLGenerator|MockObject $urlGenerator;
	private IUserManager|MockObject $userManager;
	private LoggerInterface|MockObject $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->cardDavBackend = $this->createMock(CardDavBackend::class);
		$this->defaults = $this->createMock(Defaults::class);
		$this->mapper = $this->createMock(FederatedInviteMapper::class);
		$this->invitesService = $this->createMock(FederatedInvitesService::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->httpClient = $this->createMock(IClientService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->languageFactory = $this->createMock(IFactory::class);
		$this->contactsManager = $this->createMock(IManager::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->discovery = $this->createMock(IOCMDiscoveryService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->wayfProvider = $this->createMock(WayfProvider::class);
		$this->socialApi = $this->createMock(SocialApiService::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->compareVersion = $this->createMock(CompareVersion::class);
		$this->groupSharingService = $this->createMock(GroupSharingService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::UID);
		$user->method('getDisplayName')->willReturn('Alice');
		$user->method('getEMailAddress')->willReturn('alice@example.org');
		$this->userSession->method('getUser')->willReturn($user);

		$this->controller = new FederatedInvitesController(
			$this->request,
			$this->addressHandler,
			$this->cardDavBackend,
			$this->defaults,
			$this->mapper,
			$this->invitesService,
			$this->appManager,
			$this->httpClient,
			$this->config,
			$this->initialState,
			$this->languageFactory,
			$this->contactsManager,
			$this->mailer,
			$this->discovery,
			$this->userSession,
			$this->wayfProvider,
			$this->socialApi,
			$this->timeFactory,
			$this->compareVersion,
			$this->groupSharingService,
			$this->l10n,
			$this->urlGenerator,
			$this->userManager,
			$this->logger,
		);
	}

	private function makeInvite(?string $email = null, bool $accepted = false, string $uid = self::UID): FederatedInvite {
		$invite = new FederatedInvite();
		$invite->setUserId($uid);
		$invite->setToken(self::TOKEN);
		$invite->setRecipientEmail($email);
		$invite->setAccepted($accepted);
		$invite->setCreatedAt(1_700_000_000);
		$invite->setExpiredAt(1_700_000_000 + 2_592_000);
		return $invite;
	}

	public function testAttachEmailAndSendUpdatesAndSends(): void {
		$invite = $this->makeInvite(null);

		$this->mapper->expects($this->once())
			->method('findInviteByTokenAndUid')
			->with(self::TOKEN, self::UID)
			->willReturn($invite);
		$this->mapper->method('findOpenInvitesByRecipientEmail')->willReturn([]);
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->mailer->method('createMessage')->willReturn($this->createMock(\OCP\Mail\IMessage::class));
		$this->mailer->method('send')->willReturn([]);
		$this->wayfProvider->method('getWayfEndpoint')->willReturn('https://example.org/wayf');
		$this->invitesService->method('getProviderFQDN')->willReturn('example.org');
		$this->invitesService->method('getInviteExpirationDate')->willReturnCallback(static fn (int $t): int => $t + 2_592_000);
		$now = $this->createMock(\DateTimeImmutable::class);
		$now->method('getTimestamp')->willReturn(1_800_000_000);
		$this->timeFactory->method('now')->willReturn($now);

		$this->mapper->expects($this->once())
			->method('claimInviteForEmail')
			->with(self::TOKEN, self::UID, 'recipient@example.org', 1_800_000_000, 1_800_000_000 + 2_592_000)
			->willReturn(true);
		$this->mapper->expects($this->never())->method('revertInviteEmail');

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org', 'hello');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$body = $response->getData();
		$this->assertSame('recipient@example.org', $body['recipientEmail']);
		$this->assertSame(self::TOKEN, $body['token']);
		$this->assertSame(1_800_000_000, $body['createdAt']);
	}

	public function testAttachEmailAndSendRejectsWhenClaimLosesRace(): void {
		$invite = $this->makeInvite(null);

		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);
		$this->mapper->method('findOpenInvitesByRecipientEmail')->willReturn([]);
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->invitesService->method('getInviteExpirationDate')->willReturnCallback(static fn (int $t): int => $t + 2_592_000);
		$now = $this->createMock(\DateTimeImmutable::class);
		$now->method('getTimestamp')->willReturn(1_800_000_000);
		$this->timeFactory->method('now')->willReturn($now);

		$this->mapper->expects($this->once())
			->method('claimInviteForEmail')
			->willReturn(false);
		$this->mailer->expects($this->never())->method('send');
		$this->mapper->expects($this->never())->method('revertInviteEmail');

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertSame('ocm_invite_claim_failed', $response->getData()['code']);
		$this->assertNull($invite->getRecipientEmail());
	}

	public function testAttachEmailAndSendRejectsWhenInviteBelongsToAnotherUser(): void {
		$this->mapper->expects($this->once())
			->method('findInviteByTokenAndUid')
			->with(self::TOKEN, self::UID)
			->willThrowException(new DoesNotExistException('not found'));

		$this->mailer->expects($this->never())->method('send');
		$this->mapper->expects($this->never())->method('claimInviteForEmail');
		$this->mapper->expects($this->never())->method('revertInviteEmail');

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testAttachEmailAndSendRejectsWhenInviteAlreadyAccepted(): void {
		$invite = $this->makeInvite(null, accepted: true);
		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);

		$this->mailer->expects($this->never())->method('send');
		$this->mapper->expects($this->never())->method('claimInviteForEmail');

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertSame('ocm_invite_already_accepted', $response->getData()['code']);
	}

	public function testAttachEmailAndSendRejectsWhenInviteAlreadyHasEmail(): void {
		$invite = $this->makeInvite('existing@example.org');
		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);

		$this->mailer->expects($this->never())->method('send');
		$this->mapper->expects($this->never())->method('claimInviteForEmail');

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertSame('ocm_invite_already_has_email', $response->getData()['code']);
	}

	public function testAttachEmailAndSendRejectsInvalidEmail(): void {
		$invite = $this->makeInvite(null);
		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);
		$this->mailer->method('validateMailAddress')->willReturn(false);

		$this->mailer->expects($this->never())->method('send');
		$this->mapper->expects($this->never())->method('claimInviteForEmail');

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'not-an-email');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertNull($invite->getRecipientEmail());
	}

	public function testAttachEmailAndSendRejectsCollidingOpenInvite(): void {
		$invite = $this->makeInvite(null);
		$other = $this->makeInvite('recipient@example.org');
		$other->setToken('other-token');

		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);
		$this->mapper->method('findOpenInvitesByRecipientEmail')->willReturn([$other]);
		$this->mailer->method('validateMailAddress')->willReturn(true);

		$this->mailer->expects($this->never())->method('send');
		$this->mapper->expects($this->never())->method('claimInviteForEmail');

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertSame('ocm_invite_duplicate_recipient_email', $response->getData()['code']);
	}

	public function testAttachEmailAndSendRevertsOnMailerFailure(): void {
		$invite = $this->makeInvite(null);
		$originalCreatedAt = $invite->getCreatedAt();
		$originalExpiredAt = $invite->getExpiredAt();

		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);
		$this->mapper->method('findOpenInvitesByRecipientEmail')->willReturn([]);
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->mailer->method('createMessage')->willReturn($this->createMock(\OCP\Mail\IMessage::class));
		$this->mailer->method('send')->willReturn(['recipient@example.org']);
		$this->wayfProvider->method('getWayfEndpoint')->willReturn('https://example.org/wayf');
		$this->invitesService->method('getProviderFQDN')->willReturn('example.org');
		$this->invitesService->method('getInviteExpirationDate')->willReturnCallback(static fn (int $t): int => $t + 2_592_000);
		$now = $this->createMock(\DateTimeImmutable::class);
		$now->method('getTimestamp')->willReturn(1_800_000_000);
		$this->timeFactory->method('now')->willReturn($now);

		$this->mapper->expects($this->once())
			->method('claimInviteForEmail')
			->willReturn(true);
		$this->mapper->expects($this->once())
			->method('revertInviteEmail')
			->with(self::TOKEN, self::UID, 'recipient@example.org', $originalCreatedAt, $originalExpiredAt)
			->willReturn(true);

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org');

		$this->assertNotSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testAttachEmailAndSendEscapesUserContentInHtmlBody(): void {
		$invite = $this->makeInvite(null);
		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);
		$this->mapper->method('findOpenInvitesByRecipientEmail')->willReturn([]);
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->wayfProvider->method('getWayfEndpoint')->willReturn('https://example.org/wayf');
		$this->invitesService->method('getProviderFQDN')->willReturn('example.org');
		$this->invitesService->method('getInviteExpirationDate')->willReturnCallback(static fn (int $t): int => $t + 2_592_000);
		$now = $this->createMock(\DateTimeImmutable::class);
		$now->method('getTimestamp')->willReturn(1_800_000_000);
		$this->timeFactory->method('now')->willReturn($now);
		$this->mapper->method('claimInviteForEmail')->willReturn(true);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn(self::UID);
		$user->method('getDisplayName')->willReturn('Eve <script>');
		$user->method('getEMailAddress')->willReturn(null);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);
		$reflection = new \ReflectionProperty(FederatedInvitesController::class, 'userSession');
		$reflection->setAccessible(true);
		$reflection->setValue($this->controller, $session);

		$capturedHtml = null;
		$capturedPlain = null;
		$message = $this->createMock(\OCP\Mail\IMessage::class);
		$message->method('setHtmlBody')->willReturnCallback(function ($body) use (&$capturedHtml, $message) {
			$capturedHtml = $body;
			return $message;
		});
		$message->method('setPlainBody')->willReturnCallback(function ($body) use (&$capturedPlain, $message) {
			$capturedPlain = $body;
			return $message;
		});
		$this->mailer->method('createMessage')->willReturn($message);
		$this->mailer->method('send')->willReturn([]);

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org', '<b>note</b>');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertNotNull($capturedHtml);
		$this->assertStringNotContainsString('<script>', (string)$capturedHtml);
		$this->assertStringContainsString('Eve &lt;script&gt;', (string)$capturedHtml);
		$this->assertStringNotContainsString('<b>note</b>', (string)$capturedHtml);
		$this->assertStringContainsString('&lt;b&gt;note&lt;/b&gt;', (string)$capturedHtml);
		$this->assertNotNull($capturedPlain);
		$this->assertStringContainsString('<b>note</b>', (string)$capturedPlain);
	}

	public function testAttachEmailAndSendRevertsWhenMailerThrows(): void {
		$invite = $this->makeInvite(null);
		$originalCreatedAt = $invite->getCreatedAt();
		$originalExpiredAt = $invite->getExpiredAt();

		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);
		$this->mapper->method('findOpenInvitesByRecipientEmail')->willReturn([]);
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->mailer->method('createMessage')->willReturn($this->createMock(\OCP\Mail\IMessage::class));
		$this->mailer->method('send')->willThrowException(new \RuntimeException('SMTP refused'));
		$this->wayfProvider->method('getWayfEndpoint')->willReturn('https://example.org/wayf');
		$this->invitesService->method('getProviderFQDN')->willReturn('example.org');
		$this->invitesService->method('getInviteExpirationDate')->willReturnCallback(static fn (int $t): int => $t + 2_592_000);
		$now = $this->createMock(\DateTimeImmutable::class);
		$now->method('getTimestamp')->willReturn(1_800_000_000);
		$this->timeFactory->method('now')->willReturn($now);

		$this->mapper->expects($this->once())
			->method('claimInviteForEmail')
			->willReturn(true);
		$this->mapper->expects($this->once())
			->method('revertInviteEmail')
			->with(self::TOKEN, self::UID, 'recipient@example.org', $originalCreatedAt, $originalExpiredAt)
			->willReturn(true);

		$response = $this->controller->attachEmailAndSend(self::TOKEN, 'recipient@example.org');

		$this->assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
	}

	public function testResendInviteRejectsWhenInviteBelongsToAnotherUser(): void {
		$this->mapper->expects($this->once())
			->method('findInviteByTokenAndUid')
			->with(self::TOKEN, self::UID)
			->willThrowException(new DoesNotExistException('not found'));

		$this->mailer->expects($this->never())->method('send');

		$response = $this->controller->resendInvite(self::TOKEN);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testResendInvitePreservesLifetimeOnSuccessfulSend(): void {
		$invite = $this->makeInvite('recipient@example.org');
		$createdAt = $invite->getCreatedAt();
		$expiredAt = $invite->getExpiredAt();

		$this->mapper->method('findInviteByTokenAndUid')->willReturn($invite);
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->mailer->method('createMessage')->willReturn($this->createMock(\OCP\Mail\IMessage::class));
		$this->mailer->method('send')->willReturn([]);
		$this->wayfProvider->method('getWayfEndpoint')->willReturn('https://example.org/wayf');
		$this->invitesService->method('getProviderFQDN')->willReturn('example.org');

		$this->mapper->expects($this->never())->method('update');

		$response = $this->controller->resendInvite(self::TOKEN);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($createdAt, $invite->getCreatedAt());
		$this->assertSame($expiredAt, $invite->getExpiredAt());
	}

	public function testDiscoverRejectsBlockedTargets(): void {
		$response = $this->controller->discover('localhost');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('invalid base', $response->getData()['error']);
	}

	public function testDiscoverUsesFallbackDialogAndOmitsRawPayload(): void {
		$provider = $this->createMock(\OCP\OCM\ICapabilityAwareOCMProvider::class);
		$provider->method('getInviteAcceptDialog')->willReturn('');

		$this->discovery->expects($this->once())
			->method('discover')
			->with('https://remote.example')
			->willReturn($provider);
		$this->wayfProvider->method('getInviteAcceptDialogPath')->willReturn('/index.php/apps/contacts/ocm/invite-accept-dialog');

		$response = $this->controller->discover('remote.example');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$body = $response->getData();
		$this->assertSame('https://remote.example', $body['base']);
		$this->assertSame('remote.example', $body['providerDomain']);
		$this->assertSame('https://remote.example/index.php/apps/contacts/ocm/invite-accept-dialog', $body['inviteAcceptDialogAbsolute']);
		$this->assertArrayNotHasKey('raw', $body);
	}

	public function testAcceptInviteRejectsInvalidProviderTarget(): void {
		$response = $this->controller->acceptInvite(self::TOKEN, '127.0.0.1');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testSetOcmInviteBoolSettingReturnsOkOnAllowedKey(): void {
		$this->invitesService->expects($this->once())
			->method('setOcmInviteBoolSetting')
			->with('ocm_invites_optional_mail', true)
			->willReturn(true);

		$response = $this->controller->setOcmInviteBoolSetting('ocm_invites_optional_mail', true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testSetOcmInviteBoolSettingReturnsForbiddenOnUnknownKey(): void {
		$this->invitesService->expects($this->once())
			->method('setOcmInviteBoolSetting')
			->with('not_a_real_key', true)
			->willReturn(false);

		$response = $this->controller->setOcmInviteBoolSetting('not_a_real_key', true);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}
}
