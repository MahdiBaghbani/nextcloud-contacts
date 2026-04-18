<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Contacts\Migration;

use Closure;
use OC\Core\AppInfo\ConfigLexicon as CoreConfigLexicon;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * The OCM invite routes were migrated from appinfo/routes.php to PHP
 * #[FrontpageRoute] attributes on FederatedInvitesController. Attribute
 * routes derive their internal name from the controller class name and
 * the camelCase method name (no underscore conversion), so the route
 * formerly known as 'contacts.federated_invites.invite_accept_dialog'
 * is now 'contacts.federatedinvites.inviteacceptdialog'.
 *
 * The 'core/ocm_invite_accept_dialog' app-config value is consumed by
 * OCMDiscoveryService::buildLocalProvider() via linkToRouteAbsolute(),
 * so it must point at a name the router can still resolve. New
 * deployments get the new value via EnableOcmInvites; this migration
 * rewrites it for existing deployments where the legacy name was
 * persisted.
 */
class Version8007Date20260417120200 extends SimpleMigrationStep {
	private const LEGACY_ROUTE = 'contacts.federated_invites.invite_accept_dialog';
	private const NEW_ROUTE = 'contacts.federatedinvites.inviteacceptdialog';

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$current = $this->appConfig->getValueString('core', CoreConfigLexicon::OCM_INVITE_ACCEPT_DIALOG);
		if ($current === self::LEGACY_ROUTE) {
			$this->appConfig->setValueString('core', CoreConfigLexicon::OCM_INVITE_ACCEPT_DIALOG, self::NEW_ROUTE);
			$output->info('Rewrote core/ocm_invite_accept_dialog from legacy to attribute-route name.');
		}
	}
}
