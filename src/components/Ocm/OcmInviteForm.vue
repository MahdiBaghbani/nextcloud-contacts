<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="contact-header__infos">
		<h5>{{ t('contacts', 'Invite someone outside your organisation to collaborate.') }}</h5>
		<p>{{ t('contacts', 'After the invitee has accepted the invite both of you will appear in each others\' contacts list and you can start sharing data with each other.') }}</p>
		
		<div class="form-field">
			<NcTextField type="text"
				:label="t('contacts', 'Invite label (for your reference)')"
				:placeholder="t('contacts', 'e.g. Mahdi from OCM')"
				:value="ocmInvite.note"
				data-testid="ocm-invite-note-input"
				@input="setNote" />
			<p class="hint">{{ t('contacts', 'A name or note to help you identify this invite') }}</p>
		</div>

		<div class="email-section">
			<!-- Only show toggle if optional mail is enabled -->
			<label v-if="optionalMailEnabled" class="email-toggle">
				<input type="checkbox" v-model="sendEmail" data-testid="ocm-invite-send-email-checkbox">
				<span>{{ t('contacts', 'Send invite via email') }}</span>
			</label>
			<div v-if="showEmailFields" class="email-fields">
				<NcTextField
					type="email"
					:label="t('contacts', 'Recipient email')"
					:placeholder="t('contacts', 'email@example.com')"
					:value="ocmInvite.email"
					inputmode="email"
					data-testid="ocm-invite-email-input"
					@input="setEmail" />
				<NcTextArea
					:label="t('contacts', 'Personal message (optional)')"
					:placeholder="t('contacts', 'Message to include in the email')"
					:value="ocmInvite.message"
					:rows="3"
					data-testid="ocm-invite-message-input"
					@update:value="setMessage" />
				<!-- CC checkbox - only show if enabled in config -->
				<label v-if="ccSenderEnabled" class="cc-toggle">
					<input type="checkbox" v-model="ccSender" data-testid="ocm-invite-cc-sender-checkbox">
					<span>{{ t('contacts', 'Also send a copy of this invite to me') }}</span>
				</label>
			</div>
			<p v-if="optionalMailEnabled && !sendEmail" class="hint">{{ t('contacts', 'If you do not send an email, you will need to share the invite link yourself.') }}</p>
		</div>

		<div class="actions">
			<slot name="new-invite-actions" />
		</div>
	</div>
</template>

<script>
import { NcTextField, NcTextArea } from '@nextcloud/vue'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'OcmInviteForm',
	components: {
		NcTextField,
		NcTextArea,
	},
	props: {
		ocmInvite: {
			type: Object,
			required: true,
		},
	},
	data() {
		const config = loadState('contacts', 'ocmInvitesConfig', {
			optionalMail: false,
			ccSender: true,
			encodedCopyButton: false,
		})
		return {
			sendEmail: !config.optionalMail, // Default to sending email if not optional
			ccSender: false,
			optionalMailEnabled: config.optionalMail,
			ccSenderEnabled: config.ccSender,
		}
	},
	computed: {
		showEmailFields() {
			// Always show if optional mail is disabled (email required)
			// Otherwise show based on sendEmail toggle
			return !this.optionalMailEnabled || this.sendEmail
		},
	},
	watch: {
		sendEmail: {
			immediate: true,
			handler(newVal) {
				// Expose sendEmail to parent via the invite object
				this.ocmInvite.sendEmail = newVal
				if (!newVal) {
					this.ocmInvite.email = ''
					this.ocmInvite.message = ''
					this.ccSender = false
				}
			},
		},
		ccSender(newVal) {
			// Expose ccSender to parent via the invite object
			this.ocmInvite.ccSender = newVal
		},
	},
	methods: {
		setNote(e) {
			this.ocmInvite.note = e.target.value
		},
		setEmail(e) {
			this.ocmInvite.email = e.target.value
		},
		setMessage(value) {
			// NcTextArea uses @update:value which passes the value directly
			this.ocmInvite.message = value
		},
	},
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

	.hint {
		font-size: 0.85em;
		color: var(--color-text-maxcontrast);
		margin-top: 0.5em;
		margin-bottom: 0;
	}

	.form-field {
		margin-bottom: 1.5em;
	}

	.email-section {
		padding: 1em;
		background: var(--color-background-dark);
		border-radius: var(--border-radius-large);
		margin-bottom: 1.5em;

		.email-toggle,
		.cc-toggle {
			display: flex;
			align-items: center;
			gap: 0.5em;
			cursor: pointer;
			user-select: none;
			margin-bottom: 0.5em;

			input[type="checkbox"] {
				width: 18px;
				height: 18px;
				cursor: pointer;
				accent-color: var(--color-primary);
				outline: none;
				box-shadow: none;

				&:focus,
				&:hover,
				&:focus-visible {
					outline: none;
					box-shadow: none;
				}
			}

			span {
				font-weight: 500;
			}
		}

		.cc-toggle {
			margin-top: 0.5em;
			margin-bottom: 0;

			span {
				font-weight: normal;
				font-size: 0.9em;
			}
		}

		.email-fields {
			display: flex;
			flex-direction: column;
			gap: 1em;
			margin-top: 1em;
		}

		.hint {
			margin-top: 0.5em;
		}
	}

	.actions {
		margin-top: 1em;
	}
}
</style>