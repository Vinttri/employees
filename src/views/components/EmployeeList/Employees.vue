<template id="content">
	<EmployeeList
		:employees-prop="Empleados"
		:loading-prop="loading" />
</template>

<script>
import EmployeeList from './EmployeeList.vue'
import { showError /* showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Employees',
	components: {
		EmployeeList,
	},

	data() {
		return {
			loading: true,
			Empleados: [],
			data_empleado: {},
		}
	},

	mounted() {
		this.getall()
		this.$bus.on('send-data', (data) => {
			this.data_empleado = data
		})
		this.$bus.on('getall', () => {
			this.getall()
		})
	},

	methods: {
		t,

		async getall() {
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetEmpleadosList'))
				const payload = response?.data?.ocs?.data ?? response?.data ?? {}
				const employees = payload?.Employees ?? payload?.Empleados ?? payload

				this.Empleados = Array.isArray(employees) ? employees : []
			} catch (err) {
				console.error(err)
				this.Empleados = []
				showError(t('employees', 'Could not fetch your information'))
			} finally {
				this.loading = false
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
