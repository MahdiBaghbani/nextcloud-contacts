/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ActionContext, Module } from 'vuex'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import logger from '../services/logger.js'
import {
	type OcmInviteData,
	type OcmInviteEntry,
	toOcmInviteEntry,
} from '../models/ocminvite.ts'

interface SortedEntry {
	key: string
	value: string | number | boolean | undefined
}

interface OcmInvitesState {
	ocmInvites: Record<string, OcmInviteEntry>
	sortedOcmInvites: SortedEntry[]
	orderKey: keyof OcmInviteData
}

type Context = ActionContext<OcmInvitesState, unknown>

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

const state = (): OcmInvitesState => ({
	// Using objects for performance
	// https://codepen.io/skjnldsv/pen/ZmKvQo
	ocmInvites: {},
	sortedOcmInvites: [],
	orderKey: 'recipientEmail',
})

const getters = {
	getOcmInvite: (state: OcmInvitesState) => (key: string): OcmInviteEntry | undefined => state.ocmInvites[key],
	getOcmInvites: (state: OcmInvitesState): Record<string, OcmInviteEntry> => state.ocmInvites,
	getSortedOcmInvites: (state: OcmInvitesState): SortedEntry[] => state.sortedOcmInvites,
}

const actions = {
	fetchOcmInvites(context: Context): void {
		axios.get(generateUrl('/apps/contacts/ocm/invitations'))
			.then((response) => {
				context.commit('appendInvites', response.data)
				context.commit('sortInvites')
			})
			.catch((error) => {
				logger.error('Error fetching OCM invites: ' + error)
			})
	},

	async deleteOcmInvite(context: Context, invite: OcmInviteEntry): Promise<void> {
		const token = invite.token
		const url = generateUrl('/apps/contacts/ocm/invitations/{token}', { token })
		try {
			await axios.delete(url)
			context.commit('deleteOcmInvite', invite.key)
		} catch (error) {
			logger.error('Error deleting OCM invite with token ' + token)
		}
	},

	async resendOcmInvite(_context: Context, invite: OcmInviteEntry) {
		const token = invite.token
		const url = generateUrl('/apps/contacts/ocm/invitations/{token}/resend', { token })
		try {
			return await axios.patch(url)
		} catch (error) {
			logger.error('Error resending OCM invite with token ' + token)
			throw error
		}
	},

	async newOcmInvite(_context: Context, invite: NewInvitePayload) {
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

	async attachEmailAndSendOcmInvite(context: Context, { token, email, message }: AttachEmailPayload) {
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
			context.commit('updateOcmInvite', response.data)
			context.commit('sortInvites')
		}
		return response
	},
}

const mutations = {
	/**
	 * Stores a fresh batch of raw invite payloads from the API. Skips any
	 * entry without a token because we cannot key it.
	 */
	appendInvites(state: OcmInvitesState, invites: OcmInviteData[] = []): void {
		state.ocmInvites = invites.reduce((list, raw) => {
			const entry = toOcmInviteEntry(raw)
			if (entry) {
				list[entry.key] = entry
			} else {
				console.error('Invalid invite object', raw)
			}
			return list
		}, state.ocmInvites)
	},

	/**
	 * Recomputes the sorted index from the current invite map. Filtering
	 * with computed properties was too slow on large lists; a precomputed
	 * index is cheap to read and only refreshed on writes.
	 */
	sortInvites(state: OcmInvitesState): void {
		state.sortedOcmInvites = Object.values(state.ocmInvites)
			.map((invite) => ({ key: invite.key, value: invite[state.orderKey] }))
			.sort(sortData)
	},

	deleteOcmInvite(state: OcmInvitesState, key: string): void {
		const index = state.sortedOcmInvites.findIndex((entry) => entry.key === key)
		if (index !== -1) {
			state.sortedOcmInvites.splice(index, 1)
		}
		delete state.ocmInvites[key]
	},

	/**
	 * Replaces a single cached invite with a fresh server payload, keyed
	 * by token.
	 */
	updateOcmInvite(state: OcmInvitesState, raw: OcmInviteData): void {
		const entry = toOcmInviteEntry(raw)
		if (!entry) {
			console.error('Invalid invite object', raw)
			return
		}
		state.ocmInvites = { ...state.ocmInvites, [entry.key]: entry }
	},
}

const ocmInvitesModule: Module<OcmInvitesState, unknown> = {
	state,
	getters,
	actions,
	mutations,
}

export default ocmInvitesModule
