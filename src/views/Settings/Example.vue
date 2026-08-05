<!-- eslint-disable vue/require-v-for-key -->
<template>
	<div class="container">
		<!--NcButton @click="showModal">
			Agregar estacuionamiento
		</NcButton-->

		<div class="table_component" role="region" tabindex="0">
			<table>
				<thead>
					<tr>
						<th>{{ t('employees', 'Space') }}</th>
						<th>{{ t('employees', 'Employee') }}</th>
						<th>{{ t('employees', 'Options') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="n in numtables" :key="n">
						<td>{{ n }}</td>
						<td v-if="casillaedit == n">
							<NcSelect v-bind="props" v-model="employees" :input-label="t('employees', 'Employees')" />
						</td>
						<td v-else>
							{{ t('employees', 'Ready to edit') }}
						</td>
						<td>
							<NcButton v-if="casillaedit == n" @click="guardar(n)">
								{{ t('employees', 'Save') }}
							</NcButton>
							<NcButton v-else @click="edit(n)">
								{{ t('employees', 'Edit') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<NcModal
			:show.sync="modal"
			size="large"
			:name="t('employees', 'Name')"
			out-transition
			@close="closeModal">
			<template #actions>
				<NcActionCaption :name="t('employees', 'Some action')" />
			</template>
			<div class="modal__content">
				<h1>{{ t('employees', 'Example form') }}</h1>
				<div class="form-group">
					<NcTextField :label="t('employees', 'First name')" :value.sync="name">
						<template #icon>
							<AccountChildOutline :size="20" />
						</template>
					</NcTextField>
					<NcTextField :label="t('employees', 'Last name')" :value.sync="segundonombre" />
					<NcButton @click="enviar">
						{{ t('employees', 'Send') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
// icons
import AccountChildOutline from 'vue-material-design-icons/AccountChildOutline.vue'

// nextcloud/vue
import {
	NcButton,
	NcModal,
	NcActionCaption,
	NcTextField,
	NcSelect,
} from '@nextcloud/vue'
// import { ref } from 'vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

export default {
	name: 'Example',
	components: {
		NcButton,
		NcModal,
		NcActionCaption,
		NcTextField,
		AccountChildOutline,
		NcSelect,
	},

	data() {
		return {
			modal: false,
			name: '',
			segundonombre: '',
			numtables: 24,
			props: {
				userSelect: true,
				multiple: true,
				closeOnSelect: false,
				options: [],
			},
			Employee: [],
			casillaedit: null,
		}
	},

	// ciclos de vida
	 beforeCreate() {
		// console.log('beforeCreate')
	},
	created() {
		// console.log('created')
	},
	beforeMount() {
		// console.log('beforeMount')
	},
	async mounted() {
		//
	},
	beforeUpdate() {
		// console.log('beforeUpdate')
	},
	updated() {
		// console.log('updated')
	},
	beforeUnmount() {
		// console.log('beforeUnmount')
	},
	unmounted() {
		// console.log('unmounted')
	},

	methods: {
		t,
		showModal() {
			this.modal = true
			// eslint-disable-next-line no-console
			console.log(this.modal)
		},
		closeModal() {
			this.modal = false
		},
		async enviar() {
			// eslint-disable-next-line no-console
			console.log(this.name)
			// eslint-disable-next-line no-console
			console.log(this.segundonombre)
			try {
				await axios.post(generateUrl('/apps/employees/ejemplo'), {
					nombre_enviar: this.name,
					segundonombre_enviar: this.segundonombre,
				})
				showSuccess(t('employees', 'Sent successfully'))
			} catch (error) {
				// eslint-disable-next-line no-console
				console.log(error)
				showError(t('employees', 'This request failed'))
			}
		},
		async edit(id) {
			// eslint-disable-next-line no-console
			console.log(id)
			this.casillaedit = id
			this.Employee = []
			try {
				const response = await axios.get(generateUrl('/apps/employees/GetUserLists'))
				this.props.options = response.data.ocs.data.Empleados.map(user => ({
					id: user.uid,
					displayName: user.displayName || user.uid,
					isNoUser: false,
					icon: '',
					user: user.uid,
					preloadedUserStatus: {
						icon: '',
						status: user.isEnabled ? 'online' : 'offline',
						message: user.isEnabled ? this.t('employees', 'Active') : this.t('employees', 'Inactive'),
					},
				}))
				// eslint-disable-next-line no-console
				console.log(this.props.options)
				showSuccess(t('employees', 'Sent successfully'))
			} catch (error) {
				// eslint-disable-next-line no-console
				console.log(error)
				showError(t('employees', 'This request failed'))
			}
		},
		async guardar(id) {
			this.casillaedit = null
			try {
				await axios.post(generateUrl('/apps/employees/ejemplo'), {
					Employee: this.Employee,
				})
				showSuccess(t('employees', 'Sent successfully'))
			} catch (error) {
				// eslint-disable-next-line no-console
				console.log(error)
				showError(t('employees', 'This request failed'))
			}
		},
	},
}
</script>

<style>
	.ejemplo {
		color: red;
	}
	.table_component {
    overflow: auto;
    width: 100%;
	margin-bottom: 30px;
}

.table_component table {
    border: 1px solid #dededf;
    height: 100%;
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    border-spacing: 1px;
    text-align: left;
}

.table_component caption {
    caption-side: top;
    text-align: left;
}

.table_component th {
    border: 1px solid #dededf;
    background-color: #eceff1;
    color: #000000;
    padding: 5px;
}

.table_component td {
    border: 1px solid #dededf;
    background-color: #ffffff;
    color: #000000;
    padding: 5px;
}
</style>
