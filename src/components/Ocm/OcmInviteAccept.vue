<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="contact-header__infos">
		<h5 class="">
			{{ t('contacts', 'Accept an invite to share contact information') }}
		</h5>
		<p>{{ t('contacts', 'After you accept, both of you will appear in each other\'s contacts list and you can start sharing data with each other.') }}</p>
		<dl class="invitation-details">
			<div class="detail-row">
				<dt>{{ t('contacts', 'Invite code') }}</dt>
				<dd>{{ token }}</dd>
			</div>
			<div class="detail-row">
				<dt>{{ t('contacts', 'Cloud provider') }}</dt>
				<dd>{{ provider }}</dd>
			</div>
		</dl>
		<div class="actions">
			<slot name="accept-invite-actions" />
		</div>
	</div>

</template>

<script>

export default {
	name: 'OcmInviteAccept',
	props: {
		token: {
			type: String,
			required: true,
		},
		provider: {
			type: String,
			required: true,
		},
	}
}
</script>
<style lang="scss" scoped>
.contact-header__infos {
	margin: 1em;

	h5 {
		margin: 0 0 0.5em 0;
	}

	> p {
		margin-bottom: 1.5em;
		color: var(--color-text-maxcontrast);
	}
}

.invitation-details {
	margin: 0 0 1.5em 0;
	padding: 1em;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);

	.detail-row {
		display: flex;
		padding: 0.5em 0;

		&:not(:last-child) {
			border-bottom: 1px solid var(--color-border);
		}

		dt {
			flex: 0 0 120px;
			font-weight: 500;
			color: var(--color-text-maxcontrast);
		}

		dd {
			flex: 1;
			margin: 0;
			word-break: break-all;
		}
	}
}
</style>
