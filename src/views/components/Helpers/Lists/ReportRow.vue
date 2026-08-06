<template>
	<div class="row">
		<NcListItem
			:name="titleText"
			bold
			:counter-number="counterText"
			:details="detailsText"
			:force-display-actions="true"
			@click.prevent="handleRowClick">
			<template #subname>
				{{ subnameText }}
				<span v-if="isInternalReport" class="origin-badge">{{ originLabel }}</span>
				<span v-if="isInternalReport" class="billable-badge">{{ t('employees', 'Non-billable') }}</span>
			</template>

			<template #indicator>
				<CheckboxBlankCircleOutline
					:size="16"
					:fill-color="indicatorColor" />
			</template>

			<template #actions>
				<NcActionButton v-if="editable"
					:close-after-click="true"
					@click="edit(index)">
					<template #icon>
						<DeleteAlert :size="20" />
					</template>
					{{ t('employees', 'Edit') }}
				</NcActionButton>
				<NcActionButton v-if="editable"
					:close-after-click="true"
					@click="showDialog = true, reportid = source.id">
					<template #icon>
						<DeleteAlert :size="20" />
					</template>
					{{ t('employees', 'Delete') }}
				</NcActionButton>
				<NcActionButton v-if="isSupportReport && source.id_team" @click="viewSupport">
					<template #icon>
						<OpenInNew :size="20" />
					</template>
					{{ t('employees', 'View support') }}
				</NcActionButton>
			</template>
		</NcListItem>
		<NcDialog
			:open.sync="showDialog"
			:name="t('employees', 'Confirm')"
			:message="t('employees', 'Do you want to delete this item?')"
			:buttons="buttons" />
		<NcModal
			v-if="ShowEdit"
			ref="modalRef"
			:name="t('employees', 'Report')"
			@close="closeEdit">
			<div class="modal__content">
				<form>
					<div class="form-group">
						<input
							ref="trapFocus"
							type="text"
							style="position:absolute;opacity:0;height:0;width:0;pointer-events:none;">
						<NcSelect
							v-model="activity_selected"
							:input-label="t('employees', 'Proyect')"
							:options="editActivities"
							class="fit"
							:open="false"
							:disabled="true" />
						<div class="time-selector">
							<div class="wrapper">
								<NcDateTimePicker
									v-model="time"
									class="date-picker"
									type="date"
									:disabled="!editable" />
							</div>
							<div class="estimatetime">
								<NcTextField
									required
									:value.sync="time_activity"
									type="number"
									:label="t('employees', 'Estimate time')"
									:disabled="!editable" />
							</div>
							<div class="radios">
								<NcCheckboxRadioSwitch
									v-model="type_time"
									:button-variant="true"
									value="minutos"
									:name="t('employees', 'Minutes')"
									type="radio"
									button-variant-grouped="horizontal"
									:disabled="!editable">
									{{ t('employees', 'Minutes') }}
								</NcCheckboxRadioSwitch>
								<NcCheckboxRadioSwitch
									v-model="type_time"
									:button-variant="true"
									value="horas"
									:name="t('employees', 'Hours')"
									type="radio"
									button-variant-grouped="horizontal"
									:disabled="!editable">
									{{ t('employees', 'Hours') }}
								</NcCheckboxRadioSwitch>
							</div>
						</div>
						<NcSelect
							v-model="listas_selected"
							:input-label="t('employees', 'Activity')"
							:options="editActivities"
							class="fit"
							:disabled="!editable" />
						<br>
						<NcTextArea
							required
							resize="vertical"
							:value.sync="description_activity"
							class="top"
							:label="t('employees', 'Description activity')"
							:disabled="!editable" />
						<div class="save top">
							<NcButton
								class=""
								:aria-label="t('employees', 'Edit Activity')"
								type="primary"
								:disabled="!editable"
								@click="modify()">
								{{ t('employees', 'Edit Activity') }}
							</NcButton>
						</div>
					</div>
				</form>
			</div>
		</NcModal>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import CheckboxBlankCircleOutline from 'vue-material-design-icons/CheckboxBlankCircleOutline.vue'
import {
	NcListItem,
	NcActionButton,
	NcDialog,
	NcModal,
	NcButton,
	NcSelect,
	NcDateTimePicker,
	NcTextField,
	NcCheckboxRadioSwitch,
	NcTextArea,
} from '@nextcloud/vue'
import DeleteAlert from 'vue-material-design-icons/DeleteAlert.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'

const MIN_EDITABLE = 40 // minutos

export default {
	name: 'ReportRow',
	components: {
		NcListItem,
		CheckboxBlankCircleOutline,
		NcActionButton,
		DeleteAlert,
		NcDialog,
		NcModal,
		NcButton,
		NcSelect,
		NcDateTimePicker,
		NcTextField,
		NcCheckboxRadioSwitch,
		NcTextArea,
		OpenInNew,
	},
	props: {
		source: { type: Object, required: true },
		index: { type: Number, required: false, default: null },
		Activity: { type: Array, required: false, default: () => [] },
		listas: { type: Array, required: false, default: () => [] },
	},
	data() {
		return {
			nowTick: Date.now(),
			timer: null,
			showDialog: false,
			ShowEdit: false,
			description_activity: '',
			type_time: 'minutos',
			time_activity: 0,
			time: new Date(),
			activity_selected: null,
			listas_selected: null,
			reportid: null,
			id_activity: null,
		}
	},
	computed: {
		buttons() {
			return [
				{
					label: this.t('employees', 'Cancelar'),
					callback: () => { this.lastResponse = 'Pressed "Cancel"' },
				},
				{
					label: this.t('employees', 'Eliminar'),
					type: 'primary',
					callback: () => { this.delete() },
				},
			]
		},
		// created_at viene type "2025-12-30 17:12:52"
		createdDate() {
			const s = this.source?.created_at || this.source?.createdAt || ''
			if (!s) return null
			// MySQL "YYYY-MM-DD HH:mm:ss" -> ISO simple "YYYY-MM-DDTHH:mm:ss"
			const d = new Date(String(s).replace(' ', 'T'))
			return isNaN(d.getTime()) ? null : d
		},
		minutosTranscurridos() {
			if (!this.createdDate) return null
			// usa nowTick para reactividad
			return (this.nowTick - this.createdDate.getTime()) / 60000
		},
		editable() {
			if (this.isAutomaticReport || this.isAbsenceReport) return false
			// editable = antes de 20 min
			if (this.minutosTranscurridos === null) return false
			return this.minutosTranscurridos < MIN_EDITABLE
		},
		isSupportReport() {
			return this.source?.source === 'soporte_ti'
		},
		isAbsenceReport() {
			return this.source?.type_work === 'ausencia'
				|| Number(this.source?.id_client) === 99999
				|| Number(this.source?.id_activity) === 99999
		},
		isAutomaticReport() {
			const origin = String(this.source?.source || '')
			return origin !== '' && !['manual', 'manual_interno'].includes(origin)
		},
		isInternalReport() {
			return this.source?.type_work === 'interno' || (this.source?.id_client == null && this.source?.type_work !== 'ausencia')
		},
		originLabel() {
			if (this.isSupportReport) return t('employees', 'Support TI')
			if (this.source?.source === 'manual_interno' || !this.source?.source) return t('employees', 'Manual internal report')
			return String(this.source.source)
		},
		editActivities() {
			const type = this.isInternalReport ? 'interno' : 'cliente'
			return this.listas.filter(activity => (activity.type_activity || 'cliente') === type)
		},
		indicatorColor() {
			return this.editable ? 'var(--color-error)' : 'var(--color-success)'
		},
		counterText() {
			const v = this.source?.recorded_time ?? this.source?.recordedTime ?? 0
			const mins = Number.isFinite(Number(v)) ? Math.trunc(Number(v)) : 0
			return `${mins} min`
		},
		detailsText() {
			return this.source?.date_recorded ?? this.source?.dateRecorded ?? ''
		},
		titleText() {
			return this.isInternalReport
				? `${t('employees', 'Internal work')} · ${this.source?.activityName || ''}`
				: (this.source?.clienteNombre || '')
		},
		subnameText() {
			if (!this.isInternalReport) return this.source?.activityName || ''
			const activity = this.listas.find(item => Number(item.id) === Number(this.source?.id_activity))
			const areas = (activity?.areas || []).map(area => area.name).filter(Boolean).join(', ')
			return areas ? `${t('employees', 'Specific areas')}: ${areas}` : t('employees', 'Entire company')
		},
	},
	mounted() {
		// Fuerza recalcular cada 30s para que cambie el color cuando se cumplan 20 min
		this.timer = setInterval(() => {
			this.nowTick = Date.now()
		}, 30 * 1000)
	},
	beforeDestroy() {
		if (this.timer) clearInterval(this.timer)
	},

	methods: {
		t, // exponer i18n a la plantilla
		 onKeyDown(e) {
			if (e.key === 'Escape') this.onEsc()
		},

		onEsc() {
			this.showDialog = false
		},
		handleRowClick() {
			if (!this.isAutomaticReport && !this.isAbsenceReport) this.edit()
		},
		viewSupport() {
			this.$router.push({ name: 'Inventory', query: { deviceId: String(this.source.id_team) } })
		},

		async delete() {
			try {
				await axios.post(generateUrl('/apps/employees/deleteReport'), {
					id: parseInt(this.reportid),
				}).then(
					() => {
						showSuccess(t('employees', 'Se ha eliminadon exitosamente'))
						this.$bus.emit('gethistorial')
						this.closeEdit()
					},
					(err) => { showError(this.backendError(err)) },
				)
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
			this.showDialog = false
		},
		edit() {
			this.activity_selected = this.source.clienteNombre
			this.listas_selected = this.source.activityName
			this.id_activity = this.source.id_activity
			this.description_activity = this.source.description
			this.time_activity = this.source.recorded_time
			this.type_time = 'minutos'
			this.time = new Date(this.source.date_recorded)
			this.ShowEdit = true
		},
		async modify() {
			try {
				await axios.post(generateUrl('/apps/employees/modificarReporte'), {
					id_report: parseInt(this.source.id),
					id_activity: this.listas_selected.id ?? this.id_activity,
					type_work: this.source.type_work || (this.isInternalReport ? 'interno' : 'cliente'),
					id_client: this.isInternalReport ? null : this.source.id_client,
					tiemporegistrado: Number(this.time_activity ?? 0),
					description: this.description_activity,
					type: this.type_time,
					fecharegistrada: this.time.toISOString().slice(0, 10),
				}).then(
					() => {
						showSuccess(t('employees', 'Datos actualizados correctamente'))
						this.$bus.emit('gethistorial')
						this.closeEdit()
					},
					(err) => { showError(this.backendError(err)) },
				)
			} catch (err) {
				showError(t('employees', 'Se ha producido una excepcion [03] [{error}]', { error: String(err) }))
			}
		},
		closeEdit() {
			this.ShowEdit = false
		},
		backendError(error) {
			return error?.response?.data?.ocs?.data?.message
				|| error?.response?.data?.message
				|| error?.message
				|| String(error)
		},
	},
}
</script>

<style scoped>
.row { padding: 1px 1px; border-bottom: 1px solid var(--color-border); }
.origin-badge,
.billable-badge {
	display: inline-block;
	margin-left: 6px;
	padding: 1px 6px;
	border-radius: 999px;
	background: var(--color-background-dark);
	font-size: 11px;
}
#emptycontent, .emptycontent { margin-top: 1vh; }
.center-screen {
	display: flex;
	justify-content: center;
	align-items: center;
	text-align: center;
	min-height: 100vh;
}
.center { margin: auto; width: 50%; padding: 10px; }
.container { padding-left: 20px; }
.board-title {
	margin-right: 10px;
	font-size: 25px;
	display: flex;
	align-items: center;
	font-weight: bold;
	margin-left: 20px;
}
.board-title .icon { margin-right: 8px; }
.main-content-card {
	margin-top: 10px;
	margin-left: 20px;
	margin-right: 20px;
}
.time-selector {
	display: flex;
	margin: .5rem 0;          /* margen arriba y abajo */
	align-self: center;
}

.radios {
	display: flex;
	margin-left: 7px;
	height: 35px;
	margin-top: 4px;
}

.estimatetime {
	display: flex;
}

.save {
	display: flex;
	margin-left: 10px;
	align-self: center;
}

.wrapper {
	display: flex;
	flex-direction: column;
}

.type-select {
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
}
.date-picker {
	margin-top: 3px;
	margin-right: 6px;
}
.fit {
	width: 100%;
}
.list {
  height: calc(100vh - 260px);
  overflow: auto;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  margin: 20px;
}

</style>
