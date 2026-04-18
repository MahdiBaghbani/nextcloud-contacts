/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { defineStore } from 'pinia'

import {
	type OcmInviteData,
	type OcmInviteEntry,

	toOcmInviteEntry,
} from '../models/ocminvite.ts'
import logger from '../services/logger.js'

interface SortedEntry {
	key: string
	value: string | number | boolean | undefined
}

interface OcmInvitesState {
	ocmInvites: Record<string, OcmInviteEntry>
	sortedOcmInvites: SortedEntry[]
	orderKey: keyof OcmInviteData
}

interface NewInvitePayload {
	email?: string
	message?: string
	note?: string
	ccSender?: boolean
}

interface AttachEmailPayload {
	token: string
	email?: string
	message?: string
}

const sortData = (a: SortedEntry, b: SortedEntry): number => a.key.localeCompare(b.key)

const useOcmInvitesStore = defineStore('ocminvites', {
	state: (): OcmInvitesState => ({
		// Object-keyed map for O(1) lookups; the sortedOcmInvites array
		// keeps a precomputed display order so list views do not pay the
		// cost of resorting on every render.
		// https://codepen.io/skjnldsv/pen/ZmKvQo
		ocmInvites: {},
		sortedOcmInvites: [],
		orderKey: 'recipientEmail',
	}),

	getters: {
		getOcmInvite: (state) => (key: string): OcmInviteEntry | undefined => state.ocmInvites[key],
		getOcmInvites: (state): Record<string, OcmInviteEntry> => state.ocmInvites,
		getSortedOcmInvites: (state): SortedEntry[] => state.sortedOcmInvites,
	},

	actions: {
		async fetchOcmInvites(): Promise<void> {
			try {
				const response = await axios.get(generateUrl('/apps/contacts/ocm/invitations'))
				this.appendInvites(response.data)
				this.sortInvites()
			} catch (error) {
				logger.error('Error fetching OCM invites: ' + error)
			}
		},

		async deleteOcmInvite(invite: OcmInviteEntry): Promise<void> {
			const token = invite.token
			const url = generateUrl('/apps/contacts/ocm/invitations/{token}', { token })
			try {
				await axios.delete(url)
				this.removeOcmInvite(invite.key)
			} catch (error) {
				logger.error('Error deleting OCM invite with token ' + token)
			}
		},

		async resendOcmInvite(invite: OcmInviteEntry) {
			const token = invite.token
			const url = generateUrl('/apps/contacts/ocm/invitations/{token}/resend', { token })
			try {
				return await axios.patch(url)
			} catch (error) {
				logger.error('Error resending OCM invite with token ' + token)
				throw error
			}
		},

		async newOcmInvite(invite: NewInvitePayload) {
			const url = generateUrl('/apps/contacts/ocm/invitations')
			const payload = {
				email: invite.email || '',
				message: invite.message || '',
				note: invite.note || '',
				ccSender: invite.ccSender || false,
			}
			try {
				return await axios.post(url, payload)
			} catch (error) {
				logger.error('Error creating a new OCM invite for ' + invite.email)
				throw error
			}
		},

		async attachEmailAndSendOcmInvite({ token, email, message }: AttachEmailPayload) {
			const url = generateUrl('/apps/contacts/ocm/invitations/{token}/email', { token })
			const payload = {
				email: email || '',
				message: message || '',
			}
			let response
			try {
				response = await axios.patch(url, payload)
			} catch (error) {
				logger.error('Error attaching email to OCM invite with token ' + token)
				throw error
			}
			if (response?.data) {
				this.updateOcmInvite(response.data)
				this.sortInvites()
			}
			return response
		},

		/**
		 * Stores a fresh batch of raw invite payloads from the API. Skips
		 * any entry without a token because we cannot key it.
		 */
		appendInvites(invites: OcmInviteData[] = []): void {
			this.ocmInvites = invites.reduce<Record<string, OcmInviteEntry>>((list, raw) => {
				const entry = toOcmInviteEntry(raw)
				if (entry) {
					list[entry.key] = entry
				} else {
					console.error('Invalid invite object', raw)
				}
				return list
			}, this.ocmInvites)
		},

		/**
		 * Recomputes the sorted index from the current invite map.
		 * Filtering with computed properties was too slow on large
		 * lists; a precomputed index is cheap to read and only refreshed
		 * on writes.
		 */
		sortInvites(): void {
			const invites = Object.values(this.ocmInvites) as OcmInviteEntry[]
			this.sortedOcmInvites = invites
				.map((invite) => ({ key: invite.key, value: invite[this.orderKey] }))
				.sort(sortData)
		},

		removeOcmInvite(key: string): void {
			const index = this.sortedOcmInvites.findIndex((entry) => entry.key === key)
			if (index !== -1) {
				this.sortedOcmInvites.splice(index, 1)
			}
			delete this.ocmInvites[key]
		},

		/**
		 * Replaces a single cached invite with a fresh server payload,
		 * keyed by token.
		 */
		updateOcmInvite(raw: OcmInviteData): void {
			const entry = toOcmInviteEntry(raw)
			if (!entry) {
				console.error('Invalid invite object', raw)
				return
			}
			this.ocmInvites = { ...this.ocmInvites, [entry.key]: entry }
		},
	},
})

export default useOcmInvitesStore
