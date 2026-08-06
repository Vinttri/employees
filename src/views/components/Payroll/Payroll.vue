<template>
	<NcAppContent :name="t('employees', 'Payroll')">
		<main class="payroll-page">
			<header class="page-header">
				<div>
					<p class="eyebrow">
						{{ t('employees', 'Compensation') }}
					</p><h2>{{ t('employees', 'Payroll') }}</h2><p>{{ t('employees', 'Flexible salary plans, monthly inputs, individual tax rules and payment tracking.') }}</p>
				</div>
				<div class="header-actions">
					<NcButton v-if="canManage" type="primary" @click="openDialog('period')">
						{{ t('employees', 'New period') }}
					</NcButton>
				</div>
			</header>

			<NcNoteCard v-if="error" type="error" :text="error" />
			<section class="toolbar card">
				<NcSelect v-model="selectedPeriodOption"
					:options="periodOptions"
					:clearable="false"
					:input-label="t('employees', 'Payroll period')"
					@input="periodChanged" />
				<div v-if="period" class="period-state">
					<span>{{ statusLabel(period.status) }}</span><strong>{{ formatDate(period.date_from) }} – {{ formatDate(period.date_until) }}</strong>
				</div>
				<div v-if="canManage && period" class="toolbar-actions">
					<NcButton :disabled="busy || !editablePeriod" @click="openDialog('input')">
						{{ t('employees', 'Add input') }}
					</NcButton>
					<NcButton :disabled="busy || !editablePeriod" @click="calculate">
						{{ t('employees', 'Calculate') }}
					</NcButton>
					<NcButton v-if="canApprove"
						type="primary"
						:disabled="busy || period.status !== 'calculated'"
						@click="approve">
						{{ t('employees', 'Approve') }}
					</NcButton>
				</div>
			</section>
			<nav class="workflow card" :aria-label="t('employees', 'Payroll workflow')">
				<div v-for="(step, index) in workflowSteps"
					:key="step.title"
					class="workflow-step"
					:class="{ current: step.current, complete: step.complete }">
					<span>{{ index + 1 }}</span><div><strong>{{ step.title }}</strong><small>{{ step.description }}</small></div>
				</div>
			</nav>

			<div v-if="loading" class="loading">
				<NcLoadingIcon :size="36" /><span>{{ t('employees', 'Loading payroll') }}</span>
			</div>
			<template v-else>
				<section class="metrics">
					<div class="metric card">
						<span>{{ t('employees', 'Gross') }}</span><strong>{{ money(totals.gross, period?.currency) }}</strong>
					</div>
					<div class="metric card">
						<span>{{ t('employees', 'Deductions') }}</span><strong>{{ money(totals.deductions, period?.currency) }}</strong>
					</div>
					<div class="metric card">
						<span>{{ t('employees', 'Net') }}</span><strong>{{ money(totals.net, period?.currency) }}</strong>
					</div>
					<div class="metric card">
						<span>{{ t('employees', 'Paid') }}</span><strong>{{ money(totals.paid, period?.currency) }}</strong>
					</div>
				</section>

				<section v-if="canManage" class="card table-card setup-card">
					<div class="section-header">
						<div>
							<p class="eyebrow">
								{{ t('employees', 'Step 1') }}
							</p><h3>{{ t('employees', 'Employee payroll setup') }}</h3><p>{{ t('employees', 'Set the salary formula, tax profile and IBAN once. Locked plans create a new effective version.') }}</p>
						</div>
					</div>
					<div class="table-scroll">
						<table>
							<thead><tr><th>{{ t('employees', 'Employee') }}</th><th>{{ t('employees', 'Payment mode') }}</th><th>{{ t('employees', 'Salary / rate') }}</th><th>{{ t('employees', 'Tax profile') }}</th><th>{{ t('employees', 'IBAN') }}</th><th>{{ t('employees', 'Readiness') }}</th><th /></tr></thead>
							<tbody>
								<tr v-for="employee in employees" :key="employee.id_employees">
									<td><strong>{{ employee.display_name || employee.id_user }}</strong><small>{{ employee.id_user }}</small></td>
									<td>{{ employeePlan(employee)?.payment_mode ? modeLabel(employeePlan(employee).payment_mode) : '—' }}</td>
									<td>{{ employeePlan(employee) ? planSummary(employeePlan(employee)) : '—' }}</td>
									<td>{{ employeeProfile(employee) || '—' }}</td>
									<td>{{ maskedIban(employee.number_account) }}</td>
									<td><span class="status-pill" :class="{ warning: !employeeReady(employee) }">{{ employeeReady(employee) ? t('employees', 'Ready') : t('employees', 'Needs setup') }}</span></td>
									<td>
										<NcButton @click="openEmployeeSetup(employee)">
											{{ t('employees', 'Configure') }}
										</NcButton>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</section>

				<section v-if="canManage && period" class="card table-card">
					<div class="section-header">
						<div>
							<p class="eyebrow">
								{{ t('employees', 'Step 2') }}
							</p><h3>{{ t('employees', 'Hours and adjustments') }}</h3><p>{{ t('employees', 'Time reports are used automatically unless manual hours are entered for the month.') }}</p>
						</div><NcButton :disabled="busy || !editablePeriod" @click="openDialog('input')">
							{{ t('employees', 'Add input') }}
						</NcButton>
					</div>
					<div v-if="inputs.length" class="table-scroll">
						<table>
							<thead><tr><th>{{ t('employees', 'Employee') }}</th><th>{{ t('employees', 'Input type') }}</th><th>{{ t('employees', 'Quantity') }}</th><th>{{ t('employees', 'Rate') }}</th><th>{{ t('employees', 'Amount') }}</th><th>{{ t('employees', 'Source') }}</th><th /></tr></thead><tbody>
								<tr v-for="input in inputs" :key="input.id">
									<td>{{ input.display_name || input.employee_uid }}</td><td>{{ fieldLabel(input.input_type) }}</td><td>{{ input.quantity }}</td><td>{{ input.rate }}</td><td>{{ money(input.amount, input.currency) }}</td><td>{{ fieldLabel(input.source_type) }}</td><td>
										<div class="button-row">
											<NcButton :disabled="busy || !editablePeriod" @click="openInput(input)">
												{{ t('employees', 'Edit') }}
											</NcButton><NcButton :disabled="busy || !editablePeriod" @click="removeInput(input)">
												{{ t('employees', 'Delete') }}
											</NcButton>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<NcEmptyContent v-else :name="t('employees', 'No monthly inputs')" />
				</section>

				<section class="card table-card">
					<div class="section-header">
						<div>
							<p class="eyebrow">
								{{ t('employees', 'Step 3') }}
							</p><h3>{{ t('employees', 'Review and approve') }}</h3><p>{{ t('employees', 'Approved results are immutable; corrections are entered as a new adjustment.') }}</p>
						</div>
					</div>
					<div v-if="payslips.length" class="table-scroll">
						<table>
							<thead><tr><th>{{ t('employees', 'Employee') }}</th><th>{{ t('employees', 'Gross') }}</th><th>{{ t('employees', 'Deductions') }}</th><th>{{ t('employees', 'Net') }}</th><th>{{ t('employees', 'Paid') }}</th><th>{{ t('employees', 'State') }}</th><th /></tr></thead><tbody>
								<tr v-for="slip in payslips" :key="slip.id">
									<td><strong>{{ slip.display_name || slip.employee_uid }}</strong><small>{{ slip.employee_uid }}</small></td><td>{{ money(slip.gross_amount, slip.currency) }}</td><td>{{ money(slip.deduction_amount, slip.currency) }}</td><td>{{ money(slip.net_amount, slip.currency) }}</td><td>{{ money(slip.paid_amount, slip.currency) }}</td><td><span class="status-pill">{{ statusLabel(slip.status) }}</span></td><td>
										<NcButton v-if="canPay && ['approved','partially_paid'].includes(slip.status)" @click="openPayment(slip)">
											{{ t('employees', 'Record payment') }}
										</NcButton>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<NcEmptyContent v-else :name="t('employees', 'No payslips in this period')" :description="t('employees', 'Add monthly inputs and calculate the period.')" />
				</section>

				<section v-if="period" class="card payroll-files">
					<div class="files-sidebar">
						<p class="eyebrow">
							{{ t('employees', 'Payroll archive') }}
						</p><h3>{{ t('employees', 'Salary calculations by month') }}</h3>
						<button v-for="item in overview.periods || []"
							:key="item.id"
							type="button"
							:class="{ active: Number(item.id) === Number(selectedPeriod) }"
							@click="load(Number(item.id))">
							<span class="folder-icon">▰</span><span><strong>{{ monthLabel(item.date_from) }}</strong><small>{{ statusLabel(item.status) }}</small></span>
						</button>
					</div>
					<div class="files-content">
						<div class="section-header">
							<div>
								<p class="eyebrow">
									{{ t('employees', 'Step 4') }}
								</p><h3>{{ monthLabel(period.date_from) }}</h3><p>{{ period.package_path || t('employees', 'Files are generated automatically after approval.') }}</p>
							</div>
							<NcButton v-if="canManage" @click="openBankSettings">
								{{ t('employees', 'Bank settings') }}
							</NcButton>
						</div>
						<NcNoteCard v-if="period.package_error" type="warning" :text="period.package_error" />
						<div class="file-grid">
							<a v-if="canExport && approvedPeriod" :href="exportUrl" class="file-card"><span class="file-kind">CSV</span><span><strong>{{ t('employees', 'Payroll register') }}</strong><small>{{ t('employees', 'Excel-compatible UTF-8 file') }}</small></span></a>
							<a v-if="canExport && approvedPeriod" :href="sepaUrl" class="file-card"><span class="file-kind">XML</span><span><strong>{{ t('employees', 'SEPA bank transfer') }}</strong><small>pain.001.001.09</small></span></a>
							<a v-for="slip in payslips"
								:key="`pdf-${slip.id}`"
								:href="payslipUrl(slip.id)"
								class="file-card"><span class="file-kind">PDF</span><span><strong>{{ slip.display_name || slip.id_user }}</strong><small>{{ t('employees', 'Payslip') }}</small></span></a>
						</div>
						<div v-if="approvedPeriod && canExport" class="actions">
							<NcButton :disabled="busy" @click="publishPackage">
								{{ t('employees', 'Regenerate files in Team Folder') }}
							</NcButton>
						</div>
					</div>
				</section>

				<details v-if="canManage" class="advanced-settings card">
					<summary>{{ t('employees', 'Advanced payroll settings') }}</summary>
					<div class="management-grid">
						<div class="card management-card">
							<div><h3>{{ t('employees', 'Salary plans') }}</h3><p>{{ t('employees', 'Monthly, hourly, mixed, period, commission or piecework.') }}</p></div><NcButton @click="openDialog('plan')">
								{{ t('employees', 'Create plan') }}
							</NcButton>
						</div>
						<div class="card management-card">
							<div><h3>{{ t('employees', 'Tax and deduction profiles') }}</h3><p>{{ t('employees', 'Reusable manual rules with individual effective overrides.') }}</p></div><div class="button-row">
								<NcButton @click="openDialog('profile')">
									{{ t('employees', 'New profile') }}
								</NcButton><NcButton :disabled="!profiles.length" @click="openDialog('rule')">
									{{ t('employees', 'Add rule') }}
								</NcButton>
							</div>
						</div>
						<div class="card management-card">
							<div><h3>{{ t('employees', 'Personal tax adjustments') }}</h3><p>{{ t('employees', 'Change or disable one tax rule for a specific employee and effective period.') }}</p></div><NcButton :disabled="!profiles.length" @click="openDialog('override')">
								{{ t('employees', 'Individual rule') }}
							</NcButton>
						</div>
					</div>

					<section v-if="canManage && plans.length" class="card table-card">
						<div class="section-header">
							<div><h3>{{ t('employees', 'Active compensation plans') }}</h3><p>{{ t('employees', 'The latest effective plan is used for each employee.') }}</p></div>
						</div><div class="table-scroll">
							<table>
								<thead><tr><th>{{ t('employees', 'Employee') }}</th><th>{{ t('employees', 'Mode') }}</th><th>{{ t('employees', 'Base salary') }}</th><th>{{ t('employees', 'Hourly rate') }}</th><th>{{ t('employees', 'Cost rate') }}</th><th>{{ t('employees', 'Effective from') }}</th><th /></tr></thead><tbody>
									<tr v-for="plan in plans" :key="plan.id">
										<td>{{ plan.display_name || plan.employee_uid }}</td><td>{{ modeLabel(plan.payment_mode) }}</td><td>{{ money(plan.base_salary, plan.currency) }}</td><td>{{ money(plan.hourly_rate, plan.currency) }}</td><td>{{ money(plan.cost_rate, plan.currency) }}</td><td>{{ formatDate(plan.effective_from) }}</td><td>
											<NcButton @click="openPlan(plan)">
												{{ t('employees', 'Edit') }}
											</NcButton>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</section>

					<section v-if="canManage && profiles.length" class="card table-card">
						<div class="section-header">
							<div><h3>{{ t('employees', 'Tax and deduction profiles') }}</h3><p>{{ t('employees', 'Reusable manual rules with individual effective overrides.') }}</p></div>
						</div><div class="table-scroll">
							<table>
								<thead><tr><th>{{ t('employees', 'Profile') }}</th><th>{{ t('employees', 'Rule') }}</th><th>{{ t('employees', 'Category') }}</th><th>{{ t('employees', 'Calculation') }}</th><th>{{ t('employees', 'Value') }}</th><th>{{ t('employees', 'State') }}</th><th /></tr></thead>
								<tbody v-for="profile in profiles" :key="profile.id">
									<tr class="profile-row">
										<td><strong>{{ profile.name }}</strong></td><td colspan="4">
											{{ profile.description || '—' }}
										</td><td>{{ isTrue(profile.active) ? t('employees', 'Active') : t('employees', 'Inactive') }}</td><td>
											<NcButton @click="openProfile(profile)">
												{{ t('employees', 'Edit') }}
											</NcButton>
										</td>
									</tr>
									<tr v-for="rule in profile.rules || []" :key="rule.id">
										<td /><td>{{ rule.name }}</td><td>{{ fieldLabel(rule.category) }}</td><td>{{ fieldLabel(rule.calculation_type) }}</td><td>{{ rule.value }}</td><td>{{ isTrue(rule.active) ? t('employees', 'Active') : t('employees', 'Inactive') }}</td><td>
											<NcButton @click="openRule(rule, profile)">
												{{ t('employees', 'Edit') }}
											</NcButton>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</section>
				</details>
			</template>

			<NcModal v-if="dialog"
				size="large"
				:name="dialogTitle"
				@close="dialog = null">
				<form class="dialog-form" @submit.prevent="submitDialog">
					<div>
						<p class="eyebrow">
							{{ t('employees', 'Payroll') }}
						</p><h2>{{ dialogTitle }}</h2><p>{{ dialogDescription }}</p>
					</div>
					<div class="form-grid">
						<label v-for="field in dialogFields" :key="field.key" :class="{ wide: field.wide }"><span>{{ field.label }}</span><select v-if="field.options"
							v-model="form[field.key]"
							:required="field.required"
							:disabled="field.disabled"><option value="">—</option><option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option></select><textarea v-else-if="field.multiline" v-model="form[field.key]" rows="3" /><input v-else
								v-model="form[field.key]"
								:type="field.type || 'text'"
								:step="field.step"
								:required="field.required"
								:disabled="field.disabled"
								:min="field.min"></label>
					</div>
					<div class="actions">
						<NcButton @click="dialog = null">
							{{ t('employees', 'Cancel') }}
						</NcButton><NcButton native-type="submit" type="primary" :disabled="busy">
							{{ t('employees', 'Save') }}
						</NcButton>
					</div>
				</form>
			</NcModal>
		</main>
	</NcAppContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcAppContent, NcButton, NcEmptyContent, NcLoadingIcon, NcModal, NcNoteCard, NcSelect } from '@nextcloud/vue'
import permissionsMixin from '../../../mixins/permissions.js'
import payrollService from '../../../services/payrollService.js'
import { nextcloudLocale } from '../../../utils/nextcloudLocale.js'

const iso = date => date.toISOString().slice(0, 10)
const monthRange = () => { const now = new Date(); return { from: iso(new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), 1))), until: iso(new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + 1, 0))) } }

export default {
	name: 'Payroll',
	components: { NcAppContent, NcButton, NcEmptyContent, NcLoadingIcon, NcModal, NcNoteCard, NcSelect },
	mixins: [permissionsMixin],
	data() { return { loading: true, busy: false, error: '', overview: {}, selectedPeriodOption: null, dialog: null, form: {} } },
	computed: {
		canManage() { return this.canSee('payroll.manage') },
		canApprove() { return this.canSee('payroll.approve') },
		canPay() { return this.canSee('payroll.pay') },
		canExport() { return this.canSee('payroll.export') },
		approvedPeriod() { return ['approved', 'paid'].includes(this.period?.status) },
		periodOptions() { return (this.overview.periods || []).map(item => ({ id: Number(item.id), label: `${item.name} · ${this.statusLabel(item.status)}` })) },
		selectedPeriod() { return this.selectedPeriodOption?.id || this.overview.selected_period?.id || null },
		period() { return this.overview.selected_period || null },
		payslips() { return this.overview.payslips || [] },
		inputs() { return this.overview.inputs || [] },
		plans() { return this.overview.plans || [] },
		profiles() { return this.overview.profiles || [] },
		profileAssignments() { return this.overview.profile_assignments || [] },
		employeeRuleAssignments() { return this.overview.employee_rule_assignments || [] },
		employees() { return this.overview.employees || [] },
		totals() { return this.overview.totals || {} },
		editablePeriod() { return ['draft', 'calculated'].includes(this.period?.status) },
		exportUrl() { return this.selectedPeriod ? payrollService.exportUrl(this.selectedPeriod) : '#' },
		sepaUrl() { return this.selectedPeriod ? payrollService.sepaUrl(this.selectedPeriod) : '#' },
		paymentExportUrl() { return this.selectedPeriod ? payrollService.paymentExportUrl(this.selectedPeriod) : '#' },
		workflowSteps() {
			const status = this.period?.status || 'draft'
			return [
				{ title: t('employees', 'Employee setup'), description: t('employees', 'Salary, rates, taxes and IBAN'), complete: this.employees.length > 0 && this.employees.every(this.employeeReady), current: false },
				{ title: t('employees', 'Hours and adjustments'), description: t('employees', 'Automatic reports or manual values'), complete: status !== 'draft', current: status === 'draft' },
				{ title: t('employees', 'Review and approve'), description: t('employees', 'Gross, deductions and net pay'), complete: ['approved', 'paid'].includes(status), current: status === 'calculated' },
				{ title: t('employees', 'Bank and documents'), description: t('employees', 'CSV, SEPA and payslips'), complete: this.period?.package_status === 'ready', current: ['approved', 'paid'].includes(status) },
			]
		},
		dialogTitle() { return t('employees', ({ period: 'Create payroll period', setup: 'Employee payroll setup', bankSettings: 'Bank settings', plan: 'Create salary plan', planEdit: 'Edit salary plan', input: 'Add monthly input', inputEdit: 'Edit monthly input', profile: 'Create tax profile', profileEdit: 'Edit tax profile', rule: 'Add profile rule', ruleEdit: 'Edit tax rule', assignProfile: 'Assign profile to plan', assignProfileEdit: 'Edit profile assignment', override: 'Individual rule override', overrideEdit: 'Edit individual rule', payment: 'Record payment' })[this.dialog] || 'Payroll') },
		dialogDescription() { return t('employees', ({ period: 'Define a calculation window and currency.', setup: 'This effective-dated setup is reused for future monthly calculations.', bankSettings: 'Used to create the SEPA bank file. Employee accounts are taken from their payroll setup.', plan: 'Set how one employee is paid from an effective date.', planEdit: 'Update an unused plan. Approved payroll always keeps its original snapshot.', input: 'Enter hours, bonuses, taxes, deductions or a one-off payment.', inputEdit: 'Enter hours, bonuses, taxes, deductions or a one-off payment.', profile: 'Create a reusable collection of manual tax or deduction rules.', profileEdit: 'Create a reusable collection of manual tax or deduction rules.', rule: 'Rules can be fixed amounts, percentages or per-hour values.', ruleEdit: 'Rules can be fixed amounts, percentages or per-hour values.', assignProfile: 'Profiles may be combined and are effective-dated.', assignProfileEdit: 'Profiles may be combined and are effective-dated.', override: 'Use a personal rate or disable a rule for a period.', overrideEdit: 'Use a personal rate or disable a rule for a period.', payment: 'Record a bank transfer, cash payment or another payout.' })[this.dialog] || '') },
		dialogFields() {
			const employees = this.employees.map(e => ({ value: Number(e.id_employees), label: e.display_name || e.id_user })); const plans = this.plans.map(p => ({ value: Number(p.id), label: `${p.display_name || p.employee_uid} · ${p.name}` })); const profiles = this.profiles.map(p => ({ value: Number(p.id), label: p.name })); const rules = this.profiles.flatMap(p => (p.rules || []).map(r => ({ value: Number(r.id), label: `${p.name} · ${r.name}` })))
			const planFields = [{ key: 'employee_id', label: t('employees', 'Employee'), options: employees, required: true, disabled: this.dialog === 'planEdit' }, { key: 'name', label: t('employees', 'Plan name'), required: true }, { key: 'payment_mode', label: t('employees', 'Payment mode'), options: ['monthly', 'hourly', 'monthly_plus_hours', 'fixed_period', 'commission', 'piecework'].map(v => ({ value: v, label: this.modeLabel(v) })), required: true }, { key: 'currency', label: t('employees', 'Currency'), required: true }, ...['base_salary', 'hourly_rate', 'overtime_rate', 'standard_month_hours', 'cost_rate'].map(key => ({ key, label: this.fieldLabel(key), type: 'number', step: key === 'base_salary' ? '0.01' : '0.0001', min: '0' })), { key: 'effective_from', label: t('employees', 'Effective from'), type: 'date', required: true }, { key: 'effective_until', label: t('employees', 'Effective until'), type: 'date' }]
			const activeField = { key: 'active', label: t('employees', 'State'), options: [{ value: true, label: t('employees', 'Active') }, { value: false, label: t('employees', 'Inactive') }], required: true }
			const profileFields = [{ key: 'name', label: t('employees', 'Profile name'), required: true }, { key: 'description', label: t('employees', 'Description'), multiline: true, wide: true }, ...(this.dialog === 'profileEdit' ? [activeField] : [])]
			const ruleFields = [{ key: 'profile_id', label: t('employees', 'Profile'), options: profiles, required: true, disabled: this.dialog === 'ruleEdit' }, { key: 'code', label: t('employees', 'Code'), required: true }, { key: 'name', label: t('employees', 'Rule name'), required: true }, { key: 'category', label: t('employees', 'Category'), options: [{ value: 'earning', label: t('employees', 'Earning') }, { value: 'deduction', label: t('employees', 'Deduction') }], required: true }, { key: 'calculation_type', label: t('employees', 'Calculation'), options: ['fixed', 'percent_gross', 'percent_base', 'per_hour', 'per_unit'].map(v => ({ value: v, label: this.fieldLabel(v) })), required: true }, { key: 'value', label: t('employees', 'Value'), type: 'number', step: '0.0001', min: '0', required: true }, { key: 'sort_order', label: t('employees', 'Sort order'), type: 'number', step: '1', min: '0', required: true }, { key: 'taxable', label: t('employees', 'Taxable'), options: [{ value: true, label: t('employees', 'Yes') }, { value: false, label: t('employees', 'No') }] }, ...(this.dialog === 'ruleEdit' ? [activeField] : [])]
			const inputFields = [{ key: 'employee_id', label: t('employees', 'Employee'), options: employees, required: true }, { key: 'input_type', label: t('employees', 'Input type'), options: ['hours', 'overtime_hours', 'units', 'commission', 'bonus', 'reimbursement', 'one_off', 'deduction', 'tax', 'adjustment_earning', 'adjustment_deduction'].map(v => ({ value: v, label: this.fieldLabel(v) })), required: true }, { key: 'name', label: t('employees', 'Name') }, { key: 'quantity', label: t('employees', 'Quantity'), type: 'number', step: '0.0001', min: '0' }, { key: 'rate', label: t('employees', 'Rate'), type: 'number', step: '0.0001', min: '0' }, { key: 'amount', label: t('employees', 'Amount'), type: 'number', step: '0.01', min: '0' }, { key: 'notes', label: t('employees', 'Notes'), multiline: true, wide: true }]
			return ({
				period: [{ key: 'name', label: t('employees', 'Name') }, { key: 'currency', label: t('employees', 'Currency'), required: true }, { key: 'date_from', label: t('employees', 'From'), type: 'date', required: true }, { key: 'date_until', label: t('employees', 'Until'), type: 'date', required: true }],
				setup: [{ key: 'employee_name', label: t('employees', 'Employee'), disabled: true }, { key: 'name', label: t('employees', 'Plan name'), required: true }, { key: 'payment_mode', label: t('employees', 'Payment mode'), options: ['monthly', 'hourly', 'monthly_plus_hours', 'fixed_period', 'commission', 'piecework'].map(v => ({ value: v, label: this.modeLabel(v) })), required: true }, { key: 'profile_id', label: t('employees', 'Tax profile'), options: [{ value: 0, label: t('employees', 'No tax profile') }, ...profiles] }, { key: 'base_salary', label: t('employees', 'Base salary'), type: 'number', step: '0.01', min: '0', required: true }, { key: 'hourly_rate', label: t('employees', 'Hourly rate'), type: 'number', step: '0.0001', min: '0' }, { key: 'overtime_rate', label: t('employees', 'Overtime rate'), type: 'number', step: '0.0001', min: '0' }, { key: 'standard_month_hours', label: t('employees', 'Standard month hours'), type: 'number', step: '0.0001', min: '0' }, { key: 'cost_rate', label: t('employees', 'Cost rate'), type: 'number', step: '0.0001', min: '0' }, { key: 'currency', label: t('employees', 'Currency'), required: true }, { key: 'number_account', label: t('employees', 'IBAN'), required: true }, { key: 'effective_from', label: t('employees', 'Effective from'), type: 'date', required: true }, { key: 'effective_until', label: t('employees', 'Effective until'), type: 'date' }],
				bankSettings: [{ key: 'company_name', label: t('employees', 'Company / debtor name'), required: true }, { key: 'company_iban', label: t('employees', 'Company IBAN'), required: true }, { key: 'company_bic', label: t('employees', 'Company BIC') }, { key: 'payment_reference', label: t('employees', 'Payment reference template'), required: true, wide: true }],
				plan: planFields,
				planEdit: planFields,
				input: inputFields,
				inputEdit: inputFields,
				profile: profileFields,
				profileEdit: profileFields,
				rule: ruleFields,
				ruleEdit: ruleFields,
				assignProfile: [{ key: 'plan_id', label: t('employees', 'Salary plan'), options: plans, required: true }, { key: 'profile_id', label: t('employees', 'Profile'), options: profiles, required: true }, { key: 'effective_from', label: t('employees', 'Effective from'), type: 'date', required: true }, { key: 'effective_until', label: t('employees', 'Effective until'), type: 'date' }, { key: 'sort_order', label: t('employees', 'Sort order'), type: 'number', step: '1', min: '0' }],
				assignProfileEdit: [{ key: 'plan_id', label: t('employees', 'Salary plan'), options: plans, required: true }, { key: 'profile_id', label: t('employees', 'Profile'), options: profiles, required: true }, { key: 'effective_from', label: t('employees', 'Effective from'), type: 'date', required: true }, { key: 'effective_until', label: t('employees', 'Effective until'), type: 'date' }, { key: 'sort_order', label: t('employees', 'Sort order'), type: 'number', step: '1', min: '0' }],
				override: [{ key: 'employee_id', label: t('employees', 'Employee'), options: employees, required: true }, { key: 'rule_id', label: t('employees', 'Rule'), options: rules, required: true }, { key: 'override_value', label: t('employees', 'Personal value'), type: 'number', step: '0.0001', min: '0' }, { key: 'enabled', label: t('employees', 'Rule enabled'), options: [{ value: true, label: t('employees', 'Yes') }, { value: false, label: t('employees', 'No') }], required: true }, { key: 'effective_from', label: t('employees', 'Effective from'), type: 'date', required: true }, { key: 'effective_until', label: t('employees', 'Effective until'), type: 'date' }, { key: 'notes', label: t('employees', 'Reason / note'), multiline: true, wide: true }],
				overrideEdit: [{ key: 'employee_id', label: t('employees', 'Employee'), options: employees, required: true }, { key: 'rule_id', label: t('employees', 'Rule'), options: rules, required: true }, { key: 'override_value', label: t('employees', 'Personal value'), type: 'number', step: '0.0001', min: '0' }, { key: 'enabled', label: t('employees', 'Rule enabled'), options: [{ value: true, label: t('employees', 'Yes') }, { value: false, label: t('employees', 'No') }], required: true }, { key: 'effective_from', label: t('employees', 'Effective from'), type: 'date', required: true }, { key: 'effective_until', label: t('employees', 'Effective until'), type: 'date' }, { key: 'notes', label: t('employees', 'Reason / note'), multiline: true, wide: true }],
				payment: [{ key: 'payment_date', label: t('employees', 'Payment date'), type: 'date', required: true }, { key: 'amount', label: t('employees', 'Amount'), type: 'number', step: '0.01', min: '0', required: true }, { key: 'method', label: t('employees', 'Method'), options: ['bank_transfer', 'cash', 'card', 'other'].map(v => ({ value: v, label: this.fieldLabel(v) })), required: true }, { key: 'reference', label: t('employees', 'Reference') }, { key: 'notes', label: t('employees', 'Notes'), multiline: true, wide: true }],
			})[this.dialog] || []
		},
	},
	async mounted() { await this.load() },
	methods: {
		t,
		isTrue(value) { return value === true || value === 1 || value === '1' || value === 'true' },
		fieldLabel(value) { return ({ base_salary: t('employees', 'Base salary'), hourly_rate: t('employees', 'Hourly rate'), overtime_rate: t('employees', 'Overtime rate'), standard_month_hours: t('employees', 'Standard month hours'), cost_rate: t('employees', 'Cost rate'), monthly: t('employees', 'Monthly'), hourly: t('employees', 'Hourly'), monthly_plus_hours: t('employees', 'Monthly plus hours'), fixed_period: t('employees', 'Fixed period'), commission: t('employees', 'Commission'), piecework: t('employees', 'Piecework'), hours: t('employees', 'Hours'), overtime_hours: t('employees', 'Overtime hours'), units: t('employees', 'Units'), bonus: t('employees', 'Bonus'), reimbursement: t('employees', 'Reimbursement'), one_off: t('employees', 'One-off payment'), earning: t('employees', 'Earning'), deduction: t('employees', 'Deduction'), tax: t('employees', 'Tax'), adjustment_earning: t('employees', 'Earning adjustment'), adjustment_deduction: t('employees', 'Deduction adjustment'), fixed: t('employees', 'Fixed amount'), percent_gross: t('employees', 'Percent of gross'), percent_base: t('employees', 'Percent of base'), per_hour: t('employees', 'Per hour'), per_unit: t('employees', 'Per unit'), bank_transfer: t('employees', 'Bank transfer'), cash: t('employees', 'Cash'), card: t('employees', 'Card'), other: t('employees', 'Other'), manual: t('employees', 'Manual'), ai_import: t('employees', 'AI import'), time_reports: t('employees', 'Time reports') })[value] || String(value || '').replaceAll('_', ' ').replace(/^./, c => c.toUpperCase()) },
		modeLabel(value) { return this.fieldLabel(value) },
		statusLabel(value) { return ({ draft: t('employees', 'Draft'), calculated: t('employees', 'Calculated'), approved: t('employees', 'Approved'), paid: t('employees', 'Paid'), partially_paid: t('employees', 'Partially paid') })[value] || value || t('employees', 'Draft') },
		formatDate(value) { return value ? new Intl.DateTimeFormat(nextcloudLocale(), { dateStyle: 'medium' }).format(new Date(`${value}T00:00:00`)) : '—' },
		monthLabel(value) { return value ? new Intl.DateTimeFormat(nextcloudLocale(), { month: 'long', year: 'numeric' }).format(new Date(`${value}T00:00:00`)) : '—' },
		money(value, currency = 'EUR') { return new Intl.NumberFormat(nextcloudLocale(), { style: 'currency', currency: currency || 'EUR' }).format(Number(value || 0)) },
		employeePlan(employee) { return this.plans.filter(plan => Number(plan.employee_id) === Number(employee.id_employees) && this.isTrue(plan.active)).sort((a, b) => String(b.effective_from).localeCompare(String(a.effective_from)))[0] || null },
		employeeProfile(employee) { const plan = this.employeePlan(employee); if (!plan) return ''; const assignment = this.profileAssignments.filter(item => Number(item.plan_id) === Number(plan.id)).sort((a, b) => String(b.effective_from).localeCompare(String(a.effective_from)))[0]; return assignment?.profile_name || '' },
		employeeReady(employee) { return Boolean(this.employeePlan(employee) && this.validIban(employee.number_account)) },
		validIban(value) { const iban = String(value || '').replaceAll(' ', '').toUpperCase(); if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/.test(iban)) return false; const rearranged = iban.slice(4) + iban.slice(0, 4); let remainder = 0; for (const character of rearranged) { const part = /[A-Z]/.test(character) ? String(character.charCodeAt(0) - 55) : character; for (const digit of part) remainder = (remainder * 10 + Number(digit)) % 97 } return remainder === 1 },
		planSummary(plan) { if (plan.payment_mode === 'hourly') return this.money(plan.hourly_rate, plan.currency) + ' / ' + t('employees', 'hour'); if (plan.payment_mode === 'monthly_plus_hours') return `${this.money(plan.base_salary, plan.currency)} + ${this.money(plan.hourly_rate, plan.currency)} / ${t('employees', 'hour')}`; return this.money(plan.base_salary, plan.currency) },
		maskedIban(value) { const iban = String(value || '').replaceAll(' ', ''); return iban ? `•••• ${iban.slice(-4)}` : '—' },
		payslipUrl(id) { return payrollService.payslipUrl(id) },
		async load(periodId = null) { this.loading = true; this.error = ''; try { this.overview = await payrollService.overview(periodId); const id = Number(this.overview.selected_period?.id || 0); this.selectedPeriodOption = this.periodOptions.find(item => item.id === id) || null } catch (error) { this.handleError(error) } finally { this.loading = false } },
		periodChanged(option) { if (option?.id) this.load(option.id) },
		openDialog(name) { const range = monthRange(); this.dialog = name; this.form = { currency: this.period?.currency || 'EUR', date_from: range.from, date_until: range.until, effective_from: range.from, payment_date: iso(new Date()), payment_mode: 'monthly', input_type: 'hours', category: 'deduction', calculation_type: 'fixed', method: 'bank_transfer', enabled: true, active: true, taxable: false, sort_order: 100, quantity: 0, rate: 0, amount: 0, base_salary: 0, hourly_rate: 0, overtime_rate: 0, standard_month_hours: 0, cost_rate: 0 } },
		openPlan(plan) { this.openDialog('planEdit'); this.form = { ...plan, plan_id: Number(plan.id), employee_id: Number(plan.employee_id), active: plan.active === true || plan.active === 1 || plan.active === '1' } },
		openEmployeeSetup(employee) { const plan = this.employeePlan(employee); const assignment = plan ? this.profileAssignments.filter(item => Number(item.plan_id) === Number(plan.id)).sort((a, b) => String(b.effective_from).localeCompare(String(a.effective_from)))[0] : null; this.openDialog('setup'); this.form = { ...this.form, ...(plan || {}), plan_id: plan ? Number(plan.id) : null, employee_id: Number(employee.id_employees), employee_name: employee.display_name || employee.id_user, name: plan?.name || t('employees', 'Main compensation'), number_account: employee.number_account || '', profile_id: assignment ? Number(assignment.profile_id) : 0, effective_from: plan?.locked ? iso(new Date()) : (plan?.effective_from || this.form.effective_from), active: true } },
		openBankSettings() { this.openDialog('bankSettings'); this.form = { ...this.overview.bank_settings } },
		openInput(input) { this.openDialog('inputEdit'); this.form = { ...input, input_id: Number(input.id), employee_id: Number(input.employee_id) } },
		openProfile(profile) { this.openDialog('profileEdit'); this.form = { ...profile, profile_id: Number(profile.id), active: profile.active === true || profile.active === 1 || profile.active === '1' } },
		openRule(rule, profile) { this.openDialog('ruleEdit'); this.form = { ...rule, rule_id: Number(rule.id), profile_id: Number(profile.id), active: rule.active === true || rule.active === 1 || rule.active === '1', taxable: rule.taxable === true || rule.taxable === 1 || rule.taxable === '1' } },
		openProfileAssignment(assignment) { this.openDialog('assignProfileEdit'); this.form = { ...assignment, assignment_id: Number(assignment.id), plan_id: Number(assignment.plan_id), profile_id: Number(assignment.profile_id) } },
		openEmployeeRuleAssignment(assignment) { this.openDialog('overrideEdit'); this.form = { ...assignment, assignment_id: Number(assignment.id), employee_id: Number(assignment.employee_id), rule_id: Number(assignment.rule_id), enabled: assignment.enabled === true || assignment.enabled === 1 || assignment.enabled === '1' } },
		openPayment(slip) { this.openDialog('payment'); this.form.payslip_id = Number(slip.id); this.form.amount = Math.max(0, Number(slip.net_amount || 0) - Number(slip.paid_amount || 0)).toFixed(2) },
		async submitDialog() { this.busy = true; try { let result; if (this.dialog === 'period') result = await payrollService.createPeriod(this.form); else if (this.dialog === 'setup') result = await payrollService.saveEmployeeSetup(this.form.employee_id, this.form); else if (this.dialog === 'bankSettings') result = await payrollService.updateBankSettings(this.form); else if (this.dialog === 'plan') result = await payrollService.createPlan(this.form); else if (this.dialog === 'planEdit') result = await payrollService.updatePlan(this.form.plan_id, this.form); else if (this.dialog === 'input') result = await payrollService.addInput(this.selectedPeriod, this.form); else if (this.dialog === 'inputEdit') result = await payrollService.updateInput(this.form.input_id, this.form); else if (this.dialog === 'profile') result = await payrollService.createProfile(this.form); else if (this.dialog === 'profileEdit') result = await payrollService.updateProfile(this.form.profile_id, this.form); else if (this.dialog === 'rule') result = await payrollService.createRule(this.form.profile_id, this.form); else if (this.dialog === 'ruleEdit') result = await payrollService.updateRule(this.form.rule_id, this.form); else if (this.dialog === 'assignProfile') result = await payrollService.assignProfile(this.form.plan_id, this.form); else if (this.dialog === 'assignProfileEdit') result = await payrollService.updateProfileAssignment(this.form.assignment_id, this.form); else if (this.dialog === 'override') result = await payrollService.assignEmployeeRule(this.form.employee_id, this.form); else if (this.dialog === 'overrideEdit') result = await payrollService.updateEmployeeRuleAssignment(this.form.assignment_id, this.form); else if (this.dialog === 'payment') result = await payrollService.recordPayment(this.form.payslip_id, this.form); this.dialog = null; showSuccess(t('employees', 'Payroll data saved.')); await this.load(this.selectedPeriod || result?.id) } catch (error) { this.handleError(error) } finally { this.busy = false } },
		async removeInput(input) { if (!window.confirm(t('employees', 'Delete this payroll input?'))) return; await this.run(() => payrollService.deleteInput(input.id), t('employees', 'Payroll input deleted.')) },
		async calculate() {
			this.busy = true; this.error = ''
			try {
				const result = await payrollService.calculate(this.selectedPeriod)
				if (result.errors?.length) {
					this.error = t('employees', 'Payroll calculation failed for {count} employees. Fix their plans or inputs and calculate again.', { count: result.errors.length })
					showError(this.error)
				} else { showSuccess(t('employees', 'Payroll calculated.')) }
				await this.load(this.selectedPeriod)
			} catch (error) { this.handleError(error) } finally { this.busy = false }
		},
		async approve() { this.busy = true; try { const result = await payrollService.approve(this.selectedPeriod); if (result.package?.errors?.length) showError(t('employees', 'Payroll was approved, but some files need attention.')); else showSuccess(t('employees', 'Payroll approved and files generated.')); await this.load(this.selectedPeriod) } catch (error) { this.handleError(error) } finally { this.busy = false } },
		async publishPackage() { await this.run(() => payrollService.publish(this.selectedPeriod), t('employees', 'Payroll files generated in Team Folder.')) },
		async run(operation, message) { this.busy = true; try { await operation(); showSuccess(message); await this.load(this.selectedPeriod) } catch (error) { this.handleError(error) } finally { this.busy = false } },
		handleError(error) { this.error = error?.response?.data?.error?.message || error?.message || t('employees', 'Payroll request failed.'); showError(this.error) },
	},
}
</script>

<style scoped lang="scss">
/* stylelint-disable no-descending-specificity */
.payroll-page{width:100%;min-width:0;padding:24px}.page-header,.section-header,.management-card,.toolbar{display:flex;justify-content:space-between;gap:16px;align-items:center}.page-header h2,.section-header h3,.management-card h3,.files-sidebar h3{margin:0}.section-header p,.management-card p,.page-header p:not(.eyebrow),.files-content p{margin:4px 0 0;color:var(--color-text-maxcontrast)}.eyebrow{margin:0 0 4px;color:var(--color-primary-element);font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}.header-actions,.toolbar-actions,.button-row,.actions{display:flex;gap:8px;flex-wrap:wrap}.card{border:1px solid var(--color-border);border-radius:var(--border-radius-large);background:var(--color-main-background)}.toolbar{margin-top:16px;padding:14px}.toolbar>*:first-child{min-width:260px}.period-state{display:flex;flex-direction:column}.period-state span{color:var(--color-text-maxcontrast)}.workflow{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;margin-top:12px;overflow:hidden}.workflow-step{display:flex;gap:10px;align-items:center;padding:14px;background:var(--color-background-hover)}.workflow-step>span{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--color-main-background);font-weight:700}.workflow-step small{display:block;margin-top:2px;color:var(--color-text-maxcontrast)}.workflow-step.current{box-shadow:inset 0 -3px var(--color-primary-element)}.workflow-step.complete>span{background:var(--color-primary-element);color:var(--color-primary-element-text)}.loading{display:flex;justify-content:center;align-items:center;gap:10px;padding:64px}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px}.metric{padding:16px}.metric span{display:block;color:var(--color-text-maxcontrast)}.metric strong{display:block;margin-top:6px;font-size:1.4rem}.management-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}.management-card{padding:16px;align-items:flex-end}.advanced-settings{margin-top:16px;padding:16px}.advanced-settings>summary{cursor:pointer;font-size:1.05rem;font-weight:700}.table-card{margin-top:16px;padding:18px}.table-scroll{overflow:auto;margin-top:14px}table{width:100%;border-collapse:collapse;min-width:760px}th,td{padding:10px;border-bottom:1px solid var(--color-border);text-align:left}.profile-row{background:var(--color-background-hover)}td small{display:block;color:var(--color-text-maxcontrast)}.status-pill{display:inline-flex;padding:3px 8px;border-radius:999px;background:var(--color-background-hover)}.status-pill.warning{color:var(--color-error-text);background:var(--color-error)}.payroll-files{display:grid;grid-template-columns:260px minmax(0,1fr);margin-top:16px;overflow:hidden}.files-sidebar{padding:18px;border-right:1px solid var(--color-border)}.files-sidebar button{display:flex;width:100%;gap:10px;align-items:center;margin-top:8px;padding:10px;border:0;border-radius:var(--border-radius);background:transparent;color:var(--color-main-text);text-align:left;cursor:pointer}.files-sidebar button:hover,.files-sidebar button.active{background:var(--color-background-hover)}.files-sidebar button.active{box-shadow:inset 3px 0 var(--color-primary-element)}.files-sidebar small{display:block;color:var(--color-text-maxcontrast)}.folder-icon{color:var(--color-primary-element);font-size:22px}.files-content{min-width:0;padding:18px}.file-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:18px}.file-card{display:flex;gap:12px;align-items:center;min-width:0;padding:14px;border:1px solid var(--color-border);border-radius:var(--border-radius-large);color:var(--color-main-text);text-decoration:none}.file-card:hover{background:var(--color-background-hover)}.file-card small{display:block;color:var(--color-text-maxcontrast)}.file-kind{display:grid;place-items:center;flex:0 0 44px;height:44px;border-radius:var(--border-radius);background:var(--color-primary-element-light);color:var(--color-primary-element-light-text);font-size:.75rem;font-weight:800}.dialog-form{padding:24px}.dialog-form h2{margin:0}.dialog-form>div:first-child>p:not(.eyebrow){color:var(--color-text-maxcontrast)}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}.form-grid label{display:flex;flex-direction:column;gap:6px}.form-grid label.wide{grid-column:1/-1}.form-grid label span{font-weight:600}.form-grid input,.form-grid select,.form-grid textarea{box-sizing:border-box;width:100%;padding:9px;border:1px solid var(--color-border-maxcontrast);border-radius:var(--border-radius);background:var(--color-main-background);color:var(--color-main-text)}.form-grid textarea{resize:vertical}.actions{justify-content:flex-end;margin-top:20px}@media(max-width:1100px){.workflow{grid-template-columns:repeat(2,1fr)}.payroll-files{grid-template-columns:1fr}.files-sidebar{border-right:0;border-bottom:1px solid var(--color-border)}}@media(max-width:1000px){.metrics{grid-template-columns:repeat(2,1fr)}.management-grid{grid-template-columns:1fr}}@media(max-width:700px){.payroll-page{padding:16px}.page-header,.toolbar{align-items:flex-start;flex-direction:column}.metrics,.workflow,.file-grid,.form-grid{grid-template-columns:1fr}.form-grid label.wide{grid-column:auto}}
</style>
