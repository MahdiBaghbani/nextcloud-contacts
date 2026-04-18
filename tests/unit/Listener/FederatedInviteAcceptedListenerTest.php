<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Tests\Unit\Listener;

use Exception;
use OCA\CloudFederationAPI\Db\FederatedInvite;
use OCA\CloudFederationAPI\Events\FederatedInviteAcceptedEvent;
use OCA\Contacts\Listener\FederatedInviteAcceptedListener;
use OCA\Contacts\Service\SocialApiService;
use OCA\FederatedFileSharing\AddressHandler;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class FederatedInviteAcceptedListenerTest extends TestCase {

	private AddressHandler&MockObject $addressHandler;
	private SocialApiService&MockObject $socialApiService;
	private LoggerInterface&MockObject $logger;

	private FederatedInviteAcceptedListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->socialApiService = $this->createMock(SocialApiService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new FederatedInviteAcceptedListener(
			$this->addressHandler,
			$this->socialApiService,
			$this->logger,
		);
	}

	private function buildInvitation(
		string $userId = 'einstein',
		string $recipientUserId = 'michiel',
		string $recipientProvider = 'https://nextcloud2.docker',
		string $recipientEmail = 'michiel@example.test',
		string $recipientName = 'Michiel',
		string $token = 'aaaa-bbbb-cccc-d1d2',
	): FederatedInvite {
		// Use a real entity instead of a mock: Entity's getX/setX are magic
		// methods (docblock-only on FederatedInvite), so PHPUnit cannot
		// configure them on a createMock(). Calling the setters relies on
		// Entity::__call which is the same path the production code uses.
		$invitation = new FederatedInvite();
		$invitation->setUserId($userId);
		$invitation->setRecipientUserId($recipientUserId);
		$invitation->setRecipientProvider($recipientProvider);
		$invitation->setRecipientEmail($recipientEmail);
		$invitation->setRecipientName($recipientName);
		$invitation->setToken($token);
		return $invitation;
	}

	public function testHandleHappyPathCreatesContact(): void {
		$invitation = $this->buildInvitation();
		$event = new FederatedInviteAcceptedEvent($invitation);

		$this->addressHandler->expects(self::once())
			->method('removeProtocolFromUrl')
			->with('https://nextcloud2.docker')
			->willReturn('nextcloud2.docker');

		$this->socialApiService->expects(self::once())
			->method('createFederatedContact')
			->with('michiel@nextcloud2.docker', 'michiel@example.test', 'Michiel', 'einstein')
			->willReturn(['UID' => 'created-uid']);

		// Two info logs: entry log + created log. No error log.
		$this->logger->expects(self::exactly(2))->method('info');
		$this->logger->expects(self::never())->method('error');

		$this->listener->handle($event);
	}

	public function testHandleAlreadyExistsReturnsNull(): void {
		$invitation = $this->buildInvitation();
		$event = new FederatedInviteAcceptedEvent($invitation);

		$this->addressHandler->method('removeProtocolFromUrl')
			->willReturn('nextcloud2.docker');

		$this->socialApiService->expects(self::once())
			->method('createFederatedContact')
			->willReturn(null);

		// Only the entry log fires; no "Created new contact" log; no error log.
		$this->logger->expects(self::once())->method('info');
		$this->logger->expects(self::never())->method('error');

		$this->listener->handle($event);
	}

	public function testHandleSwallowsServiceException(): void {
		$invitation = $this->buildInvitation();
		$event = new FederatedInviteAcceptedEvent($invitation);

		$this->addressHandler->method('removeProtocolFromUrl')
			->willReturn('nextcloud2.docker');

		$boom = new Exception('boom');
		$this->socialApiService->expects(self::once())
			->method('createFederatedContact')
			->willThrowException($boom);

		// Entry log fires, then error log; no created log.
		$this->logger->expects(self::once())->method('info');
		$this->logger->expects(self::once())
			->method('error')
			->with(
				self::stringContains('unexpected error'),
				self::callback(static function (array $context) use ($boom): bool {
					return ($context['exception'] ?? null) === $boom
						&& ($context['userId'] ?? null) === 'einstein'
						&& ($context['cloudId'] ?? null) === 'michiel@nextcloud2.docker';
				}),
			);

		$this->listener->handle($event);
	}

	public function testHandleIgnoresUnrelatedEvent(): void {
		$this->addressHandler->expects(self::never())->method('removeProtocolFromUrl');
		$this->socialApiService->expects(self::never())->method('createFederatedContact');
		$this->logger->expects(self::never())->method('info');
		$this->logger->expects(self::never())->method('error');

		$this->listener->handle(new Event());
	}

	public function testHandleShortTokenLogsMaskedSuffix(): void {
		$invitation = $this->buildInvitation(token: 'abc');
		$event = new FederatedInviteAcceptedEvent($invitation);

		$this->addressHandler->method('removeProtocolFromUrl')
			->willReturn('nextcloud2.docker');
		$this->socialApiService->method('createFederatedContact')
			->willReturn(['UID' => 'created-uid']);

		$this->logger->expects(self::exactly(2))
			->method('info')
			->willReturnCallback(function (string $message, array $context = []): void {
				if (str_contains($message, 'tokenSuffix')) {
					self::assertSame('****', $context['tokenSuffix'] ?? null);
				}
			});

		$this->listener->handle($event);
	}
}
