<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Command;

use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\Service\FederatedInvitesService;
use OCP\IAppConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read or write the per-deployment OCM invite admin toggles.
 *
 * Usage:
 *   occ contacts:ocm-invites-config                 list all toggles
 *   occ contacts:ocm-invites-config <option>        print "1" or "0"
 *   occ contacts:ocm-invites-config <option> on|off persist a new value
 *
 * The supported options match FederatedInvitesService::OCM_INVITES_BOOL_KEYS
 * so admins can drive the same flags the settings UI exposes, without
 * needing one occ command per key.
 */
class OcmInvitesConfig extends Command {
	public function __construct(
		private IAppConfig $appConfig,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('contacts:ocm-invites-config')
			->setDescription('Read or write OCM invite admin toggles.')
			->addArgument(
				'option',
				InputArgument::OPTIONAL,
				'Toggle key (' . implode(', ', FederatedInvitesService::OCM_INVITES_BOOL_KEYS) . '). Omit to list all toggles.',
			)
			->addArgument(
				'value',
				InputArgument::OPTIONAL,
				'New value (on/off, true/false, 1/0, yes/no). Omit to read the current value.',
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$option = $input->getArgument('option');
		$value = $input->getArgument('value');

		if ($option === null) {
			return $this->listAll($output);
		}

		if (!in_array($option, FederatedInvitesService::OCM_INVITES_BOOL_KEYS, true)) {
			$output->writeln(sprintf(
				'<error>Unknown OCM invite toggle "%s". Allowed: %s.</error>',
				$option,
				implode(', ', FederatedInvitesService::OCM_INVITES_BOOL_KEYS),
			));
			return self::FAILURE;
		}

		if ($value === null) {
			$current = $this->appConfig->getValueBool(Application::APP_ID, $option);
			$output->writeln($current ? '1' : '0');
			return self::SUCCESS;
		}

		$parsed = $this->parseBool($value);
		if ($parsed === null) {
			$output->writeln(sprintf(
				'<error>Cannot parse "%s" as boolean. Use on/off, true/false, 1/0, or yes/no.</error>',
				$value,
			));
			return self::INVALID;
		}

		$current = $this->appConfig->getValueBool(Application::APP_ID, $option);
		if ($current === $parsed) {
			$output->writeln(sprintf('%s already %s.', $option, $parsed ? 'on' : 'off'));
			return self::SUCCESS;
		}

		$this->appConfig->setValueBool(Application::APP_ID, $option, $parsed);
		$output->writeln(sprintf('%s set to %s.', $option, $parsed ? 'on' : 'off'));
		return self::SUCCESS;
	}

	private function listAll(OutputInterface $output): int {
		$table = new Table($output);
		$table->setHeaders(['option', 'value']);
		foreach (FederatedInvitesService::OCM_INVITES_BOOL_KEYS as $key) {
			$current = $this->appConfig->getValueBool(Application::APP_ID, $key);
			$table->addRow([$key, $current ? 'on' : 'off']);
		}
		$table->render();
		return self::SUCCESS;
	}

	private function parseBool(string $raw): ?bool {
		$normalised = strtolower(trim($raw));
		if (in_array($normalised, ['true', '1', 'on', 'yes'], true)) {
			return true;
		}
		if (in_array($normalised, ['false', '0', 'off', 'no'], true)) {
			return false;
		}
		return null;
	}
}
