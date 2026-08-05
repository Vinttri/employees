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

				this.Empleados = Array.isArray(employees)
					? employees.map(this.normalizeEmployee)
					: []
			} catch (err) {
				console.error(err)
				this.Empleados = []
				showError(t('employees', 'Could not fetch your information'))
			} finally {
				this.loading = false
			}
		},

		normalizeEmployee(employee) {
			const source = employee && typeof employee === 'object' ? employee : {}
			const uid = String(source.id_user ?? source.employee_uid ?? source.uid ?? '').trim()
			const displayName = String(
				source.displayname
				?? source.display_name
				?? source.name
				?? uid,
			).trim()

			return {
				...source,
				id_employees: Number(source.id_employees ?? source.id_employee),
				id_user: uid,
				uid,
				displayname: displayName || uid,
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
