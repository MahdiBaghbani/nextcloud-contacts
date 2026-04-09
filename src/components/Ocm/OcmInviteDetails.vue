<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContentDetails>
		<!-- nothing selected or invite not found -->
		<NcEmptyContent v-if="!invite" class="empty-content" :name="t('contacts', 'No invite selected')"
			:description="t('contacts', 'Select an invite on the list to begin')">
			<template #icon>
				<IconAccountSwitchOutline :size="20" />
			</template>
		</NcEmptyContent>

		<template v-else>
			<div class="invite-details">
				<h2>{{ t('contacts', 'OCM invite') }}</h2>
				
				<div class="invite-info">
					<div v-if="invite.recipientName" class="info-row">
						<span class="info-label">{{ t('contacts', 'Label') }}</span>
						<span class="info-value" data-testid="ocm-invite-detail-label">{{ invite.recipientName }}</span>
					</div>
					<div class="info-row">
						<span class="info-label">{{ t('contacts', 'Sent to') }}</span>
						<span class="info-value" data-testid="ocm-invite-detail-email">{{ invite.recipientEmail || t('contacts', 'No email (link-only)') }}</span>
					</div>
					<div class="info-row">
						<span class="info-label">{{ t('contacts', 'Created') }}</span>
						<span class="info-value">{{ formatDate(invite.createdAt) }}</span>
					</div>
					<div class="info-row">
						<span class="info-label">{{ t('contacts', 'Expires') }}</span>
						<span class="info-value">{{ formatDate(invite.expiredAt) }}</span>
					</div>
				</div>

				<!-- Share buttons -->
				<div class="share-section" data-testid="ocm-invite-share-section">
					<h3>{{ t('contacts', 'Share invite') }}</h3>
					<p class="share-hint">{{ t('contacts', 'The invite link is the easiest way to share. Invite codes are for manual acceptance.') }}</p>
					<div class="share-buttons">
						<NcButton type="secondary" @click="copyToClipboard(wayfLink, 'Invite link')" data-testid="ocm-invite-link-copy-btn">
							<template #icon>
								<ContentCopyIcon :size="20" />
							</template>
							{{ t('contacts', 'Copy invite link') }}
						</NcButton>
						<NcButton type="secondary" @click="copyToClipboard(plainInviteString, 'Invite code')" data-testid="ocm-invite-token-copy-btn">
							<template #icon>
								<ContentCopyIcon :size="20" />
							</template>
							{{ t('contacts', 'Copy invite code') }}
						</NcButton>
						<NcButton v-if="encodedCopyButtonEnabled" type="secondary" @click="copyToClipboard(base64InviteString, 'Encoded invite')" data-testid="ocm-invite-base64-copy-btn">
							<template #icon>
								<ContentCopyIcon :size="20" />
							</template>
							{{ t('contacts', 'Copy encoded invite') }}
						</NcButton>
					</div>
				</div>

				<!-- Action buttons -->
				<div class="action-buttons">
					<NcButton v-if="invite.recipientEmail"
						type="primary"
						@click="onResend"
						data-testid="ocm-invite-resend-btn">
						<template #icon>
							<CheckIcon :size="20" />
						</template>
						{{ t('contacts', 'Resend email') }}
					</NcButton>
					<NcButton type="error"
						@click="onRevoke"
						data-testid="ocm-invite-revoke-btn">
						{{ t('contacts', 'Revoke invite') }}
					</NcButton>
				</div>
			</div>
		</template>
	</NcAppContentDetails>
</template>

<script>

import {
	NcAppContentDetails,
	NcButton,
	NcEmptyContent,
} from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'

import CheckIcon from 'vue-material-design-icons/Check.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import IconAccountSwitchOutline from 'vue-material-design-icons/AccountSwitchOutline.vue'
import moment from '@nextcloud/moment'

const dateFormat = 'lll'

export default {
	name: 'OcmInviteDetails',

	components: {
		CheckIcon,
		ContentCopyIcon,
		IconAccountSwitchOutline,
		NcAppContentDetails,
		NcButton,
		NcEmptyContent,
	},

	props: {
		inviteKey: {
			type: String,
			default: undefined,
		},
	},

	data() {
		const config = loadState('contacts', 'ocmInvitesConfig', {
			optionalMail: false,
			ccSender: true,
			encodedCopyButton: false,
		})
		return {
			encodedCopyButtonEnabled: config.encodedCopyButton,
		}
	},

	computed: {
		invite() {
			return this.$store.getters.getOcmInvite(this.inviteKey)
		},
		provider() {
			return window.location.host
		},
		wayfLink() {
			if (!this.invite) return ''
			return `https://${this.provider}/index.php/apps/contacts/wayf?token=${this.invite.token}`
		},
		plainInviteString() {
			if (!this.invite) return ''
			return `${this.invite.token}@${this.provider}`
		},
		base64InviteString() {
			if (!this.invite) return ''
			return btoa(this.plainInviteString)
		},
	},

	methods: {
		formatDate(date) {
			// moment takes milliseconds
			return moment(date*1000).format(dateFormat)
		},
		async copyToClipboard(text, label) {
			try {
				await navigator.clipboard.writeText(text)
				showSuccess(t('contacts', '{label} copied to clipboard', { label }))
			} catch (error) {
				showError(t('contacts', 'Failed to copy to clipboard'))
			}
		},
		async onResend() {
			try {
				const response = await this.$store.dispatch('resendOcmInvite', this.invite)
				window.open(response.data.invite, '_self')
			} catch(error) {
				const message = error.response.data.message
				showError(t('contacts', message))
			}
		},
		async onRevoke() {
			await this.$store.dispatch('deleteOcmInvite', this.invite)
		},
	},

}
</script>

<style lang="scss" scoped>
.empty-content {
	margin-top: 5em;
}

.invite-details {
	padding: 1.5em;
	max-width: 600px;

	h2 {
		margin: 0 0 1.5em 0;
		font-size: 1.4em;
		font-weight: 600;
	}

	h3 {
		margin: 0 0 0.75em 0;
		font-size: 1em;
		font-weight: 600;
		color: var(--color-text-maxcontrast);
	}
}

.invite-info {
	margin-bottom: 2em;

	.info-row {
		display: flex;
		padding: 0.6em 0;
		border-bottom: 1px solid var(--color-border-dark);

		&:last-child {
			border-bottom: none;
		}

		.info-label {
			flex: 0 0 100px;
			font-weight: 500;
			color: var(--color-text-maxcontrast);
		}

		.info-value {
			flex: 1;
			word-break: break-word;
		}
	}
}

.share-section {
	margin-bottom: 1.5em;
	padding: 1em;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);

	.share-hint {
		font-size: 0.85em;
		color: var(--color-text-maxcontrast);
		margin-bottom: 0.75em;
	}

	.share-buttons {
		display: flex;
		flex-direction: column;
		gap: 0.5em;

		:deep(.button-vue) {
			width: 100%;
			justify-content: flex-start;
		}
	}
}

.action-buttons {
	display: flex;
	gap: 0.5em;

	@media (max-width: 400px) {
		flex-direction: column;
	}
}
</style>
