<template id="EmployeeList">
	<NcAppContent v-if="loading" :name="t('employees', 'Loading')">
		<NcEmptyContent class="empty-content" :name="t('employees', 'Loading')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>
	</NcAppContent>
	<NcAppContent v-else :name="t('employees', 'Loading')">
		<!-- contacts list -->
		<template #list>
			<PositionsFullList
				:list="positionsList"
				:contacts="Positions"
				:search-query="searchQuery"
				:reload-bus="reloadBus" />
		</template>

		<!-- main contacts details -->
		<PositionsDetails :data="data_positions" :people-area="peopleArea" />
		<FloatingHelpButton
			:open.sync="modalPositionsMessage"
			:title="t('employees', 'Positions information')"
			:icon="AccountGroup">
			<PositionsMessage />
		</FloatingHelpButton>
	</NcAppContent>
</template>

<script>
// agregados
import PositionsFullList from './PositionsFullList.vue'
import PositionsDetails from './PositionsDetails.vue'
import FloatingHelpButton from '../Helpers/FloatingHelpButton.vue'
import PositionsMessage from './PositionsMessage.vue'

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
	name: 'PositionsList',
	components: {
		PositionsFullList,
		NcEmptyContent,
		NcAppContent,
		NcLoadingIcon,
		PositionsDetails,
		// ContactsList,
		FloatingHelpButton,
		PositionsMessage,
	},

	data() {
		return {
			loading: true,
			Positions: [],
			searchQuery: '',
			reloadBus: mitt(),
			positionsList: [],
			data_positions: {},
			peopleArea: {},
			modalPositionsMessage: false,
			AccountGroup,
		}
	},

	async mounted() {
		this.getall()
		this.$root.$on('send-data-position', (data) => {
			this.data_positions = data
			this.getallpuesto(data.id_positions)
		})
		this.$root.$on('delete-Position', (data) => {
			this.getall()
		})
		this.$root.$on('reload', () => {
			this.getall()
		})
	},

	methods: {
		t,

		async getallpuesto(puesto) {
			try {
				await axios.get(generateUrl('/apps/employees/GetEmpleadosPuesto/' + puesto))
					.then(
						(response) => {
							this.peopleArea = response?.data?.ocs?.data
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('employees', 'An exception has occurred [01] [{error}]', { error: String(err) }))
			}
		},

		async getall() {
			try {
				await axios.get(generateUrl('/apps/employees/GetPositionsList'))
					.then(
						(response) => {
							this.Positions = response?.data?.ocs?.data
							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('employees', 'An exception has occurred [01] [{error}]', { error: String(err) }))
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
