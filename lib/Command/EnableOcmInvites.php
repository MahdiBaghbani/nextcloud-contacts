<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Contacts\Command;

use OC\Core\AppInfo\ConfigLexicon as CoreConfigLexicon;
use OCA\Contacts\AppInfo\Application;
use OCA\Contacts\ConfigLexicon;
use OCP\IAppConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnableOcmInvites extends Command {
	public function __construct(
		protected IAppConfig $appConfig,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('contacts:enable-ocm-invites')
			->setDescription('Enable OCM Invites.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$isAlreadyEnabled = $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_ENABLED);

		if ($isAlreadyEnabled) {
			$output->writeln('OCM Invites already enabled.');
			return self::SUCCESS;
		}

		$this->appConfig->setValueBool(Application::APP_ID, ConfigLexicon::OCM_INVITES_ENABLED, true);
		$this->appConfig->setValueString('core', CoreConfigLexicon::OCM_INVITE_ACCEPT_DIALOG, 'contacts.federatedinvites.inviteacceptdialog');

		$output->writeln('OCM Invites successfully enabled.');
		return self::SUCCESS;
	}
}
