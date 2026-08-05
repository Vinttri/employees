<template id="content">
	<NcContent app-name="employees">
		<navigator v-if="hasDataManager" />
		<router-view v-if="hasDataManager" />
		<NcEmptyContent v-else
			:name="t('employees', 'Finish the initial setup')"
			:description="t('employees', 'Go to global settings and select the data manager.')"
			style="background-color: white;">
			<template #icon>
				<AlertCircleOutline />
			</template>
		</NcEmptyContent>
	</NcContent>
</template>

<script>
import navigator from './navigator/SideNavigation.vue'
import { NcContent, NcEmptyContent } from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'

import AlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'

export default {
	name: 'App',

	components: {
		navigator,
		NcContent,
		NcEmptyContent,
		AlertCircleOutline,
	},

	provide() {
		return {
			Settings: this.Settings,
			groupuser: this.groupsuser,
			employee: this.employeeUser,
			subordinates: this.subordinates,
			permissions: this.permissions,
		}
	},

	props: {
		parameters: {
			type: Object,
			required: true,
		},
		groupsUser: {
			type: Object,
			required: true,
		},
		employee: {
			type: Array,
			required: true,
		},
		subordinatesGroup: {
			type: Array,
			required: true,
		},
		permissionsContext: {
			type: Object,
			default: () => ({
				uid: null,
				is_admin: false,
				groups: [],
				modules: {},
			}),
		},
	},

	data() {
		return {
			Settings: this.parameters,
			groupsuser: this.groupsUser,
			employeeUser: this.employee,
			subordinates: this.subordinatesGroup,
			permissions: this.permissionsContext,
		}
	},

	computed: {
		hasDataManager() {
			return this.Settings.usuario_almacenamiento !== null
				&& this.Settings.usuario_almacenamiento !== undefined
				&& String(this.Settings.usuario_almacenamiento).trim() !== ''
		},
	},

	methods: {
		t,
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
