/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

jest.mock('@nextcloud/axios', () => ({
	__esModule: true,
	default: {
		get: jest.fn(),
		post: jest.fn(),
		patch: jest.fn(),
		delete: jest.fn(),
	},
}))

jest.mock('@nextcloud/router', () => ({
	__esModule: true,
	generateUrl: (path, params = {}) => {
		let result = path
		for (const [key, value] of Object.entries(params)) {
			result = result.replaceAll(`{${key}}`, encodeURIComponent(String(value)))
		}
		return result
	},
}))

import axios from '@nextcloud/axios'
import ocmInvites from '../../../src/store/ocminvites.ts'
import { toOcmInviteEntry } from '../../../src/models/ocminvite.ts'

const TOKEN = 'token-1234'

const flatInvitePayload = (overrides = {}) => ({
	accepted: false,
	acceptedAt: null,
	createdAt: 1_800_000_000,
	expiredAt: 1_800_000_000 + 2_592_000,
	recipientEmail: 'recipient@example.org',
	recipientName: null,
	recipientProvider: null,
	recipientUserId: null,
	token: TOKEN,
	userId: 'alice',
	...overrides,
})

const makeStore = (initialState = {}) => {
	const state = {
		ocmInvites: {},
		sortedOcmInvites: [],
		orderKey: 'recipientEmail',
		...initialState,
	}
	const mutations = ocmInvites.mutations
	const commit = (mutation, payload) => {
		mutations[mutation](state, payload)
	}
	const context = { state, commit }
	return { state, context, commit }
}

describe('ocminvites store', () => {
	beforeEach(() => {
		jest.clearAllMocks()
	})

	describe('attachEmailAndSendOcmInvite', () => {
		test('PATCHes the per-invite email endpoint with the email and message payload', async () => {
			axios.patch.mockResolvedValue({ data: flatInvitePayload() })

			const { context } = makeStore()
			await ocmInvites.actions.attachEmailAndSendOcmInvite(context, {
				token: TOKEN,
				email: 'recipient@example.org',
				message: 'hello',
			})

			expect(axios.patch).toHaveBeenCalledTimes(1)
			const [url, payload] = axios.patch.mock.calls[0]
			expect(url).toBe(`/apps/contacts/ocm/invitations/${TOKEN}/email`)
			expect(payload).toEqual({
				email: 'recipient@example.org',
				message: 'hello',
			})
		})

		test('coerces missing email and message to empty strings', async () => {
			axios.patch.mockResolvedValue({ data: flatInvitePayload() })

			const { context } = makeStore()
			await ocmInvites.actions.attachEmailAndSendOcmInvite(context, { token: TOKEN })

			const [, payload] = axios.patch.mock.calls[0]
			expect(payload).toEqual({ email: '', message: '' })
		})

		test('stores a fresh invite entry from a flat backend response', async () => {
			axios.patch.mockResolvedValue({ data: flatInvitePayload() })

			const { state, context } = makeStore()
			const response = await ocmInvites.actions.attachEmailAndSendOcmInvite(context, {
				token: TOKEN,
				email: 'recipient@example.org',
				message: '',
			})

			expect(response.data.token).toBe(TOKEN)
			const stored = state.ocmInvites[TOKEN]
			expect(stored.key).toBe(TOKEN)
			expect(stored.token).toBe(TOKEN)
			expect(stored.recipientEmail).toBe('recipient@example.org')
			expect(state.sortedOcmInvites).toHaveLength(1)
			expect(state.sortedOcmInvites[0].key).toBe(TOKEN)
		})

		test('rethrows when the request fails and leaves state untouched', async () => {
			const failure = new Error('boom')
			axios.patch.mockRejectedValue(failure)

			const { state, context } = makeStore()
			await expect(
				ocmInvites.actions.attachEmailAndSendOcmInvite(context, {
					token: TOKEN,
					email: 'recipient@example.org',
					message: '',
				}),
			).rejects.toBe(failure)

			expect(state.ocmInvites).toEqual({})
			expect(state.sortedOcmInvites).toEqual([])
		})
	})

	describe('updateOcmInvite mutation', () => {
		test('replaces the invite for the matching token without dropping others', () => {
			const { state, commit } = makeStore({
				ocmInvites: {
					'other-token': toOcmInviteEntry({ token: 'other-token', recipientEmail: 'other@example.org' }),
				},
			})

			commit('updateOcmInvite', flatInvitePayload({ recipientEmail: 'fresh@example.org' }))

			expect(Object.keys(state.ocmInvites)).toEqual(expect.arrayContaining(['other-token', TOKEN]))
			expect(state.ocmInvites[TOKEN].recipientEmail).toBe('fresh@example.org')
			expect(state.ocmInvites['other-token'].recipientEmail).toBe('other@example.org')
		})

		test('ignores payloads without a token and never mutates state', () => {
			const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {})
			const { state, commit } = makeStore()

			commit('updateOcmInvite', { recipientEmail: 'no-token@example.org' })

			expect(state.ocmInvites).toEqual({})
			expect(errorSpy).toHaveBeenCalled()
			errorSpy.mockRestore()
		})
	})

	describe('deleteOcmInvite mutation', () => {
		test('removes only the targeted invite from the sorted list', () => {
			const a = toOcmInviteEntry({ token: 'a' })
			const b = toOcmInviteEntry({ token: 'b' })
			const { state, commit } = makeStore({
				ocmInvites: { a, b },
				sortedOcmInvites: [a, b],
			})

			commit('deleteOcmInvite', 'a')

			expect(state.sortedOcmInvites.map(i => i.key)).toEqual(['b'])
			expect(state.ocmInvites).not.toHaveProperty('a')
			expect(state.ocmInvites).toHaveProperty('b')
		})

		test('does not splice the last entry when the key is unknown', () => {
			const a = toOcmInviteEntry({ token: 'a' })
			const b = toOcmInviteEntry({ token: 'b' })
			const { state, commit } = makeStore({
				ocmInvites: { a, b },
				sortedOcmInvites: [a, b],
			})

			commit('deleteOcmInvite', 'missing-key')

			expect(state.sortedOcmInvites.map(i => i.key)).toEqual(['a', 'b'])
			expect(state.ocmInvites).toEqual({ a, b })
		})
	})
})
