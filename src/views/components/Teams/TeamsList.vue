<template>
	<NcAppContent v-if="loading" :name="t('employees', 'Loading')">
		<NcEmptyContent class="empty-content" :name="t('employees', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else :name="t('employees', 'Loading')">
		<template #list>
			<TeamsFullList
				:list="TeamsList"
				:contacts="Teams"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<TeamsDetails
			:data="data_Teams"
			:people-area="peopleArea" />

		<FloatingHelpButton
			:open.sync="modalTeamsMessage"
			:title="t('employees', 'Team information')"
			:icon="AccountGroup">
			<TeamsMessage />
		</FloatingHelpButton>
	</NcAppContent>
</template>

<script>
// agregados
import TeamsFullList from './TeamsFullList.vue'
import TeamsDetails from './TeamsDetails.vue'
import FloatingHelpButton from '../Helpers/FloatingHelpButton.vue'
import TeamsMessage from './TeamsMessage.vue'

import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import mitt from 'mitt'
import { translate as t } from '@nextcloud/l10n'

import AccountGroup from 'vue-material-design-icons/AccountGroup.vue'

import {
	NcEmptyContent,
	NcAppContent,
	NcLoadingIcon,
} from '@nextcloud/vue'

export default {
	name: 'TeamsList',
	components: {
		TeamsFullList,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		TeamsDetails,
		FloatingHelpButton,
		TeamsMessage,
	},

	data() {
		return {
			loading: true,
			Teams: [],
			searchQuery: '',
			reloadBus: mitt(),
			TeamsList: [],
			data_Teams: {},
			peopleArea: {},
			modalTeamsMessage: false,
			AccountGroup,
		}
	},

	async mounted() {
		this.getall()

		this.$root.$on('send-data-team', (data) => {
			this.data_Teams = data || {}
			if (data && data.id_team) {
				this.getallequipo(data.id_team)
			} else {
				this.peopleArea = {}
			}
		})

		this.$root.$on('delete-Teams', () => {
			this.getall()
		})

		this.$root.$on('reload', () => {
			this.getall()
		})
	},

	methods: {
		// Exponer t a la plantilla
		t,

		async getallequipo(equipo) {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetEmpleadosEquipo/' + encodeURIComponent(equipo)))
				this.peopleArea = response?.data?.ocs?.data
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async getall() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetTeamsList'))
				this.Teams = response?.data?.ocs?.data
				this.loading = false
			} catch (err) {
				this.loading = false
				showError(t('employees', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},
	},
}
</script>

<style scoped lang="scss">
	.container {
		padding-left: 60px;
	}
	.board-title {
		padding-left: 60px;
		margin-right: 10px;
		margin-top: 14px;
		font-size: 25px;
		display: flex;
		align-items: center;
		font-weight: bold;

		.icon {
			margin-right: 8px;
		}
	}
</style>
