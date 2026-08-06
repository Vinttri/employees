<template id="content">
	<NcAppContent :name="t('employees', 'Employees - Activities')">
		<List
			:loading="loading"
			:listas="listas"
			:select="select"
			:defaultbuttons="false"
			:custom="true">
			<template #custombuttons>
				<div class="button-container">
					<NcActions>
						<template #icon>
							<DatabaseCog :size="20" />
						</template>
						<NcActionButton
							:close-after-click="true"
							@click="reportConfig()">
							<template #icon>
								<AccountMultiplePlusOutline :size="20" />
							</template>
							{{ t('employees', 'configure period') }}
						</NcActionButton>

						<NcActionButton @click="Exportar()">
							<template #icon>
								<DatabaseExport :size="20" />
							</template>
							{{ t('employees', 'Export period report') }}
						</NcActionButton>

						<NcActionSeparator />
					</NcActions>
				</div>
			</template>
			<template #custom>
				<div class="periodo-details">
					<h3>
						{{ t('employees', 'General summary') }} - {{ monthLabel(period_start) }} -
						{{ monthLabel(period_end) }}
						({{ normalizedPeriod.anio || '-' }})
					</h3>

					<AdminOverview
						:resumen="resumenGeneral"
						:loading="loadingResumen"
						:Activity-list="Activity"
						:proyectos-list="temp_listas" />
				</div>
			</template>
			<template #details>
				<h3>{{ t('employees', 'Employee summary') }} - {{ monthLabel(period_start) }} - {{ monthLabel(period_end) }} ({{ normalizedPeriod.anio || '-' }})</h3>
				<AdminDetails :select="select"
					:salary="salary"
					:Activity-list="Activity"
					:proyectos-list="temp_listas" />
			</template>
		</List>

		<NcModal
			v-if="modal"
			ref="modalRef"
			:name="t('employees', 'Add new activity')"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<NcTextField
						required
						:value.sync="name_activity"
						:label="t('employees', 'Activity name')" />
					<NcTextArea
						required
						resize="vertical"
						:value.sync="description_activity"
						:label="t('employees', 'Description activity')" />
					<div class="time-selector">
						<div class="radios">
							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="minutos"
								:name="t('employees', 'Minutes')"
								type="radio"
								button-variant-grouped="horizontal">
								{{ t('employees', 'Minutes') }}
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch
								v-model="type_time"
								:button-variant="true"
								value="horas"
								:name="t('employees', 'Hours')"
								type="radio"
								button-variant-grouped="horizontal">
								{{ t('employees', 'Hours') }}
							</NcCheckboxRadioSwitch>
						</div>
						<div class="estimatetime">
							<NcTextField
								required
								:value.sync="time_activity"
								type="number"
								:label="t('employees', 'Estimate time')" />
						</div>
						<div class="save">
							<NcButton
								v-if="editing"
								class="center"
								:aria-label="t('employees', 'Edit Activity')"
								type="primary"
								@click="modify()">
								{{ t('employees', 'Edit Activity') }}
							</NcButton>
							<NcButton
								v-else
								class="center"
								:aria-label="t('employees', 'Create Activity')"
								type="primary"
								@click="create()">
								{{ t('employees', 'Create Activity') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
		</NcModal>
		<input
			ref="file"
			type="file"
			style="display: none"
			accept=".xlsx"
			@change="importar()">
		<NcModal
			v-if="modalReport"
			ref="modalRef"
			:name="t('employees', 'Report configuration')"
			size="large"
			@close="closeModal">
			<div class="modal__content">
				<div class="form-group center">
					<input
						ref="trapFocus"
						type="text"
						style="position:absolute;opacity:0;height:0;width:0;pointer-events:none;">
					<div class="report-config">
						<NcSelect
							v-model="period_start"
							:options="meses"
							label="label"
							:reduce="m => m.value"
							:input-label="t('employees', 'Start month')"
							class="select-date" />

						<NcSelect
							v-model="period_end"
							:options="meses"
							label="label"
							:reduce="m => m.value"
							:input-label="t('employees', 'End month')"
							class="select-date" />
						<NcSelect
							v-model="anioSeleccionado"
							:options="anios"
							:reduce="a => a"
							:input-label="t('employees', 'Year')"
							class="select-date" />
					</div>
					<NcButton
						class="appli-report"
						:aria-label="t('employees', 'apply changes')"
						type="primary"
						@click="ChangeReportConfig()">
						{{ t('employees', 'Apply changes') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</NcAppContent>
</template>

<script>
// Icons
import DatabaseExport from 'vue-material-design-icons/DatabaseExport.vue'
import AccountMultiplePlusOutline from 'vue-material-design-icons/AccountMultiplePlusOutline.vue'
import DatabaseCog from 'vue-material-design-icons/DatabaseCog.vue'

// public imports
import { showError /*, showSuccess */ } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'

import List from '../../Helpers/Lists/List.vue'
import AdminDetails from './AdminDetails.vue'
import AdminOverview from './AdminOverview.vue'

import {
	NcAppContent,
	NcModal,
	NcTextField,
	NcButton,
	NcTextArea,
	NcCheckboxRadioSwitch,
	NcActions,
	NcActionButton,
	NcSelect,
} from '@nextcloud/vue'

export default {
	name: 'AdminReports',
	components: {
		NcAppContent,
		List,
		NcModal,
		NcTextField,
		NcButton,
		NcTextArea,
		NcCheckboxRadioSwitch,
		AdminDetails,
		NcActions,
		NcActionButton,
		DatabaseExport,
		AccountMultiplePlusOutline,
		DatabaseCog,
		NcSelect,
		AdminOverview,
	},
	data() {
		return {
			editing: false,
			loading: true,
			listas: [],
			select: [],
			modal: false,
			name_activity: '',
			description_activity: '',
			type_time: 'minutos',
			time_activity: 0,
			modalReport: false,
			period_start: null,
			period_end: null,
			anioSeleccionado: null,
			Activity: [],
			meses: [
				{ label: t('employees', 'January'), value: 1 },
				{ label: t('employees', 'February'), value: 2 },
				{ label: t('employees', 'March'), value: 3 },
				{ label: t('employees', 'April'), value: 4 },
				{ label: t('employees', 'May'), value: 5 },
				{ label: t('employees', 'June'), value: 6 },
				{ label: t('employees', 'July'), value: 7 },
				{ label: t('employees', 'August'), value: 8 },
				{ label: t('employees', 'September'), value: 9 },
				{ label: t('employees', 'October'), value: 10 },
				{ label: t('employees', 'November'), value: 11 },
				{ label: t('employees', 'December'), value: 12 },
			],
			anios: Array.from({ length: Math.max(0, new Date().getFullYear() - 2025 + 1) }, (_, i) => 2025 + i),
			resumenGeneral: null,
			loadingResumen: false,
			salary: 0,
			temp_listas: [],
		}
	},

	computed: {
		normalizedPeriod() {
			let periodoInicio = this.normalizeSelectNumber(this.period_start)
			let periodoFin = this.normalizeSelectNumber(this.period_end)
			const anio = this.normalizeSelectNumber(this.anioSeleccionado)

			if (periodoInicio !== null && periodoFin !== null && periodoInicio > periodoFin) {
				[periodoInicio, periodoFin] = [periodoFin, periodoInicio]
			}

			return {
				period_start: periodoInicio,
				period_end: periodoFin,
				anio,
			}
		},

		resumenFmt() {
			const kpis = this.resumenGeneral?.kpis || {}

			const num2 = new Intl.NumberFormat(document.documentElement.lang || 'en', { maximumFractionDigits: 2 })
			const int = new Intl.NumberFormat(document.documentElement.lang || 'en')
			const money = new Intl.NumberFormat(document.documentElement.lang || 'en', {
				style: 'currency',
				currency: 'MXN',
			})

			return {
				horas_reportadas: num2.format(kpis.horas_reportadas || 0),
				costo_total: money.format(kpis.costo_total || 0),
				empleados_con_reportes: int.format(kpis.empleados_con_reportes || 0),
				proyectos_activos: int.format(kpis.proyectos_activos || 0),
				Activity: int.format(kpis.Activity || 0),
				total_reportes: int.format(kpis.total_reportes || 0),
				promedio_horas_reporte: `${num2.format(kpis.promedio_horas_reporte || 0)} h`,
			}
		},
	},

	async mounted() {
		this.period_start = Number(localStorage.getItem('nextcloud_empleados_mes_inicio')) || null
		this.period_end = Number(localStorage.getItem('nextcloud_empleados_mes_fin')) || null
		this.anioSeleccionado = Number(localStorage.getItem('nextcloud_empleados_anio_seleccionado')) || null

		this._onDetails = (id) => this.gethistorial(id)
		this._onNew = () => this.openModal()
		this._onExport = () => this.Exportar()
		this._onImport = () => this.$refs.file.click()
		// deteccion de esc
		window.addEventListener('keydown', this.onKeyDown)

		this.$root.$on('details', this._onDetails)
		this.$root.$on('new', this._onNew)
		this.$root.$on('delete', this._onDelete)
		this.$root.$on('edit', this._onEdit)
		this.$root.$on('exportlist', this._onExport)
		this.$root.$on('importlist', this._onImport)
		this.GetEmpleadosReports()
		this.GetCompaniesGroups()
		this.GetActivities()
		this.GetAdminReportsSummary()
	},

	beforeDestroy() {
		window.removeEventListener('keydown', this.onKeyDown)

		this.$root.$off('details', this._onDetails)
		this.$root.$off('new', this._onNew)
		this.$root.$off('delete', this._onDelete)
		this.$root.$off('edit', this._onEdit)
		this.$root.$off('exportlist', this._onExport)
		this.$root.$off('importlist', this._onImport)
	},

	methods: {
		t,

		 onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},

		onEsc() {
			this.select = []
			this.salary = 0
		},

		openModal() {
			this.editing = false
			this.name_activity = null
			this.description_activity = null
			this.type_time = 'minutos'
			this.time_activity = null

			this.modal = true
		},

		closeModal() {
			this.modal = false
			this.modalReport = false
		},

		reportConfig() {
			this.modalReport = true
		},

		async GetActivities() {
			try {
				await axios.get(generateUrl('/apps/employees/GetActivities'))
					.then(
						(response) => {
							const keyMap = {
								id_activity: 'id',
								name: 'label',
								time_actual: 'count',
							}

							const renameKeys = (obj, map) =>
								Object.fromEntries(Object.entries(obj).map(([k, v]) => [map[k] ?? k, v]))

							const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

							this.Activity = arr.map(o => renameKeys(o, keyMap))

							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async GetCompaniesGroups() {
			try {
				await axios.get(generateUrl('/apps/employees/GetCompaniesGroups'))
					.then(
						(response) => {
							if (response?.data?.ocs?.meta?.status !== 'ok') {
								showError(response?.data?.ocs?.meta?.message)
								this.loading = false
								window.location.href = '/apps/employees/#/'
								return
							}
							const keyMap = {
								id_client: 'id',
								name: 'name',
								client_parent: 'count',
							}

							const renameKeys = (obj, map) =>
								Object.fromEntries(Object.entries(obj).map(([k, v]) => [map[k] ?? k, v]))

							const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

							this.temp_listas = arr.map(o => renameKeys(o, keyMap))

							const data = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

							// Lista para tu <List>
							this.temp_listas = data.map(o => ({
								id: o.id_client,
								name: o.name,
								count: o.child_count,
							}))

							// Opciones para <NcSelect>
							this.empresasOptions = data.map(o => ({
								id: o.id_client,
								label: o.name,
							}))

							this.loading = false
						},
						(err) => {
							showError(err)
						},
					)
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		async GetEmpleadosReports() {
			try {
				await axios.post(generateUrl('/apps/employees/GetEmpleadosReports'), this.normalizedPeriod).then(
					(response) => {
						if (response?.data?.ocs?.meta?.status !== 'ok') {
							showError(response?.data?.ocs?.meta?.message)
							this.loading = false
							window.location.href = '/apps/employees/#/'
							return
						}
						const keyMap = {
							id_employees: 'id',
							displayname: 'name',
							id_user: 'image',
							total_tiempo_registrado: 'count',
							salary: 'salary',
						}

						const renameKeys = (obj, map) =>
							Object.fromEntries(
								Object.entries(obj).map(([k, v]) => {
									if (k === 'displayname') {
										return ['name', v ?? obj.id_user]
									}
									return [map[k] ?? k, v]
								}),
							)

						const arr = Array.isArray(response?.data?.ocs?.data) ? response.data.ocs.data : []

						this.listas = arr.map(o => renameKeys(o, keyMap))

						this.loading = false
					},
					(err) => {
						showError(err)
					},
				)
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [01] [{error}]', { error: String(err) }))
			}
		},

		ChangeReportConfig() {
			const period = this.normalizedPeriod

			this.period_start = period.period_start
			this.period_end = period.period_end
			this.anioSeleccionado = period.anio

			localStorage.setItem('nextcloud_empleados_mes_inicio', String(period.period_start ?? ''))
			localStorage.setItem('nextcloud_empleados_mes_fin', String(period.period_end ?? ''))
			localStorage.setItem('nextcloud_empleados_anio_seleccionado', String(period.anio ?? ''))

			this.closeModal()
			this.select = []
			this.salary = 0
			this.GetEmpleadosReports()
			this.GetAdminReportsSummary()
		},

		async gethistorial(id) {
			try {
				await axios.post(generateUrl('/apps/employees/GetReportesById'), {
					id,
					...this.normalizedPeriod,
				}).then(
					(response) => {
						this.select = response?.data?.ocs?.data
						this.salary = parseInt(this.listas.find(e => e.id === id).salary)
					},
					(err) => {
						showError(err)
					},
				)

			} catch (e) {
				showError(t('savingsgossler', 'Could not fetch your information'))
			} finally {
				this.loading = false
			}
		},

		async GetAdminReportsSummary() {
			try {
				this.loadingResumen = true

				const response = await axios.post(generateUrl('/apps/employees/GetAdminReportsSummary'), this.normalizedPeriod)

				if (response?.data?.ocs?.meta?.status !== 'ok') {
					showError(response?.data?.ocs?.meta?.message)
					return
				}

				this.resumenGeneral = response?.data?.ocs?.data ?? null
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [Resumen] [{error}]', { error: String(err) }))
			} finally {
				this.loadingResumen = false
			}
		},

		Exportar() {
			axios.post(
				generateUrl('/apps/employees/ExportarReportes'),
				this.normalizedPeriod,
				{
					responseType: 'blob',
				},
			).then((response) => {
				const blob = new Blob([response.data], {
					type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				})

				const url = URL.createObjectURL(blob)
				const link = document.createElement('a')
				link.href = url
				link.download = 'TimeReport.xlsx'
				document.body.appendChild(link)
				link.click()
				link.remove()
				URL.revokeObjectURL(url)
			}).catch((err) => {
				showError(t('employees', 'Se ha producido un error {error}, reporte al administrador', { error: String(err) }))
			})
		},

		normalizeSelectNumber(value) {
			const raw = value && typeof value === 'object'
				? value.value ?? value.id ?? null
				: value

			if (raw === null || raw === undefined || raw === '') {
				return null
			}

			const number = Number(raw)

			return Number.isFinite(number) ? number : null
		},

		monthLabel(value) {
			const month = this.normalizeSelectNumber(value)

			return this.meses.find(m => m.value === month)?.label || '-'
		},
	},
}
</script>

<style scoped lang="scss">
.time-selector {
	display: flex;
	margin: .5rem 0;          /* margen arriba y abajo */
	align-self: center;
}

.radios {
	display: flex;
	margin-right: 10px;
}

.estimatetime {
	display: flex;
}

.save {
	display: flex;
	margin-left: 10px;
}
.select-date {
	margin-right: 10px;
}
.report-config {
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
}
.appli-report {
	margin-top: 15px;
	align-self: center;
}
.periodo-details {
	margin-bottom: 10px;
	text-align: center;
}
.summary-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 20px;
	margin: 24px 0;
}

.summary-card {
	background: #fff;
	border-radius: 10px;
	padding: 22px 20px;
	text-align: center;
	box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
	border: 1px solid rgba(0, 0, 0, 0.06);
}

.summary-value {
	font-family: "Cormorant Garamond", serif;
	font-size: 2.2rem;
	font-weight: 600;
	color: #555352;
	line-height: 1.1;
}

.summary-label {
	margin-top: 6px;
	font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
	font-size: 0.75rem;
	letter-spacing: 1.5px;
	text-transform: uppercase;
	color: #555352;
}

@media (max-width: 480px) {
	.summary-grid {
		grid-template-columns: 1fr;
	}
}
</style>
