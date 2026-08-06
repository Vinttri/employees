<template>
	<NcAppContent :name="t('employees', 'AI import')">
		<main class="ai-import-page">
			<header class="page-header">
				<div>
					<p class="eyebrow">
						{{ t('employees', 'Nextcloud Assistant') }}
					</p>
					<h2>{{ t('employees', 'AI-assisted import') }}</h2>
					<p>{{ t('employees', 'Paste text or upload CSV, Markdown or TXT. Review every extracted row before anything is saved.') }}</p>
				</div>
			</header>

			<NcNoteCard type="info">
				{{ t('employees', 'The configured Nextcloud AI provider extracts data. Imported text is treated as untrusted and never applied automatically.') }}
			</NcNoteCard>

			<section class="card source-card">
				<div class="form-grid">
					<NcSelect v-model="selectedTarget"
						:options="targetOptions"
						:clearable="false"
						:input-label="t('employees', 'Import destination')" />
					<label class="file-field">
						<span>{{ t('employees', 'File') }}</span>
						<input ref="file"
							type="file"
							accept=".csv,.md,.txt,text/csv,text/markdown,text/plain"
							@change="selectFile">
						<small>{{ file ? file.name : t('employees', 'CSV, MD or TXT, or paste text below') }}</small>
					</label>
				</div>
				<label class="text-field">
					<span>{{ t('employees', 'Source text') }}</span>
					<textarea v-model="sourceText"
						:disabled="Boolean(file)"
						rows="9"
						:placeholder="t('employees', 'Paste a table, message, list or free-form text…')" />
				</label>
				<div class="actions">
					<NcButton v-if="file" @click="clearFile">
						{{ t('employees', 'Remove file') }}
					</NcButton>
					<NcButton type="primary" :disabled="busy || !canStart" @click="startImport">
						<NcLoadingIcon v-if="busy" :size="18" />
						{{ busy ? t('employees', 'Analysing') : t('employees', 'Analyse with AI') }}
					</NcButton>
				</div>
			</section>

			<NcNoteCard v-if="error" type="error" :text="error" />

			<section v-if="batch" class="card review-card">
				<div class="review-header">
					<div>
						<p class="eyebrow">
							{{ t('employees', 'Human review') }}
						</p>
						<h3>{{ statusLabel(batch.status) }}</h3>
					</div>
					<div class="counts" aria-live="polite">
						<span class="count count--ready">{{ t('employees', 'Ready') }}: {{ batch.ready_count || 0 }}</span>
						<span class="count count--review">{{ t('employees', 'Review') }}: {{ batch.review_count || 0 }}</span>
						<span class="count count--invalid">{{ t('employees', 'Invalid') }}: {{ batch.invalid_count || 0 }}</span>
					</div>
				</div>

				<div v-if="isWaiting" class="waiting">
					<NcLoadingIcon :size="30" /><span>{{ t('employees', 'Waiting for the Nextcloud AI provider…') }}</span>
				</div>
				<div v-else-if="rows.length" class="table-scroll">
					<table>
						<thead>
							<tr>
								<th>{{ t('employees', 'State') }}</th><th v-for="field in fields" :key="field">
									{{ fieldLabel(field) }}
								</th><th>{{ t('employees', 'Messages') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="(row, index) in rows" :key="index" :class="`row--${row._status}`">
								<td><span class="status-dot" :title="statusLabel(row._status)" />{{ statusLabel(row._status) }}</td>
								<td v-for="field in fields" :key="field">
									<select v-if="fieldOptions(field)" v-model="row[field]" :aria-label="fieldLabel(field)">
										<option value="">
											—
										</option>
										<option v-for="option in fieldOptions(field)" :key="String(option.value)" :value="option.value">
											{{ option.label }}
										</option>
									</select>
									<input v-else
										v-model="row[field]"
										:type="inputType(field)"
										:step="inputType(field) === 'number' ? 'any' : undefined"
										:aria-label="fieldLabel(field)">
								</td>
								<td class="messages">
									{{ (row._messages || []).join(' ') || '—' }}
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<NcEmptyContent v-else-if="batch.status === 'review'" :name="t('employees', 'No rows were extracted')" />

				<div v-if="batch.status === 'review'" class="actions">
					<NcButton :disabled="busy" @click="reviewRows">
						{{ t('employees', 'Revalidate') }}
					</NcButton>
					<NcButton type="primary" :disabled="busy || !canApply" @click="applyImport">
						{{ t('employees', 'Apply reviewed rows') }}
					</NcButton>
				</div>
			</section>
		</main>
	</NcAppContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcAppContent, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import aiImportService from '../../../services/aiImportService.js'

export default {
	name: 'AiImport',
	components: { NcAppContent, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard, NcSelect },
	data() { return { targets: {}, selectedTarget: null, sourceText: '', file: null, batch: null, rows: [], busy: false, error: '', pollTimer: null } },
	computed: {
		targetOptions() { return Object.keys(this.targets).map(id => ({ id, label: this.targetLabel(id) })) },
		fields() { return this.selectedTarget ? Object.keys(this.targets[this.selectedTarget.id] || {}) : [] },
		canStart() { return this.selectedTarget && (this.file || this.sourceText.trim()) },
		canApply() { return this.rows.length > 0 && Number(this.batch?.review_count || 0) === 0 && Number(this.batch?.invalid_count || 0) === 0 },
		isWaiting() { return ['scheduled', 'running'].includes(this.batch?.status) },
	},
	async mounted() {
		try {
			this.targets = await aiImportService.targets()
			const requested = String(this.$route.query.target || '')
			this.selectedTarget = this.targetOptions.find(item => item.id === requested) || this.targetOptions[0] || null
		} catch (error) { this.handleError(error) }
	},
	beforeDestroy() { clearTimeout(this.pollTimer) },
	methods: {
		t,
		targetLabel(id) { return ({ employees: t('employees', 'Employees'), departments: t('employees', 'Departments'), positions: t('employees', 'Positions'), teams: t('employees', 'Teams'), time_entries: t('employees', 'Time entries'), absences: t('employees', 'Absences'), payroll_plans: t('employees', 'Payroll plans'), payroll_profiles: t('employees', 'Tax profiles'), payroll_rules: t('employees', 'Tax rules'), payroll_employee_rules: t('employees', 'Individual rule overrides'), payroll_inputs: t('employees', 'Payroll inputs'), payroll_payments: t('employees', 'Payroll payments'), clients: t('employees', 'Customers'), activities: t('employees', 'Activities'), costs: t('employees', 'Employee costs'), purchases: t('employees', 'Purchase requests'), inventory: t('employees', 'Inventory'), maintenance: t('employees', 'Maintenance') })[id] || id },
		fieldLabel(field) {
			const labels = {
				employee: t('employees', 'Employee'),
				id_user: t('employees', 'Nextcloud user ID'),
				number_employee: t('employees', 'Employee number'),
				email_contact: t('employees', 'Email'),
				hire_date: t('employees', 'Hire date'),
				department: t('employees', 'Department'),
				position: t('employees', 'Position'),
				team: t('employees', 'Team'),
				manager_uid: t('employees', 'Manager'),
				base_salary: t('employees', 'Base salary'),
				currency: t('employees', 'Currency'),
				name: t('employees', 'Name'),
				parent: t('employees', 'Parent'),
				level: t('employees', 'Level'),
				leader: t('employees', 'Leader'),
				date: t('employees', 'Date'),
				hours: t('employees', 'Hours'),
				activity: t('employees', 'Activity'),
				client: t('employees', 'Customer'),
				description: t('employees', 'Description'),
				type: t('employees', 'Type'),
				date_from: t('employees', 'Start date'),
				date_until: t('employees', 'End date'),
				notes: t('employees', 'Notes'),
				status: t('employees', 'Status'),
				payment_mode: t('employees', 'Payment mode'),
				profile: t('employees', 'Profile'),
				rule_code: t('employees', 'Code'),
				calculation_type: t('employees', 'Calculation'),
				override_value: t('employees', 'Personal value'),
				enabled: t('employees', 'Rule enabled'),
				hourly_rate: t('employees', 'Hourly rate'),
				cost_rate: t('employees', 'Cost rate'),
				standard_month_hours: t('employees', 'Standard monthly hours'),
				overtime_rate: t('employees', 'Overtime multiplier'),
				pending: t('employees', 'Pending'),
				approved: t('employees', 'Approved'),
				monthly: t('employees', 'Monthly'),
				hourly: t('employees', 'Hourly'),
				monthly_plus_hours: t('employees', 'Monthly plus hours'),
				fixed_period: t('employees', 'Fixed period'),
				commission: t('employees', 'Commission'),
				piecework: t('employees', 'Piecework'),
				earning: t('employees', 'Earning'),
				deduction: t('employees', 'Deduction'),
				fixed: t('employees', 'Fixed amount'),
				percent_base: t('employees', 'Percent of base'),
				percent_gross: t('employees', 'Percent of gross'),
				per_hour: t('employees', 'Per hour'),
				per_unit: t('employees', 'Per unit'),
				overtime_hours: t('employees', 'Overtime hours'),
				units: t('employees', 'Units'),
				bonus: t('employees', 'Bonus'),
				reimbursement: t('employees', 'Reimbursement'),
				one_off: t('employees', 'One-off payment'),
				tax: t('employees', 'Tax'),
				adjustment_earning: t('employees', 'Earning adjustment'),
				adjustment_deduction: t('employees', 'Deduction adjustment'),
				bank_transfer: t('employees', 'Bank transfer'),
				cash: t('employees', 'Cash'),
				card: t('employees', 'Card'),
				other: t('employees', 'Other'),
				effective_from: t('employees', 'Effective from'),
				effective_until: t('employees', 'Effective until'),
				period_id: t('employees', 'Period'),
				input_type: t('employees', 'Input type'),
				code: t('employees', 'Code'),
				quantity: t('employees', 'Quantity'),
				rate: t('employees', 'Rate'),
				amount: t('employees', 'Amount'),
				payslip_id: t('employees', 'Payslip'),
				payment_date: t('employees', 'Payment date'),
				method: t('employees', 'Method'),
				reference: t('employees', 'Reference'),
				legal_name: t('employees', 'Legal name'),
				email: t('employees', 'Email'),
				phone: t('employees', 'Phone'),
				location: t('employees', 'Location'),
				project_leader: t('employees', 'Project leader'),
				details: t('employees', 'Details'),
				estimated_hours: t('employees', 'Estimated hours'),
				billable: t('employees', 'Billable'),
				requester: t('employees', 'Requester'),
				title: t('employees', 'Title'),
				asset_name: t('employees', 'Asset name'),
				serial_number: t('employees', 'Serial number'),
				model: t('employees', 'Model'),
				asset: t('employees', 'Asset'),
				scheduled_date: t('employees', 'Scheduled date'),
				assignee: t('employees', 'Assignee'),
			}
			return labels[field] || field.replaceAll('_', ' ').replace(/^./, value => value.toUpperCase())
		},
		statusLabel(status) { return ({ scheduled: t('employees', 'Scheduled'), running: t('employees', 'Running'), review: t('employees', 'Ready for review'), applied: t('employees', 'Applied'), failed: t('employees', 'Failed'), ready: t('employees', 'Ready'), invalid: t('employees', 'Invalid') })[status] || status || t('employees', 'Unknown') },
		enumLabel(value) { return ({ client: t('employees', 'Client'), internal: t('employees', 'Internal') })[value] || this.fieldLabel(value) },
		fieldOptions(field) {
			const definition = this.targets[this.selectedTarget?.id]?.[field]
			if (definition?.type === 'boolean') {
				return [{ value: true, label: t('employees', 'Yes') }, { value: false, label: t('employees', 'No') }]
			}
			return definition?.values?.map(value => ({ value, label: this.enumLabel(value) })) || null
		},
		inputType(field) { return /(date|_from|_until)$/.test(field) ? 'date' : /(amount|salary|rate|hours|quantity|units|period_id|payslip_id)$/.test(field) ? 'number' : 'text' },
		selectFile(event) { this.file = event.target.files?.[0] || null },
		clearFile() { this.file = null; if (this.$refs.file) this.$refs.file.value = '' },
		async startImport() {
			this.busy = true; this.error = ''; this.batch = null; this.rows = []
			try { this.batch = await aiImportService.create(this.selectedTarget.id, { text: this.sourceText, file: this.file }); this.rows = this.batch.rows || []; this.schedulePoll() } catch (error) { this.handleError(error) } finally { this.busy = false }
		},
		schedulePoll() { clearTimeout(this.pollTimer); if (this.isWaiting) this.pollTimer = setTimeout(this.refresh, 1500) },
		async refresh() {
			if (!this.batch?.id) return
			try { this.batch = await aiImportService.get(this.batch.id); this.rows = this.batch.rows || []; if (this.batch.status === 'failed') this.error = this.batch.error_message || t('employees', 'AI import failed.'); this.schedulePoll() } catch (error) { this.handleError(error) }
		},
		async reviewRows() {
			if (!this.batch?.id) return
			this.busy = true; this.error = ''
			try { this.batch = await aiImportService.review(this.batch.id, this.rows); this.rows = this.batch.rows || [] } catch (error) { this.handleError(error) } finally { this.busy = false }
		},
		async applyImport() {
			this.busy = true; this.error = ''
			try { const result = await aiImportService.apply(this.batch.id, this.rows); this.batch = result.batch || await aiImportService.get(this.batch.id); showSuccess(t('employees', '{count} rows were imported.', { count: result.applied || 0 })) } catch (error) { this.handleError(error) } finally { this.busy = false }
		},
		handleError(error) { this.error = error?.response?.data?.error?.message || error?.message || t('employees', 'AI import failed.'); showError(this.error) },
	},
}
</script>

<style scoped lang="scss">
.ai-import-page{width:100%;min-width:0;padding:24px;}.page-header{display:flex;justify-content:space-between;gap:16px;margin-bottom:16px}.page-header h2,.review-header h3{margin:0}.page-header p:not(.eyebrow){color:var(--color-text-maxcontrast)}.eyebrow{margin:0 0 4px;color:var(--color-primary-element);font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}.card{margin-top:16px;padding:18px;border:1px solid var(--color-border);border-radius:var(--border-radius-large);background:var(--color-main-background)}.form-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr);gap:16px}.file-field,.text-field{display:flex;flex-direction:column;gap:6px}.file-field span,.text-field span{font-weight:600}.file-field small{color:var(--color-text-maxcontrast)}textarea,input,td select{box-sizing:border-box;width:100%;border:1px solid var(--color-border-maxcontrast);border-radius:var(--border-radius);background:var(--color-main-background);color:var(--color-main-text)}textarea{margin-top:14px;padding:10px;resize:vertical}.actions{display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-top:16px}.review-header{display:flex;justify-content:space-between;gap:16px;align-items:center}.counts{display:flex;gap:8px;flex-wrap:wrap}.count{padding:4px 9px;border-radius:999px;background:var(--color-background-hover)}.count--ready{color:var(--color-success-text)}.count--review{color:var(--color-warning-text)}.count--invalid{color:var(--color-error-text)}.waiting{display:flex;justify-content:center;align-items:center;gap:10px;padding:48px}.table-scroll{overflow:auto;margin-top:16px}table{width:100%;border-collapse:collapse;min-width:900px}th,td{padding:8px;border-bottom:1px solid var(--color-border);text-align:left;vertical-align:top}th{position:sticky;top:0;background:var(--color-main-background);z-index:1}td input,td select{min-width:130px;padding:7px}.messages{min-width:220px;color:var(--color-text-maxcontrast)}.status-dot{display:inline-block;width:9px;height:9px;margin-right:6px;border-radius:50%;background:var(--color-text-maxcontrast)}.row--ready .status-dot{background:var(--color-success)}.row--review .status-dot{background:var(--color-warning)}.row--invalid .status-dot{background:var(--color-error)}@media(max-width:700px){.ai-import-page{padding:16px}.form-grid{grid-template-columns:1fr}.review-header{align-items:flex-start;flex-direction:column}}
</style>
