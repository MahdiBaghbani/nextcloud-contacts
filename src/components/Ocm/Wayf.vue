<template>
	<NcGuestContent app-name="contacts" class="wayf">
		<template #default>
			<div class="wayf-body">
				<div v-if="token !== ''">
					<h2>{{ t('contacts', 'Providers') }}</h2>
					<p>{{ t('contacts', 'Where are you from?') }}</p>
					<p>{{ t('contacts', 'Please tell us your cloud provider.') }}</p>
					<div v-if="federations">
						<NcTextField
							id="wayf-search"
							v-model="query"
							:label="t('contacts', 'Type to search')"
							type="search"
							name="search">
							<template #icon>
								<Magnify :size="20" />
							</template>
						</NcTextField>
						<div
							v-for="(providers, federation) in federations"
							:key="federation">
							<h3>{{ federation }}</h3>
							<ul id="wayf-list" class="wayf-list">
								<NcListItem
									v-for="p in filteredBy(providers)"
									:key="p.fqdn"
									:href="providerInviteUrl(p)"
									:name="p.name"
									one-line>
									<template #icon>
										<NcListItemIcon :name="p.name" :subname="p.fqdn">
											<WeatherCloudyArrowRight :size="20" />
										</NcListItemIcon>
									</template>
								</NcListItem>
							</ul>
						</div>
					</div>
					<NcTextField
						id="wayf-manual"
						v-model="manualProvider"
						:label="t('contacts', 'No provider listed? Enter one manually.')"
						type="text"
						name="manual"
						@keyup.enter="goToManualProvider">
						<template #icon>
							<WeatherCloudyArrowRight :size="20" />
						</template>
					</NcTextField>
				</div>
				<div v-else>
					<p>{{ t('contacts', 'You need a token for this feature to work.') }}</p>
				</div>
			</div>
		</template>
	</NcGuestContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import {
	NcGuestContent,
	NcListItem,
	NcListItemIcon,
	NcTextField,
} from '@nextcloud/vue'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import WeatherCloudyArrowRight from 'vue-material-design-icons/WeatherCloudyArrowRight.vue'

export default {
	name: 'Wayf',
	components: {
		Magnify,
		NcGuestContent,
		NcListItem,
		NcListItemIcon,
		NcTextField,
		WeatherCloudyArrowRight,
	},

	props: {
		federations: { type: Object, default: () => ({}) },
		providerDomain: { type: String, default: '' },
		token: { type: String, default: '' },
	},

	data: () => ({ query: '', manualProvider: '' }),
	methods: {
		async discoverProvider(base) {
			const resp = await axios.get(generateUrl('/apps/contacts/discover'), {
				params: { base },
				timeout: 8000,
			})
			if (!resp.data?.inviteAcceptDialogAbsolute) {
				throw new Error('Discovery failed')
			}

			// append provider & token safely
			const u = new URL(resp.data.inviteAcceptDialogAbsolute)
			if (this.providerDomain) {
				u.searchParams.set('providerDomain', this.providerDomain)
			}
			if (this.token) {
				u.searchParams.set('token', this.token)
			}
			return u.toString()
		},

		providerInviteUrl(providerEntry) {
			const source = providerEntry?.inviteAcceptDialog || ''
			if (!source) {
				return '#'
			}
			try {
				const url = new URL(source, window.location.origin)
				if (this.providerDomain) {
					url.searchParams.set('providerDomain', this.providerDomain)
				}
				if (this.token) {
					url.searchParams.set('token', this.token)
				}
				return url.toString()
			} catch (error) {
				return '#'
			}
		},

		filteredBy(providers) {
			const s = (this.query || '').toLowerCase()
			return providers.filter((p) => p.name.toLowerCase().includes(s) || p.fqdn.toLowerCase().includes(s))
		},

		async goToManualProvider() {
			const input = (this.manualProvider || '').trim()
			if (!input) {
				return
			}
			try {
				const target = await this.discoverProvider(input)
				window.location.href = target
			} catch (error) {
				showError(this.t('contacts', 'Could not discover that provider. Check the address and try again.'))
			}
		},
	},
}
</script>
