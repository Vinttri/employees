<template>
	<NcAppContent :name="t('employees', 'AI import')">
		<main class="ai-import-page">
			<header class="page-header">
				<div>
					<p class="eyebrow">
						{{ t('employees', 'Nextcloud Assistant') }}
					</p>
					<h2>{{ t('employees', 'AI-assisted import') }}</h2>
					<p class="page-copy">
						{{ t('employees', 'Upload mixed business data. AI determines what every row represents, then you review where it will be imported.') }}
					</p>
				</div>
			</header>

			<NcNoteCard type="info">
				{{ t('employees', 'Nothing is saved automatically. The configured Nextcloud AI provider proposes destinations and values; you confirm every row before import.') }}
			</NcNoteCard>

			<section class="card capability-card">
				<div class="section-heading">
					<div>
						<p class="eyebrow">
							{{ t('employees', 'Automatic recognition') }}
						</p>
						<h3>{{ t('employees', 'What can be imported') }}</h3>
					</div>
					<p class="section-copy">
						{{ t('employees', 'One file may contain several kinds of records. AI routes each row separately.') }}
					</p>
				</div>
				<div class="capability-grid">
					<article v-for="group in capabilityGroups" :key="group.id" class="capability-group">
						<h4>{{ group.label }}</h4>
						<ul>
							<li v-for="target in group.targets" :key="target.id">
								<strong>{{ target.label }}</strong>
								<span>{{ target.description }}</span>
							</li>
						</ul>
					</article>
				</div>
			</section>

			<section class="card source-card">
				<div class="section-heading section-heading--compact">
					<div>
						<h3>{{ t('employees', 'Upload or paste data') }}</h3>
						<p class="section-copy">
							{{ t('employees', 'CSV, Markdown, TXT, tables, lists and free-form text are supported.') }}
						</p>
					</div>
				</div>
				<label class="file-field">
					<span>{{ t('employees', 'File') }}</span>
					<input ref="file"
						type="file"
						accept=".csv,.md,.txt,text/csv,text/markdown,text/plain"
						@change="selectFile">
					<small>{{ file ? file.name : t('employees', 'Choose CSV, MD or TXT, or paste text below') }}</small>
				</label>
				<label class="text-field">
					<span>{{ t('employees', 'Source text') }}</span>
					<textarea v-model="sourceText"
						:disabled="Boolean(file)"
						rows="9"
						:placeholder="t('employees', 'For example: a staff table, a list of positions, payroll inputs and absence notes…')" />
				</label>
				<div class="actions">
					<NcButton v-if="file" @click="clearFile">
						{{ t('employees', 'Remove file') }}
					</NcButton>
					<NcButton type="primary" :disabled="busy || !canStart" @click="startImport">
						<NcLoadingIcon v-if="busy" :size="18" />
						{{ busy ? t('employees', 'Analysing') : t('employees', 'Analyse and route rows') }}
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
					<NcLoadingIcon :size="30" /><span>{{ t('employees', 'AI is identifying records and choosing destinations…') }}</span>
				</div>
				<template v-else-if="rows.length">
					<section class="routing-summary" aria-labelledby="routing-summary-title">
						<div>
							<p class="eyebrow">
								{{ t('employees', 'Routing summary') }}
							</p>
							<h4 id="routing-summary-title">
								{{ t('employees', 'Where rows will be imported') }}
							</h4>
						</div>
						<div class="route-list">
							<span v-for="route in routingSummary" :key="route.id" class="route-chip">
								<strong>{{ route.count }}</strong>{{ route.label }}
							</span>
						</div>
					</section>

					<div class="review-list">
						<article v-for="(row, index) in rows"
							:key="index"
							class="review-row"
							:class="`review-row--${row._status}`">
							<header class="row-header">
								<div>
									<span class="row-number">{{ t('employees', 'Source row {number}', { number: row._source_row || row._row || index + 1 }) }}</span>
									<strong>{{ targetLabel(row._target) }}</strong>
								</div>
								<span class="row-state"><span class="status-dot" />{{ statusLabel(row._status) }}</span>
							</header>
							<p v-if="row._source_excerpt" class="source-excerpt">
								{{ row._source_excerpt }}
							</p>
							<NcSelect :value="targetOption(row._target)"
								:options="targetOptions"
								:clearable="false"
								:input-label="t('employees', 'Proposed destination')"
								@input="changeTarget(index, $event)" />
							<div v-if="fieldsForRow(row).length" class="field-grid">
								<label v-for="field in fieldsForRow(row)" :key="field">
									<span>{{ fieldLabel(field) }}</span>
									<select v-if="fieldOptions(row, field)"
										v-model="row[field]"
										:aria-label="fieldLabel(field)"
										@change="markChanged(row)">
										<option value="">
											—
										</option>
										<option v-for="option in fieldOptions(row, field)" :key="String(option.value)" :value="option.value">
											{{ option.label }}
										</option>
									</select>
									<input v-else
										v-model="row[field]"
										:type="inputType(row, field)"
										:step="inputType(row, field) === 'number' ? 'any' : undefined"
										:aria-label="fieldLabel(field)"
										@input="markChanged(row)">
								</label>
							</div>
							<div v-if="row._messages && row._messages.length" class="messages">
								<strong>{{ t('employees', 'Check this row') }}</strong>
								<ul>
									<li v-for="message in row._messages" :key="message">
										{{ message }}
									</li>
								</ul>
							</div>
						</article>
					</div>
				</template>
				<NcEmptyContent v-else-if="batch.status === 'review'" :name="t('employees', 'No rows were extracted')" />

				<div v-if="batch.status === 'review'" class="actions review-actions">
					<p class="review-copy">
						{{ applyExplanation }}
					</p>
					<NcButton :disabled="busy" @click="reviewRows">
						{{ t('employees', 'Revalidate destinations and fields') }}
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

const TARGET_GROUPS = [
	{ id: 'organization', targets: ['employees', 'departments', 'positions', 'teams'] },
	{ id: 'time', targets: ['time_entries', 'absences'] },
	{ id: 'payroll', targets: ['payroll_plans', 'payroll_profiles', 'payroll_rules', 'payroll_employee_rules', 'payroll_inputs', 'payroll_payments'] },
	{ id: 'operations', targets: ['clients', 'activities', 'costs', 'purchases', 'inventory', 'maintenance'] },
]

export default {
	name: 'AiImport',
	components: { NcAppContent, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard, NcSelect },
	data() { return { targets: {}, sourceText: '', file: null, batch: null, rows: [], busy: false, error: '', pollTimer: null } },
	computed: {
		targetOptions() {
			return TARGET_GROUPS.flatMap(group => group.targets)
				.filter(id => Boolean(this.targets[id]))
				.map(id => ({ id, label: this.targetLabel(id) }))
		},
		capabilityGroups() {
			return TARGET_GROUPS.map(group => ({
				id: group.id,
				label: this.groupLabel(group.id),
				targets: group.targets.filter(id => Boolean(this.targets[id])).map(id => ({
					id,
					label: this.targetLabel(id),
					description: this.targetDescription(id),
				})),
			})).filter(group => group.targets.length)
		},
		canStart() { return this.targetOptions.length > 0 && Boolean(this.file || this.sourceText.trim()) },
		canApply() { return this.rows.length > 0 && Number(this.batch?.review_count || 0) === 0 && Number(this.batch?.invalid_count || 0) === 0 },
		isWaiting() { return ['scheduled', 'running'].includes(this.batch?.status) },
		routingSummary() {
			const counts = new Map()
			this.rows.forEach(row => {
				const id = row._target && this.targets[row._target] ? row._target : 'unresolved'
				counts.set(id, (counts.get(id) || 0) + 1)
			})
			return [...counts.entries()].map(([id, count]) => ({
				id,
				count,
				label: id === 'unresolved' ? t('employees', 'Needs destination') : this.targetLabel(id),
			}))
		},
		applyExplanation() {
			if (this.canApply) {
				return t('employees', '{count} reviewed rows are ready. They will be written only after you confirm.', { count: this.rows.length })
			}
			return t('employees', 'Correct destinations and highlighted fields, then revalidate before applying.')
		},
	},
	async mounted() {
		try { this.targets = await aiImportService.targets() } catch (error) { this.handleError(error) }
	},
	beforeDestroy() { clearTimeout(this.pollTimer) },
	methods: {
		t,
		groupLabel(id) { return ({ organization: t('employees', 'Organization and people'), time: t('employees', 'Time and absences'), payroll: t('employees', 'Payroll and payments'), operations: t('employees', 'Business and IT') })[id] || id },
		targetLabel(id) { return ({ employees: t('employees', 'Employees'), departments: t('employees', 'Departments'), positions: t('employees', 'Positions'), teams: t('employees', 'Teams'), time_entries: t('employees', 'Time entries'), absences: t('employees', 'Absences'), payroll_plans: t('employees', 'Payroll plans'), payroll_profiles: t('employees', 'Tax profiles'), payroll_rules: t('employees', 'Tax rules'), payroll_employee_rules: t('employees', 'Individual rule overrides'), payroll_inputs: t('employees', 'Payroll inputs'), payroll_payments: t('employees', 'Payroll payments'), clients: t('employees', 'Customers'), activities: t('employees', 'Activities'), costs: t('employees', 'Employee costs'), purchases: t('employees', 'Purchase requests'), inventory: t('employees', 'Inventory'), maintenance: t('employees', 'Maintenance') })[id] || t('employees', 'Needs destination') },
		targetDescription(id) {
			return ({
				employees: t('employees', 'People linked to Nextcloud users, including department, position, team and salary.'),
				departments: t('employees', 'Departments and their parent structure.'),
				positions: t('employees', 'Job titles and organizational levels.'),
				teams: t('employees', 'Teams and their leaders.'),
				time_entries: t('employees', 'Worked hours, activity, customer and description.'),
				absences: t('employees', 'Vacation, sick leave and other absence periods.'),
				payroll_plans: t('employees', 'Monthly, hourly and mixed compensation plans.'),
				payroll_profiles: t('employees', 'Reusable tax and deduction profiles.'),
				payroll_rules: t('employees', 'Tax, deduction and earning rules.'),
				payroll_employee_rules: t('employees', 'Personal tax discounts and rule overrides.'),
				payroll_inputs: t('employees', 'Hours, bonuses, commissions and one-off adjustments.'),
				payroll_payments: t('employees', 'Recorded salary payments and references.'),
				clients: t('employees', 'Customers, contacts and parent companies.'),
				activities: t('employees', 'Internal and customer activities.'),
				costs: t('employees', 'Employee cost rates effective from a date.'),
				purchases: t('employees', 'Purchase requests with amount and requester.'),
				inventory: t('employees', 'Assets, serial numbers, models and assignees.'),
				maintenance: t('employees', 'Planned maintenance for inventory assets.'),
			})[id] || ''
		},
		targetOption(id) { return this.targetOptions.find(option => option.id === id) || null },
		fieldsForRow(row) { return Object.keys(this.targets[row._target] || {}) },
		fieldLabel(field) {
			const labels = {
				employee: t('employees', 'Employee'), id_user: t('employees', 'Nextcloud user ID'), number_employee: t('employees', 'Employee number'), email_contact: t('employees', 'Email'), hire_date: t('employees', 'Hire date'), department: t('employees', 'Department'), position: t('employees', 'Position'), team: t('employees', 'Team'), manager_uid: t('employees', 'Manager'), base_salary: t('employees', 'Base salary'), currency: t('employees', 'Currency'), name: t('employees', 'Name'), parent: t('employees', 'Parent'), level: t('employees', 'Level'), leader: t('employees', 'Leader'), date: t('employees', 'Date'), hours: t('employees', 'Hours'), activity: t('employees', 'Activity'), client: t('employees', 'Customer'), description: t('employees', 'Description'), type: t('employees', 'Type'), date_from: t('employees', 'Start date'), date_until: t('employees', 'End date'), notes: t('employees', 'Notes'), status: t('employees', 'Status'), payment_mode: t('employees', 'Payment mode'), profile: t('employees', 'Profile'), rule_code: t('employees', 'Code'), category: t('employees', 'Category'), calculation_type: t('employees', 'Calculation'), value: t('employees', 'Value'), taxable: t('employees', 'Taxable'), override_value: t('employees', 'Personal value'), enabled: t('employees', 'Rule enabled'), hourly_rate: t('employees', 'Hourly rate'), cost_rate: t('employees', 'Cost rate'), standard_month_hours: t('employees', 'Standard monthly hours'), overtime_rate: t('employees', 'Overtime multiplier'), effective_from: t('employees', 'Effective from'), effective_until: t('employees', 'Effective until'), period_id: t('employees', 'Period'), input_type: t('employees', 'Input type'), code: t('employees', 'Code'), quantity: t('employees', 'Quantity'), rate: t('employees', 'Rate'), amount: t('employees', 'Amount'), payslip_id: t('employees', 'Payslip'), payment_date: t('employees', 'Payment date'), method: t('employees', 'Method'), reference: t('employees', 'Reference'), legal_name: t('employees', 'Legal name'), email: t('employees', 'Email'), phone: t('employees', 'Phone'), location: t('employees', 'Location'), project_leader: t('employees', 'Project leader'), details: t('employees', 'Details'), estimated_hours: t('employees', 'Estimated hours'), billable: t('employees', 'Billable'), requester: t('employees', 'Requester'), title: t('employees', 'Title'), asset_name: t('employees', 'Asset name'), serial_number: t('employees', 'Serial number'), model: t('employees', 'Model'), asset: t('employees', 'Asset'), scheduled_date: t('employees', 'Scheduled date'), assignee: t('employees', 'Assignee'),
			}
			return labels[field] || field.replaceAll('_', ' ').replace(/^./, value => value.toUpperCase())
		},
		statusLabel(status) { return ({ scheduled: t('employees', 'Scheduled'), running: t('employees', 'Running'), review: t('employees', 'Ready for review'), applied: t('employees', 'Applied'), failed: t('employees', 'Failed'), ready: t('employees', 'Ready'), invalid: t('employees', 'Invalid') })[status] || status || t('employees', 'Unknown') },
		enumLabel(value) { return ({ client: t('employees', 'Client'), internal: t('employees', 'Internal'), pending: t('employees', 'Pending'), approved: t('employees', 'Approved'), monthly: t('employees', 'Monthly'), hourly: t('employees', 'Hourly'), monthly_plus_hours: t('employees', 'Monthly plus hours'), fixed_period: t('employees', 'Fixed period'), commission: t('employees', 'Commission'), piecework: t('employees', 'Piecework'), earning: t('employees', 'Earning'), deduction: t('employees', 'Deduction'), fixed: t('employees', 'Fixed amount'), percent_base: t('employees', 'Percent of base'), percent_gross: t('employees', 'Percent of gross'), per_hour: t('employees', 'Per hour'), per_unit: t('employees', 'Per unit'), overtime_hours: t('employees', 'Overtime hours'), units: t('employees', 'Units'), bonus: t('employees', 'Bonus'), reimbursement: t('employees', 'Reimbursement'), one_off: t('employees', 'One-off payment'), tax: t('employees', 'Tax'), adjustment_earning: t('employees', 'Earning adjustment'), adjustment_deduction: t('employees', 'Deduction adjustment'), bank_transfer: t('employees', 'Bank transfer'), cash: t('employees', 'Cash'), card: t('employees', 'Card'), other: t('employees', 'Other') })[value] || this.fieldLabel(value) },
		fieldOptions(row, field) {
			const definition = this.targets[row._target]?.[field]
			if (definition?.type === 'boolean') {
				return [{ value: true, label: t('employees', 'Yes') }, { value: false, label: t('employees', 'No') }]
			}
			return definition?.values?.map(value => ({ value, label: this.enumLabel(value) })) || null
		},
		inputType(row, field) {
			const type = this.targets[row._target]?.[field]?.type
			return type === 'date' ? 'date' : ['decimal', 'integer'].includes(type) ? 'number' : 'text'
		},
		selectFile(event) { this.file = event.target.files?.[0] || null },
		clearFile() { this.file = null; if (this.$refs.file) this.$refs.file.value = '' },
		async startImport() {
			this.busy = true; this.error = ''; this.batch = null; this.rows = []
			try { this.batch = await aiImportService.create('auto', { text: this.sourceText, file: this.file }); this.rows = this.batch.rows || []; this.schedulePoll() } catch (error) { this.handleError(error) } finally { this.busy = false }
		},
		schedulePoll() { clearTimeout(this.pollTimer); if (this.isWaiting) this.pollTimer = setTimeout(this.refresh, 1500) },
		async refresh() {
			if (!this.batch?.id) return
			try { this.batch = await aiImportService.get(this.batch.id); this.rows = this.batch.rows || []; if (this.batch.status === 'failed') this.error = this.batch.error_message || t('employees', 'AI import failed.'); this.schedulePoll() } catch (error) { this.handleError(error) }
		},
		changeTarget(index, option) {
			const previous = this.rows[index]
			const target = option?.id || null
			const next = {
				_target: target,
				_source_row: previous._source_row,
				_source_excerpt: previous._source_excerpt,
				_row: previous._row,
				_status: 'review',
				_messages: [t('employees', 'Destination changed. Revalidate this row before applying.')],
			}
			Object.keys(this.targets[target] || {}).forEach(field => { next[field] = previous[field] ?? null })
			this.$set(this.rows, index, next)
			this.updateCounts()
		},
		markChanged(row) {
			if (row._status !== 'review') {
				row._status = 'review'
				row._messages = [t('employees', 'Values changed. Revalidate this row before applying.')]
				this.updateCounts()
			}
		},
		updateCounts() {
			const counts = { ready: 0, review: 0, invalid: 0 }
			this.rows.forEach(row => { counts[row._status] = (counts[row._status] || 0) + 1 })
			this.batch = { ...this.batch, ready_count: counts.ready, review_count: counts.review, invalid_count: counts.invalid }
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
.ai-import-page {
	width: 100%;
	min-width: 0;
	padding: 24px;
}

.page-header { margin-bottom: 16px; }
.page-header h2,
.review-header h3,
.section-heading h3,
.routing-summary h4 { margin: 0; }
.page-copy,
.section-copy,
.review-copy { color: var(--color-text-maxcontrast); }
.section-copy { margin: 0; }

.eyebrow {
	margin: 0 0 4px;
	color: var(--color-primary-element);
	font-size: .78rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .05em;
}

.card {
	margin-top: 16px;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.section-heading {
	display: flex;
	justify-content: space-between;
	gap: 24px;
	align-items: flex-start;
}

.section-heading > .section-copy { max-width: 42rem; }
.section-heading--compact { display: block; }
.capability-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px;
	margin-top: 16px;
}

.capability-group {
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.capability-group h4 { margin: 0 0 10px; }
.capability-group ul {
	display: grid;
	gap: 8px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.capability-group li {
	display: grid;
	grid-template-columns: minmax(8rem, .45fr) 1fr;
	gap: 12px;
}

.capability-group span,
.file-field small { color: var(--color-text-maxcontrast); }
.file-field,
.text-field,
.field-grid label {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.file-field { margin-top: 16px; }
.file-field span,
.text-field span,
.field-grid label > span { font-weight: 600; }
textarea,
input,
.field-grid select {
	box-sizing: border-box;
	width: 100%;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

textarea {
	margin-top: 14px;
	padding: 10px;
	resize: vertical;
}

.actions {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 8px;
	margin-top: 16px;
}

.review-header {
	display: flex;
	justify-content: space-between;
	gap: 16px;
	align-items: center;
}

.counts,
.route-list {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.count,
.route-chip,
.row-state {
	padding: 4px 9px;
	border-radius: 999px;
	background: var(--color-background-hover);
}

.count--ready { color: var(--color-success-text); }
.count--review { color: var(--color-warning-text); }
.count--invalid { color: var(--color-error-text); }
.waiting {
	display: flex;
	justify-content: center;
	align-items: center;
	gap: 10px;
	padding: 48px;
}

.routing-summary {
	display: flex;
	justify-content: space-between;
	gap: 20px;
	margin-top: 18px;
	padding: 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.route-list {
	align-items: center;
	justify-content: flex-end;
}

.route-chip { display: flex; gap: 6px; }
.review-list {
	display: grid;
	gap: 12px;
	margin-top: 16px;
}

.review-row {
	padding: 16px;
	border: 1px solid var(--color-border);
	border-inline-start: 4px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
}

.review-row--ready { border-inline-start-color: var(--color-success); }
.review-row--review { border-inline-start-color: var(--color-warning); }
.review-row--invalid { border-inline-start-color: var(--color-error); }
.row-header {
	display: flex;
	justify-content: space-between;
	gap: 16px;
	align-items: center;
	margin-bottom: 12px;
}

.row-header > div {
	display: flex;
	gap: 10px;
	align-items: baseline;
}

.row-number,
.source-excerpt { color: var(--color-text-maxcontrast); }
.row-state {
	display: flex;
	gap: 6px;
	align-items: center;
}

.status-dot {
	display: inline-block;
	width: 9px;
	height: 9px;
	border-radius: 50%;
	background: var(--color-text-maxcontrast);
}

.review-row--ready .status-dot { background: var(--color-success); }
.review-row--review .status-dot { background: var(--color-warning); }
.review-row--invalid .status-dot { background: var(--color-error); }
.source-excerpt {
	margin: 0 0 12px;
	padding: 10px;
	border-inline-start: 2px solid var(--color-border-maxcontrast);
	white-space: pre-wrap;
}

.field-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin-top: 14px;
}

.field-grid input,
.field-grid select {
	min-height: 36px;
	padding: 7px;
}

.messages {
	margin-top: 12px;
	padding: 10px;
	border-radius: var(--border-radius);
	background: var(--color-background-hover);
}

.messages ul {
	margin: 6px 0 0;
	padding-inline-start: 20px;
}

.review-actions {
	position: sticky;
	bottom: 0;
	padding: 12px;
	border-top: 1px solid var(--color-border);
	background: var(--color-main-background);
}

.review-copy { margin: 0 auto 0 0; }

@media (max-width: 900px) {
	.capability-grid { grid-template-columns: 1fr; }
	.field-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.section-heading,
	.routing-summary { flex-direction: column; }
	.route-list { justify-content: flex-start; }
}

@media (max-width: 600px) {
	.ai-import-page { padding: 16px; }
	.field-grid { grid-template-columns: 1fr; }
	.capability-group li {
		grid-template-columns: 1fr;
		gap: 2px;
	}
	.review-header,
	.row-header,
	.review-actions {
		align-items: flex-start;
		flex-direction: column;
	}
	.review-actions { position: static; }
}
</style>
