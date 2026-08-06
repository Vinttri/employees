import { translate as t } from '@nextcloud/l10n'

const rules = [
	{
		match: /search|filter|поиск|фильтр|buscar|filtrar/i,
		short: 'Filters the visible records without changing them.',
		purpose: 'Use search or filters to narrow the current list; the underlying employee or business data is not modified.',
		example: 'Type finance to show matching employees or records',
	},
	{
		match: /period|период|per[ií]odo/i,
		short: 'Selects the reporting or calculation period.',
		purpose: 'The selected period determines which dates and records are shown or included in the calculation.',
		example: 'August 2026 for the monthly payroll calculation',
	},
	{
		match: /\biban\b|bank account|account number|банковск|номер сч[её]та|сч[её]т получателя|cuenta bancaria/i,
		short: 'Used to prepare the employee payment.',
		purpose: 'The account is included in payroll bank files and identifies where the payment must be sent.',
		example: 'CY00 … 1234 (the employee’s valid IBAN)',
	},
	{
		match: /\bbic\b|\bswift\b/i,
		short: 'Identifies the receiving bank.',
		purpose: 'The bank code is required by SEPA and other bank exports together with the IBAN.',
		example: 'BCYPCY2N',
	},
	{
		match: /tax|налог|impuesto/i,
		short: 'Controls taxes and payroll deductions.',
		purpose: 'The selected profile defines which tax and deduction rules are applied during the monthly calculation.',
		example: 'Cyprus standard tax with an individual discount',
	},
	{
		match: /salary|base pay|оклад|зарплат|sueldo|salario/i,
		short: 'Used as the base of the payroll calculation.',
		purpose: 'This amount is the employee’s regular pay before taxes, absence deductions and one-off adjustments.',
		example: '3500.00 EUR per month',
	},
	{
		match: /overtime|сверхуроч|extra hours|horas extra/i,
		short: 'Sets how overtime is paid.',
		purpose: 'The value is multiplied by approved overtime hours and added to the selected payroll period.',
		example: '25.00 EUR per overtime hour',
	},
	{
		match: /hourly rate|rate per hour|ставк.*час|почас|tarifa.*hora|cost per hour/i,
		short: 'Sets the value of one working hour.',
		purpose: 'The application multiplies this rate by imported or manually entered hours.',
		example: '18.50 EUR per hour',
	},
	{
		match: /hours|час|horas|working time|рабоч.*врем/i,
		short: 'Used to calculate time-based pay and reports.',
		purpose: 'Enter the approved hours for the period, or leave the automatic time-report source enabled.',
		example: '160 regular hours and 4 overtime hours',
	},
	{
		match: /absence|vacation|sick|leave|отпуск|больнич|неяв|ausencia|vacaciones/i,
		short: 'Defines how an absence affects payroll and calendars.',
		purpose: 'Approved dates are used for availability, employee status and the paid or unpaid payroll adjustment.',
		example: 'Vacation from 10 to 14 August, paid at 100%',
	},
	{
		match: /percent|percentage|процент|ставка налога|porcentaje|contingency/i,
		short: 'Enter a value from 0 to 100 percent.',
		purpose: 'The percentage changes the related amount proportionally and is stored with the rule or calculation.',
		example: '12.5 means twelve and a half percent',
	},
	{
		match: /currency|валют|moneda/i,
		short: 'Sets the currency used for amounts and exports.',
		purpose: 'All values in the period or profile are formatted and exported using this ISO currency code.',
		example: 'EUR',
	},
	{
		match: /employee|user|сотрудник|пользователь|empleado|usuario/i,
		short: 'Links the record to a Nextcloud user.',
		purpose: 'The link controls personal access, documents, notifications and data shown to this employee.',
		example: 'finance.accountant',
	},
	{
		match: /department|area|отдел|област|departamento|área/i,
		short: 'Places the record in the organisation structure.',
		purpose: 'Departments are used for managers, reporting, permissions and automatic directory synchronisation.',
		example: 'Finance / Accounting',
	},
	{
		match: /position|job title|role|должност|роль|puesto|cargo/i,
		short: 'Defines the person’s function in the organisation.',
		purpose: 'The position is displayed in the employee card and is used in organisation and staffing views.',
		example: 'Senior Accountant',
	},
	{
		match: /team|collective|group|команд|коллектив|групп|equipo|grupo/i,
		short: 'Controls team membership or access scope.',
		purpose: 'Teams and groups are used to assign colleagues, managers, visibility and shared resources.',
		example: 'Finance team',
	},
	{
		match: /company|client|customer|компан|клиент|empresa|cliente/i,
		short: 'Links the record to a company or customer.',
		purpose: 'The company is used to group contacts, activities, costs and commercial reports.',
		example: 'Aonius Cyprus Ltd',
	},
	{
		match: /start date|date from|effective from|дата начал|с дат|начало|fecha de inicio|desde/i,
		short: 'Sets when the value or period begins.',
		purpose: 'The application applies the record starting on this date and uses it when selecting monthly data.',
		example: '2026-08-01',
	},
	{
		match: /end date|date until|effective until|дата оконч|по дат|окончание|fecha final|hasta/i,
		short: 'Sets the last day of the value or period.',
		purpose: 'The application stops applying the record after this date and validates that it is not before the start.',
		example: '2026-08-31',
	},
	{
		match: /email|e-mail|электрон.*почт|correo/i,
		short: 'Used for employee and workflow messages.',
		purpose: 'Notifications, payslip messages and contact actions can be sent to this address.',
		example: 'employee@example.com',
	},
	{
		match: /phone|mobile|телефон|мобил|teléfono|móvil/i,
		short: 'Used as the contact telephone number.',
		purpose: 'The number is shown in the employee or company card for authorised colleagues.',
		example: '+357 99 123456',
	},
	{
		match: /serial|asset tag|inventory|серийн|инвентар|mac address|ip address|manufacturer|model|модель|производител/i,
		short: 'Identifies the equipment in inventory.',
		purpose: 'This value helps distinguish the device during assignment, maintenance and support.',
		example: 'LT-2026-0042 or the manufacturer serial number',
	},
	{
		match: /amount|price|cost|budget|sum|сумм|цен|стоим|бюджет|importe|precio|costo/i,
		short: 'Enter the monetary amount for this record.',
		purpose: 'The amount is used in totals, approvals, payroll or cost reports according to the current form.',
		example: '1250.50',
	},
	{
		match: /file|attachment|document|файл|вложен|документ|archivo/i,
		short: 'Attach the document that confirms this record.',
		purpose: 'The file is stored with the related employee, request or calculation for later review.',
		example: 'A PDF contract, receipt or medical certificate',
	},
	{
		match: /name|title|subject|назван|наименован|заголов|тема|nombre|título/i,
		short: 'Use a short name that colleagues will recognise.',
		purpose: 'The name is used to find and identify this record in lists, reports and notifications.',
		example: 'August 2026 payroll',
	},
	{
		match: /description|comment|note|reason|описан|комментар|замет|причин|descripción|comentario|nota/i,
		short: 'Add the context needed to understand the record.',
		purpose: 'The explanation helps reviewers and colleagues understand why the value or request was created.',
		example: 'One-off relocation payment approved by HR',
	},
]

const fallbackFor = (control) => {
	const type = String(control?.getAttribute?.('type') || '').toLowerCase()
	const tag = String(control?.tagName || '').toLowerCase()
	const role = String(control?.getAttribute?.('role') || '').toLowerCase()

	if (type === 'checkbox' || type === 'radio') {
		return {
			short: 'Enable this option only when the rule should apply.',
			purpose: 'The switch controls whether the related setting is active for this record.',
			example: 'Enabled means the option will be used after saving',
		}
	}
	if (tag === 'select' || role === 'combobox') {
		return {
			short: 'Select the option that matches the real situation.',
			purpose: 'The selected value determines how the application classifies and processes this record.',
			example: 'Choose the existing option that best describes the record',
		}
	}
	if (type === 'date' || type === 'datetime-local') {
		return {
			short: 'Enter the date used for this record.',
			purpose: 'The date controls ordering, validity and inclusion in period reports.',
			example: '2026-08-31',
		}
	}
	if (type === 'number') {
		return {
			short: 'Enter a numeric value without extra text.',
			purpose: 'The number is validated and used in the calculation or report shown on this form.',
			example: '40 or 1250.50',
		}
	}

	return {
		short: 'Enter the value that should be saved in this field.',
		purpose: 'The value is stored with this record and shown wherever the field is used.',
		example: 'Use the actual value that authorised colleagues should see',
	}
}

export const resolveFieldHelp = (label, control) => {
	const normalizedLabel = String(label || '').replace(/\s+/g, ' ').trim()
	const rule = rules.find(item => item.match.test(normalizedLabel)) || fallbackFor(control)
	const example = t('employees', rule.example)

	return {
		short: t('employees', rule.short),
		detail: t('employees', 'Why: {purpose} Example: {example}.', {
			purpose: t('employees', rule.purpose),
			example,
		}),
		exampleText: t('employees', 'Example: {example}.', { example }),
		accessibleLabel: t('employees', 'Help for {field}', {
			field: normalizedLabel || t('employees', 'this field'),
		}),
	}
}

export const fieldHelpRules = rules
