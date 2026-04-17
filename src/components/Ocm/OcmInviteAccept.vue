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
		<div class="invitation-details">
			<div class="detail-row">
				<label>{{ t('contacts', 'Invite code') }}</label>
				<span>{{ token }}</span>
			</div>
			<div class="detail-row">
				<label>{{ t('contacts', 'Cloud provider') }}</label>
				<span>{{ provider }}</span>
			</div>
		</div>
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
	margin-bottom: 1.5em;
	padding: 1em;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);

	.detail-row {
		display: flex;
		padding: 0.5em 0;

		&:not(:last-child) {
			border-bottom: 1px solid var(--color-border);
		}

		label {
			flex: 0 0 120px;
			font-weight: 500;
			color: var(--color-text-maxcontrast);
		}

		span {
			flex: 1;
			word-break: break-all;
		}
	}
}
</style>
