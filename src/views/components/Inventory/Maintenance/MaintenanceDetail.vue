<template>
	<NcAppContent :name="t('employees', 'Maintenance detail')">
		<main class="page">
			<nav class="navigation">
				<NcButton @click="backToCampaign">
					{{ t('employees', 'Back to campaign') }}
				</NcButton>
				<NcButton @click="backToCalendar">
					{{ t('employees', 'Back to maintenance calendar') }}
				</NcButton>
			</nav>
			<NcNoteCard v-if="invalidId" type="error" :text="t('employees', 'The requested maintenance ID is invalid.')" />
			<div v-else-if="loading" class="state">
				<NcLoadingIcon :size="48" />
			</div>
			<NcNoteCard v-else-if="error" type="error" :text="error" />
			<template v-else-if="maintenance">
				<header>
					<div><h2>{{ deviceName }}</h2><p>{{ group.title || t('employees', 'Maintenance campaign') }}</p></div>
					<span class="status">{{ maintenanceStatusLabel(maintenance.status, t) }}</span>
				</header>

				<section class="card facts">
					<h3>{{ t('employees', 'Device information') }}</h3>
					<p><strong>{{ t('employees', 'Device') }}:</strong> {{ deviceName }}</p>
					<p><strong>{{ t('employees', 'Model') }}:</strong> {{ display(maintenance.model_name) }}</p>
					<p><strong>{{ t('employees', 'Serial number') }}:</strong> {{ display(maintenance.serial_number) }}</p>
					<p><strong>{{ t('employees', 'Custodian') }}:</strong> {{ display(maintenance.employee_name) }}</p>
					<p><strong>{{ t('employees', 'Department') }}:</strong> {{ display(maintenance.department_name || group.department_name) }}</p>
					<p><strong>{{ t('employees', 'Type') }}:</strong> {{ maintenanceTypeLabel(maintenance.type, t) }}</p>
				</section>

				<section class="card facts">
					<h3>{{ t('employees', 'Campaign period') }}</h3>
					<p><strong>{{ t('employees', 'Period start') }}:</strong> {{ formatCalendarDate(periodStart) }}</p>
					<p><strong>{{ t('employees', 'Period end') }}:</strong> {{ formatCalendarDate(periodEnd) }}</p>
					<p><strong>{{ t('employees', 'Default schedule') }}:</strong> {{ defaultSchedule }}</p>
				</section>

				<section class="card">
					<h3>{{ t('employees', 'Individual scheduling') }}</h3>
					<p v-if="!maintenance.date_scheduled">
						{{ t('employees', 'No specific day assigned') }}
					</p>
					<p v-else>
						{{ formatCalendarDate(maintenance.date_scheduled) }} · {{ individualSchedule }}
					</p>
					<form v-if="canSchedule" class="form-grid" @submit.prevent="saveSchedule">
						<label>{{ t('employees', 'Scheduled day') }}<input v-model="scheduleForm.scheduledDate"
							type="date"
							:min="periodStart"
							:max="periodEnd"
							required
							@input="scheduleDirty = true"></label>
						<label>{{ t('employees', 'Start time') }}<input v-model="scheduleForm.startTime" type="time" @input="scheduleDirty = true"></label>
						<label>{{ t('employees', 'End time') }}<input v-model="scheduleForm.endTime" type="time" @input="scheduleDirty = true"></label>
						<NcButton native-type="submit" :disabled="busy || !scheduleDirty">
							{{ t('employees', 'Save scheduling') }}
						</NcButton>
					</form>
				</section>

				<section class="card facts">
					<h3>{{ t('employees', 'Status and technician') }}</h3>
					<p><strong>{{ t('employees', 'Technician') }}:</strong> {{ display(maintenance.technician_name) }}</p>
					<p><strong>{{ t('employees', 'Actual start') }}:</strong> {{ displayDateTime(maintenance.date_start_actual) }}</p>
					<p><strong>{{ t('employees', 'Actual completion') }}:</strong> {{ displayDateTime(maintenance.date_end_actual) }}</p>
					<p><strong>{{ t('employees', 'Last update') }}:</strong> {{ displayDateTime(maintenance.date_update) }}</p>
					<label v-if="canAssignTechnician" class="full">{{ t('employees', 'Technician') }}
						<select v-model="technicianUid" :disabled="busy || techniciansLoading || Boolean(techniciansError)" @change="assignTechnician">
							<option value="">{{ technicianOptionLabel }}</option>
							<option v-for="item in technicians" :key="item.uid" :value="item.uid">{{ item.displayName }}</option>
						</select>
					</label>
					<NcNoteCard v-if="canAssignTechnician && techniciansError"
						class="full"
						type="error"
						:text="t('employees', 'Could not load technicians.')" />
					<NcNoteCard v-else-if="canAssignTechnician && !techniciansLoading && !technicians.length"
						class="full"
						type="warning"
						:text="t('employees', 'There are no users with inventory technician permission. You can leave this maintenance unassigned and configure permissions later.')" />
				</section>

				<section class="card">
					<h3>{{ t('employees', 'Checklist') }}</h3>
					<MaintenanceChecklist :items="checklist"
						:editable="canEditChecklist"
						:saving="savingChecklist"
						@dirty-change="checklistDirty = $event"
						@save="saveChecklist" />
				</section>

				<section class="card">
					<h3>{{ t('employees', 'Work performed') }}</h3>
					<form class="form-grid" @submit.prevent="saveWork">
						<label>{{ t('employees', 'Preliminary result') }}<textarea v-model="workForm.result" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('employees', 'Actions performed') }}<textarea v-model="workForm.actionsPerformed" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('employees', 'Incidents') }}<textarea v-model="workForm.incidents" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('employees', 'Spare parts') }}<textarea v-model="workForm.spare_parts" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('employees', 'Observations') }}<textarea v-model="workForm.observations" :readonly="!canSaveWork" @input="workDirty = true" /></label>
						<label>{{ t('employees', 'Suggested next date') }}<input v-model="workForm.nextDate"
							type="date"
							:readonly="!canSaveWork"
							@input="workDirty = true"></label>
						<div v-if="canSaveWork" class="full form-action">
							<span v-if="workDirty">{{ t('employees', 'Unsaved changes') }}</span><NcButton native-type="submit" :disabled="busy || !workDirty">
								{{ t('employees', 'Save progress') }}
							</NcButton>
						</div>
					</form>
				</section>

				<section class="card">
					<h3>{{ t('employees', 'Audit') }}</h3>
					<ul v-if="audit.length">
						<li v-for="(item, index) in audit" :key="item.id || index">
							{{ display(item.change_type || item.type) }} · {{ displayDateTime(item.date_creation || item.date) }}<span v-if="item.comment"> · {{ item.comment }}</span>
						</li>
					</ul>
					<p v-else>
						{{ t('employees', 'Audit information is not available for your access level.') }}
					</p>
				</section>

				<section v-if="hasActions" class="card actions">
					<h3>{{ t('employees', 'Actions') }}</h3>
					<NcButton v-if="canStart"
						type="primary"
						:disabled="busy"
						@click="startMaintenance">
						{{ t('employees', 'Start maintenance') }}
					</NcButton>
					<NcButton v-if="canComplete"
						type="primary"
						:disabled="busy"
						@click="completeMaintenance">
						{{ t('employees', 'Complete maintenance') }}
					</NcButton>
					<NcButton v-if="canReschedule" :disabled="busy" @click="openReschedule">
						{{ t('employees', 'Reschedule') }}
					</NcButton>
					<NcButton v-if="canMarkNotApplicable" :disabled="busy" @click="markNotApplicable">
						{{ t('employees', 'Mark as not applicable') }}
					</NcButton>
					<NcButton v-if="canCancel"
						type="error"
						:disabled="busy"
						@click="cancelMaintenance">
						{{ t('employees', 'Cancel maintenance') }}
					</NcButton>
				</section>

				<div v-if="showReschedule" class="modal-backdrop" @click.self="closeReschedule">
					<form class="modal" @submit.prevent="rescheduleMaintenance">
						<h3>{{ t('employees', 'Reschedule') }}</h3>
						<label>{{ t('employees', 'New date') }}<input v-model="rescheduleForm.scheduledDate"
							type="date"
							:min="periodStart"
							:max="periodEnd"
							required></label>
						<label>{{ t('employees', 'Start time') }}<input v-model="rescheduleForm.startTime" type="time"></label>
						<label>{{ t('employees', 'End time') }}<input v-model="rescheduleForm.endTime" type="time"></label>
						<label>{{ t('employees', 'Reason') }}<textarea v-model="rescheduleForm.reason" required /></label>
						<div class="actions">
							<NcButton @click="closeReschedule">
								{{ t('employees', 'Cancel') }}
							</NcButton><NcButton type="primary" native-type="submit" :disabled="busy">
								{{ t('employees', 'Reschedule') }}
							</NcButton>
						</div>
					</form>
				</div>
			</template>
		</main>
	</NcAppContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcAppContent, NcButton, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import permissionsMixin from '../../../../mixins/permissions.js'
import maintenanceService from '../../../../services/maintenanceService.js'
import { formatCalendarDate, formatOptionalTimeRange, isPositiveId, maintenanceCapabilities, maintenanceRecordCapabilities, maintenanceStatusLabel, maintenanceTypeLabel } from '../../../../utils/maintenanceFormatters.js'
import MaintenanceChecklist from './MaintenanceChecklist.vue'

export default {
	name: 'MaintenanceDetail',
	components: { NcAppContent, NcButton, NcLoadingIcon, NcNoteCard, MaintenanceChecklist },
	mixins: [permissionsMixin],
	inject: { Settings: { default: () => ({}) } },
	beforeRouteLeave(to, from, next) { next(!this.hasUnsavedChanges || window.confirm(t('employees', 'You have unsaved changes. Leave this page?'))) },
	data() {
		return {
			maintenance: null,
			group: {},
			checklist: [],
			audit: [],
			technicians: [],
			techniciansLoading: false,
			techniciansError: '',
			loading: false,
			busy: false,
			savingChecklist: false,
			error: '',
			scheduleDirty: false,
			checklistDirty: false,
			workDirty: false,
			showReschedule: false,
			technicianUid: '',
			scheduleForm: { scheduledDate: '', startTime: '', endTime: '' },
			rescheduleForm: { scheduledDate: '', startTime: '', endTime: '', reason: '' },
			workForm: { result: '', actionsPerformed: '', incidents: '', spare_parts: '', observations: '', nextDate: '' },
		}
	},
	computed: {
		invalidId() { return !isPositiveId(this.$route.params.id) },
		maintenanceId() { return Number(this.$route.params.id) },
		capabilities() { return maintenanceCapabilities(this.permissions, this.Settings) },
		actionCapabilities() { return maintenanceRecordCapabilities(this.maintenance, this.capabilities, this.permissions?.uid) },
		canOperate() { return this.actionCapabilities.canOperate },
		status() { return this.maintenance?.status || '' },
		canSchedule() { return this.actionCapabilities.canSchedule },
		canStart() { return this.actionCapabilities.canStart },
		canEditChecklist() { return this.actionCapabilities.canEditChecklist },
		canSaveWork() { return this.actionCapabilities.canSaveWork },
		canComplete() { return this.actionCapabilities.canComplete },
		canReschedule() { return this.actionCapabilities.canReschedule },
		canMarkNotApplicable() { return this.actionCapabilities.canMarkNotApplicable },
		canCancel() { return this.actionCapabilities.canCancel },
		canAssignTechnician() { return this.actionCapabilities.canAssignTechnician },
		hasActions() { return this.canStart || this.canComplete || this.canReschedule || this.canMarkNotApplicable || this.canCancel },
		hasUnsavedChanges() { return this.scheduleDirty || this.checklistDirty || this.workDirty },
		deviceName() { return this.maintenance?.team_identifier || this.maintenance?.team_name || '—' },
		periodStart() { return this.group?.date_start || this.group?.periodStart || this.group?.date_scheduled || '' },
		periodEnd() { return this.group?.date_end || this.group?.periodEnd || this.group?.date_scheduled || '' },
		defaultSchedule() { return formatOptionalTimeRange(this.group?.time_start || this.group?.startTime, this.group?.time_end || this.group?.endTime, t('employees', 'No schedule defined')) },
		individualSchedule() { return formatOptionalTimeRange(this.maintenance?.time_start_scheduled, this.maintenance?.time_end_scheduled, t('employees', 'No schedule defined')) },
		technicianOptionLabel() { if (this.techniciansLoading) return t('employees', 'Loading technicians…'); if (this.techniciansError) return t('employees', 'Could not load technicians.'); return t('employees', 'Unassigned') },
	},
	mounted() { window.addEventListener('beforeunload', this.beforeUnload); if (!this.invalidId) this.load() },
	beforeDestroy() { window.removeEventListener('beforeunload', this.beforeUnload) },
	methods: {
		t,
		formatCalendarDate,
		maintenanceStatusLabel,
		maintenanceTypeLabel,
		display(value) { return value === null || value === undefined || value === '' || typeof value === 'object' ? '—' : String(value) },
		displayDateTime(value) { if (!value || Number.isNaN(Date.parse(String(value).replace(' ', 'T')))) return '—'; return String(value) },
		beforeUnload(event) { if (!this.hasUnsavedChanges) return; event.preventDefault(); event.returnValue = '' },
		backToCampaign() { const id = this.$route.query.groupId || this.maintenance?.id_group; this.$router.push(isPositiveId(id) ? { name: 'MaintenanceGroup', params: { id }, query: this.returnQuery() } : { name: 'Maintenance' }) },
		backToCalendar() { this.$router.push({ name: 'Maintenance' }) },
		returnQuery() { const query = { ...this.$route.query }; delete query.groupId; return query },
		setForms() {
			const m = this.maintenance
			this.scheduleForm = { scheduledDate: m.date_scheduled || '', startTime: String(m.time_start_scheduled || '').slice(0, 5), endTime: String(m.time_end_scheduled || '').slice(0, 5) }
			this.workForm = { result: m.result || '', actionsPerformed: m.actions_performed || '', incidents: m.incidents || '', spare_parts: m.spare_parts || '', observations: m.observations || '', nextDate: m.next_date || '' }
			this.technicianUid = m.technician_uid || ''
			this.scheduleDirty = this.checklistDirty = this.workDirty = false
		},
		async load() {
			this.loading = true; this.error = ''
			try {
				const data = await maintenanceService.getMaintenance(this.maintenanceId)
				this.maintenance = data.maintenance || {}; this.group = data.group || {}; this.checklist = data.checklist || []; this.audit = data.audit || []; this.setForms()
				if (this.capabilities.canAdministerMaintenance && !this.technicians.length) await this.loadTechnicians()
			} catch (error) { this.error = this.errorMessage(error) } finally { this.loading = false }
		},
		async loadTechnicians() { this.techniciansLoading = true; this.techniciansError = ''; try { this.technicians = await maintenanceService.getTechnicians() } catch (error) { this.technicians = []; this.techniciansError = t('employees', 'Could not load technicians.') } finally { this.techniciansLoading = false } },
		errorMessage(error) {
			if (error.status === 403) return t('employees', 'Access denied.')
			if (error.status === 404) return t('employees', 'The maintenance record does not exist.')
			if (error.status === 500) return t('employees', 'An internal error occurred.')
			return error.message
		},
		validateSchedule(form, requireReason = false) {
			if (!form.scheduledDate || form.scheduledDate < this.periodStart || form.scheduledDate > this.periodEnd) return t('employees', 'The scheduled date must be within the campaign period.')
			if (Boolean(form.startTime) !== Boolean(form.endTime) || (form.startTime && form.endTime <= form.startTime)) return t('employees', 'The end time must be later than the start time.')
			if (requireReason && !form.reason.trim()) return t('employees', 'Reason is required.')
			return ''
		},
		async perform(operation, success) {
			if (this.busy) return; this.busy = true
			try { await operation(); showSuccess(success); await this.load(); this.$bus?.emit('maintenance-updated', { groupId: this.maintenance.id_group }) } catch (error) { if (error.status === 409) { showError(t('employees', 'The maintenance changed while you were editing it. The data will be reloaded.')); await this.load() } else showError(this.errorMessage(error)) } finally { this.busy = false }
		},
		async saveSchedule() { const validation = this.validateSchedule(this.scheduleForm); if (validation) return showError(validation); await this.perform(() => maintenanceService.scheduleMaintenance(this.maintenanceId, this.scheduleForm), t('employees', 'Scheduling saved.')) },
		async startMaintenance() { if (!window.confirm(t('employees', 'Do you want to start maintenance on this device?'))) return; await this.perform(() => maintenanceService.startMaintenance(this.maintenanceId), t('employees', 'Maintenance started.')) },
		async saveChecklist(items) {
			if (this.savingChecklist) return; this.savingChecklist = true
			try { await maintenanceService.updateChecklist(this.maintenanceId, items); showSuccess(t('employees', 'Checklist saved.')); await this.load() } catch (error) { if (error.status === 409) { showError(t('employees', 'The maintenance changed while you were editing it. The data will be reloaded.')); await this.load() } else showError(this.errorMessage(error)) } finally { this.savingChecklist = false }
		},
		workPayload() { const payload = {}; for (const [key, value] of Object.entries(this.workForm)) if (String(value || '').trim()) payload[key] = String(value).trim(); return payload },
		async saveWork() { await this.perform(() => maintenanceService.updateWork(this.maintenanceId, this.workPayload()), t('employees', 'Progress saved.')) },
		async completeMaintenance() {
			if (this.checklistDirty) return showError(t('employees', 'Save checklist changes before completing.'))
			if (!this.workForm.result.trim()) return showError(t('employees', 'Result is required.'))
			if (!this.workForm.actionsPerformed.trim()) return showError(t('employees', 'Actions performed are required.'))
			const pending = this.checklist.filter(item => item.result === 'pending').length
			const attention = this.checklist.filter(item => item.result === 'attention').length
			if (pending) return showError(t('employees', 'Complete every checklist item before finishing.'))
			if (this.checklist.some(item => item.result === 'attention' && !String(item.observation || '').trim())) return showError(t('employees', 'Attention observation is required'))
			const summary = `${this.deviceName}\n${t('employees', 'Result')}: ${this.workForm.result}\n${t('employees', 'Checklist completed')}: ${this.checklist.length - pending}/${this.checklist.length}\n${t('employees', 'Items requiring attention')}: ${attention}\n${t('employees', 'Next date')}: ${this.workForm.nextDate || '—'}\n\n${t('employees', 'This maintenance will become read-only after completion.')}`
			if (!window.confirm(summary)) return
			await this.perform(() => maintenanceService.completeMaintenance(this.maintenanceId, this.workPayload()), t('employees', 'Maintenance completed.'))
		},
		openReschedule() { this.rescheduleForm = { scheduledDate: this.maintenance.date_scheduled || '', startTime: String(this.maintenance.time_start_scheduled || '').slice(0, 5), endTime: String(this.maintenance.time_end_scheduled || '').slice(0, 5), reason: '' }; this.showReschedule = true },
		closeReschedule() { if (this.rescheduleForm.reason && !window.confirm(t('employees', 'Discard these changes?'))) return; this.showReschedule = false },
		async rescheduleMaintenance() { const validation = this.validateSchedule(this.rescheduleForm, true); if (validation) return showError(validation); await this.perform(() => maintenanceService.rescheduleMaintenance(this.maintenanceId, this.rescheduleForm), t('employees', 'Maintenance rescheduled.')); this.showReschedule = false },
		async markNotApplicable() { const reason = window.prompt(t('employees', 'Enter the reason.')); if (!reason?.trim() || !window.confirm(t('employees', 'Mark this maintenance as not applicable?'))) return; await this.perform(() => maintenanceService.markNotApplicable(this.maintenanceId, reason.trim()), t('employees', 'Maintenance marked as not applicable.')) },
		async cancelMaintenance() { const reason = window.prompt(t('employees', 'Enter the cancellation reason.')); if (!reason?.trim() || !window.confirm(t('employees', 'Cancel this maintenance?'))) return; await this.perform(() => maintenanceService.cancelMaintenance(this.maintenanceId, reason.trim()), t('employees', 'Maintenance cancelled.')) },
		async assignTechnician() { if (!window.confirm(t('employees', 'Change the assigned technician?'))) { this.technicianUid = this.maintenance.technician_uid || ''; return } await this.perform(() => maintenanceService.assignTechnician(this.maintenanceId, this.technicianUid || null), t('employees', 'Technician updated.')) },
	},
}
</script>

<style scoped lang="scss">
.page { padding: 24px; width: 100%; min-width: 0; }
.navigation, .actions, .form-action { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
header { display: flex; justify-content: space-between; align-items: start; margin-top: 18px; gap: 12px; } header h2 { margin: 0; }
.status { padding: 6px 12px; border-radius: 16px; background: var(--color-background-dark); }
.card { margin-top: 16px; padding: 18px; border: 1px solid var(--color-border); border-radius: var(--border-radius-large); background: var(--color-main-background); }
.card h3 { grid-column: 1 / -1; margin-top: 0; }
.facts, .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
.facts p { margin: 0; }.full { grid-column: 1 / -1; }
label { display: flex; flex-direction: column; gap: 5px; }
input, select, textarea { box-sizing: border-box; width: 100%; min-height: 38px; padding: 8px; border: 1px solid var(--color-border-maxcontrast); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); }
textarea { min-height: 90px; resize: vertical; }.form-action { justify-content: flex-end; }.state { text-align: center; padding: 40px; } ul { padding-left: 22px; }
.modal-backdrop { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 20px; background: rgba(0, 0, 0, .5); }
.modal { width: min(520px, 100%); max-height: 90vh; overflow: auto; padding: 24px; border-radius: var(--border-radius-large); background: var(--color-main-background); box-shadow: 0 8px 30px rgba(0, 0, 0, .25); }
.modal label { margin-bottom: 12px; }.modal .actions { justify-content: flex-end; }
</style>
